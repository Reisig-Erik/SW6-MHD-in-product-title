<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Psr\Log\LoggerInterface;

class SingleEanManager
{
    private EntityRepository $productRepository;
    private ProductDescriptionUpdater $descriptionUpdater;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $productRepository,
        ProductDescriptionUpdater $descriptionUpdater,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->descriptionUpdater = $descriptionUpdater;
        $this->logger = $logger;
    }
    
    /**
     * Migrate SW5 EAN data to new custom field
     * 
     * @param string $productId
     * @param Context $context
     * @return bool True if migration was performed
     */
    public function migrateSw5EanData(string $productId, Context $context): bool
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('translations');
        
        $product = $this->productRepository->search($criteria, $context)->first();
        
        if (!$product) {
            return false;
        }
        
        $migrated = false;
        $updateData = [
            'id' => $productId,
            'translations' => []
        ];
        
        foreach ($product->getTranslations() as $translation) {
            $customFields = $translation->getCustomFields() ?? [];
            
            // Check for SW5 migration field
            $sw5Ean = $customFields['migration_SW566_product_attr12'] ?? null;
            
            if (!empty($sw5Ean) && empty($customFields['custom_product_single_ean'])) {
                // Migrate the EAN
                $customFields['custom_product_single_ean'] = $sw5Ean;
                
                // Update description with EAN span
                $description = $translation->getDescription();
                $newDescription = $this->descriptionUpdater->updateSingleEan($description, $sw5Ean);
                
                $updateData['translations'][] = [
                    'languageId' => $translation->getLanguageId(),
                    'customFields' => $customFields,
                    'description' => $newDescription
                ];
                
                $migrated = true;
            }
        }
        
        if ($migrated && !empty($updateData['translations'])) {
            $this->productRepository->update([$updateData], $context);
            
            $this->logger->info('SingleEanManager: Migrated SW5 EAN data', [
                'productId' => $productId
            ]);
        }
        
        return $migrated;
    }
    
    /**
     * Batch migrate SW5 EAN data for all products
     * 
     * @param Context $context
     * @param int $limit Number of products to process per batch
     * @return array Statistics about the migration
     */
    public function batchMigrateSw5EanData(Context $context, int $limit = 100): array
    {
        $stats = [
            'total' => 0,
            'migrated' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        $offset = 0;
        
        do {
            // Find products with SW5 EAN data but no new field
            $criteria = new Criteria();
            $criteria->setLimit($limit);
            $criteria->setOffset($offset);
            $criteria->addAssociation('translations');
            
            // This filter would need custom SQL since we need JSON path queries
            // For now, we'll check all products and filter in PHP
            
            $products = $this->productRepository->search($criteria, $context);
            
            if ($products->count() === 0) {
                break;
            }
            
            foreach ($products as $product) {
                $stats['total']++;
                
                $hasOldEan = false;
                $hasNewEan = false;
                
                foreach ($product->getTranslations() as $translation) {
                    $customFields = $translation->getCustomFields() ?? [];
                    
                    if (!empty($customFields['migration_SW566_product_attr12'])) {
                        $hasOldEan = true;
                    }
                    
                    if (!empty($customFields['custom_product_single_ean'])) {
                        $hasNewEan = true;
                    }
                }
                
                if ($hasOldEan && !$hasNewEan) {
                    try {
                        if ($this->migrateSw5EanData($product->getId(), $context)) {
                            $stats['migrated']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } catch (\Exception $e) {
                        $stats['failed']++;
                        $this->logger->error('SingleEanManager: Failed to migrate product', [
                            'productId' => $product->getId(),
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    $stats['skipped']++;
                }
            }
            
            $offset += $limit;
            
        } while ($products->count() === $limit);
        
        return $stats;
    }
    
    /**
     * Validate EAN format
     * 
     * @param string $ean
     * @return bool
     */
    public function validateEan(string $ean): bool
    {
        // Basic validation: 8 or 13 digits
        if (!preg_match('/^\d{8}$|^\d{13}$/', $ean)) {
            return false;
        }
        
        // Could add checksum validation here if needed
        
        return true;
    }
    
    /**
     * Update single EAN for a product
     * 
     * @param string $productId
     * @param string|null $ean
     * @param Context $context
     * @return bool
     */
    public function updateProductEan(string $productId, ?string $ean, Context $context): bool
    {
        if ($ean !== null && !$this->validateEan($ean)) {
            $this->logger->warning('SingleEanManager: Invalid EAN format', [
                'productId' => $productId,
                'ean' => $ean
            ]);
            return false;
        }
        
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('translations');
        
        $product = $this->productRepository->search($criteria, $context)->first();
        
        if (!$product) {
            return false;
        }
        
        $updateData = [
            'id' => $productId,
            'translations' => []
        ];
        
        foreach ($product->getTranslations() as $translation) {
            $customFields = $translation->getCustomFields() ?? [];
            
            if ($ean !== null) {
                $customFields['custom_product_single_ean'] = $ean;
            } else {
                unset($customFields['custom_product_single_ean']);
            }
            
            // Update description
            $description = $translation->getDescription();
            $newDescription = $this->descriptionUpdater->updateSingleEan($description, $ean);
            
            $updateData['translations'][] = [
                'languageId' => $translation->getLanguageId(),
                'customFields' => $customFields,
                'description' => $newDescription
            ];
        }
        
        if (!empty($updateData['translations'])) {
            $this->productRepository->update([$updateData], $context);
            
            $this->logger->info('SingleEanManager: Updated product EAN', [
                'productId' => $productId,
                'ean' => $ean
            ]);
            
            return true;
        }
        
        return false;
    }
}