# LebensmittelMhdManager Plugin

## Description
This Shopware 6 plugin automatically manages MHD (Mindesthaltbarkeitsdatum/Best Before Date) information by synchronizing the manufacturer_number field with product titles and custom expiry date fields.

## Features
- Automatically parses DDMMYY format from manufacturer_number field
- Updates product titles with "MHD DD.MM.YY" suffix
- Syncs with custom_product_detail_expiry_date field
- Handles all product update events (admin, API, imports)
- Multi-language support
- Per-sales-channel configuration

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

### Date Format
- Input: DDMMYY in manufacturer_number field (e.g., "250324" for March 25, 2024)
- Output: "MHD 25.03.24" appended to product title

### Examples
- Product: "Nutella 450g" + manufacturer_number: "311224"
  → "Nutella 450g MHD 31.12.24"
  
- Product: "Kinder Riegel MHD 01.01.24" + manufacturer_number: "311224"
  → "Kinder Riegel MHD 31.12.24" (replaces existing date)
  
- Product: "Haribo 200g MHD 15.06.24" + manufacturer_number: "" (cleared)
  → "Haribo 200g" (removes MHD)

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
│   ├── Service/
│   │   ├── MhdDateParser.php       # Date parsing logic
│   │   └── ProductTitleUpdater.php # Title manipulation
│   ├── Subscriber/
│   │   └── ProductSubscriber.php   # Event handling
│   └── Resources/
│       └── config/
│           ├── services.xml        # Service definitions
│           └── config.xml          # Plugin configuration
└── composer.json
```

## Troubleshooting

### MHD not appearing in title
- Check manufacturer_number is exactly 6 digits
- Verify the date is valid (not 32.13.24)
- Check plugin is activated
- Clear cache after changes

### Performance issues
- The plugin uses Shopware's event system
- Processing is lightweight and async where possible
- For bulk updates, consider using batch operations

## License
Proprietary