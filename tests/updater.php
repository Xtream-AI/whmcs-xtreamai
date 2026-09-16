<?php

namespace WhmcsXtreamAI {
    final class Settings
    {
        public static $values = array();

        public static function get($key, $default = '')
        {
            return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
        }

        public static function set($key, $value)
        {
            self::$values[$key] = $value;
        }
    }
}

namespace {
    $GLOBALS['passed'] = 0;
    $GLOBALS['failed'] = 0;

    function section($label)
    {
        echo "\n== " . $label . " ==\n";
    }

    function ok($label, $condition, $detail = '')
    {
        if ($condition) {
            $GLOBALS['passed']++;
            echo "PASS  " . $label . "\n";

            return;
        }

        $GLOBALS['failed']++;
        echo "FAIL  " . $label . ($detail === '' ? '' : ' -> ' . $detail) . "\n";
    }

    function same($label, $expected, $actual)
    {
        ok(
            $label,
            $expected === $actual,
            'expected ' . json_encode($expected) . ', got ' . json_encode($actual)
        );
    }

    function removeTree($path)
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach ((array) @scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === false) {
                continue;
            }

            removeTree($path . '/' . $entry);
        }

        @rmdir($path);
    }

    function copyTree($from, $to)
    {
        if (is_link($from) || !is_dir($from)) {
            return false;
        }

        if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
            return false;
        }

        foreach ((array) @scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === false) {
                continue;
            }

            $source = $from . '/' . $entry;
            $destination = $to . '/' . $entry;

            if (is_link($source)) {
                continue;
            }

            if (is_dir($source)) {
                if (!copyTree($source, $destination)) {
                    return false;
                }

                continue;
            }

            if (!@copy($source, $destination)) {
                return false;
            }
        }

        return true;
    }

    function jsonVersion($file)
    {
        if (!is_file($file)) {
            return '';
        }

        $data = json_decode((string) @file_get_contents($file), true);

        if (!is_array($data) || !isset($data['version'])) {
            return '';
        }

        return trim((string) $data['version']);
    }

    function setJsonVersion($file, $version)
    {
        $data = json_decode((string) @file_get_contents($file), true);

        if (!is_array($data)) {
            return false;
        }

        $data['version'] = $version;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json)) {
            return false;
        }

        return @file_put_contents($file, $json . "\n") !== false;
    }

    function matches($parent, $prefix)
    {
        $found = array();

        foreach ((array) @glob($parent . '/' . $prefix . '*') as $path) {
            $found[] = basename($path);
        }

        sort($found);

        return $found;
    }

    function tempDirectories($prefix)
    {
        $found = array();

        foreach ((array) @glob($prefix . '*') as $path) {
            $found[] = basename($path);
        }

        sort($found);

        return $found;
    }

    function resetLiveFolders($root, $repo)
    {
        $parents = array($root . '/modules/servers', $root . '/modules/addons');

        foreach ($parents as $parent) {
            foreach ((array) @scandir($parent) as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === false) {
                    continue;
                }

                if (strpos($entry, 'xtreamai.') === 0) {
                    removeTree($parent . '/' . $entry);
                }
            }
        }

        removeTree($root . '/modules/servers/xtreamai');
        removeTree($root . '/modules/addons/xtreamai');

        return copyTree($repo . '/modules/servers/xtreamai', $root . '/modules/servers/xtreamai')
            && copyTree($repo . '/modules/addons/xtreamai', $root . '/modules/addons/xtreamai');
    }

    function buildRelease($scratch, $repo, $version)
    {
        $releaseParent = $scratch . '/release';
        $releaseRoot = $releaseParent . '/whmcs-xtreamai';

        removeTree($releaseParent);

        if (!copyTree($repo . '/modules/servers/xtreamai', $releaseRoot . '/modules/servers/xtreamai')) {
            return null;
        }

        if (!copyTree($repo . '/modules/addons/xtreamai', $releaseRoot . '/modules/addons/xtreamai')) {
            return null;
        }

        setJsonVersion($releaseRoot . '/modules/servers/xtreamai/whmcs.json', $version);
        setJsonVersion($releaseRoot . '/modules/addons/xtreamai/whmcs.json', $version);
        @file_put_contents($releaseRoot . '/modules/servers/xtreamai/release-marker.txt', $version . "\n");
        @file_put_contents($releaseRoot . '/modules/addons/xtreamai/release-marker.txt', $version . "\n");

        $tarball = $scratch . '/whmcs-xtreamai-' . $version . '.tar.gz';
        @unlink($tarball);
        exec(
            'tar -czf ' . escapeshellarg($tarball)
            . ' -C ' . escapeshellarg($releaseParent)
            . ' ' . escapeshellarg('whmcs-xtreamai') . ' 2>&1',
            $output,
            $status
        );

        if ($status !== 0 || !is_file($tarball)) {
            return null;
        }

        $checksum = hash_file('sha256', $tarball);

        if (!is_string($checksum)) {
            return null;
        }

        $checksumFile = $tarball . '.sha256';
        @file_put_contents($checksumFile, $checksum . '  ' . basename($tarball) . "\n");

        return array('tarball' => $tarball, 'checksum' => $checksumFile);
    }

    function releaseFetch($assetName, $version)
    {
        $base = 'https://github.com/Xtream-AI/whmcs-xtreamai/releases/download/v' . $version . '/';
        $body = json_encode(array(
            'tag_name' => 'v' . $version,
            'name' => 'Release ' . $version,
            'body' => 'Test release.',
            'published_at' => '2026-01-01T00:00:00Z',
            'html_url' => 'https://github.com/Xtream-AI/whmcs-xtreamai/releases/tag/v' . $version,
            'assets' => array(
                array(
                    'name' => $assetName,
                    'browser_download_url' => $base . $assetName,
                ),
                array(
                    'name' => $assetName . '.sha256',
                    'browser_download_url' => $base . $assetName . '.sha256',
                ),
            ),
        ));

        return function ($url, $headers, $timeout) use ($body) {
            return array('ok' => true, 'status' => 200, 'body' => $body, 'error' => '');
        };
    }

    function releaseDownload($tarball, $checksum)
    {
        $files = array(
            basename($tarball) => $tarball,
            basename($checksum) => $checksum,
        );

        return function ($url, $destination, $timeout) use ($files) {
            $name = basename((string) parse_url($url, PHP_URL_PATH));

            if (!isset($files[$name])) {
                return false;
            }

            return copy($files[$name], $destination);
        };
    }

    function runApply($move, $fetch, $download)
    {
        \WhmcsXtreamAI\Updater::$fetch = $fetch;
        \WhmcsXtreamAI\Updater::$download = $download;
        \WhmcsXtreamAI\Updater::$move = $move;

        $release = \WhmcsXtreamAI\Updater::latestRelease(true);

        if (!is_array($release)) {
            return null;
        }

        return \WhmcsXtreamAI\Updater::apply($release);
    }

    function serverFolder($root)
    {
        return $root . '/modules/servers/xtreamai';
    }

    function addonFolder($root)
    {
        return $root . '/modules/addons/xtreamai';
    }

    function main()
    {
        $repo = realpath(dirname(__DIR__));
        $scratch = sys_get_temp_dir() . '/whmcs-xtreamai-updater-test-' . bin2hex(random_bytes(4));
        $root = $scratch . '/whmcs';
        $tempPrefix = rtrim(sys_get_temp_dir(), '/') . '/xtreamai-update-';
        $releaseVersion = '9.9.9';

        @mkdir($root . '/modules/servers', 0755, true);
        @mkdir($root . '/modules/addons', 0755, true);

        if (!copyTree($repo . '/modules/servers/xtreamai', serverFolder($root))
            || !copyTree($repo . '/modules/addons/xtreamai', addonFolder($root))
        ) {
            echo "FAIL  the fake WHMCS root could not be prepared\n";
            exit(1);
        }

        require $root . '/modules/addons/xtreamai/lib/Updater.php';

        $releaseFiles = buildRelease($scratch, $repo, $releaseVersion);

        if ($releaseFiles === null) {
            removeTree($scratch);
            echo "FAIL  the test release tarball could not be built\n";
            exit(1);
        }

        $installedVersion = jsonVersion(addonFolder($root) . '/whmcs.json');
        $fetch = releaseFetch(basename($releaseFiles['tarball']), $releaseVersion);
        $download = releaseDownload($releaseFiles['tarball'], $releaseFiles['checksum']);

        echo "WHMCS Xtream AI updater tests (no WHMCS, no network)\n";
        echo 'installed version ' . $installedVersion . ', fake release ' . $releaseVersion . "\n";

        section('A. The updater temp directory sits on another filesystem');

        resetLiveFolders($root, $repo);
        $tempBefore = tempDirectories($tempPrefix);
        $crossDeviceMove = function ($from, $to) use ($tempPrefix) {
            if ((strpos($from, $tempPrefix) === 0) !== (strpos($to, $tempPrefix) === 0)) {
                return false;
            }

            return rename($from, $to);
        };
        $result = runApply($crossDeviceMove, $fetch, $download);

        if ($result === null) {
            removeTree($scratch);
            echo "FAIL  the fake release could not be parsed\n";
            exit(1);
        }

        same('a temp dir on another filesystem still updates', true, $result['ok']);
        same('the server folder holds the release version', $releaseVersion, jsonVersion(serverFolder($root) . '/whmcs.json'));
        same('the addon folder holds the release version', $releaseVersion, jsonVersion(addonFolder($root) . '/whmcs.json'));
        ok('the server release file was installed', is_file(serverFolder($root) . '/release-marker.txt'));
        ok('the addon release file was installed', is_file(addonFolder($root) . '/release-marker.txt'));
        ok('the server templates were copied', is_file(serverFolder($root) . '/templates/overview.tpl'));
        ok('the addon library was copied', is_file(addonFolder($root) . '/lib/Updater.php'));
        same('one server backup was kept', 1, count((array) @glob($root . '/modules/servers/xtreamai.bak-*')));
        same('one addon backup was kept', 1, count((array) @glob($root . '/modules/addons/xtreamai.bak-*')));
        same('no server staging folder is left', array(), matches($root . '/modules/servers', 'xtreamai.new-'));
        same('no addon staging folder is left', array(), matches($root . '/modules/addons', 'xtreamai.new-'));
        same('no updater temp directory is left', $tempBefore, tempDirectories($tempPrefix));

        section('B. Plain rename for every step');

        resetLiveFolders($root, $repo);
        $tempBefore = tempDirectories($tempPrefix);
        $result = runApply(null, $fetch, $download);

        same('the update succeeds with plain renames', true, $result['ok']);
        same('the server folder holds the release version', $releaseVersion, jsonVersion(serverFolder($root) . '/whmcs.json'));
        same('the addon folder holds the release version', $releaseVersion, jsonVersion(addonFolder($root) . '/whmcs.json'));
        same('one server backup was kept', 1, count((array) @glob($root . '/modules/servers/xtreamai.bak-*')));
        same('one addon backup was kept', 1, count((array) @glob($root . '/modules/addons/xtreamai.bak-*')));
        same('no server staging folder is left', array(), matches($root . '/modules/servers', 'xtreamai.new-'));
        same('no addon staging folder is left', array(), matches($root . '/modules/addons', 'xtreamai.new-'));
        same('no updater temp directory is left', $tempBefore, tempDirectories($tempPrefix));

        section('C. The staged server folder cannot be moved into place');

        resetLiveFolders($root, $repo);
        $tempBefore = tempDirectories($tempPrefix);
        $serverTarget = realpath(serverFolder($root));
        $serverSwapMove = function ($from, $to) use ($serverTarget) {
            if ($to === $serverTarget && strpos(basename($from), 'xtreamai.new-') === 0) {
                return false;
            }

            return rename($from, $to);
        };
        $result = runApply($serverSwapMove, $fetch, $download);

        same('a failed final rename is reported as a failure', false, $result['ok']);
        ok(
            'the failure message says the previous version was restored',
            strpos($result['message'], 'The previous version was restored') !== false,
            $result['message']
        );
        same('the server folder keeps the installed version', $installedVersion, jsonVersion(serverFolder($root) . '/whmcs.json'));
        same('the addon folder keeps the installed version', $installedVersion, jsonVersion(addonFolder($root) . '/whmcs.json'));
        ok('no release file was installed in the server folder', !is_file(serverFolder($root) . '/release-marker.txt'));
        ok('the server entry point is back in place', is_file(serverFolder($root) . '/xtreamai.php'));
        same('the server backup was consumed by the rollback', array(), matches($root . '/modules/servers', 'xtreamai.bak-'));
        same('no server staging folder is left', array(), matches($root . '/modules/servers', 'xtreamai.new-'));
        same('no addon staging folder is left', array(), matches($root . '/modules/addons', 'xtreamai.new-'));
        same('no updater temp directory is left', $tempBefore, tempDirectories($tempPrefix));

        section('D. A staging folder left by an interrupted run is removed');

        resetLiveFolders($root, $repo);
        $tempBefore = tempDirectories($tempPrefix);
        $stale = $root . '/modules/servers/xtreamai.new-0.0.1-20200101000000';
        @mkdir($stale, 0755, true);
        @file_put_contents($stale . '/stale.txt', "stale\n");
        ok('the stale staging folder was created', is_dir($stale));
        $result = runApply(null, $fetch, $download);

        same('the update still succeeds', true, $result['ok']);
        ok('the stale staging folder is gone', !is_dir($stale));
        same('the server folder holds the release version', $releaseVersion, jsonVersion(serverFolder($root) . '/whmcs.json'));
        same('no server staging folder is left', array(), matches($root . '/modules/servers', 'xtreamai.new-'));
        same('no updater temp directory is left', $tempBefore, tempDirectories($tempPrefix));

        section('E. The addon swap fails after the server swap on another filesystem');

        resetLiveFolders($root, $repo);
        $tempBefore = tempDirectories($tempPrefix);
        $addonTarget = realpath(addonFolder($root));
        $addonSwapMove = function ($from, $to) use ($tempPrefix, $addonTarget) {
            if ((strpos($from, $tempPrefix) === 0) !== (strpos($to, $tempPrefix) === 0)) {
                return false;
            }

            if ($to === $addonTarget && strpos(basename($from), 'xtreamai.new-') === 0) {
                return false;
            }

            return rename($from, $to);
        };
        $result = runApply($addonSwapMove, $fetch, $download);

        same('a failed addon swap is reported as a failure', false, $result['ok']);
        ok(
            'the failure message says the server module was rolled back',
            strpos($result['message'], 'Restored: server module') !== false,
            $result['message']
        );
        same('the server folder is back on the installed version', $installedVersion, jsonVersion(serverFolder($root) . '/whmcs.json'));
        same('the addon folder keeps the installed version', $installedVersion, jsonVersion(addonFolder($root) . '/whmcs.json'));
        ok('no release file remains in the server folder', !is_file(serverFolder($root) . '/release-marker.txt'));
        ok('the server entry point is back in place', is_file(serverFolder($root) . '/xtreamai.php'));
        same('the server backup was consumed by the rollback', array(), matches($root . '/modules/servers', 'xtreamai.bak-'));
        same('the addon backup was consumed by the restore', array(), matches($root . '/modules/addons', 'xtreamai.bak-'));
        same('no server staging folder is left', array(), matches($root . '/modules/servers', 'xtreamai.new-'));
        same('no addon staging folder is left', array(), matches($root . '/modules/addons', 'xtreamai.new-'));
        same('no updater temp directory is left', $tempBefore, tempDirectories($tempPrefix));

        removeTree($scratch);

        if ($GLOBALS['failed'] === 0) {
            echo "\nOK (" . $GLOBALS['passed'] . " checks)\n";

            return 0;
        }

        echo "\nFAILED (" . $GLOBALS['passed'] . " passed, " . $GLOBALS['failed'] . " failed)\n";

        return 1;
    }

    exit(main());
}
