<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Service;

class ProductTitleUpdater
{
    /**
     * Pattern to match MHD date in product title
     * Matches: " MHD DD.MM.YY" or " MHD DD.MM.YYYY" at the end of string
     */
    private const MHD_PATTERN = '/\sMHD\s\d{2}\.\d{2}\.\d{2,4}\s*$/';
    
    /**
     * Update product title with MHD date
     * If MHD already exists, replace it. Otherwise append it.
     * 
     * @param string $title Current product title
     * @param string $mhdDate Formatted date (DD.MM.YY)
     * @return string Updated title
     */
    public function updateTitle(string $title, string $mhdDate): string
    {
        $title = trim($title);
        
        if (empty($title)) {
            return $title;
        }
        
        // Check if MHD already exists in title
        if ($this->hasMhd($title)) {
            return $this->replaceMhd($title, $mhdDate);
        }
        
        return $this->appendMhd($title, $mhdDate);
    }
    
    /**
     * Remove MHD date from product title
     * 
     * @param string $title
     * @return string Title without MHD
     */
    public function removeMhdFromTitle(string $title): string
    {
        $title = trim($title);
        
        if (empty($title)) {
            return $title;
        }
        
        // Remove MHD pattern from the end of title
        $cleanTitle = preg_replace(self::MHD_PATTERN, '', $title);
        
        return trim($cleanTitle);
    }
    
    /**
     * Check if title already contains MHD date
     * 
     * @param string $title
     * @return bool
     */
    private function hasMhd(string $title): bool
    {
        return (bool) preg_match(self::MHD_PATTERN, $title);
    }
    
    /**
     * Replace existing MHD date in title
     * 
     * @param string $title
     * @param string $newDate
     * @return string
     */
    private function replaceMhd(string $title, string $newDate): string
    {
        // Replace the existing MHD date with the new one
        $updatedTitle = preg_replace(
            self::MHD_PATTERN,
            ' MHD ' . $newDate,
            $title
        );
        
        return trim($updatedTitle);
    }
    
    /**
     * Append MHD date to title
     * 
     * @param string $title
     * @param string $mhdDate
     * @return string
     */
    private function appendMhd(string $title, string $mhdDate): string
    {
        // Ensure single space before MHD
        return $title . ' MHD ' . $mhdDate;
    }
    
    /**
     * Extract MHD date from title if present
     * 
     * @param string $title
     * @return string|null The date string (DD.MM.YY) or null if not found
     */
    public function extractMhdFromTitle(string $title): ?string
    {
        if (preg_match(self::MHD_PATTERN, $title, $matches)) {
            // Extract just the date part (remove " MHD " prefix)
            $mhdPart = trim($matches[0]);
            $parts = explode(' ', $mhdPart);
            
            return end($parts);
        }
        
        return null;
    }
}