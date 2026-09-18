<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

final class Updater
{
    public const API_URL = 'https://api.github.com/repos/Xtream-AI/whmcs-xtreamai/releases/latest';
    private const CACHE_JSON = 'update_check_json';
    private const CACHE_AT = 'update_check_at';
    private const CACHE_TTL = 86400;
    private const HTTP_TIMEOUT = 10;
    private const DOWNLOAD_TIMEOUT = 60;
    private const ARCHIVE_ROOT = 'whmcs-xtreamai';
    private const BACKUP_PREFIX = 'xtreamai.bak-';
    private const STAGING_PREFIX = 'xtreamai.new-';
    private const DIR_NAME = 'xtreamai';

    public static $fetch = null;
    public static $download = null;
    public static $move = null;

    public static function latestRelease(bool $force = false): ?array
    {
        $cached = self::cached();

        if (!$force && $cached !== null && self::cacheFresh()) {
            return $cached;
        }

        $fresh = null;

        try {
            $fresh = self::request();
        } catch (\Throwable $e) {
            $fresh = null;
        }

        if ($fresh !== null) {
            self::store($fresh);

            return $fresh;
        }

        return $cached;
    }

    public static function isNewer(string $latest, string $current): bool
    {
        $latest = self::normalizeVersion($latest);
        $current = self::normalizeVersion($current);

        if ($latest === '' || $current === '') {
            return false;
        }

        return version_compare($latest, $current, '>');
    }

    public static function currentVersion(): string
    {
        $paths = self::paths();

        if ($paths === null) {
            return '';
        }

        return self::jsonVersion($paths['addon'] . '/whmcs.json');
    }

    public static function environment(): array
    {
        $paths = self::paths();

        if ($paths === null) {
            return [
                'ok' => false,
                'message' => 'The module folders could not be resolved inside the WHMCS modules/ directory.',
                'paths' => [],
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'message' => 'The PHP cURL extension (ext-curl) is not available, so the release cannot be downloaded.',
                'paths' => $paths,
            ];
        }

        if (!class_exists('PharData') && !function_exists('gzopen')) {
            return [
                'ok' => false,
                'message' => 'Neither the Phar extension (PharData) nor the zlib extension (gzopen) are available, so the release archive cannot be extracted.',
                'paths' => $paths,
            ];
        }

        $temp = sys_get_temp_dir();

        if ($temp === '' || !is_dir($temp) || !is_writable($temp)) {
            return [
                'ok' => false,
                'message' => 'The system temporary directory (' . $temp . ') is not writable by PHP.',
                'paths' => $paths,
            ];
        }

        foreach (['server', 'addon'] as $key) {
            $dir = $paths[$key];
            $parent = dirname($dir);

            if (!is_dir($dir) || !is_dir($parent) || !is_writable($dir) || !is_writable($parent)) {
                return [
                    'ok' => false,
                    'message' => 'PHP cannot write to ' . $dir . ' or to its parent directory ' . $parent . '.',
                    'paths' => $paths,
                ];
            }
        }

        return ['ok' => true, 'message' => '', 'paths' => $paths];
    }

