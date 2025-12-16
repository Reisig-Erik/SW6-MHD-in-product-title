<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Service;

class ProductDescriptionUpdater
{
    /**
     * Update or create invisible-date span in description
     * 
     * @param string|null $description Current description HTML
     * @param string|null $mhdDate Formatted date (DD.MM.YYYY) or null to remove
     * @return string Updated description
     */
    public function updateInvisibleDate(?string $description, ?string $mhdDate): string
    {
        if ($description === null) {
            $description = '';
        }
        
        // Pattern to find existing invisible-date span
        $pattern = '/<span[^>]*class=["\']invisible-date["\'][^>]*>.*?<\/span>/is';
        
        if (empty($mhdDate)) {
            // Remove the span if no date
            return preg_replace($pattern, '', $description);
        }
        
        // Format the content for the span
        $spanContent = 'Mindestens haltbar bis: ' . $mhdDate;
        $newSpan = '<span class="invisible-date">' . $spanContent . '</span>';
        
        // Check if span exists
        if (preg_match($pattern, $description)) {
            // Replace existing span
            return preg_replace($pattern, $newSpan, $description);
        } else {
            // Add span at the end of description with double newline for proper spacing
            return trim($description) . "\n\n" . $newSpan;
        }
    }
    
    /**
     * Update or create single-ean span in description
     * 
     * @param string|null $description Current description HTML
     * @param string|null $ean EAN code or null to remove
     * @return string Updated description
     */
    public function updateSingleEan(?string $description, ?string $ean): string
    {
        if ($description === null) {
            $description = '';
        }
        
        // Pattern to find the specific single-ean span with "Einzel EAN:"
        // We need to be precise to avoid matching other single-ean spans
        $pattern = '/<span[^>]*class=["\']single-ean["\'][^>]*>Einzel\s+EAN:\s*[^<]*<\/span>/is';
        
        if (empty($ean)) {
            // Remove the span if no EAN
            return preg_replace($pattern, '', $description);
        }
        
        // Format the content for the span
        $spanContent = 'Einzel EAN: ' . $ean;
        $newSpan = '<span class="single-ean">' . $spanContent . '</span>';
        
        // Check if our specific EAN span exists
        if (preg_match($pattern, $description)) {
            // Replace existing span
            return preg_replace($pattern, $newSpan, $description);
        } else {
            // Add span at the end of description with double newline for proper spacing
            return trim($description) . "\n\n" . $newSpan;
        }
    }
    
    /**
     * Find content of a specific span class
     * 
     * @param string|null $description Description HTML
     * @param string $class Class name to search for
     * @return string|null Content of the span or null if not found
     */
    public function findSpanContent(?string $description, string $class): ?string
    {
        if (empty($description)) {
            return null;
        }
        
        // Build pattern for the specific class
        $pattern = '/<span[^>]*class=["\']*' . preg_quote($class, '/') . '["\']*[^>]*>(.*?)<\/span>/is';
        
        if (preg_match($pattern, $description, $matches)) {
            return strip_tags($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Extract MHD date from invisible-date span
     * 
     * @param string|null $description
     * @return string|null Date in DD.MM.YYYY format or null
     */
    public function extractMhdFromDescription(?string $description): ?string
    {
        $content = $this->findSpanContent($description, 'invisible-date');
        
        if ($content && preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $content, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
    
    /**
     * Extract EAN from single-ean span
     * 
     * @param string|null $description
     * @return string|null EAN or null
     */
    public function extractEanFromDescription(?string $description): ?string
    {
        if (empty($description)) {
            return null;
        }
        
        // Look specifically for "Einzel EAN: " pattern
        $pattern = '/<span[^>]*class=["\']single-ean["\'][^>]*>Einzel\s+EAN:\s*([^<]*)<\/span>/is';
        
        if (preg_match($pattern, $description, $matches)) {
            $ean = trim($matches[1]);
            // Validate it looks like an EAN (8-13 digits)
            if (preg_match('/^\d{8,13}$/', $ean)) {
                return $ean;
            }
        }
        
        return null;
    }
    
    /**
     * Clean description from migration artifacts
     * Removes empty spans and cleans up whitespace
     * 
     * @param string|null $description
     * @return string Cleaned description
     */
    public function cleanDescription(?string $description): string
    {
        if (empty($description)) {
            return '';
        }
        
        // Remove empty spans
        $description = preg_replace('/<span[^>]*>\s*<\/span>/is', '', $description);
        
        // Remove multiple consecutive line breaks
        $description = preg_replace('/(\n\s*){3,}/', "\n\n", $description);
        
        // Trim whitespace
        return trim($description);
    }
}