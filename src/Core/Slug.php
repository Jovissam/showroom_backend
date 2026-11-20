<?php

namespace App\Core;

// SEO-friendly URL identifiers: "Modern Sliding-Door Wardrobe!" → "modern-sliding-door-wardrobe".
final class Slug
{
    public static function from(string $text, int $max = 120): string
    {
        $s = function_exists('transliterator_transliterate')
            ? (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text)
            : strtolower($text);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim(substr(trim($s, '-'), 0, $max), '-');
        return $s !== '' ? $s : 'item';
    }

    /** First free variant of $base (`base`, `base-2`, `base-3`…) according to $taken. */
    public static function unique(string $base, callable $taken): string
    {
        $slug = $base;
        for ($n = 2; $taken($slug); $n++) {
            $slug = substr($base, 0, 110) . '-' . $n;
        }
        return $slug;
    }
}
