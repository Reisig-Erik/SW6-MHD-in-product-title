<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Service;

class MhdDateParser
{
    /**
     * Parse manufacturer number in DDMMYY format to DateTime
     * 
     * @param string $manufacturerNumber The 6-digit date string
     * @return \DateTimeInterface|null Returns DateTime object or null if invalid
     */
    public function parseManufacturerNumber(string $manufacturerNumber): ?\DateTimeInterface
    {
        // Trim and check format
        $manufacturerNumber = trim($manufacturerNumber);
        
        if (!$this->isValidMhdFormat($manufacturerNumber)) {
            return null;
        }
        
        // Extract components
        $day = (int) substr($manufacturerNumber, 0, 2);
        $month = (int) substr($manufacturerNumber, 2, 2);
        $year = (int) substr($manufacturerNumber, 4, 2);
        
        // Convert 2-digit year to 4-digit (assume 2000+)
        $fullYear = 2000 + $year;
        
        // Validate date components
        if (!checkdate($month, $day, $fullYear)) {
            return null;
        }
        
        try {
            // Create DateTime object
            $date = \DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $fullYear, $month, $day));
            
            if ($date === false) {
                return null;
            }
            
            // Set time to midnight
            $date->setTime(0, 0, 0);
            
            return $date;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if the manufacturer number is in valid MHD format (DDMMYY)
     * 
     * @param string $manufacturerNumber
     * @return bool
     */
    public function isValidMhdFormat(string $manufacturerNumber): bool
    {
        // Must be exactly 6 digits
        if (!preg_match('/^\d{6}$/', $manufacturerNumber)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Format date for display in product title (DD.MM.YY)
     * 
     * @param \DateTimeInterface $date
     * @return string
     */
    public function formatForDisplay(\DateTimeInterface $date): string
    {
        return $date->format('d.m.y');
    }
    
    /**
     * Format date for storage in custom field (DD.MM.YY)
     * Same as display format for this implementation
     * 
     * @param \DateTimeInterface $date
     * @return string
     */
    public function formatForStorage(\DateTimeInterface $date): string
    {
        return $date->format('d.m.y');
    }
    
    /**
     * Convert DateTime back to manufacturer number format (DDMMYY)
     * 
     * @param \DateTimeInterface $date
     * @return string
     */
    public function formatToManufacturerNumber(\DateTimeInterface $date): string
    {
        return $date->format('dmy');
    }
}