# LebensmittelMhdManager Plugin

## Description
This Shopware 6 plugin automatically manages MHD (Mindesthaltbarkeitsdatum/Best Before Date) and single EAN information by synchronizing data between custom fields, product titles, and HTML spans in product descriptions.

## Features

### MHD (Expiry Date) Management
- Automatically parses DDMMYY and YYYY formats from manufacturer_number field
- Updates product titles with "MHD DD.MM.YY" suffix
- Syncs with custom_product_detail_expiry_date field
- Updates invisible-date spans in product descriptions with full date format

### Single EAN Management
- Manages single EAN codes via custom_product_single_ean field
- Updates single-ean spans in product descriptions
- Automatically creates spans if not present when data changes
- Removes spans when EAN is cleared

### General Features
- Handles all product update events (admin, API, imports)
- Multi-language support
- Per-sales-channel configuration
- Automatic HTML span management in descriptions

## Installation

```bash
# From Shopware root directory
php bin/console plugin:refresh
php bin/console plugin:install LebensmittelMhdManager
php bin/console plugin:activate LebensmittelMhdManager
php bin/console cache:clear
```

## Configuration

1. Navigate to Settings > Plugins > MHD Manager
2. Select sales channels where the plugin should be active (leave empty for all)

## Usage

The plugin works automatically when:
- Creating or updating products in admin
- Updating products via API
- Importing products
- Updating custom fields

### MHD Date Format
- Input: DDMMYY or YYYY in manufacturer_number field (e.g., "250324" for March 25, 2024, or "2025" for year 2025)
- Title Output: "MHD DD.MM.YY" appended to product title
- Description Span: `<span class="invisible-date">Mindestens haltbar bis: DD.MM.YYYY</span>`

### Single EAN Format
- Input: EAN code in custom_product_single_ean field
- Description Span: `<span class="single-ean">Einzel EAN: [EAN]</span>`

### Examples

#### MHD Examples
- Product: "Nutella 450g" + manufacturer_number: "311224"
  → Title: "Nutella 450g MHD 31.12.24"
  → Description contains: `<span class="invisible-date">Mindestens haltbar bis: 31.12.2024</span>`
  
- Product: "Kinder Riegel MHD 01.01.24" + manufacturer_number: "311224"
  → Title: "Kinder Riegel MHD 31.12.24" (replaces existing date)
  
- Product: "Haribo 200g MHD 15.06.24" + manufacturer_number: "" (cleared)
  → Title: "Haribo 200g" (removes MHD)
  → Description: invisible-date span removed

#### EAN Examples  
- Product with custom_product_single_ean: "4001686322840"
  → Description contains: `<span class="single-ean">Einzel EAN: 4001686322840</span>`
  
- Product with custom_product_single_ean cleared
  → Description: single-ean span removed

## Development

### Running Tests
```bash
# Unit tests
vendor/bin/phpunit tests/Unit

# Integration tests
vendor/bin/phpunit tests/Integration
```

### File Structure
```
LebensmittelMhdManager/
├── src/
│   ├── Migration/
│   │   └── Migration1735738000CreateSingleEanField.php # Creates custom field
│   ├── Service/
│   │   ├── MhdDateParser.php           # Date parsing logic
│   │   ├── ProductTitleUpdater.php     # Title manipulation
│   │   ├── ProductDescriptionUpdater.php # HTML span management
│   │   └── SingleEanManager.php        # EAN field handling
│   ├── Subscriber/
│   │   └── ProductSubscriber.php       # Event handling for both MHD and EAN
│   └── Resources/
│       └── config/
│           ├── services.xml            # Service definitions
│           └── config.xml              # Plugin configuration
└── composer.json
```

## Troubleshooting

### MHD not appearing in title
- Check manufacturer_number is exactly 6 digits (DDMMYY) or 4 digits (YYYY)
- Verify the date is valid (not 32.13.24)
- Check plugin is activated
- Clear cache after changes

### EAN not appearing in description
- Check custom_product_single_ean field has a value
- Verify the product_translation.written event is being triggered
- Check that description field allows HTML content
- Clear cache after changes

### Performance issues
- The plugin uses Shopware's event system
- Processing is lightweight and async where possible
- For bulk updates, consider using batch operations

## Migration

The plugin includes migration script to:
1. Create custom_product_single_ean field
2. Migrate data from SW5 field (migration_SW566_product_attr12)
3. Update product descriptions with appropriate spans

Run migration after installation:
```bash
php bin/console database:migrate LebensmittelMhdManager
```

## License
Proprietary