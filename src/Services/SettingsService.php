<?php

namespace App\Services;

use App\Core\AppException;
use App\Core\Validator;
use App\Repositories\SettingsRepository;

// Showroom settings: copy and limits for the public portfolio and homepage sections.
// Only the keys listed here can be stored, each with its own validation rule.
final class SettingsService
{
    public const RULES = [
        'portfolio_title' => 'required|string|max:120',
        'portfolio_intro' => 'nullable|string|max:600',
        'homepage_featured_limit' => 'required|int|min:0|max:12',
        'homepage_latest_limit' => 'required|int|min:0|max:12',
        'portfolio_per_page' => 'required|int|min:3|max:48',
        'enquiry_cta_title' => 'nullable|string|max:120',
        'enquiry_cta_text' => 'nullable|string|max:400',
    ];
    private const INTS = ['homepage_featured_limit', 'homepage_latest_limit', 'portfolio_per_page'];
    private const DEFAULTS = [
        'portfolio_title' => 'Our work', 'portfolio_intro' => null, 'homepage_featured_limit' => 6,
        'homepage_latest_limit' => 6, 'portfolio_per_page' => 12, 'enquiry_cta_title' => null, 'enquiry_cta_text' => null,
    ];

    public function all(): array
    {
        $stored = (new SettingsRepository())->all();
        $out = [];
        foreach (self::DEFAULTS as $k => $default) {
            $v = $stored[$k] ?? null;
            $out[$k] = in_array($k, self::INTS, true) ? (int) ($v ?? $default) : ($v ?? $default);
        }
        return $out;
    }

    public function int(string $key): int
    {
        return (int) $this->all()[$key];
    }

    public function save(array $body): array
    {
        $unknown = array_diff(array_keys($body), array_keys(self::RULES));
        if ($unknown) {
            throw new AppException('Validation failed', 422, ['settings' => ['Unknown setting: ' . implode(', ', $unknown)]]);
        }
        $merged = array_merge($this->all(), $body);
        $d = Validator::make($merged, self::RULES);
        foreach ($d as $k => $v) {
            if (is_string($v)) {
                $d[$k] = trim(strip_tags($v)) ?: null;
            }
        }
        (new SettingsRepository())->save($d);
        return $this->all();
    }
}
