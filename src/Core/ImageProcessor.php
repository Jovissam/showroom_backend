<?php

namespace App\Core;

// The one way an uploaded image is accepted (adapted from LeasePro's ImageUpload).
//
//  - JPG, JPEG, PNG or WebP only, decided from the file's CONTENT (finfo + getimagesize must
//    agree), never its name or the type the browser claims. SVG, GIF, PDF etc. are refused.
//  - Size-capped (PORTFOLIO_MAX_UPLOAD_MB) and pixel-capped (40 MP / 10,000 px a side), so a
//    small file can't expand into something that exhausts memory ("decompression bomb").
//  - Re-encoded: decoded to pixels and written out again as WebP. Anything that isn't pixels
//    is dropped (appended payloads, scripts in comments, EXIF such as GPS). Phone photos are
//    turned upright first. The caller stores results under new random names.
//  - Produces optimised variants for responsive delivery: large / medium / thumb.
final class ImageProcessor
{
    private const MAX_PIXELS = 40_000_000;
    private const MAX_SIDE = 10_000;
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    /** Longest side per variant, in pixels. */
    public const VARIANTS = ['large' => 1920, 'medium' => 1024, 'thumb' => 480];
    private const QUALITY = ['large' => 82, 'medium' => 80, 'thumb' => 76];

    public static function maxBytes(): int
    {
        return (int) (defined('PORTFOLIO_MAX_UPLOAD_MB') ? PORTFOLIO_MAX_UPLOAD_MB : 10) * 1024 * 1024;
    }

    /**
     * Validates one $_FILES entry and returns WebP variants.
     * @return array{width: int, height: int, variants: array<string, array{bytes: string, width: int, height: int}>}
     */
    public static function fromUpload(array $file, string $field): array
    {
        $mb = (int) (self::maxBytes() / 1024 / 1024);
        $name = mb_substr((string) ($file['name'] ?? 'image'), 0, 80);
        $fail = fn (string $msg) => new AppException('Validation failed', 422, [$field => ["$name: $msg"]]);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new AppException('Validation failed', 422, [$field => ['Choose at least one image to upload']]);
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > self::maxBytes()) {
            throw $fail("must be $mb MB or smaller");
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw $fail('could not be uploaded, please try again');
        }
        return self::process($file['tmp_name'], $fail);
    }

    /** Validates and processes an image on disk (used by fromUpload and by tests). */
    public static function process(string $path, ?\Closure $fail = null): array
    {
        $fail ??= fn (string $msg) => new AppException('Validation failed', 422, ['image' => [$msg]]);
        $size = (int) @filesize($path);
        if ($size > self::maxBytes()) {
            throw $fail('is too large');
        }
        if ($size < 32) {
            throw $fail('is empty or unreadable');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!in_array($mime, self::ALLOWED, true)) {
            throw $fail('must be a JPG, PNG or WebP image');
        }
        $info = @getimagesize($path);
        if ($info === false || ($info['mime'] ?? '') !== $mime) {
            throw $fail('looks damaged');
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w > self::MAX_SIDE || $h > self::MAX_SIDE || $w * $h > self::MAX_PIXELS) {
            throw $fail('is too large in pixels (max 10,000 px a side)');
        }
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };
        if (!$img) {
            throw $fail('looks damaged');
        }

        $level = ob_get_level();
        try {
            if ($mime === 'image/jpeg') {
                $img = self::upright($img, $path);
            }
            imagepalettetotruecolor($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $variants = [];
            foreach (self::VARIANTS as $key => $max) {
                $scaled = self::fit($img, $max);
                ob_start();
                imagewebp($scaled, null, self::QUALITY[$key]);
                $bytes = (string) ob_get_clean();
                if ($bytes === '') {
                    throw $fail('could not be processed, please try another image');
                }
                $variants[$key] = ['bytes' => $bytes, 'width' => imagesx($scaled), 'height' => imagesy($scaled)];
                if ($scaled !== $img) {
                    imagedestroy($scaled);
                }
            }
            return ['width' => imagesx($img), 'height' => imagesy($img), 'variants' => $variants, 'focal' => self::focalPoint($img)];
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            imagedestroy($img);
        }
    }

    /**
     * Where the subject of a photo is, as [x, y] in 0..1 from the left/top. Frames that crop a
     * photo (cards, thumbnails) centre on this point via CSS object-position.
     *
     * Heuristic tuned for furniture photos: detail (luminance edges) marks the furniture, weighted
     * by a centre-biased Gaussian shifted slightly down, because these photos tend to have busy
     * ceilings (lights, POP mouldings) at the top and tiled floors at the bottom edge. The result
     * is clamped to 0.2..0.8 so a crop never hugs an edge. Admins can override it per image.
     *
     * @return array{0: float, 1: float}
     */
    public static function focalPoint(\GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $sw = 96;
        $sh = max(8, (int) round($h * $sw / max(1, $w)));
        $small = imagescale($img, $sw, $sh, IMG_BILINEAR_FIXED);
        if (!$small) {
            return [0.5, 0.5];
        }
        $lum = [];
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $c = imagecolorat($small, $x, $y);
                $lum[$y][$x] = 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
            }
        }
        imagedestroy($small);

        $sum = $sx = $sy = 0.0;
        $sigX = 0.32;
        $sigY = 0.30;
        for ($y = 1; $y < $sh - 1; $y++) {
            for ($x = 1; $x < $sw - 1; $x++) {
                $gx = $lum[$y][$x + 1] - $lum[$y][$x - 1];
                $gy = $lum[$y + 1][$x] - $lum[$y - 1][$x];
                $energy = sqrt($gx * $gx + $gy * $gy);
                $u = $x / ($sw - 1);
                $v = $y / ($sh - 1);
                $weight = exp(-(($u - 0.5) ** 2) / (2 * $sigX ** 2) - (($v - 0.55) ** 2) / (2 * $sigY ** 2));
                $e = $energy * $weight;
                $sum += $e;
                $sx += $e * $u;
                $sy += $e * $v;
            }
        }
        if ($sum <= 0) {
            return [0.5, 0.5];
        }
        $clamp = fn (float $v) => round(min(0.8, max(0.2, $v)), 3);
        return [$clamp($sx / $sum), $clamp($sy / $sum)];
    }

    public static function randomName(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function upright(\GdImage $img, string $path): \GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated) {
            imagedestroy($img);
            return $rotated;
        }
        return $img;
    }

    /** Scaled copy no larger than $max on its longest side (or the original if already small). */
    private static function fit(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $max && $h <= $max) {
            return $img;
        }
        $scale = $max / max($w, $h);
        $resized = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC);
        if (!$resized) {
            return $img;
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        return $resized;
    }
}
