<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Service;

use Shopware\Core\Defaults;

class MhdCustomFieldSynchronizer
{
    private MhdDateParser $mhdDateParser;

    public function __construct(MhdDateParser $mhdDateParser)
    {
        $this->mhdDateParser = $mhdDateParser;
    }

    /**
     * Convert manufacturerNumber (DDMMYY format) to ISO datetime string for custom field storage
     *
     * @param string|null $manufacturerNumber The MHD in DDMMYY format
     * @return string|null ISO datetime string or null if invalid
     */
    public function convertToCustomFieldValue(?string $manufacturerNumber): ?string
    {
        if ($manufacturerNumber === null || $manufacturerNumber === '') {
            return null;
        }

        $dateTime = $this->mhdDateParser->parseManufacturerNumber($manufacturerNumber);

        if ($dateTime === null) {
            return null;
        }

        // Return ISO format for Shopware datetime custom field
        return $dateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    /**
     * Calculate days until MHD from manufacturerNumber
     *
     * @param string|null $manufacturerNumber The MHD in DDMMYY format
     * @return int|null Days until MHD (negative = expired) or null if invalid
     */
    public function calculateDaysUntilMhd(?string $manufacturerNumber): ?int
    {
        if ($manufacturerNumber === null || $manufacturerNumber === '') {
            return null;
        }

        $mhdDate = $this->mhdDateParser->parseManufacturerNumber($manufacturerNumber);

        if ($mhdDate === null) {
            return null;
        }

        $today = new \DateTime('today');
        $diff = $today->diff($mhdDate);

        // Return positive for future dates, negative for past dates
        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Get the custom field name for MHD date
     */
    public function getCustomFieldName(): string
    {
        return 'custom_product_mhd_date';
    }

    /**
     * Get the custom field name for days until MHD
     */
    public function getDaysFieldName(): string
    {
        return 'custom_product_mhd_days';
    }

    /**
     * Build custom fields array with MHD date for product update
     *
     * @param array|null $existingCustomFields Existing custom fields array
     * @param string|null $manufacturerNumber MHD in DDMMYY format
     * @return array Updated custom fields array
     */
    public function buildCustomFieldsWithMhdDate(?array $existingCustomFields, ?string $manufacturerNumber): array
    {
        $customFields = $existingCustomFields ?? [];

        $mhdDateValue = $this->convertToCustomFieldValue($manufacturerNumber);

        if ($mhdDateValue !== null) {
            $customFields[$this->getCustomFieldName()] = $mhdDateValue;
        } else {
            // Remove the field if MHD is not valid
            unset($customFields[$this->getCustomFieldName()]);
        }

        return $customFields;
    }
}
