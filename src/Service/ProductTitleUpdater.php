<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Service;

class ProductTitleUpdater
{
    /**
     * Pattern to match MHD and everything after it
     * Matches: MHD (with optional colon) followed by anything until end of string
     * Case-insensitive, handles variations like:
     * - MHD 31.12.24
     * - MHD: 12/24
     * - MHD12.2024
     * - mhd 31-12-2024
     */
    private const MHD_PATTERN = '/\s*MHD:?\s*.+$/i';
    
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
     * @return string|null The full MHD string or null if not found
     */
    public function extractMhdFromTitle(string $title): ?string
    {
        if (preg_match(self::MHD_PATTERN, $title, $matches)) {
            // Return the full matched MHD string
            return trim($matches[0]);
        }
        
        return null;
    }
}