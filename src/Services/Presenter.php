<?php

namespace App\Services;

// Turns database rows into API objects: typed values, image URLs instead of paths, and
// nested category objects. The public flag hides admin-only fields.
final class Presenter
{
    public static function image(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'url' => ImageStorage::url($r['image_path']),
            'medium_url' => ImageStorage::url($r['medium_path']),
            'thumbnail_url' => ImageStorage::url($r['thumbnail_path']),
            'width' => (int) $r['width'],
            'height' => (int) $r['height'],
            // Subject position (0..1) for object-position wherever the photo is cropped.
            'focal_x' => (float) ($r['focal_x'] ?? 0.5),
            'focal_y' => (float) ($r['focal_y'] ?? 0.5),
            'alt_text' => $r['alt_text'],
            'display_order' => (int) $r['display_order'],
            'is_featured' => (bool) $r['is_featured'],
        ];
    }

    public static function category(array $r, bool $public = false): array
    {
        $out = [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
            'description' => $r['description'],
            'image_url' => ImageStorage::url($r['image'] ?? null),
            'display_order' => (int) $r['display_order'],
            'project_count' => (int) ($public ? ($r['published_count'] ?? 0) : ($r['project_count'] ?? 0)),
        ];
        if (!$public) {
            $out += [
                'status' => $r['status'],
                'published_count' => (int) ($r['published_count'] ?? 0),
                'created_at' => $r['created_at'],
                'updated_at' => $r['updated_at'],
            ];
        }
        return $out;
    }

    /**
     * @param array[]|null $images the project's image rows; null = summary (featured image only)
     */
    public static function project(array $r, ?array $images, bool $public = false, ?array $featuredRow = null): array
    {
        $category = null;
        // Inactive categories are hidden from the public site; their projects remain listed.
        if ($r['category_id'] !== null && (!$public || ($r['category_status'] ?? 'active') === 'active')) {
            $category = ['id' => (int) $r['category_id'], 'name' => $r['category_name'], 'slug' => $r['category_slug']];
        }
        if ($featuredRow === null && $images !== null) {
            foreach ($images as $img) {
                if ((int) $img['id'] === (int) $r['featured_image_id']) {
                    $featuredRow = $img;
                }
            }
            $featuredRow ??= $images[0] ?? null;
        }
        $out = [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'slug' => $r['slug'],
            'description' => $r['description'],
            'materials_used' => $r['materials_used'],
            'dimensions' => $r['dimensions'],
            'location' => $r['location'],
            'completion_date' => $r['completion_date'],
            'is_featured' => (bool) $r['is_featured'],
            'category' => $category,
            'featured_image' => $featuredRow ? self::image($featuredRow) : null,
            'image_count' => (int) ($r['image_count'] ?? 0),
            'meta_title' => $r['meta_title'],
            'meta_description' => $r['meta_description'],
            'published_at' => $r['published_at'],
            'updated_at' => $r['updated_at'],
        ];
        if (!$public) {
            $out += [
                'status' => $r['status'],
                'display_order' => (int) $r['display_order'],
                'featured_order' => (int) $r['featured_order'],
                'category_id' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
                'created_at' => $r['created_at'],
            ];
        }
        if ($images !== null) {
            $out['images'] = array_map([self::class, 'image'], $images);
        }
        return $out;
    }
}
