<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Subscriber;

use LebensmittelMhdManager\Service\MhdDateParser;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class StockMovementSubscriber implements EventSubscriberInterface
{
    private EntityRepository $productRepository;
    private MhdDateParser $dateParser;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $productRepository,
        MhdDateParser $dateParser,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->dateParser = $dateParser;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'pickware_erp_stock_movement.written' => 'onStockMovementWritten',
        ];
    }

    /**
     * Handle stock movement written event
     */
    public function onStockMovementWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            
            // Check if comment field was updated
            if (!array_key_exists('comment', $payload)) {
                continue;
            }
            
            $comment = $payload['comment'] ?? null;
            $productId = $payload['productId'] ?? null;
            
            // Skip if no comment or no product ID
            if (empty($comment) || empty($productId)) {
                continue;
            }
            
            // Try to parse the comment as a date
            $mhdDate = $this->dateParser->parseManufacturerNumber($comment);
            
            if (!$mhdDate) {
                // Comment doesn't contain a valid date format
                $this->logger->debug('StockMovement MHD: Comment does not contain valid date', [
                    'comment' => $comment,
                    'productId' => $productId
                ]);
                continue;
            }
            
            // Valid date found - update product's manufacturer number
            try {
                $this->updateProductManufacturerNumber($productId, $comment, $context);
                
                $this->logger->info('StockMovement MHD: Updated product manufacturer number from stock movement', [
                    'productId' => $productId,
                    'comment' => $comment,
                    'parsedDate' => $mhdDate->format('Y-m-d')
                ]);
            } catch (\Exception $e) {
                $this->logger->error('StockMovement MHD: Failed to update product manufacturer number', [
                    'productId' => $productId,
                    'comment' => $comment,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Update product's manufacturer number field
     */
    private function updateProductManufacturerNumber(string $productId, string $manufacturerNumber, Context $context): void
    {
        // First check if product exists
        $criteria = new Criteria([$productId]);
        $product = $this->productRepository->search($criteria, $context)->first();
        
        if (!$product) {
            $this->logger->warning('StockMovement MHD: Product not found', [
                'productId' => $productId
            ]);
            return;
        }
        
        // Check if manufacturer number is different
        $currentManufacturerNumber = $product->getManufacturerNumber();
        
        if ($currentManufacturerNumber === $manufacturerNumber) {
            // No change needed
            $this->logger->debug('StockMovement MHD: Manufacturer number already up to date', [
                'productId' => $productId,
                'manufacturerNumber' => $manufacturerNumber
            ]);
            return;
        }
        
        // Update the manufacturer number
        $updateData = [
            'id' => $productId,
            'manufacturerNumber' => $manufacturerNumber
        ];
        
        // This update will trigger the ProductSubscriber to update title, description, and expiry date
        $this->productRepository->update([$updateData], $context);
        
        $this->logger->info('StockMovement MHD: Product manufacturer number updated', [
            'productId' => $productId,
            'oldManufacturerNumber' => $currentManufacturerNumber,
            'newManufacturerNumber' => $manufacturerNumber
        ]);
    }
}