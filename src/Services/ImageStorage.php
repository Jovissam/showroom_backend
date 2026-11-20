<?php

namespace App\Services;

use App\Core\AppException;
use App\Core\ImageProcessor;
use App\Core\Logger;

// Writes processed images under uploads/ and deletes them again. The database stores only
// relative paths (e.g. "portfolio/12/3fa9…-large.webp"); UPLOADS_URL turns them into URLs.
final class ImageStorage
{
    public static function root(): string
    {
        return dirname(__DIR__, 2) . '/uploads';
    }

    public static function url(?string $relative): ?string
    {
        return $relative ? UPLOADS_URL . '/' . $relative : null;
    }

    /**
     * Stores every variant of a processed image in $folder (relative to uploads/).
     * @return array<string, string> variant => relative path
     */
    public static function store(array $processed, string $folder, array $only = []): array
    {
        $folder = trim($folder, '/');
        $dir = self::root() . '/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new AppException('Could not save the image', 500);
        }
        $base = ImageProcessor::randomName();
        $paths = [];
        foreach ($processed['variants'] as $variant => $data) {
            if ($only && !in_array($variant, $only, true)) {
                continue;
            }
            $rel = "$folder/$base-$variant.webp";
            if (file_put_contents(self::root() . '/' . $rel, $data['bytes'], LOCK_EX) === false) {
                self::delete($paths);
                throw new AppException('Could not save the image', 500);
            }
            $paths[$variant] = $rel;
        }
        return $paths;
    }

    /** Deletes stored files. Only paths inside uploads/ are ever touched. */
    public static function delete(array $relativePaths): void
    {
        $root = realpath(self::root());
        foreach ($relativePaths as $rel) {
            if (!$rel || !$root) {
                continue;
            }
            $full = realpath(self::root() . '/' . $rel);
            if ($full && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full) && !@unlink($full)) {
                Logger::warning('uploads', 'Could not delete an uploaded file', ['path' => $rel]);
            }
        }
    }

    /** Removes an (emptied) project folder. */
    public static function removeFolder(string $folder): void
    {
        $dir = self::root() . '/' . trim($folder, '/');
        if (is_dir($dir)) {
            foreach (glob("$dir/*") ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }
    }
}
