<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Subscriber;

use LebensmittelMhdManager\Service\MhdDateParser;
use LebensmittelMhdManager\Service\ProductTitleUpdater;
use LebensmittelMhdManager\Service\ProductDescriptionUpdater;
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
    private ProductDescriptionUpdater $descriptionUpdater;
    private SystemConfigService $systemConfigService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $productRepository,
        MhdDateParser $dateParser,
        ProductTitleUpdater $titleUpdater,
        ProductDescriptionUpdater $descriptionUpdater,
        SystemConfigService $systemConfigService,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->dateParser = $dateParser;
        $this->titleUpdater = $titleUpdater;
        $this->descriptionUpdater = $descriptionUpdater;
        $this->systemConfigService = $systemConfigService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            'product_translation.written' => 'onProductTranslationWritten',
        ];
    }

    /**
     * Handle product translation written event (for custom field updates)
     */
    public function onProductTranslationWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        
        // Check if plugin is enabled for this sales channel
        if (!$this->isEnabledForContext($context)) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            
            // Check if custom fields were updated
            if (!array_key_exists('customFields', $payload)) {
                continue;
            }
            
            // Check specifically for single EAN update
            $hasSingleEanUpdate = isset($payload['customFields']['custom_product_single_ean']);
            
            if (!$hasSingleEanUpdate) {
                continue;
            }
            
            // Get product ID from payload
            $productId = $payload['productId'] ?? null;
            if (!$productId) {
                continue;
            }
            
            $this->logger->info('MHD Manager: Translation written with single EAN update', [
                'productId' => $productId,
                'ean' => $payload['customFields']['custom_product_single_ean'] ?? null
            ]);
            
            try {
                // Process only EAN update (not MHD)
                $this->processProduct($productId, null, $context, false, true);
            } catch (\Exception $e) {
                $this->logger->error('MHD Manager: Failed to process translation update', [
                    'productId' => $productId,
                    'error' => $e->getMessage()
                ]);
            }
        }
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
            $hasManufacturerNumber = array_key_exists('manufacturerNumber', $payload);
            
            // Note: Custom field updates come through product_translation.written event,
            // not in the product.written event payload
            
            // Skip if manufacturer number wasn't updated
            if (!$hasManufacturerNumber) {
                continue;
            }
            
            $productId = $writeResult->getPrimaryKey();
            $manufacturerNumber = $payload['manufacturerNumber'] ?? null;
            
            try {
                // Process MHD update only (custom fields handled in onProductTranslationWritten)
                $this->processProduct($productId, $manufacturerNumber, $context, true, false);
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
    private function processProduct(
        string $productId, 
        ?string $manufacturerNumber, 
        Context $context, 
        bool $processMhd = true,
        bool $processEan = false
    ): void {
        // Load current product with translations
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('translations');
        
        $product = $this->productRepository->search($criteria, $context)->first();
        
        if (!$product) {
            return;
        }

        // Parse manufacturer number as date if we need to process MHD
        $mhdDate = null;
        $mhdDateFormatted = null;
        $mhdDateFormattedFull = null;
        
        if ($processMhd && $manufacturerNumber !== null) {
            if (!empty($manufacturerNumber)) {
                $mhdDate = $this->dateParser->parseManufacturerNumber($manufacturerNumber);
                
                if ($mhdDate) {
                    $mhdDateFormatted = $this->dateParser->formatForDisplay($mhdDate);
                    $mhdDateFormattedFull = $this->dateParser->formatForDescription($mhdDate);
                }
            }
        } else if ($processMhd) {
            // Need to get manufacturer number from product if not provided
            $manufacturerNumber = $product->getManufacturerNumber();
            if (!empty($manufacturerNumber)) {
                $mhdDate = $this->dateParser->parseManufacturerNumber($manufacturerNumber);
                
                if ($mhdDate) {
                    $mhdDateFormatted = $this->dateParser->formatForDisplay($mhdDate);
                    $mhdDateFormattedFull = $this->dateParser->formatForDescription($mhdDate);
                }
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
            $currentDescription = $translation->getDescription();
            $customFields = $translation->getCustomFields() ?? [];
            
            $translationUpdate = [
                'languageId' => $languageId
            ];
            
            $hasChanges = false;

            // Process MHD updates
            if ($processMhd && $manufacturerNumber !== null) {
                // Update or remove MHD from title
                if (!empty($currentName)) {
                    if ($mhdDateFormatted) {
                        // Add/update MHD in title
                        $newName = $this->titleUpdater->updateTitle($currentName, $mhdDateFormatted);
                        if ($newName !== $currentName) {
                            $translationUpdate['name'] = $newName;
                            $hasChanges = true;
                        }
                    } else {
                        // Remove MHD from title
                        $newName = $this->titleUpdater->removeMhdFromTitle($currentName);
                        if ($newName !== $currentName) {
                            $translationUpdate['name'] = $newName;
                            $hasChanges = true;
                        }
                    }
                }
                
                // Update invisible-date span in description
                $newDescription = $this->descriptionUpdater->updateInvisibleDate($currentDescription, $mhdDateFormattedFull);
                if ($newDescription !== $currentDescription) {
                    $translationUpdate['description'] = $newDescription;
                    $currentDescription = $newDescription;
                    $hasChanges = true;
                }
                
                // Update custom field for expiry date
                if ($mhdDateFormatted) {
                    // Set expiry date
                    $customFields['custom_product_detail_expiry_date'] = $mhdDateFormatted;
                    $hasChanges = true;
                } else {
                    // Clear expiry date
                    if (isset($customFields['custom_product_detail_expiry_date'])) {
                        unset($customFields['custom_product_detail_expiry_date']);
                        $hasChanges = true;
                    }
                }
            }

            // Process EAN updates
            if ($processEan) {
                $singleEan = $customFields['custom_product_single_ean'] ?? null;
                
                // Update single-ean span in description
                $newDescription = $this->descriptionUpdater->updateSingleEan($currentDescription, $singleEan);
                if ($newDescription !== $currentDescription) {
                    $translationUpdate['description'] = $newDescription;
                    $hasChanges = true;
                }
            }
            
            // Add custom fields if changed
            if ($hasChanges && (isset($translationUpdate['name']) || isset($translationUpdate['description']) || 
                isset($customFields['custom_product_detail_expiry_date']) || isset($customFields['custom_product_single_ean']))) {
                $translationUpdate['customFields'] = $customFields;
            }

            // Add translation update if there are changes
            if ($hasChanges) {
                $updateData['translations'][] = $translationUpdate;
            }
        }

        // Only update if there are changes
        if (!empty($updateData['translations'])) {
            $this->productRepository->update([$updateData], $context);
            
            $this->logger->info('MHD Manager: Updated product', [
                'productId' => $productId,
                'manufacturerNumber' => $manufacturerNumber,
                'mhdDate' => $mhdDateFormatted,
                'processMhd' => $processMhd,
                'processEan' => $processEan
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