<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1735738000CreateSingleEanField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1735738000;
    }

    public function update(Connection $connection): void
    {
        // Check if custom field set exists
        $setId = $this->getOrCreateCustomFieldSet($connection);
        
        // Create the single EAN custom field
        $this->createSingleEanField($connection, $setId);
    }

    private function getOrCreateCustomFieldSet(Connection $connection): string
    {
        // First check if our set exists
        $setId = $connection->fetchOne(
            'SELECT id FROM custom_field_set WHERE name = :name',
            ['name' => 'lebensmittel_product_details']
        );

        if ($setId) {
            return $setId;
        }

        // Create new set
        $setId = Uuid::randomBytes();
        
        $connection->insert('custom_field_set', [
            'id' => $setId,
            'name' => 'lebensmittel_product_details',
            'active' => 1,
            'global' => 0,
            'position' => 1,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ]);

        // Add translation
        $connection->insert('custom_field_set_translation', [
            'custom_field_set_id' => $setId,
            'language_id' => $this->getLanguageId($connection, 'de-DE'),
            'label' => 'Produktdetails',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ]);

        $connection->insert('custom_field_set_translation', [
            'custom_field_set_id' => $setId,
            'language_id' => $this->getLanguageId($connection, 'en-GB'),
            'label' => 'Product Details',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ]);

        // Assign to product entity
        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::randomBytes(),
            'set_id' => $setId,
            'entity_name' => 'product',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ]);

        return $setId;
    }

    private function createSingleEanField(Connection $connection, string $setId): void
    {
        // Check if field already exists
        $fieldExists = $connection->fetchOne(
            'SELECT id FROM custom_field WHERE name = :name',
            ['name' => 'custom_product_single_ean']
        );

        if ($fieldExists) {
            return;
        }

        // Create the field
        $fieldId = Uuid::randomBytes();
        
        $config = [
            'label' => [
                'de-DE' => 'Einzel EAN',
                'en-GB' => 'Single EAN'
            ],
            'helpText' => [
                'de-DE' => 'EAN-Code für Einzelprodukt',
                'en-GB' => 'EAN code for single product'
            ],
            'placeholder' => [
                'de-DE' => 'z.B. 4000539809033',
                'en-GB' => 'e.g. 4000539809033'
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'text',
            'customFieldPosition' => 2, // Position it after expiry date field
            'type' => 'text'
        ];

        $connection->insert('custom_field', [
            'id' => $fieldId,
            'name' => 'custom_product_single_ean',
            'type' => CustomFieldTypes::TEXT,
            'config' => json_encode($config),
            'set_id' => $setId,
            'active' => 1,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function getLanguageId(Connection $connection, string $code): string
    {
        $id = $connection->fetchOne(
            'SELECT LOWER(HEX(language.id)) FROM language 
             INNER JOIN locale ON locale.id = language.locale_id 
             WHERE locale.code = :code',
            ['code' => $code]
        );

        if (!$id) {
            throw new \RuntimeException('Language with code ' . $code . ' not found');
        }

        return hex2bin(str_replace('-', '', $id));
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}