<?php
declare(strict_types=1);
namespace Learmingo\Services;

/** Site-wide visual content. File-based to avoid changes to the production DB schema. */
final class FrontendSettings
{
    public static function file(): string { return dirname(__DIR__, 2) . '/storage/frontend/site.json'; }
    public static function defaults(): array
    {
        return [
            'brand' => 'Learmingo', 'logo_light' => '', 'logo_dark' => '',
            'header_search' => '1', 'header_sticky' => '1', 'search_hint' => 'Search courses, skills, topics...',
            'courses_label' => 'Courses', 'categories_label' => 'Categories',
            'courses_url' => '/courses', 'categories_url' => '/categories',
            'sign_in_label' => 'Sign In', 'sign_up_label' => 'Sign Up',
            'default_theme' => 'system', 'footer_about' => 'Learn practical skills, grow your confidence, and make progress at your own pace.',
            'footer_columns' => '4', 'copyright' => 'Copyright 2026 | All Rights Reserved.',
            'hero_eyebrow' => 'Learn · Grow · Advance', 'hero_title' => 'Learn skills that move you forward.',
            'hero_subtitle' => 'Build real-world skills with focused courses and learn at your own pace.',
            'hero_button' => 'Explore courses', 'hero_button_url' => '/courses',
            'featured_title' => 'Featured Courses', 'featured_subtitle' => 'Discover courses to build practical skills.',
            'social_facebook' => '', 'social_instagram' => '', 'social_linkedin' => '', 'social_youtube' => '',
            'footer_title_1' => 'Learmingo', 'footer_title_2' => 'Explore', 'footer_title_3' => 'Learning', 'footer_title_4' => 'Support',
            'footer_links_1' => '',
            'footer_links_2' => "Courses|/courses\nCategories|/categories",
            'footer_links_3' => "My learning|/dashboard\nBecome a learner|/register",
            'footer_links_4' => "Sign in|/login\nContact support|https://tonsamith.com/contact",
        ];
    }
    public static function all(): array
    {
        $defaults = self::defaults(); $path = self::file();
        if (is_link($path) || !is_file($path)) { return $defaults; }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || strlen($raw) > 40000) { return $defaults; }
        $data = json_decode($raw, true);
        if (!is_array($data)) { return $defaults; }
        return array_replace($defaults, array_intersect_key(array_filter($data, 'is_string'), $defaults));
    }
    public static function cleanUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') { return ''; }
        if (strlen($url) > 500 || preg_match('/[\x00-\x1F\x7F\\\\<>"\']/', $url)) {
            throw new \InvalidArgumentException('Invalid link URL.');
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) { return $url; }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Links must be site-relative or HTTPS.');
        }
        return $url;
    }
    public static function links(string $value): array
    {
        $result = [];
        foreach (preg_split('/\r?\n/', $value) ?: [] as $line) {
            if (trim($line) === '') { continue; }
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) { continue; }
            try { $url = self::cleanUrl(trim($parts[1])); }
            catch (\InvalidArgumentException) { continue; }
            $title = trim($parts[0]);
            if ($title !== '' && $url !== '') { $result[] = ['title' => $title, 'url' => $url]; }
        }
        return array_slice($result, 0, 8);
    }
    public static function save(array $post): void
    {
        $defaults = self::defaults(); $data = [];
        foreach ($defaults as $key => $default) {
            $value = $post[$key] ?? (($key === 'header_search' || $key === 'header_sticky') ? '0' : $default);
            if (!is_string($value) || strlen($value) > ($key === 'footer_links_1' || str_starts_with($key, 'footer_links_') ? 1800 : 600)) {
                throw new \InvalidArgumentException('Invalid value for ' . $key);
            }
            $value = trim($value);
            if (str_starts_with($key, 'social_') || str_ends_with($key, '_url') || str_starts_with($key, 'logo_')) {
                $value = self::cleanUrl($value);
            }
            if (str_starts_with($key, 'footer_links_')) {
                if (count(preg_split('/\r?\n/', $value) ?: []) > 8) { throw new \InvalidArgumentException('Maximum 8 links per column.'); }
                foreach (preg_split('/\r?\n/', $value) ?: [] as $line) {
                    if (trim($line) === '') { continue; }
                    $pair = explode('|', $line, 2);
                    if (count($pair) !== 2 || trim($pair[0]) === '' || strlen(trim($pair[0])) > 80) {
                        throw new \InvalidArgumentException('Use Label|/path for footer links.');
                    }
                    if (self::cleanUrl(trim($pair[1])) === '') { throw new \InvalidArgumentException('Footer link URL required.'); }
                }
            }
            if (in_array($key, ['header_search', 'header_sticky'], true)) { $value = $value === '1' ? '1' : '0'; }
            if ($key === 'default_theme' && !in_array($value, ['light','dark','system'], true)) { throw new \InvalidArgumentException('Invalid theme.'); }
            if ($key === 'footer_columns' && !in_array($value, ['2','3','4'], true)) { throw new \InvalidArgumentException('Choose 2, 3, or 4 footer columns.'); }
            $data[$key] = $value;
        }
        $path = self::file(); $dir = dirname($path);
        if (is_link($dir) || !is_dir($dir) || is_link($path)) { throw new \RuntimeException('Frontend settings storage is unavailable.'); }
        $lock = $dir . '/.settings.lock';
        if (is_link($lock)) { throw new \RuntimeException('Settings lock is unsafe.'); }
        $handle = fopen($lock, 'c');
        if ($handle === false) { throw new \RuntimeException('Cannot lock settings.'); }
        try {
            if (!flock($handle, LOCK_EX)) { throw new \RuntimeException('Cannot lock settings.'); }
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $temporary = tempnam($dir, '.setting-');
            if ($temporary === false) { throw new \RuntimeException('Cannot create settings file.'); }
            try {
                chmod($temporary, 0640);
                if (file_put_contents($temporary, $json . "\n") === false || !rename($temporary, $path)) {
                    throw new \RuntimeException('Cannot save settings.');
                }
            } finally { if (is_file($temporary)) { @unlink($temporary); } }
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