    public static function apply(array $release): array
    {
        $steps = [];
        $version = self::normalizeVersion((string) (isset($release['version']) ? $release['version'] : ''));
        $tarballUrl = trim((string) (isset($release['tarball_url']) ? $release['tarball_url'] : ''));
        $checksumUrl = trim((string) (isset($release['checksum_url']) ? $release['checksum_url'] : ''));
        $assetName = basename(trim((string) (isset($release['asset_name']) ? $release['asset_name'] : '')));

        if ($version === '' || $tarballUrl === '' || $checksumUrl === '') {
            return self::failure('The release information is incomplete. Run Check for updates again.', $steps);
        }

        if ($assetName === '') {
            $assetName = 'whmcs-xtreamai-' . $version . '.tar.gz';
        }

        $environment = self::environment();

        if (empty($environment['ok'])) {
            return self::failure((string) $environment['message'] . ' Nothing was changed.', $steps);
        }

        $paths = $environment['paths'];
        $current = self::currentVersion();

        if ($current === '') {
            $current = '0.0.0';
        }

        if (!self::isNewer($version, $current)) {
            return self::failure(
                'Refused to update: the installed version is ' . $current . ' and the release is ' . $version . '.',
                $steps
            );
        }

        $temp = self::tempDirectory();

        if ($temp === null) {
            return self::failure('Could not create a working directory inside ' . sys_get_temp_dir() . '.', $steps);
        }

        $steps[] = 'Working directory created at ' . $temp . '.';

        $tarballPath = $temp . '/' . $assetName;
        $checksumPath = $temp . '/' . $assetName . '.sha256';

        if (!self::downloadAsset($tarballUrl, $tarballPath)) {
            self::removeTree($temp);

            return self::failure('Could not download ' . $assetName . ' from GitHub.', $steps);
        }

        if (!self::downloadAsset($checksumUrl, $checksumPath)) {
            self::removeTree($temp);

            return self::failure('Could not download the SHA256 checksum file of the release.', $steps);
        }

        $steps[] = 'Downloaded ' . $assetName . ' and its SHA256 checksum into ' . $temp . '.';

        $expected = self::expectedChecksum($checksumPath);
        $actual = @hash_file('sha256', $tarballPath);

        if ($expected === '' || !is_string($actual) || !hash_equals($expected, strtolower($actual))) {
            self::removeTree($temp);

            return self::failure(
                'The downloaded archive does not match the SHA256 checksum published with the release. Nothing was changed.',
                $steps
            );
        }

        $steps[] = 'SHA256 checksum verified (' . $expected . ').';

        $extractDir = $temp . '/extract';

        if (!@mkdir($extractDir, 0700, true) && !is_dir($extractDir)) {
            self::removeTree($temp);

            return self::failure('Could not create the extraction directory inside the temporary directory.', $steps);
        }

        try {
            self::extractTarGz($tarballPath, $extractDir);
        } catch (\Throwable $e) {
            self::removeTree($temp);

            return self::failure('Could not extract the release archive: ' . self::cleanMessage($e) . '.', $steps);
        }

        $steps[] = 'Archive extracted into ' . $extractDir . '.';

        $sourceRoot = self::archiveRoot($extractDir);

        if ($sourceRoot === null) {
            self::removeTree($temp);

            return self::failure(
                'The archive does not contain the ' . self::DIR_NAME . ' folder of the server module and of the addon. Nothing was changed.',
                $steps
            );
        }

        $sourceServer = $sourceRoot . '/modules/servers/' . self::DIR_NAME;
        $sourceAddon = $sourceRoot . '/modules/addons/' . self::DIR_NAME;

        $archiveVersion = self::jsonVersion($sourceAddon . '/whmcs.json');

        if ($archiveVersion !== $version) {
            self::removeTree($temp);

            return self::failure(
                'The archive contains version ' . ($archiveVersion === '' ? 'unknown' : $archiveVersion)
                . ' but the release is ' . $version . '. Nothing was changed.',
                $steps
            );
        }

        $steps[] = 'Archive contents verified (version ' . $version . ').';

        $timestamp = date('YmdHis');
        $swaps = [
            [
                'label' => 'server module',
                'target' => $paths['server'],
                'source' => $sourceServer,
                'staged' => dirname($paths['server']) . '/' . self::STAGING_PREFIX . $version . '-' . $timestamp,
            ],
            [
                'label' => 'addon',
                'target' => $paths['addon'],
                'source' => $sourceAddon,
                'staged' => dirname($paths['addon']) . '/' . self::STAGING_PREFIX . $version . '-' . $timestamp,
            ],
        ];

        $prunedStaging = [];

        foreach ($swaps as $swap) {
            $prunedStaging = array_merge($prunedStaging, self::pruneStaging(dirname($swap['staged'])));
        }

        if ($prunedStaging !== []) {
            $steps[] = 'Removed staging folders left behind by an interrupted run: ' . implode(', ', $prunedStaging) . '.';
        }

        foreach ($swaps as $swap) {
            if (!self::copyTree($swap['source'], $swap['staged'])) {
                self::removeStaging($swaps);
                self::removeTree($temp);

                return self::failure(
                    'Could not stage the new ' . $swap['label'] . ' next to the current one. Nothing was changed.',
                    $steps
                );
            }
        }

        $steps[] = 'The new files were staged next to the current folders.';

        $done = [];
        $backups = [];

        foreach ($swaps as $swap) {
            $target = self::safeTarget($swap['target'], $paths['modules']);

            if ($target === null) {
                $restored = self::rollback($done);
                self::removeStaging($swaps);
                self::removeTree($temp);

                return self::failure(
                    'Refused to replace ' . $swap['target'] . ': it is not a directory inside the WHMCS modules/ directory.'
                    . self::restoredNote($restored),
                    $steps
                );
            }

            $backup = dirname($target) . '/' . self::BACKUP_PREFIX . $version . '-' . $timestamp;

            if (!self::move($target, $backup)) {
                $restored = self::rollback($done);
                self::removeStaging($swaps);
                self::removeTree($temp);

                return self::failure(
                    'Could not move the current ' . $swap['label'] . ' out of the way.' . self::restoredNote($restored),
                    $steps
                );
            }

            if (!self::move($swap['staged'], $target)) {
                $restoredCurrent = self::move($backup, $target);
                $restored = self::rollback($done);
                self::removeStaging($swaps);
                self::removeTree($temp);

                return self::failure(
                    'Could not move the new ' . $swap['label'] . ' into place.'
                    . ($restoredCurrent ? ' The previous version was restored.' : ' The previous files are in ' . $backup . '.')
                    . self::restoredNote($restored),
                    $steps
                );
            }

            $done[] = ['label' => $swap['label'], 'target' => $target, 'backup' => $backup];
            $backups[] = $backup;
            $steps[] = 'Replaced the ' . $swap['label'] . ' in ' . $target . '.';
        }

        foreach (['server', 'addon'] as $key) {
            $pruned = self::pruneBackups(dirname($paths[$key]), $backups);

            if ($pruned !== []) {
                $steps[] = 'Removed older backups: ' . implode(', ', $pruned) . '.';
            }
        }

        self::removeTree($temp);
        $steps[] = 'Temporary files removed.';

        return [
            'ok' => true,
            'message' => 'Updated to ' . $version . '. Reload the page.',
            'steps' => $steps,
            'backups' => $backups,
            'version' => $version,
        ];
    }

