<?php

namespace WhmcsXtreamAI {
    final class PanelApiRequestException extends \RuntimeException
    {
    }

    final class PanelApi
    {
        public static $keyTypeValue = 'admin';
        public static $pageResult = null;
        public static $calls = array();

        public static function keyType($panelId)
        {
            self::$calls[] = array('keyType', $panelId);

            return self::$keyTypeValue;
        }

        public static function resellersPage($panelId, $cursor = null)
        {
            self::$calls[] = array('resellersPage', $panelId);
            if (self::$pageResult instanceof \Throwable) {
                throw self::$pageResult;
            }

            return self::$pageResult;
        }

        public static function adjustResellerCredits($panelId, $resellerId, $delta, $reason)
        {
            self::$calls[] = array('adjustResellerCredits', $panelId, $resellerId, $delta);

            return '10.00';
        }
    }

    final class PanelStore
    {
        public static function all()
        {
            return array((object) array('id' => 3, 'name' => 'Main panel', 'active' => 1, 'key_type' => PanelApi::$keyTypeValue));
        }

        public static function find($id)
        {
            return (int) $id === 3 ? self::all()[0] : null;
        }
    }
}

namespace {
    define('WHMCS', true);

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
        ok($label, $expected === $actual, 'expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }

    function renderResellers($keyType, $pageResult)
    {
        \WhmcsXtreamAI\PanelApi::$keyTypeValue = $keyType;
        \WhmcsXtreamAI\PanelApi::$pageResult = $pageResult;
        \WhmcsXtreamAI\PanelApi::$calls = array();
        ob_start();
        xtreamai_render('addonmodules.php?module=xtreamai', 'resellers', null, 0, 3);

        return (string) ob_get_clean();
    }

    function onePage()
    {
        return array(
            'items' => array(
                array('id' => 41, 'username' => 'subone', 'email' => 'a@example.test', 'status' => true, 'group' => 'RESELLER', 'credits' => '5.00'),
                array('id' => 42, 'username' => 'subtwo', 'email' => 'b@example.test', 'status' => false, 'group' => 'RESELLER', 'credits' => '0.00'),
            ),
            'next_cursor' => null,
        );
    }

    require __DIR__ . '/../modules/addons/xtreamai/xtreamai.php';

    echo "WHMCS Xtream AI Sub-Resellers view tests (no WHMCS required)\n";

    section('Admin key');
    $html = renderResellers('admin', onePage());
    ok('lists both sub-resellers', strpos($html, 'subone') !== false && strpos($html, 'subtwo') !== false);
    ok('shows the Actions column', strpos($html, '<th>Actions</th>') !== false);
    same('one credit form per row', 2, substr_count($html, 'value="adjust_credits"'));
    ok('keeps the admin subtitle', strpos($html, 'Sub-resellers and their credit balances on the selected panel.') !== false);

    section('Reseller key');
    $html = renderResellers('reseller', onePage());
    ok('lists both sub-resellers', strpos($html, 'subone') !== false && strpos($html, 'subtwo') !== false);
    ok('shows credits', strpos($html, '5.00') !== false);
    ok('shows a Transfer column instead of Actions', strpos($html, '<th>Transfer</th>') !== false && strpos($html, '<th>Actions</th>') === false);
    same('one transfer form per row', 2, substr_count($html, 'value="adjust_credits"'));
    same('each form blocks a second submit', 2, substr_count($html, 'this.dataset.sent = \'1\''));
    ok('explains that credits come out of the reseller balance', strpos($html, 'Credits you add come out of your own balance') !== false);
    same('every row is closed', substr_count($html, '<tr>'), substr_count($html, '</tr>'));
    same('six cells per row, like the header', 6, substr_count(explode('<tbody>', $html)[1] ?? '', '<td>') / 2);

    section('Reseller key without the permission');
    $html = renderResellers('reseller', new \WhmcsXtreamAI\PanelApiRequestException('This API key lacks the required scope: subresellers:read.', 403));
    ok('explains how to get the permission', strpos($html, 'create a new API key with the &quot;See sub-resellers&quot; permission') !== false);
    ok('does not print the raw scope message', strpos($html, 'lacks the required scope') === false);

    section('Other load errors pass through');
    $html = renderResellers('reseller', new \WhmcsXtreamAI\PanelApiRequestException('Your account is not allowed to manage sub-resellers. Ask the panel administrator to enable it for your group.', 403));
    ok('shows the panel message', strpos($html, 'Ask the panel administrator to enable it for your group.') !== false);

    section('Credit adjustment');
    $_POST = array('panel_id' => '3', 'reseller_id' => '41', 'delta' => '5', 'reason' => 'x');
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    \WhmcsXtreamAI\PanelApi::$calls = array();
    $error = '';
    try {
        xtreamai_adjust_credits();
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    }
    same('a reseller key sends the transfer to the panel', '', $error);
    $adjusted = array_values(array_filter(\WhmcsXtreamAI\PanelApi::$calls, function ($c) {
        return $c[0] === 'adjustResellerCredits';
    }));
    same('one call with the typed amount', array(array('adjustResellerCredits', 3, '41', 5.0)), $adjusted);

    section('Transfer permission missing');
    same(
        'explains how to get the permission',
        'This panel key is not allowed to transfer credits. On the panel, create a new API key with the "Transfer credits to sub-resellers" permission and paste it on this panel entry.',
        xtreamai_credits_error_message(new \WhmcsXtreamAI\PanelApiRequestException('This API key lacks the required scope: subresellers:credits.', 403))
    );
    same(
        'other panel messages pass through',
        'Not enough credits to transfer',
        xtreamai_credits_error_message(new \WhmcsXtreamAI\PanelApiRequestException('Not enough credits to transfer', 402))
    );

    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'admin';
    \WhmcsXtreamAI\PanelApi::$calls = array();
    same('an admin key still adjusts', '10.00', xtreamai_adjust_credits());

    echo "\nSUMMARY: passed=" . $GLOBALS['passed'] . " failed=" . $GLOBALS['failed'] . "\n";
    exit($GLOBALS['failed'] === 0 ? 0 : 1);
}
