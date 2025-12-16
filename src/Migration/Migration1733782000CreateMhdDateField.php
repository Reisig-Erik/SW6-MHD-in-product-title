<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1733782000CreateMhdDateField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733782000;
    }

    public function update(Connection $connection): void
    {
        // Check if custom field set already exists
        $customFieldSetId = $connection->fetchOne(
            'SELECT id FROM custom_field_set WHERE name = :name',
            ['name' => 'custom_product_mhd']
        );

        if (!$customFieldSetId) {
            $customFieldSetId = Uuid::randomBytes();

            // Create custom field set
            $connection->insert('custom_field_set', [
                'id' => $customFieldSetId,
                'name' => 'custom_product_mhd',
                'config' => json_encode([
                    'label' => [
                        'de-DE' => 'MHD Datum',
                        'en-GB' => 'Best Before Date'
                    ]
                ]),
                'active' => 1,
                'position' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            // Assign to product entity
            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'set_id' => $customFieldSetId,
                'entity_name' => 'product',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // Check if custom field already exists
        $customFieldExists = $connection->fetchOne(
            'SELECT id FROM custom_field WHERE name = :name',
            ['name' => 'custom_product_mhd_date']
        );

        if (!$customFieldExists) {
            // Create the datetime custom field
            $connection->insert('custom_field', [
                'id' => Uuid::randomBytes(),
                'name' => 'custom_product_mhd_date',
                'type' => 'datetime',
                'config' => json_encode([
                    'label' => [
                        'de-DE' => 'MHD Datum',
                        'en-GB' => 'Best Before Date'
                    ],
                    'helpText' => [
                        'de-DE' => 'Mindesthaltbarkeitsdatum als Datumsfeld für dynamische Produktgruppen',
                        'en-GB' => 'Best before date as datetime field for dynamic product groups'
                    ],
                    'type' => 'datetime',
                    'dateType' => 'date',
                    'customFieldType' => 'date',
                    'customFieldPosition' => 1,
                    'componentName' => 'sw-field',
                ]),
                'active' => 1,
                'set_id' => $customFieldSetId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do here
    }
}