    private static function request(): ?array
    {
        $response = self::httpGet(
            self::API_URL,
            [
                'User-Agent: ' . self::userAgent(),
                'Accept: application/vnd.github+json',
            ],
            self::HTTP_TIMEOUT
        );

        if (empty($response['ok']) || $response['status'] < 200 || $response['status'] >= 300) {
            return null;
        }

        return self::parseRelease((string) $response['body']);
    }

    private static function parseRelease(string $body): ?array
    {
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return null;
        }

        $tag = trim((string) (isset($data['tag_name']) ? $data['tag_name'] : ''));
        $version = self::normalizeVersion($tag);

        if ($version === '') {
            return null;
        }

        $assets = isset($data['assets']) && is_array($data['assets']) ? $data['assets'] : [];
        $tarballUrl = '';
        $checksumUrl = '';
        $assetName = '';

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = basename(trim((string) (isset($asset['name']) ? $asset['name'] : '')));
            $url = trim((string) (isset($asset['browser_download_url']) ? $asset['browser_download_url'] : ''));

            if ($name === '' || $url === '' || !self::trustedAssetUrl($url)) {
                continue;
            }

            if (substr($name, -7) === '.sha256') {
                $checksumUrl = $url;
            } elseif (substr($name, -7) === '.tar.gz') {
                $tarballUrl = $url;
                $assetName = $name;
            }
        }

        if ($tarballUrl === '' || $checksumUrl === '') {
            return null;
        }

        return [
            'version' => $version,
            'tag' => $tag,
            'name' => trim((string) (isset($data['name']) ? $data['name'] : '')),
            'notes' => (string) (isset($data['body']) ? $data['body'] : ''),
            'published_at' => trim((string) (isset($data['published_at']) ? $data['published_at'] : '')),
            'release_url' => trim((string) (isset($data['html_url']) ? $data['html_url'] : '')),
            'asset_name' => $assetName,
            'tarball_url' => $tarballUrl,
            'checksum_url' => $checksumUrl,
            'checked_at' => time(),
        ];
    }

    private static function cached(): ?array
    {
        try {
            $raw = Settings::get(self::CACHE_JSON);

            if ($raw === null || trim($raw) === '') {
                return null;
            }

            $data = json_decode($raw, true);

            if (!is_array($data) || !isset($data['version'], $data['tarball_url'])) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function cacheFresh(): bool
    {
        try {
            $at = Settings::get(self::CACHE_AT);

            if ($at === null || !ctype_digit(trim($at))) {
                return false;
            }

            return (time() - (int) trim($at)) < self::CACHE_TTL;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function store(array $release): void
    {
        try {
            $json = json_encode($release, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!is_string($json)) {
                return;
            }

            Settings::set(self::CACHE_JSON, $json);
            Settings::set(self::CACHE_AT, (string) time());
        } catch (\Throwable $e) {
            return;
        }
    }

    private static function httpGet(string $url, array $headers, int $timeout): array
    {
        if (is_callable(self::$fetch)) {
            $result = call_user_func(self::$fetch, $url, $headers, $timeout);

            if (!is_array($result)) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Invalid stubbed response.'];
            }

            return [
                'ok' => !empty($result['ok']),
                'status' => isset($result['status']) ? (int) $result['status'] : 0,
                'body' => isset($result['body']) ? (string) $result['body'] : '',
                'error' => isset($result['error']) ? (string) $result['error'] : '',
            ];
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL could not be initialised.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno === 0 ? '' : (string) curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($body)) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => '',
                'error' => $error === '' ? 'The request failed.' : $error,
            ];
        }

        return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => ''];
    }

    private static function download(string $url, string $destination, int $timeout): bool
    {
        if (is_callable(self::$download)) {
            return call_user_func(self::$download, $url, $destination, $timeout) === true;
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return false;
        }

        $handle = @fopen($destination, 'wb');

        if ($handle === false) {
            curl_close($ch);

            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['User-Agent: ' . self::userAgent()],
        ]);

        $executed = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($handle);

        if ($errno !== 0 || $executed === false || $status < 200 || $status >= 300) {
            @unlink($destination);

            return false;
        }

        return is_file($destination) && (int) filesize($destination) > 0;
    }

    private static function downloadAsset(string $url, string $destination): bool
    {
        try {
            return self::download($url, $destination, self::DOWNLOAD_TIMEOUT);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function expectedChecksum(string $path): string
    {
        $raw = @file_get_contents($path);

        if (!is_string($raw)) {
            return '';
        }

        $parts = preg_split('/\s+/', trim($raw));

        if (!is_array($parts) || !isset($parts[0])) {
            return '';
        }

        $hex = strtolower(trim($parts[0]));

        if (preg_match('/^[0-9a-f]{64}$/', $hex) !== 1) {
            return '';
        }

        return $hex;
    }

    private static function trustedAssetUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (!is_array($parts) || strtolower((string) (isset($parts['scheme']) ? $parts['scheme'] : '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) (isset($parts['host']) ? $parts['host'] : ''));

        return $host === 'github.com'
            || substr($host, -11) === '.github.com'
            || substr($host, -22) === '.githubusercontent.com';
    }

    private static function move(string $from, string $to): bool
    {
        if (is_callable(self::$move)) {
            return call_user_func(self::$move, $from, $to) === true;
        }

        return @rename($from, $to);
    }

    private static function rollback(array $done): array
    {
        $restored = [];

        foreach (array_reverse($done) as $index => $item) {
            $hold = dirname($item['target']) . '/' . self::STAGING_PREFIX . 'rollback-' . $index . '-' . date('YmdHis');

            if (!self::move($item['target'], $hold)) {
                continue;
            }

            if (!self::move($item['backup'], $item['target'])) {
                self::move($hold, $item['target']);

                continue;
            }

            $restored[] = $item['label'];
            self::removeTree($hold);
        }

        return $restored;
    }

    private static function restoredNote(array $restored): string
    {
        if ($restored === []) {
            return '';
        }

        return ' Restored: ' . implode(', ', $restored) . '.';
    }

    private static function pruneBackups(string $parent, array $keep): array
    {
        $removed = [];
        $entries = @scandir($parent);
        $parentReal = realpath($parent);

        if (!is_array($entries) || $parentReal === false) {
            return $removed;
        }

        $candidates = [];

        foreach ($entries as $entry) {
            if (strpos($entry, self::BACKUP_PREFIX) !== 0) {
                continue;
            }

            $path = $parent . '/' . $entry;

            if (is_link($path) || !is_dir($path)) {
                continue;
            }

            $real = realpath($path);

            if ($real === false || dirname($real) !== $parentReal) {
                continue;
            }

            $candidates[] = $real;
        }

        if (count($candidates) < 2) {
            return $removed;
        }

        usort($candidates, static function (string $a, string $b): int {
            $timeA = (int) @filemtime($a);
            $timeB = (int) @filemtime($b);

            if ($timeA === $timeB) {
                return strcmp($b, $a);
            }

            return $timeB - $timeA;
        });

        $newest = $candidates[0];

        foreach ($candidates as $path) {
            if ($path === $newest || in_array($path, $keep, true)) {
                continue;
            }

            self::removeTree($path);
            $removed[] = $path;
        }

        return $removed;
    }

    private static function copyTree(string $from, string $to): bool
    {
        if (is_link($from) || !is_dir($from)) {
            return false;
        }

        if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
            return false;
        }

        $entries = @scandir($from);

        if (!is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $source = $from . '/' . $entry;
            $destination = $to . '/' . $entry;

            if (is_link($source)) {
                continue;
            }

            if (is_dir($source)) {
                if (!self::copyTree($source, $destination)) {
                    return false;
                }

                continue;
            }

            if (!is_file($source) || !@copy($source, $destination)) {
                return false;
            }
        }

        return true;
    }

    private static function pruneStaging(string $parent): array
    {
        $removed = [];
        $entries = @scandir($parent);
        $parentReal = realpath($parent);

        if (!is_array($entries) || $parentReal === false) {
            return $removed;
        }

        foreach ($entries as $entry) {
            if (strpos($entry, self::STAGING_PREFIX) !== 0) {
                continue;
            }

            $path = $parent . '/' . $entry;

            if (is_link($path) || !is_dir($path)) {
                continue;
            }

            $real = realpath($path);

            if ($real === false || dirname($real) !== $parentReal) {
                continue;
            }

            self::removeTree($real);
            $removed[] = $real;
        }

        return $removed;
    }

    private static function removeStaging(array $swaps): void
    {
        foreach ($swaps as $swap) {
            self::removeTree($swap['staged']);
        }
    }

    private static function removeTree(string $path): void
    {
        $trimmed = rtrim($path, '/');

        if ($trimmed === '' || $trimmed === '/') {
            return;
        }

        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = @scandir($path);

        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                self::removeTree($path . '/' . $item);
            }
        }

        @rmdir($path);
    }

    private static function tempDirectory(): ?string
    {
        $base = sys_get_temp_dir();

        if ($base === '' || !is_dir($base) || !is_writable($base)) {
            return null;
        }

        $suffix = '';

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            $suffix = preg_replace('/[^A-Za-z0-9]/', '', uniqid('u', true));
        }

        if (!is_string($suffix) || $suffix === '') {
            return null;
        }

        $path = rtrim($base, '/') . '/xtreamai-update-' . $suffix;

        if (!@mkdir($path, 0700, true) && !is_dir($path)) {
            return null;
        }

        return $path;
    }

    private static function safeTarget(string $path, string $modules): ?string
    {
        $real = realpath($path);

        if ($real === false || !is_dir($real)) {
            return null;
        }

        if (basename($real) !== self::DIR_NAME) {
            return null;
        }

        if (strpos($real, rtrim($modules, '/') . '/') !== 0) {
            return null;
        }

        return $real;
    }

    private static function paths(): ?array
    {
        $addon = realpath(dirname(__DIR__));

        if ($addon === false) {
            return null;
        }

        $modules = realpath(dirname(dirname($addon)));

        if ($modules === false || basename($modules) !== 'modules') {
            return null;
        }

        if ($addon !== $modules . '/addons/' . self::DIR_NAME) {
            return null;
        }

        $server = realpath($modules . '/servers/' . self::DIR_NAME);

        if ($server === false || strpos($server, $modules . '/servers/') !== 0) {
            return null;
        }

        return ['modules' => $modules, 'server' => $server, 'addon' => $addon];
    }

    private static function archiveRoot(string $extractDir): ?string
    {
        $entries = (array) @scandir($extractDir);
        $preferred = $extractDir . '/' . self::ARCHIVE_ROOT;

        if (self::isModuleRoot($preferred)) {
            return $preferred;
        }

        $candidates = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || !is_string($entry)) {
                continue;
            }

            $path = $extractDir . '/' . $entry;

            if (!is_dir($path) || !self::isModuleRoot($path)) {
                continue;
            }

            $candidates[] = $path;
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    private static function isModuleRoot(string $path): bool
    {
        return is_file($path . '/modules/servers/' . self::DIR_NAME . '/xtreamai.php')
            && is_file($path . '/modules/addons/' . self::DIR_NAME . '/xtreamai.php');
    }

    private static function jsonVersion(string $file): string
    {
        if (!is_file($file)) {
            return '';
        }

        $raw = @file_get_contents($file);

        if (!is_string($raw)) {
            return '';
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['version'])) {
            return '';
        }

        return self::normalizeVersion((string) $data['version']);
    }

    private static function normalizeVersion(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $first = substr($value, 0, 1);

        if ($first === 'v' || $first === 'V') {
            $value = trim(substr($value, 1));
        }

        $value = (string) preg_replace('/[^0-9A-Za-z._+-]/', '', $value);

        if ($value === '' || strpos($value, '..') !== false || strlen($value) > 32) {
            return '';
        }

        return $value;
    }

    private static function userAgent(): string
    {
        $version = self::currentVersion();

        return 'whmcs-xtreamai-updater/' . ($version === '' ? 'unknown' : $version) . ' (PHP/' . PHP_VERSION . ')';
    }

    private static function cleanMessage(\Throwable $e): string
    {
        $message = trim((string) $e->getMessage());

        return $message === '' ? 'unknown error' : $message;
    }

    private static function failure(string $message, array $steps): array
    {
        return ['ok' => false, 'message' => $message, 'steps' => $steps];
    }

    private static function extractTarGz(string $tarballPath, string $extractDir): void
    {
        if (class_exists('PharData')) {
            try {
                self::extractWithPhar($tarballPath, $extractDir);
                return;
            } catch (\Throwable $e) {
                self::pruneExtractDir($extractDir);

            }
        }

        self::extractTarGzPurePhp($tarballPath, $extractDir);
    }

    private static function extractWithPhar(string $tarballPath, string $extractDir): void
    {
        $archive = new \PharData($tarballPath);
        $archive->decompress();
        unset($archive);

        $plainPath = preg_replace('/\.gz$/', '', $tarballPath);

        if (!is_string($plainPath) || !is_file($plainPath)) {
            throw new \RuntimeException('the archive could not be decompressed');
        }

        $plainArchive = new \PharData($plainPath);
        $plainArchive->extractTo($extractDir, null, true);
        unset($plainArchive);
    }

    private static function pruneExtractDir(string $extractDir): void
    {
        if (!is_dir($extractDir)) {
            return;
        }
        foreach ((array) @scandir($extractDir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === false) {
                continue;
            }
            self::removeTree($extractDir . '/' . $entry);
        }
    }

    private static function extractTarGzPurePhp(string $tarballPath, string $extractDir): void
    {
        if (!function_exists('gzopen')) {
            throw new \RuntimeException('the zlib extension (gzopen) is required to extract the archive without PharData');
        }

        $fh = @gzopen($tarballPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('could not open the release archive with gzopen');
        }

        try {
            $longName = null;

            while (!gzeof($fh)) {
                $header = self::gzReadExact($fh, 512);

                if ($header === '' || $header === str_repeat("\0", 512)) {
                    break;
                }

                if (strlen($header) !== 512) {
                    throw new \RuntimeException('unexpected end of tar header');
                }

                $name = rtrim(substr($header, 0, 100), "\0");
                $sizeField = trim(substr($header, 124, 12), " \0");
                $typeflag = substr($header, 156, 1);
                $prefix = rtrim(substr($header, 345, 155), "\0");

                if ($prefix !== '') {
                    $name = $prefix . '/' . $name;
                }

                $size = $sizeField === '' ? 0 : (int) octdec($sizeField);
                $blocks = (int) (($size + 511) / 512);

                if ($typeflag === 'L') {
                    $payload = self::gzReadExact($fh, $blocks * 512);
                    $longName = rtrim(substr($payload, 0, $size), "\0");
                    continue;
                }

                if ($typeflag === 'K' || $typeflag === 'x' || $typeflag === 'g') {
                    self::gzSkip($fh, $blocks * 512);
                    continue;
                }

                if ($longName !== null) {
                    $name = $longName;
                    $longName = null;
                }

                if ($name === '') {
                    self::gzSkip($fh, $blocks * 512);
                    continue;
                }

                $target = self::joinSafeUnderRoot($extractDir, $name);
                if ($target === null) {
                    throw new \RuntimeException('unsafe path in archive: ' . $name);
                }

                $isDir = ($typeflag === '5') || (substr($name, -1) === '/');
                if ($isDir) {
                    if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                        throw new \RuntimeException('could not create ' . $target);
                    }
                    self::gzSkip($fh, $blocks * 512);
                    continue;
                }

                if ($typeflag !== '' && $typeflag !== '0' && $typeflag !== "\0") {
                    self::gzSkip($fh, $blocks * 512);
                    continue;
                }

                $parent = dirname($target);
                if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
                    throw new \RuntimeException('could not create ' . $parent);
                }

                $out = @fopen($target, 'wb');
                if ($out === false) {
                    throw new \RuntimeException('could not open ' . $target . ' for writing');
                }

                try {
                    $remaining = $size;
                    while ($remaining > 0) {
                        $chunk = self::gzReadExact($fh, min(65536, $remaining));
                        if ($chunk === '') {
                            throw new \RuntimeException('unexpected end of file body for ' . $name);
                        }
                        if (fwrite($out, $chunk) === false) {
                            throw new \RuntimeException('could not write ' . $target);
                        }
                        $remaining -= strlen($chunk);
                    }
                } finally {
                    fclose($out);
                }

                $padding = $blocks * 512 - $size;
                if ($padding > 0) {
                    self::gzSkip($fh, $padding);
                }
            }
        } finally {
            gzclose($fh);
        }
    }

    private static function gzReadExact($fh, int $length): string
    {
        $out = '';
        while ($length > 0) {
            $chunk = gzread($fh, $length);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $out .= $chunk;
            $length -= strlen($chunk);
        }
        return $out;
    }

    private static function gzSkip($fh, int $length): void
    {
        while ($length > 0) {
            $chunk = gzread($fh, min(65536, $length));
            if ($chunk === false || $chunk === '') {
                return;
            }
            $length -= strlen($chunk);
        }
    }

    private static function joinSafeUnderRoot(string $base, string $rel): ?string
    {
        $rel = str_replace('\\', '/', $rel);
        $parts = [];
        foreach (explode('/', $rel) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $parts[] = $part;
        }
        if ($parts === []) {
            return null;
        }
        return $base . '/' . implode('/', $parts);
    }
}
