<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Subscriber;

use LebensmittelMhdManager\Service\MhdDateParser;
use LebensmittelMhdManager\Service\ProductTitleUpdater;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    private EntityRepository $productRepository;
    private MhdDateParser $dateParser;
    private ProductTitleUpdater $titleUpdater;
    private SystemConfigService $systemConfigService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $productRepository,
        MhdDateParser $dateParser,
        ProductTitleUpdater $titleUpdater,
        SystemConfigService $systemConfigService,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->dateParser = $dateParser;
        $this->titleUpdater = $titleUpdater;
        $this->systemConfigService = $systemConfigService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    /**
     * Handle product written event
     */
    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        
        // Check if plugin is enabled for this sales channel
        if (!$this->isEnabledForContext($context)) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            
            // Check if manufacturer_number was updated
            if (!array_key_exists('manufacturerNumber', $payload)) {
                continue;
            }
            
            $productId = $writeResult->getPrimaryKey();
            $manufacturerNumber = $payload['manufacturerNumber'] ?? null;
            
            try {
                $this->processProduct($productId, $manufacturerNumber, $context);
            } catch (\Exception $e) {
                $this->logger->error('MHD Manager: Failed to process product', [
                    'productId' => $productId,
                    'manufacturerNumber' => $manufacturerNumber,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Process a single product
     */
    private function processProduct(string $productId, ?string $manufacturerNumber, Context $context): void
    {
        // Load current product with translations
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('translations');
        
        $product = $this->productRepository->search($criteria, $context)->first();
        
        if (!$product) {
            return;
        }

        // Parse manufacturer number as date
        $mhdDate = null;
        $mhdDateFormatted = null;
        
        if (!empty($manufacturerNumber)) {
            $mhdDate = $this->dateParser->parseManufacturerNumber($manufacturerNumber);
            
            if ($mhdDate) {
                $mhdDateFormatted = $this->dateParser->formatForDisplay($mhdDate);
            }
        }

        // Prepare update data
        $updateData = [
            'id' => $productId,
            'translations' => []
        ];

        // Process all translations
        foreach ($product->getTranslations() as $translation) {
            $languageId = $translation->getLanguageId();
            $currentName = $translation->getName();
            
            if (empty($currentName)) {
                continue;
            }

            $translationUpdate = [
                'languageId' => $languageId
            ];

            // Update or remove MHD from title
            if ($mhdDateFormatted) {
                // Add/update MHD in title
                $newName = $this->titleUpdater->updateTitle($currentName, $mhdDateFormatted);
                $translationUpdate['name'] = $newName;
            } else {
                // Remove MHD from title
                $newName = $this->titleUpdater->removeMhdFromTitle($currentName);
                if ($newName !== $currentName) {
                    $translationUpdate['name'] = $newName;
                }
            }

            // Update custom field for expiry date
            $customFields = $translation->getCustomFields() ?? [];
            
            if ($mhdDateFormatted) {
                // Set expiry date
                $customFields['custom_product_detail_expiry_date'] = $mhdDateFormatted;
            } else {
                // Clear expiry date
                unset($customFields['custom_product_detail_expiry_date']);
            }
            
            // Only update custom fields if changed
            if ($mhdDateFormatted || isset($translation->getCustomFields()['custom_product_detail_expiry_date'])) {
                $translationUpdate['customFields'] = $customFields;
            }

            // Add translation update if there are changes
            if (count($translationUpdate) > 1) {
                $updateData['translations'][] = $translationUpdate;
            }
        }

        // Only update if there are changes
        if (!empty($updateData['translations'])) {
            $this->productRepository->update([$updateData], $context);
            
            $this->logger->info('MHD Manager: Updated product', [
                'productId' => $productId,
                'manufacturerNumber' => $manufacturerNumber,
                'mhdDate' => $mhdDateFormatted
            ]);
        }
    }

    /**
     * Check if plugin is enabled for the current context/sales channel
     */
    private function isEnabledForContext(Context $context): bool
    {
        // Get enabled sales channels from config
        $enabledChannels = $this->systemConfigService->get('LebensmittelMhdManager.config.enabledSalesChannels');
        
        // If no specific channels configured, enable for all
        if (empty($enabledChannels)) {
            return true;
        }
        
        // Check if current context's sales channel is enabled
        $salesChannelId = $context->getSource()->getSalesChannelId();
        
        if (!$salesChannelId) {
            // Admin context without specific sales channel - always enabled
            return true;
        }
        
        return in_array($salesChannelId, $enabledChannels, true);
    }
}