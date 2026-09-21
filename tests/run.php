<?php

namespace WHMCS\Database {
    final class CapsuleQuery
    {
        private $table;
        private $wheres = array();

        public function __construct($table)
        {
            $this->table = (string) $table;
        }

        public function where($column, $value)
        {
            $this->wheres[(string) $column] = $value;

            return $this;
        }

        public function first()
        {
            foreach (Capsule::$rows[$this->table] as $row) {
                if ($this->matches($row)) {
                    return (object) $row;
                }
            }

            return null;
        }

        public function get()
        {
            $rows = array();
            foreach (Capsule::$rows[$this->table] as $row) {
                if ($this->matches($row)) {
                    $rows[] = (object) $row;
                }
            }

            return $rows;
        }

        public function update(array $values)
        {
            $count = 0;
            foreach (Capsule::$rows[$this->table] as $index => $row) {
                if (!$this->matches($row)) {
                    continue;
                }
                Capsule::$rows[$this->table][$index] = array_merge($row, $values);
                $count++;
            }

            return $count;
        }

        private function matches(array $row)
        {
            foreach ($this->wheres as $column => $value) {
                if (!array_key_exists($column, $row) || (string) $row[$column] !== (string) $value) {
                    return false;
                }
            }

            return true;
        }
    }

    final class Capsule
    {
        public static $rows = array();

        public static function table($name)
        {
            return new CapsuleQuery($name);
        }
    }
}

namespace WhmcsXtreamAI {
    final class PanelApi
    {
        public static $calls = array();
        public static $keyTypeValue = 'admin';
        public static $packageMaxConnectionsValue = 4;
        public static $packageMaxConnectionsError = '';
        public static $renewResult = array('expires_at' => '2027-02-01 00:00:00');
        public static $updateError = '';
        public static $updateLineResult = array();
        public static $getLineResult = array();
        public static $getLineError = '';
        public static $adjustResult = '150';
        public static $findResellerResult = null;
        public static $findResellerError = '';
        public static $adminOwnerMemberIdValue = 260595;
        public static $linesResult = array();
        public static $linesError = '';
        public static $createLineResult = array('id' => '900001', 'username' => '', 'expires_at' => '2027-01-01 00:00:00');
        public static $createResellerResult = array('id' => '770001', 'username' => '');

        private static function record($name, array $args)
        {
            self::$calls[] = array('name' => $name, 'args' => $args);
        }

        public static function keyType($panelId)
        {
            self::record('keyType', func_get_args());

            return self::$keyTypeValue;
        }

        public static function packageMaxConnections($panelId, $packageId)
        {
            self::record('packageMaxConnections', func_get_args());
            if (self::$packageMaxConnectionsError !== '') {
                throw new \RuntimeException(self::$packageMaxConnectionsError);
            }

            return self::$packageMaxConnectionsValue;
        }

        public static function renewLine($panelId, $lineId, $packageId, $bouquets = null, $idempotencyKey = null)
        {
            self::record('renewLine', func_get_args());

            return self::$renewResult;
        }

        public static function updateLine($panelId, $lineId, array $fields)
        {
            self::record('updateLine', array($panelId, $lineId, $fields));
            if (self::$updateError !== '') {
                throw new \RuntimeException(self::$updateError);
            }

            return self::$updateLineResult;
        }

        public static function getLine($panelId, $lineId, $quick = false)
        {
            self::record('getLine', func_get_args());
            if (self::$getLineError !== '') {
                throw new \RuntimeException(self::$getLineError);
            }

            return self::$getLineResult;
        }

        public static function lineConnections($panelId, $lineId, $quick = false)
        {
            self::record('lineConnections', func_get_args());

            return array();
        }

        public static function setLineEnabled($panelId, $lineId, $enabled)
        {
            self::record('setLineEnabled', func_get_args());
        }

        public static function setResellerStatus($panelId, $lineId, $enabled)
        {
            self::record('setResellerStatus', func_get_args());
        }

        public static function adjustResellerCredits($panelId, $lineId, $credits, $reason)
        {
            self::record('adjustResellerCredits', func_get_args());

            return self::$adjustResult;
        }

        public static function resellerCredits($panelId, $lineId)
        {
            self::record('resellerCredits', func_get_args());

            return self::$adjustResult;
        }

        public static function findResellerByUsername($panelId, $username)
        {
            self::record('findResellerByUsername', func_get_args());
            if (self::$findResellerError !== '') {
                throw new \RuntimeException(self::$findResellerError);
            }

            return self::$findResellerResult;
        }

        public static function lines($panelId, $username = null, $enabled = null)
        {
            self::record('lines', func_get_args());
            if (self::$linesError !== '') {
                throw new \RuntimeException(self::$linesError);
            }

            return self::$linesResult;
        }

        public static function createLine(
            $panelId,
            $packageId,
            $memberId,
            $username,
            $password,
            $bouquets,
            $maxConnections = null,
            $notes = null
        ) {
            self::record('createLine', func_get_args());

            return self::$createLineResult;
        }

        public static function createReseller(
            $panelId,
            $username,
            $password,
            $email,
            $credits = null,
            $notes = null,
            $memberGroupId = null
        ) {
            self::record('createReseller', func_get_args());

            return self::$createResellerResult;
        }

        public static function adminOwnerMemberId($panelId)
        {
            return self::$adminOwnerMemberIdValue;
        }

        public static function deleteLine($panelId, $lineId)
        {
            self::record('deleteLine', func_get_args());
        }

        public static function packages($panelId)
        {
            self::record('packages', func_get_args());

            return array(
                array(
                    'id' => 76,
                    'name' => 'Test Package',
                    'duration' => '1 month',
                    'is_official' => true,
                    'is_trial' => false,
                    'max_connections' => 4,
                ),
                array(
                    'id' => 91,
                    'name' => 'Trial Package',
                    'duration' => '1 day',
                    'is_official' => false,
                    'is_trial' => true,
                    'max_connections' => 1,
                ),
            );
        }

        public static function bouquets($panelId)
        {
            self::record('bouquets', func_get_args());

            return array(
                array('id' => 14, 'name' => 'Bouquet A'),
                array('id' => 107, 'name' => 'Bouquet B'),
            );
        }

        public static function credits($panelId)
        {
            self::record('credits', func_get_args());

            return '12.50';
        }
    }

    final class ServiceStore
    {
        public static $rows = array();
        public static $statuses = array();
        public static $actions = array();
        public static $checks = array();
        public static $clientResellers = array();
        public static $resellerLookups = array();
        public static $links = array();

        public static function find($serviceId)
        {
            return isset(self::$rows[$serviceId]) ? (object) self::$rows[$serviceId] : null;
        }

        public static function updateStatus($serviceId, $status, $expiresAt = null)
        {
            self::$statuses[] = array(
                'service_id' => $serviceId,
                'status' => $status,
                'expires_at' => $expiresAt,
            );
            if (isset(self::$rows[$serviceId])) {
                self::$rows[$serviceId]['status'] = $status;
                if ($expiresAt !== null) {
                    self::$rows[$serviceId]['expires_at'] = $expiresAt;
                }
            }
        }

        public static function updateFromLine($serviceId, array $line)
        {
            self::updateStatus(
                $serviceId,
                \WhmcsXtreamAI\LineStatus::fromPanel($line),
                isset($line['expires_at']) ? (string) $line['expires_at'] : null
            );
        }

        public static function recordPanelCheck($serviceId, $checkedAt = null)
        {
            self::$checks[] = $serviceId;
            if (isset(self::$rows[$serviceId])) {
                self::$rows[$serviceId]['panel_checked_at'] = $checkedAt === null ? date('Y-m-d H:i:s') : $checkedAt;
            }
        }

        public static function invalidatePanelCheck($serviceId)
        {
            if (isset(self::$rows[$serviceId])) {
                self::$rows[$serviceId]['panel_checked_at'] = null;
            }
        }

        public static function recordAction($serviceId, $action)
        {
            self::$actions[] = array('service_id' => $serviceId, 'action' => $action);
            if (isset(self::$rows[$serviceId])) {
                self::$rows[$serviceId]['last_action'] = $action;
                self::$rows[$serviceId]['last_action_at'] = date('Y-m-d H:i:s');
            }
        }

        public static function link($serviceId, $panelId, $panelAccountId, $username, $packageId)
        {
            self::$links[] = array(
                'service_id' => $serviceId,
                'panel_id' => $panelId,
                'panel_account_id' => (string) $panelAccountId,
                'username' => (string) $username,
                'package_id' => (int) $packageId,
            );
            self::$rows[$serviceId] = array_merge(
                isset(self::$rows[$serviceId]) ? self::$rows[$serviceId] : array(),
                array(
                    'panel_id' => $panelId,
                    'panel_account_id' => (string) $panelAccountId,
                    'username' => (string) $username,
                    'package_id' => (int) $packageId,
                )
            );
        }

        public static function resellerServicesForClient($userId, $panelId)
        {
            self::$resellerLookups[] = array(
                'user_id' => $userId,
                'panel_id' => $panelId,
            );

            return self::$clientResellers;
        }

        public static function setPackageId($serviceId, $packageId)
        {
        }

        public static function unlink($serviceId)
        {
        }
    }

    final class Settings
    {
        public static $values = array();

        public static function ensureTables()
        {
        }

        public static function set($key, $value)
        {
            self::$values[(string) $key] = (string) $value;
        }

        public static function get($key, $default = '')
        {
            return array_key_exists((string) $key, self::$values) ? self::$values[(string) $key] : $default;
        }

        public static function randomString($length, $type = 'alphanumeric')
        {
            return str_repeat('x', (int) $length);
        }

        public static function credentialSettings()
        {
            return array(
                'username_auto' => '1',
                'username_prefix' => 'xt',
                'username_length' => '10',
                'username_type' => 'alphanumeric',
                'password_auto' => '1',
                'password_length' => '10',
                'password_type' => 'alphanumeric',
            );
        }
    }

    final class PanelStore
    {
        public static function allActive()
        {
            return array((object) array('id' => 1, 'name' => 'Panel One'));
        }

        public static function firstActive()
        {
            return (object) array('id' => 1, 'name' => 'Panel One');
        }

        public static function find($panelId)
        {
            return (object) array(
                'id' => $panelId,
                'name' => 'Panel One',
                'key_type' => PanelApi::$keyTypeValue,
                'admin_owner_member_id' => 260595,
                'm3u_url' => '',
                'epg_url' => '',
            );
        }
    }
}

namespace {
    define('WHMCS', true);

    $GLOBALS['passed'] = 0;
    $GLOBALS['failed'] = 0;
    $GLOBALS['moduleLog'] = array();

    function logModuleCall()
    {
        if (!empty($GLOBALS['moduleLogThrows'])) {
            throw new \RuntimeException('module log unavailable');
        }

        $GLOBALS['moduleLog'][] = func_get_args();
    }

    function encrypt($value)
    {
        return 'encrypted:' . (string) $value;
    }

    function decrypt($value)
    {
        return (string) $value;
    }

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

    function resetApi()
    {
        \WhmcsXtreamAI\PanelApi::$calls = array();
        \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'admin';
        \WhmcsXtreamAI\PanelApi::$packageMaxConnectionsValue = 4;
        \WhmcsXtreamAI\PanelApi::$packageMaxConnectionsError = '';
        \WhmcsXtreamAI\PanelApi::$renewResult = array('expires_at' => '2027-02-01 00:00:00');
        \WhmcsXtreamAI\PanelApi::$updateError = '';
        \WhmcsXtreamAI\PanelApi::$updateLineResult = array();
        \WhmcsXtreamAI\PanelApi::$getLineResult = array();
        \WhmcsXtreamAI\PanelApi::$getLineError = '';
        \WhmcsXtreamAI\PanelApi::$adjustResult = '150';
        \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
        \WhmcsXtreamAI\PanelApi::$findResellerError = '';
        \WhmcsXtreamAI\PanelApi::$adminOwnerMemberIdValue = 260595;
        \WhmcsXtreamAI\PanelApi::$linesResult = array();
        \WhmcsXtreamAI\PanelApi::$linesError = '';
        \WhmcsXtreamAI\PanelApi::$createLineResult = array(
            'id' => '900001',
            'username' => '',
            'expires_at' => '2027-01-01 00:00:00',
        );
        \WhmcsXtreamAI\PanelApi::$createResellerResult = array('id' => '770001', 'username' => '');
        \WhmcsXtreamAI\ServiceStore::$statuses = array();
        \WhmcsXtreamAI\ServiceStore::$actions = array();
        \WhmcsXtreamAI\ServiceStore::$checks = array();
        \WhmcsXtreamAI\ServiceStore::$clientResellers = array();
        \WhmcsXtreamAI\ServiceStore::$resellerLookups = array();
        \WhmcsXtreamAI\ServiceStore::$links = array();
        \WhmcsXtreamAI\Settings::$values = array();
        $GLOBALS['moduleLog'] = array();
    }

    function apiCalls($name)
    {
        $out = array();
        foreach (\WhmcsXtreamAI\PanelApi::$calls as $call) {
            if ($call['name'] === $name) {
                $out[] = $call;
            }
        }

        return $out;
    }

    function loggedActions()
    {
        $out = array();
        foreach ($GLOBALS['moduleLog'] as $entry) {
            $out[] = (string) $entry[1] . ':' . (string) $entry[4];
        }

        return $out;
    }

    function checkoutLogs()
    {
        $out = array();
        foreach ($GLOBALS['moduleLog'] as $entry) {
            if ((string) $entry[1] === 'checkout_validate') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    function checkoutHookLogs()
    {
        $out = array();
        foreach ($GLOBALS['moduleLog'] as $entry) {
            if ((string) $entry[1] === 'checkout_hook') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    function logActions()
    {
        $out = array();
        foreach ($GLOBALS['moduleLog'] as $entry) {
            $out[] = (string) $entry[1];
        }

        return $out;
    }

    function logRequest(array $entry)
    {
        $decoded = json_decode(isset($entry[2]) ? (string) $entry[2] : '', true);

        return is_array($decoded) ? $decoded : array();
    }

    function logResponse(array $entry)
    {
        return isset($entry[3]) ? (string) $entry[3] : '';
    }

    function logDecision(array $entry)
    {
        $response = logResponse($entry);
        if (strpos($response, 'decision=') !== 0) {
            return '';
        }

        $rest = substr($response, 9);
        $space = strpos($rest, ' ');

        return $space === false ? $rest : substr($rest, 0, $space);
    }

    function logDecisions()
    {
        $out = array();
        foreach (checkoutLogs() as $entry) {
            $out[] = logDecision($entry);
        }

        return $out;
    }

    function logField($name)
    {
        $entries = checkoutLogs();
        if (!$entries) {
            return null;
        }

        $request = logRequest($entries[count($entries) - 1]);

        return array_key_exists($name, $request) ? $request[$name] : null;
    }

    function panelWrites()
    {
        return count(apiCalls('createLine'))
            + count(apiCalls('updateLine'))
            + count(apiCalls('renewLine'))
            + count(apiCalls('deleteLine'))
            + count(apiCalls('setLineEnabled'))
            + count(apiCalls('setResellerStatus'))
            + count(apiCalls('adjustResellerCredits'));
    }

    function baseParams(array $overrides = array())
    {
        $params = array(
            'serviceid' => 135,
            'userid' => 44,
            'productname' => 'IPTV Line',
            'billingcycle' => 'Monthly',
            'username' => 'line_user',
            'password' => 'LinePass123',
            'configoption1' => '1',
            'configoption2' => '76',
            'configoption3' => 'official',
            'configoption4' => '14',
            'configoption5' => 'line',
            'configoption6' => '0',
            'configoption7' => '0',
            'configoption8' => '0',
            'configoption9' => 'disable',
            'configoption10' => 'any',
        );

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
                continue;
            }
            $params[$key] = $value;
        }

        return $params;
    }

    function linkService(array $overrides = array())
    {
        \WhmcsXtreamAI\ServiceStore::$rows[135] = array_merge(array(
            'panel_id' => 1,
            'panel_account_id' => '987654',
            'package_id' => 76,
            'status' => 'Active',
            'username' => 'line_user',
            'expires_at' => '2026-10-01 00:00:00',
        ), $overrides);
    }

    function resellerParams(array $overrides = array())
    {
        return baseParams(array_merge(array(
            'configoption5' => 'reseller',
            'configoption8' => '20',
            'clientsdetails' => array('email' => 'cliente@example.com'),
        ), $overrides));
    }

    function hostingRow()
    {
        return array(
            array('id' => 135, 'username' => 'line_user', 'password' => '', 'nextduedate' => '2026-10-01'),
        );
    }

    function customFieldValueRow($id)
    {
        $rows = isset(\WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues'])
            ? \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues']
            : array();

        foreach ($rows as $row) {
            if ((int) $row['id'] === (int) $id) {
                return $row['value'];
            }
        }

        return null;
    }

    function setCustomFieldValueRow($id, $value)
    {
        $rows = isset(\WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues'])
            ? \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues']
            : array();

        foreach ($rows as $index => $row) {
            if ((int) $row['id'] === (int) $id) {
                \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues'][$index]['value'] = $value;
            }
        }
    }

    require __DIR__ . '/../modules/servers/xtreamai/xtreamai.php';

    \WHMCS\Database\Capsule::$rows = array(
        'tblhosting' => array(
            array('id' => 135, 'username' => 'line_user', 'password' => '', 'nextduedate' => '2026-10-01'),
        ),
        'tblproducts' => array(),
    );

    echo "WHMCS Xtream AI server module tests (no WHMCS required)\n";

    section('A. Renew re-applies the product connection count');

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$renewResult = array('expires_at' => '2027-02-01 00:00:00', 'max_connections' => 3);
    $result = xtreamai_Renew(baseParams(array('configoption7' => '5')));
    same('renew returns success when the panel count differs', 'success', $result);
    same('one updateLine call is sent', 1, count(apiCalls('updateLine')));
    same(
        'the updateLine call carries the product count',
        array(1, '987654', array('max_connections' => 5)),
        apiCalls('updateLine') ? apiCalls('updateLine')[0]['args'] : null
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$renewResult = array('expires_at' => '2027-02-01 00:00:00', 'max_connections' => 3);
    $result = xtreamai_Renew(baseParams(array('configoption7' => '3')));
    same('renew returns success when the panel count matches', 'success', $result);
    same('no updateLine call is sent when the count matches', 0, count(apiCalls('updateLine')));
    same('the renew still hits the panel', 1, count(apiCalls('renewLine')));

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$renewResult = array('expires_at' => '2027-02-01 00:00:00');
    $result = xtreamai_Renew(baseParams(array('configoption7' => '5')));
    same('renew returns success when the panel omits max_connections', 'success', $result);
    same('the product count is re-applied when the panel omits it', 1, count(apiCalls('updateLine')));
    same(
        'the fallback updateLine call carries the product count',
        array(1, '987654', array('max_connections' => 5)),
        apiCalls('updateLine') ? apiCalls('updateLine')[0]['args'] : null
    );

    linkService();
    resetApi();
    $result = xtreamai_Renew(baseParams());
    same('renew returns success with no product count', 'success', $result);
    same('no updateLine call is sent when the product has no count', 0, count(apiCalls('updateLine')));

    linkService();
    resetApi();
    $result = xtreamai_Renew(baseParams(array(
        'configoptions' => array('extra_connections' => '2'),
    )));
    same('renew returns success with a connections configurable option', 'success', $result);
    same(
        'the package count plus the configurable option is re-applied',
        array(1, '987654', array('max_connections' => 6)),
        apiCalls('updateLine') ? apiCalls('updateLine')[0]['args'] : null
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$updateError = 'Panel rejected max_connections';
    $result = xtreamai_Renew(baseParams(array('configoption7' => '5')));
    same('renew stays success when the connections update fails', 'success', $result);
    same('the failed update is attempted once', 1, count(apiCalls('updateLine')));
    ok('the failed update is written to the module log', in_array('renew_connections:error', loggedActions(), true));
    same(
        'the service is still marked active after a failed update',
        'Active',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$packageMaxConnectionsError = 'Package #76 was not found on the panel.';
    $result = xtreamai_Renew(baseParams(array(
        'configoptions' => array('extra_connections' => '2'),
    )));
    same('renew stays success when the count cannot be resolved', 'success', $result);
    same('no updateLine call is sent when the count cannot be resolved', 0, count(apiCalls('updateLine')));
    ok('the unresolved count is written to the module log', in_array('renew_connections:error', loggedActions(), true));

    linkService();
    resetApi();
    $result = xtreamai_Renew(baseParams(array('configoption5' => 'reseller', 'configoption6' => '10')));
    same('renew returns success for a sub-reseller product', 'success', $result);
    same('a sub-reseller renewal never calls updateLine', 0, count(apiCalls('updateLine')));
    same('a sub-reseller renewal adds credits', 1, count(apiCalls('adjustResellerCredits')));

    linkService();
    resetApi();
    $result = xtreamai_Renew(baseParams(array('configoption7' => '5')));
    $statuses = \WhmcsXtreamAI\ServiceStore::$statuses;
    same('renew marks the service active with the panel expiry', '2027-02-01 00:00:00', $statuses[0]['expires_at']);
    same(
        'renew syncs the WHMCS next due date to the panel expiry',
        '2027-02-01',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['nextduedate']
    );

    section('B. Suspend action config option');

    same('helper reads none', 'none', xtreamai_suspendAction(array('configoption9' => 'none')));
    same('helper reads disable', 'disable', xtreamai_suspendAction(array('configoption9' => 'disable')));
    same('helper accepts upper case', 'none', xtreamai_suspendAction(array('configoption9' => ' NONE ')));
    same('helper falls back to disable on an empty value', 'disable', xtreamai_suspendAction(array('configoption9' => '')));
    same('helper falls back to disable on a missing option', 'disable', xtreamai_suspendAction(array()));
    same('helper falls back to disable on an unknown value', 'disable', xtreamai_suspendAction(array('configoption9' => 'disable_something')));
    same('helper reads the configoptions map', 'none', xtreamai_suspendAction(array('configoptions' => array('suspend_action' => 'none'))));
    same('helper unwraps an array value', 'none', xtreamai_suspendAction(array('configoption9' => array('none'))));

    linkService();
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption9' => 'none')));
    same('suspend with none returns success', 'success', $result);
    same('suspend with none does not touch the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));
    same(
        'suspend with none still marks the service suspended',
        'Suspended',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );

    linkService();
    resetApi();
    $result = xtreamai_UnsuspendAccount(baseParams(array('configoption9' => 'none')));
    same('unsuspend with none returns success', 'success', $result);
    same('unsuspend with none does not touch the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));
    same(
        'unsuspend with none still marks the service active',
        'Active',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );

    linkService();
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption9' => 'disable')));
    same('suspend with disable returns success', 'success', $result);
    same(
        'suspend with disable disables the line',
        array(1, '987654', false),
        apiCalls('setLineEnabled') ? apiCalls('setLineEnabled')[0]['args'] : null
    );

    linkService();
    resetApi();
    $result = xtreamai_UnsuspendAccount(baseParams(array('configoption9' => 'disable')));
    same('unsuspend with disable returns success', 'success', $result);
    same(
        'unsuspend with disable enables the line',
        array(1, '987654', true),
        apiCalls('setLineEnabled') ? apiCalls('setLineEnabled')[0]['args'] : null
    );

    linkService();
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption9' => null)));
    same('suspend with a missing option returns success', 'success', $result);
    same('suspend with a missing option disables the line', 1, count(apiCalls('setLineEnabled')));

    linkService();
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption9' => 'whatever')));
    same('suspend with an unknown value returns success', 'success', $result);
    same('suspend with an unknown value disables the line', 1, count(apiCalls('setLineEnabled')));

    linkService();
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption9' => 'NONE')));
    same('suspend accepts the upper case value', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    linkService();
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption5' => 'reseller', 'configoption9' => 'none')));
    same('a sub-reseller product ignores the option', 0, count(apiCalls('setLineEnabled')));
    same(
        'a sub-reseller product still changes the reseller status',
        array(1, '987654', false),
        apiCalls('setResellerStatus') ? apiCalls('setResellerStatus')[0]['args'] : null
    );

    linkService();
    resetApi();
    $result = xtreamai_UnsuspendAccount(baseParams(array('configoption5' => 'reseller', 'configoption9' => 'none')));
    same(
        'a sub-reseller unsuspend still changes the reseller status',
        array(1, '987654', true),
        apiCalls('setResellerStatus') ? apiCalls('setResellerStatus')[0]['args'] : null
    );

    section('C. Config option layout');

    $options = xtreamai_ConfigOptions();
    $keys = array_values(array_keys($options));
    same('the product has twelve config options', 12, count($keys));
    same(
        'every existing option keeps its slot',
        array(
            'panel_id',
            'package_id',
            'package_type',
            'bouquets',
            'account_type',
            'credits',
            'max_connections',
            'sub_reseller_member_group_id',
            'suspend_action',
            'topup_scope',
            'customer_username',
            'customer_password',
        ),
        $keys
    );
    same('Max Connections is still configoption7', 'max_connections', $keys[6]);
    same(
        'the Sub-Reseller Member Group ID is still configoption8',
        'sub_reseller_member_group_id',
        $keys[7]
    );
    same('the suspend action is still configoption9', 'suspend_action', $keys[8]);
    same('the top-up scope is still configoption10', 'topup_scope', $keys[9]);
    same('the customer username switch is configoption11', 'customer_username', $keys[10]);
    same('the customer password switch is configoption12', 'customer_password', $keys[11]);
    same('the new option is a dropdown', 'dropdown', $options['suspend_action']['Type']);
    same('the new option defaults to disable', 'disable', $options['suspend_action']['Default']);
    same('the new option offers disable', 'Disable the line on the panel', $options['suspend_action']['Options']['disable']);
    same(
        'the new option offers none',
        'Leave the line untouched, let it expire',
        $options['suspend_action']['Options']['none']
    );

    section('D. Panel line status helper');

    $now = 1800000000;
    same(
        'an enabled line inside its expiry is Active',
        'Active',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => true, 'admin_enabled' => true, 'exp_date' => $now + 1), $now)
    );
    same(
        'an enabled line past its expiry is Expired',
        'Expired',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => true, 'admin_enabled' => true, 'exp_date' => $now - 1), $now)
    );
    same(
        'a line switched off on the panel is Disabled',
        'Disabled',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => false, 'admin_enabled' => true, 'exp_date' => $now + 1), $now)
    );
    same(
        'a line blocked on the panel is Blocked by panel',
        'Blocked by panel',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => true, 'admin_enabled' => false, 'exp_date' => $now + 1), $now)
    );
    same(
        'the panel block wins over the disabled flag',
        'Blocked by panel',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => false, 'admin_enabled' => false, 'exp_date' => $now + 1), $now)
    );
    same(
        'a line with no expiry date is Active',
        'Active',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => true, 'admin_enabled' => true, 'exp_date' => null), $now)
    );
    same(
        'a date-only expiry in the past is Expired',
        'Expired',
        \WhmcsXtreamAI\LineStatus::fromPanel(array('enabled' => true, 'admin_enabled' => true, 'expires_at' => '2020-01-01'), $now)
    );
    same(
        'the blocked status uses the danger badge',
        'xtai-badge--danger',
        \WhmcsXtreamAI\LineStatus::badgeClass('Blocked by panel')
    );

    section('E. Renew idempotency key');

    $lineBefore = array(
        'id' => '987654',
        'username' => 'line_user',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1790000000,
        'expires_at' => '2026-10-01',
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$getLineResult = $lineBefore;
    xtreamai_Renew(baseParams());
    $firstKey = apiCalls('renewLine') ? apiCalls('renewLine')[0]['args'][4] : null;
    same(
        'the key is the hash of the service, the panel expiry and the package',
        hash('sha256', 'whmcs-renew|135|1790000000|76'),
        $firstKey
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$getLineResult = $lineBefore;
    xtreamai_Renew(baseParams());
    $secondKey = apiCalls('renewLine') ? apiCalls('renewLine')[0]['args'][4] : null;
    same('a second renewal of the same cycle reuses the key', $firstKey, $secondKey);

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '987654',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1800000000,
        'expires_at' => '2027-01-15',
    );
    xtreamai_Renew(baseParams());
    $thirdKey = apiCalls('renewLine') ? apiCalls('renewLine')[0]['args'][4] : null;
    ok(
        'the next cycle uses a different key',
        $thirdKey !== $firstKey,
        'both keys are ' . json_encode($thirdKey)
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '987654',
        'enabled' => false,
        'admin_enabled' => false,
        'exp_date' => 1790000000,
        'expires_at' => '2026-10-01',
    );
    $result = xtreamai_Renew(baseParams());
    same('renew still succeeds on a line blocked by the panel', 'success', $result);
    same('the blocked line is still renewed', 1, count(apiCalls('renewLine')));
    ok('the blocked line is written to the module log', in_array('renew_state:info', loggedActions(), true));
    ok(
        'the blocked line is written to last_action',
        strpos((string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action'], 'Blocked by panel') !== false,
        (string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action']
    );
    same(
        'the renewal summary carries both expiry dates and the package',
        'Renewed: 01/10/2026 -> 01/02/2027 (package #76) · line was Blocked by panel and the panel enabled it again',
        (string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action']
    );

    linkService();
    resetApi();
    $result = xtreamai_Renew(baseParams(array('configoption2' => '0')));
    same(
        'a missing package names the service and the product',
        'No package selected for service #135 (product "IPTV Line"): set the package in the product\'s Module Settings.',
        $result
    );
    same('a missing package never hits the panel', 0, count(apiCalls('renewLine')));

    section('F. Push and pull expiry');

    \WHMCS\Database\Capsule::$rows['tblhosting'][0]['nextduedate'] = '2026-10-01';

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    $result = xtreamai_push_expiry(baseParams());
    ok(
        'a reseller key is rejected with an admin key message',
        strpos((string) $result, 'requires an admin panel key') !== false,
        (string) $result
    );
    same('the rejected push sends nothing to the panel', 0, count(apiCalls('updateLine')));
    same('the rejected push does not read the line either', 0, count(apiCalls('getLine')));

    linkService();
    resetApi();
    $result = xtreamai_push_expiry(baseParams());
    same('an admin key pushes the expiry', 'success', $result);
    same(
        'the pushed expiry is the next due date at noon UTC',
        strtotime('2026-10-01 12:00:00 UTC'),
        apiCalls('updateLine') ? (int) apiCalls('updateLine')[0]['args'][2]['exp_date'] : null
    );
    ok(
        'the pushed expiry is written to last_action',
        strpos((string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action'], 'Panel expiry set to 01/10/2026') === 0,
        (string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action']
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '987654',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1804288400,
        'expires_at' => '2027-03-05',
    );
    $result = xtreamai_pull_expiry(baseParams());
    same('an admin key pulls the expiry', 'success', $result);
    same(
        'the WHMCS next due date follows the panel expiry',
        '2027-03-05',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['nextduedate']
    );
    same('the pull reads the line once', 1, count(apiCalls('getLine')));
    same('the pull invalidates the cached panel check', null, \WhmcsXtreamAI\ServiceStore::$rows[135]['panel_checked_at']);

    section('G. Sync persists the panel state');

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$updateLineResult = array(
        'id' => '987654',
        'username' => 'line_user',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1804288400,
        'expires_at' => '2027-03-05',
    );
    $result = xtreamai_sync(baseParams());
    same('sync returns success', 'success', $result);
    same(
        'sync pushes the notes and the bouquets',
        array('notes' => 'WHMCS:135', 'bouquets' => array(14)),
        apiCalls('updateLine') ? apiCalls('updateLine')[0]['args'][2] : null
    );
    same('sync stores the panel expiry', '2027-03-05', \WhmcsXtreamAI\ServiceStore::$rows[135]['expires_at']);
    same('sync stores the panel status', 'Active', \WhmcsXtreamAI\ServiceStore::$rows[135]['status']);
    same(
        'sync leaves a summary in last_action',
        'Synced notes, bouquets (1) · panel: Active, expires 05/03/2027',
        (string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action']
    );

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$updateLineResult = array(
        'id' => '987654',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1700000000,
        'expires_at' => '2023-11-14',
    );
    xtreamai_sync(baseParams());
    same('sync stores an expired line as Expired', 'Expired', \WhmcsXtreamAI\ServiceStore::$rows[135]['status']);

    linkService();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$updateLineResult = array(
        'id' => '987654',
        'enabled' => false,
        'admin_enabled' => true,
        'exp_date' => 1804288400,
        'expires_at' => '2027-03-05',
    );
    xtreamai_sync(baseParams());
    same('sync stores a switched off line as Disabled', 'Disabled', \WhmcsXtreamAI\ServiceStore::$rows[135]['status']);

    section('H. Admin service tab');

    linkService();
    resetApi();
    unset(
        \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action'],
        \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action_at'],
        \WhmcsXtreamAI\ServiceStore::$rows[135]['panel_checked_at']
    );
    \WHMCS\Database\Capsule::$rows['tblhosting'][0]['nextduedate'] = '2027-10-01';
    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '987654',
        'username' => 'line_user',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1822521600,
        'expires_at' => '2027-10-01',
    );

    $fields = xtreamai_AdminServicesTabFields(baseParams());
    same('the tab names the panel line id', '987654', isset($fields['Panel line ID']) ? $fields['Panel line ID'] : null);
    same('the tab names the panel username', 'line_user', isset($fields['Panel username']) ? $fields['Panel username'] : null);
    same('the tab shows the panel status', 'Active', isset($fields['Line status']) ? $fields['Line status'] : null);
    same('the tab shows the WHMCS next due date', '01/10/2027', isset($fields['WHMCS next due date']) ? $fields['WHMCS next due date'] : null);
    same('the tab shows the panel expiry', '01/10/2027', isset($fields['Panel expiry']) ? $fields['Panel expiry'] : null);
    same('the tab reports no module action yet', 'None yet', isset($fields['Last module action']) ? $fields['Last module action'] : null);
    same('the first tab render reads the panel once', 1, count(apiCalls('getLine')));
    ok('the first tab render is marked live', substr((string) $fields['Panel checked'], -4) === 'live', (string) $fields['Panel checked']);

    $fields = xtreamai_AdminServicesTabFields(baseParams());
    same('the second tab render reuses the cached check', 1, count(apiCalls('getLine')));
    ok('the cached render says cached', substr((string) $fields['Panel checked'], -6) === 'cached', (string) $fields['Panel checked']);

    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '987654',
        'username' => 'line_user',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1820000000,
        'expires_at' => '2027-09-01',
    );
    \WhmcsXtreamAI\Settings::$values['last_update_warning'] = 'Dropped admin-only fields on reseller key: max_connections';
    $result = xtreamai_refresh(baseParams());
    same('the refresh button returns success', 'success', $result);
    same('the refresh button forces a panel read', 2, count(apiCalls('getLine')));
    same(
        'the refresh button leaves a summary in last_action',
        'Panel check: Active, expires 01/09/2027',
        (string) \WhmcsXtreamAI\ServiceStore::$rows[135]['last_action']
    );

    $fields = xtreamai_AdminServicesTabFields(baseParams());
    ok(
        'the tab warns about the date divergence',
        strpos((string) $fields['Warning'], 'WHMCS next due date is 01/10/2027, the panel line expires 01/09/2027') !== false,
        (string) $fields['Warning']
    );
    ok(
        'the tab shows the update warning',
        strpos((string) $fields['Warning'], 'Dropped admin-only fields on reseller key: max_connections') !== false,
        (string) $fields['Warning']
    );
    same(
        'the update warning is cleared once shown',
        '',
        (string) \WhmcsXtreamAI\Settings::get('last_update_warning', '')
    );

    \WhmcsXtreamAI\PanelApi::$getLineError = 'Could not reach the panel.';
    \WhmcsXtreamAI\ServiceStore::invalidatePanelCheck(135);
    $fields = xtreamai_AdminServicesTabFields(baseParams());
    ok(
        'a failed check is reported without breaking the tab',
        strpos((string) $fields['Panel checked'], 'Panel check: failed (Could not reach the panel.)') === 0,
        (string) $fields['Panel checked']
    );

    linkService();
    resetApi();
    $fields = xtreamai_AdminServicesTabFields(baseParams(array('configoption5' => 'reseller')));
    same('a Sub-Reseller service is never read as a line', 0, count(apiCalls('getLine')));
    same(
        'a Sub-Reseller service says the panel check does not apply',
        'Not applicable to Sub-Reseller accounts',
        isset($fields['Panel checked']) ? $fields['Panel checked'] : null
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    $fields = xtreamai_AdminServicesTabFields(baseParams());
    same(
        'an unlinked service is explained in the tab',
        'Not linked yet. Provision the service or use Bulk tools &gt; Link existing services.',
        isset($fields['Panel line']) ? $fields['Panel line'] : null
    );

    section('I. Credit top-up account type');

    $accountOptions = xtreamai_ConfigOptions();
    same('the account type helper reads a credit top-up', 'topup', xtreamai_accountType(array('configoption5' => 'TopUp ')));
    same('the account type helper still reads a sub-reseller', 'reseller', xtreamai_accountType(array('configoption5' => ' reseller ')));
    same('the account type helper falls back to a line', 'line', xtreamai_accountType(array('configoption5' => 'x')));
    same(
        'the account type dropdown offers the credit top-up',
        'Credit top-up (existing Sub-Reseller)',
        $accountOptions['account_type']['Options']['topup']
    );
    same('the top-up scope helper reads a linked scope', 'linked', xtreamai_topUpScope(array('configoption10' => 'Linked ')));
    same('the top-up scope helper falls back to any reseller', 'any', xtreamai_topUpScope(array('configoption10' => 'x')));
    same('the top-up scope is the tenth config option', 'topup_scope', array_keys($accountOptions)[9]);
    same('the top-up scope defaults to any reseller', 'any', $accountOptions['topup_scope']['Default']);

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
    );
    $result = xtreamai_CreateAccount(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    same('a credit top-up order returns success', 'success', $result);
    same(
        'the top-up adds the product credits to the only candidate',
        array(1, '41', 50.0, 'WHMCS top-up service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );
    same(
        'the top-up resolves the target among the services of the client',
        array(array('user_id' => 44, 'panel_id' => 1)),
        \WhmcsXtreamAI\ServiceStore::$resellerLookups
    );
    same(
        'the top-up marks the service active',
        'Active',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );
    same(
        'the top-up records the credited account and the balance',
        'Topped up +50 credits to resA (balance 150)',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );
    same(
        'the service username follows the target account',
        'resA',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['username']
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
    );
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'configoptions' => array('credits|Credits' => '200'),
    )));
    same('a top-up with a credits configurable option returns success', 'success', $result);
    same(
        'the configurable option replaces the product credits',
        200.0,
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'][2] : null
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    same(
        'a top-up without a sub-reseller to top up is refused',
        'This client has no active Sub-Reseller service on this panel to top up. Add a required custom field named "Reseller username" to the top-up product so the customer types the panel account, or order the Sub-Reseller product first.',
        $result
    );
    same('a refused top-up never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));
    same(
        'a top-up without a custom field never queries the panel by username',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
        array('service_id' => 91, 'reseller_id' => '42', 'username' => 'resB'),
    );
    $result = xtreamai_CreateAccount(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    same(
        'several sub-resellers without a custom field are refused',
        'This client has several Sub-Reseller accounts on this panel (resA, resB). Add a required custom field named "Reseller username" to the top-up product so the customer chooses the account.',
        $result
    );
    same('an ambiguous top-up never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
        array('service_id' => 91, 'reseller_id' => '42', 'username' => 'resB'),
    );
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'customfields' => array('Reseller username' => 'resB'),
    )));
    same('a top-up with a chosen reseller returns success', 'success', $result);
    same(
        'the chosen reseller receives the credits',
        array(1, '42', 50.0, 'WHMCS top-up service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );
    same(
        'the chosen reseller is recorded in the action',
        'Topped up +50 credits to resB (balance 150)',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );
    same(
        'a username that matches a linked service never queries the panel',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'customfields' => array('Reseller username' => 'ghost'),
    )));
    same(
        'a custom field that matches nothing on the panel is refused',
        'The reseller username "ghost" was not found on this panel.',
        $result
    );
    same('an unmatched top-up never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));
    same(
        'the unmatched username is looked up on the panel',
        array(1, 'ghost'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '5150', 'username' => 'panelres', 'member_group_id' => 4);
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'customfields' => array('Reseller username' => 'panelres'),
    )));
    same('a username that only exists on the panel returns success', 'success', $result);
    same(
        'the panel account found by username receives the credits',
        array(1, '5150', 50.0, 'WHMCS top-up service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );
    same(
        'the panel lookup receives the typed username',
        array(1, 'panelres'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same(
        'the panel account found by username is recorded in the action',
        'Topped up +50 credits to panelres (balance 150)',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );
    same(
        'the service username follows the panel account found by username',
        'panelres',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['username']
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
    );
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '5150', 'username' => 'panelres', 'member_group_id' => 4);
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'customfields' => array('Reseller username' => 'PanelRes'),
    )));
    same('a differently cased panel username returns success', 'success', $result);
    same(
        'a differently cased panel username still credits the panel account',
        array(1, '5150', 50.0, 'WHMCS top-up service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );
    same(
        'a differently cased username is looked up exactly as typed',
        array(1, 'PanelRes'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same(
        'the panel account username from the lookup is recorded',
        'Topped up +50 credits to panelres (balance 150)',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'configoption10' => 'linked',
        'customfields' => array('Reseller username' => 'ghost'),
    )));
    same(
        'a linked-only top-up refuses a username that is not linked',
        'The reseller username "ghost" does not match any of this client\'s linked Sub-Reseller accounts on this panel.',
        $result
    );
    same('a linked-only refusal never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));
    same(
        'a linked-only refusal never queries the panel by username',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
    );
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'configoption10' => 'linked',
        'customfields' => array('Reseller username' => 'resa'),
    )));
    same('a linked-only top-up still credits a linked account', 'success', $result);
    same(
        'the linked account chosen under a linked-only scope receives the credits',
        array(1, '41', 50.0, 'WHMCS top-up service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );
    same(
        'a linked account under a linked-only scope never queries the panel',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '5150', 'username' => 'panelres', 'member_group_id' => 4);
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'configoption10' => null,
        'customfields' => array('Reseller username' => 'panelres'),
    )));
    same('a top-up without a scope falls back to any reseller', 'success', $result);
    same(
        'the any reseller fallback still looks the username up on the panel',
        array(1, 'panelres'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same(
        'the any reseller fallback credits the panel account',
        array(1, '5150', 50.0, 'WHMCS top-up service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '5150', 'username' => 'panelres', 'member_group_id' => 1);
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'customfields' => array('Reseller username' => 'panelres'),
    )));
    same(
        'a panel administrator account is refused',
        'The reseller username "panelres" belongs to a panel administrator and cannot receive a top-up.',
        $result
    );
    same('a refused administrator top-up never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '260595', 'username' => 'panelres', 'member_group_id' => 4);
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption5' => 'topup',
        'configoption6' => '50',
        'customfields' => array('Reseller username' => 'panelres'),
    )));
    same(
        'the panel administrator owner account is refused',
        'The reseller username "panelres" belongs to a panel administrator and cannot receive a top-up.',
        $result
    );
    same('a refused administrator owner top-up never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));
    same(
        'the administrator owner account is still looked up once',
        1,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
    );
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    $result = xtreamai_CreateAccount(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    same('a reseller key cannot run a top-up', 'Credit top-ups require an admin panel key on this panel entry.', $result);
    same('the admin key gate runs before any adjustment', 0, count(apiCalls('adjustResellerCredits')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 90, 'reseller_id' => '41', 'username' => 'resA'),
    );
    $result = xtreamai_CreateAccount(baseParams(array('configoption5' => 'topup', 'configoption6' => '0')));
    same(
        'a top-up without a credit amount is refused',
        'No credit amount configured for this top-up product. Set Credits on the product\'s Module Settings tab or add a configurable option named credits.',
        $result
    );
    same('a top-up without credits never adjusts credits', 0, count(apiCalls('adjustResellerCredits')));

    \WhmcsXtreamAI\ServiceStore::$rows = array(
        135 => array(
            'panel_id' => 1,
            'panel_account_id' => '41',
            'package_id' => 0,
            'status' => 'Active',
            'username' => 'resA',
            'last_action' => 'Topped up +50 credits to resA (balance 150)',
        ),
    );
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    same(
        'provisioning an already applied top-up is refused',
        'This top-up was already applied (Topped up +50 credits to resA (balance 150)). Use a renewal or the Sub-Resellers tab to add more credits.',
        $result
    );
    same('the double application guard runs before any adjustment', 0, count(apiCalls('adjustResellerCredits')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    $result = xtreamai_Renew(baseParams(array('configoption5' => 'topup', 'configoption6' => '25')));
    same('a credit top-up renewal returns success', 'success', $result);
    same(
        'the renewal tops up the linked reseller account',
        array(1, '41', 25.0, 'WHMCS top-up renewal service #135'),
        apiCalls('adjustResellerCredits') ? apiCalls('adjustResellerCredits')[0]['args'] : null
    );
    same(
        'the renewal marks the service active',
        'Active',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );
    same(
        'the renewal records the credited account',
        'Topped up +25 credits to resA (balance 150)',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );

    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    $result = xtreamai_SuspendAccount(baseParams(array('configoption5' => 'topup')));
    same('a top-up suspend returns success', 'success', $result);
    same(
        'a top-up suspend never touches the panel',
        0,
        count(apiCalls('setResellerStatus')) + count(apiCalls('setLineEnabled')) + count(apiCalls('deleteLine'))
    );
    same(
        'a top-up suspend marks the service suspended',
        'Suspended',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );

    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    $result = xtreamai_UnsuspendAccount(baseParams(array('configoption5' => 'topup')));
    same('a top-up unsuspend returns success', 'success', $result);
    same(
        'a top-up unsuspend never touches the panel',
        0,
        count(apiCalls('setResellerStatus')) + count(apiCalls('setLineEnabled')) + count(apiCalls('deleteLine'))
    );
    same(
        'a top-up unsuspend marks the service active',
        'Active',
        \WhmcsXtreamAI\ServiceStore::$statuses ? \WhmcsXtreamAI\ServiceStore::$statuses[0]['status'] : null
    );

    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    $result = xtreamai_TerminateAccount(baseParams(array('configoption5' => 'topup')));
    same('a top-up terminate returns success', 'success', $result);
    same(
        'a top-up terminate never touches the panel',
        0,
        count(apiCalls('setResellerStatus')) + count(apiCalls('setLineEnabled')) + count(apiCalls('deleteLine'))
    );

    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    same(
        'a top-up password change is refused',
        'Password changes are not supported for Credit top-up products: the password belongs to the Sub-Reseller service.',
        xtreamai_ChangePassword(baseParams(array('configoption5' => 'topup')))
    );
    same(
        'a top-up package change is refused',
        'Package changes are not supported for Credit top-up products.',
        xtreamai_ChangePackage(baseParams(array('configoption5' => 'topup')))
    );
    same(
        'a top-up sync is refused',
        'Sync is not supported for Credit top-up products.',
        xtreamai_sync(baseParams(array('configoption5' => 'topup')))
    );
    same(
        'a top-up refresh is refused',
        'Refresh from panel is not supported for Credit top-up products.',
        xtreamai_refresh(baseParams(array('configoption5' => 'topup')))
    );
    same(
        'a top-up expiry push is refused',
        'Expiry alignment is not supported for Credit top-up products.',
        xtreamai_push_expiry(baseParams(array('configoption5' => 'topup')))
    );
    same(
        'a top-up expiry pull is refused',
        'Expiry alignment is not supported for Credit top-up products.',
        xtreamai_pull_expiry(baseParams(array('configoption5' => 'topup')))
    );
    same(
        'a top-up product has no admin buttons',
        array(),
        xtreamai_AdminCustomButtonArray(baseParams(array('configoption5' => 'topup')))
    );
    same('the refused buttons never touch the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $fields = xtreamai_AdminServicesTabFields(baseParams(array('configoption5' => 'topup')));
    same(
        'a top-up without a target is explained in the tab',
        'Not applied yet. The top-up runs when the service is created.',
        isset($fields['Top-up target']) ? $fields['Top-up target'] : null
    );

    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    $fields = xtreamai_AdminServicesTabFields(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    same('the top-up tab names the product', 'Credit top-up', isset($fields['Product']) ? $fields['Product'] : null);
    same(
        'the top-up tab names the target account',
        'resA (id 41)',
        isset($fields['Sub-Reseller account']) ? $fields['Sub-Reseller account'] : null
    );
    same('the top-up tab shows the credits per order', '50', isset($fields['Credits per order']) ? $fields['Credits per order'] : null);
    same('the top-up tab shows the live balance', '150', isset($fields['Current balance']) ? $fields['Current balance'] : null);
    same('the top-up tab reads the balance once', 1, count(apiCalls('resellerCredits')));
    same('the top-up tab is not read as a line', 0, count(apiCalls('getLine')));

    linkService(array('panel_account_id' => '41', 'username' => 'resA'));
    resetApi();
    $area = xtreamai_ClientArea(baseParams(array('configoption5' => 'topup', 'configoption6' => '50')));
    $vars = isset($area['templateVariables']) ? $area['templateVariables'] : array();
    same('the top-up client area names the target account', 'resA', isset($vars['topup_username']) ? $vars['topup_username'] : null);
    same('the top-up client area shows the credits per order', '50', isset($vars['topup_credits']) ? $vars['topup_credits'] : null);
    same('the top-up client area shows the live balance', '150', isset($vars['credits']) ? $vars['credits'] : null);
    same(
        'the top-up client area leaves the M3U and EPG links empty',
        array('', ''),
        array(isset($vars['m3u_url']) ? $vars['m3u_url'] : null, isset($vars['epg_url']) ? $vars['epg_url'] : null)
    );
    same('the top-up client area loads no connections', array(), isset($vars['connections']) ? $vars['connections'] : null);
    same(
        'the top-up client area never reads a panel line',
        0,
        count(apiCalls('getLine')) + count(apiCalls('lineConnections'))
    );

    section('J. Customer username');

    $options = xtreamai_ConfigOptions();
    same('the customer username switch is configoption11', 'customer_username', array_keys($options)[10]);
    same('the switch is named Customer username', 'Customer username', $options['customer_username']['FriendlyName']);
    same('the switch is a dropdown', 'dropdown', $options['customer_username']['Type']);
    same('the switch defaults to off', 'off', $options['customer_username']['Default']);
    same(
        'the switch keeps the generated usernames as the default',
        'Generated by the module (default)',
        $options['customer_username']['Options']['off']
    );
    same(
        'the switch offers the customer username',
        'Customer types it in the "Line username" or "Reseller username" custom field',
        $options['customer_username']['Options']['on']
    );
    same(
        'the switch explains the feature',
        'Line and Sub-Reseller products. With "Customer types it", add a custom field to this product named "Line username" (Line) or "Reseller username" (Sub-Reseller), Show on Order Form, Required if you want it mandatory. The module creates the account with that username when it is free on the panel; a username that is already taken fails the provisioning with a clear message. With the field empty, or with this option off, usernames are generated as today. Credit top-up products do not use this option.',
        $options['customer_username']['Description']
    );

    same('the helper reads the product switch', true, xtreamai_customerUsernameEnabled(array('configoption11' => 'on')));
    same('the helper is case insensitive', true, xtreamai_customerUsernameEnabled(array('configoption11' => ' ON ')));
    same(
        'the helper reads the config options map',
        true,
        xtreamai_customerUsernameEnabled(array('configoptions' => array('customer_username' => 'on')))
    );
    same('the helper unwraps an array value', true, xtreamai_customerUsernameEnabled(array('configoption11' => array('on'))));
    same('the helper falls back to off', false, xtreamai_customerUsernameEnabled(array()));
    same('the helper ignores the off value', false, xtreamai_customerUsernameEnabled(array('configoption11' => 'off')));
    same('the helper ignores an unknown value', false, xtreamai_customerUsernameEnabled(array('configoption11' => 'maybe')));

    same(
        'the line username field reads Line username',
        'cliente1',
        xtreamai_lineUsernameField(array('customfields' => array('Line username' => ' cliente1 ')))
    );
    same(
        'the line username field reads Username',
        'cliente2',
        xtreamai_lineUsernameField(array('customfields' => array('Username' => 'cliente2')))
    );
    same(
        'the line username field reads Panel username',
        'cliente3',
        xtreamai_lineUsernameField(array('customfields' => array('Panel username' => 'cliente3')))
    );
    same(
        'the line username field ignores the reseller custom field',
        '',
        xtreamai_lineUsernameField(array('customfields' => array('Reseller username' => 'resA')))
    );
    same('the line username field is empty without custom fields', '', xtreamai_lineUsernameField(array()));
    same(
        'the line username field is empty with a blank value',
        '',
        xtreamai_lineUsernameField(array('customfields' => array('Line username' => '   ')))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'off',
        'customfields' => array('Line username' => 'cliente1'),
    )));
    same('an off switch with a custom field still provisions', 'success', $result);
    same(
        'an off switch generates the username',
        xtreamai_lineUsername(baseParams()),
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][3] : null
    );
    same('an off switch never searches the panel by username', 0, count(apiCalls('lines')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'on',
        'customfields' => array('Line username' => '   '),
    )));
    same('an on switch with an empty field still provisions', 'success', $result);
    same(
        'an on switch with an empty field generates the username',
        xtreamai_lineUsername(baseParams()),
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][3] : null
    );
    same(
        'the generated case keeps the configured password',
        xtreamai_linePassword(baseParams()),
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][4] : null
    );
    same('an empty field never searches the panel', 0, count(apiCalls('lines')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'on',
        'customfields' => array('Line username' => 'no good!'),
    )));
    same(
        'an invalid username fails with the exact message',
        'The username "no good!" is not valid: use 3 to 32 letters, digits, dashes or underscores.',
        $result
    );
    same('an invalid username never creates a line', 0, count(apiCalls('createLine')));
    same('an invalid username is never looked up on the panel', 0, count(apiCalls('lines')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'on',
        'customfields' => array('Line username' => 'ab'),
    )));
    same(
        'a too short username is refused',
        'The username "ab" is not valid: use 3 to 32 letters, digits, dashes or underscores.',
        $result
    );
    same('a too short username never creates a line', 0, count(apiCalls('createLine')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 41, 'username' => 'Cliente1', 'enabled' => true, 'expires_at' => '2027-01-01'),
    );
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'on',
        'customfields' => array('Line username' => 'cliente1'),
    )));
    same(
        'a taken username fails with the exact message',
        'The username "cliente1" is already taken on this panel. Ask the customer to choose another one.',
        $result
    );
    same('a taken username never creates a line', 0, count(apiCalls('createLine')));
    same(
        'the panel is searched with the typed username',
        array(1, 'cliente1'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );
    same('a taken username links nothing', array(), \WhmcsXtreamAI\ServiceStore::$links);

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$createLineResult = array(
        'id' => '900001',
        'username' => 'cliente_1',
        'password' => 'panelPass999',
        'expires_at' => '2027-01-01 00:00:00',
    );
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'on',
        'customfields' => array('Line username' => 'cliente_1'),
    )));
    same('a free username provisions the line', 'success', $result);
    same(
        'the free username is sent to the panel',
        'cliente_1',
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][3] : null
    );
    same('the free username is only searched once', 1, count(apiCalls('lines')));
    same(
        'the created line is linked with the panel credentials',
        array(
            'service_id' => 135,
            'panel_id' => 1,
            'panel_account_id' => '900001',
            'username' => 'cliente_1',
            'package_id' => 76,
        ),
        \WhmcsXtreamAI\ServiceStore::$links ? \WhmcsXtreamAI\ServiceStore::$links[0] : null
    );
    same(
        'the created line stores the panel password on the service',
        'encrypted:panelPass999',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password']
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 42, 'username' => 'cliente_10', 'enabled' => true, 'expires_at' => '2027-01-01'),
    );
    $result = xtreamai_CreateAccount(baseParams(array(
        'configoption11' => 'on',
        'customfields' => array('Line username' => 'cliente_1'),
    )));
    same('a line that only matches partially does not block the username', 'success', $result);
    same(
        'a partial match still creates the typed username',
        'cliente_1',
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][3] : null
    );
    same(
        'a partial match is never adopted as the service line',
        '900001',
        \WhmcsXtreamAI\ServiceStore::$links ? \WhmcsXtreamAI\ServiceStore::$links[0]['panel_account_id'] : null
    );

    section('K. Link existing line');

    $buttons = xtreamai_AdminCustomButtonArray(baseParams());
    same('the link button is offered first', 'Link existing line', array_keys($buttons)[0]);
    same('the link button maps to link_existing', 'link_existing', $buttons['Link existing line']);
    same(
        'a sub-reseller product has no link button',
        array(),
        xtreamai_AdminCustomButtonArray(baseParams(array('configoption5' => 'reseller')))
    );
    same(
        'a credit top-up product has no link button',
        array(),
        xtreamai_AdminCustomButtonArray(baseParams(array('configoption5' => 'topup')))
    );

    linkService();
    resetApi();
    $result = xtreamai_link_existing(baseParams(array('configoption5' => 'reseller')));
    same(
        'a sub-reseller service cannot link a line',
        'Link existing line is only available for Line products.',
        $result
    );
    same('the refused sub-reseller link never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_link_existing(baseParams(array('configoption5' => 'topup')));
    same(
        'a credit top-up service cannot link a line',
        'Link existing line is only available for Line products.',
        $result
    );
    same('the refused top-up link never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    linkService();
    resetApi();
    $result = xtreamai_link_existing(baseParams());
    same('an already linked service is refused', 'This service is already linked to line #987654 (line_user).', $result);
    same('the already linked service never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_link_existing(baseParams(array('username' => '   ')));
    same(
        'an empty service username is refused',
        'Type the panel username in the Username field of this service, save, then press Link existing line.',
        $result
    );
    same('the empty username never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 41, 'username' => 'otra_linea', 'enabled' => true, 'expires_at' => '2027-01-01'),
    );
    $result = xtreamai_link_existing(baseParams(array('username' => 'cliente1')));
    same(
        'a username that is not on the panel is refused',
        'No line with username "cliente1" was found on this panel.',
        $result
    );
    same(
        'an unmatched link is searched on the panel',
        array(1, 'cliente1'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );
    same('an unmatched link stores nothing', array(), \WhmcsXtreamAI\ServiceStore::$links);
    same('an unmatched link records no action', array(), \WhmcsXtreamAI\ServiceStore::$actions);
    same('an unmatched link writes nothing on the panel', 0, panelWrites());

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WHMCS\Database\Capsule::$rows['tblhosting'][0] = array(
        'id' => 135,
        'username' => 'cliente1',
        'password' => 'encrypted:oldPass',
        'nextduedate' => '2026-10-01',
    );
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array(
            'id' => 41,
            'username' => 'Cliente1',
            'enabled' => true,
            'expires_at' => '2027-03-05',
            'password' => 'panelPass123',
        ),
    );
    $result = xtreamai_link_existing(baseParams(array('username' => 'cliente1', 'status' => 'Active')));
    same('an existing line is linked', 'success', $result);
    same(
        'the link stores the line id, the username and the package',
        array(
            'service_id' => 135,
            'panel_id' => 1,
            'panel_account_id' => '41',
            'username' => 'Cliente1',
            'package_id' => 76,
        ),
        \WhmcsXtreamAI\ServiceStore::$links ? \WhmcsXtreamAI\ServiceStore::$links[0] : null
    );
    same(
        'the linked row carries the panel expiry',
        '2027-03-05',
        isset(\WhmcsXtreamAI\ServiceStore::$rows[135]['expires_at']) ? \WhmcsXtreamAI\ServiceStore::$rows[135]['expires_at'] : null
    );
    same(
        'the linked row carries the panel state',
        'Active',
        isset(\WhmcsXtreamAI\ServiceStore::$rows[135]['status']) ? \WhmcsXtreamAI\ServiceStore::$rows[135]['status'] : null
    );
    same(
        'the service username follows the panel line',
        'Cliente1',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['username']
    );
    same(
        'the panel password is stored on the service',
        'encrypted:panelPass123',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password']
    );
    same(
        'the WHMCS next due date is left untouched',
        '2026-10-01',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['nextduedate']
    );
    same(
        'the link is recorded in the action log',
        'Linked to existing line #41 (Cliente1), expires 05/03/2027',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );
    same('the link searches the panel once', 1, count(apiCalls('lines')));
    same('the link writes nothing on the panel', 0, panelWrites());

    resetApi();
    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '41',
        'username' => 'Cliente1',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1800000000,
        'expires_at' => '2027-03-05',
    );
    $fields = xtreamai_AdminServicesTabFields(baseParams(array('username' => 'Cliente1')));
    same('the tab shows the linked line id', '41', isset($fields['Panel line ID']) ? $fields['Panel line ID'] : null);
    same('the tab shows the linked username', 'Cliente1', isset($fields['Panel username']) ? $fields['Panel username'] : null);
    same('the tab shows the linked status', 'Active', isset($fields['Line status']) ? $fields['Line status'] : null);
    same('the tab shows the linked panel expiry', '05/03/2027', isset($fields['Panel expiry']) ? $fields['Panel expiry'] : null);
    same('the linked line is read from the panel once', 1, count(apiCalls('getLine')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 42, 'username' => 'cliente2', 'enabled' => true, 'expires_at' => '2027-03-05'),
    );
    $result = xtreamai_link_existing(baseParams(array('username' => 'cliente2', 'status' => 'Suspended')));
    same('a suspended service is linked', 'success', $result);
    same(
        'a suspended service stays suspended after the link',
        'Suspended',
        isset(\WhmcsXtreamAI\ServiceStore::$rows[135]['status']) ? \WhmcsXtreamAI\ServiceStore::$rows[135]['status'] : null
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password'] = 'encrypted:oldPass';
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 43, 'username' => 'cliente3', 'enabled' => true, 'expires_at' => '2027-04-01'),
    );
    $result = xtreamai_link_existing(baseParams(array('username' => 'cliente3')));
    same('a line without a password in the response still links', 'success', $result);
    same(
        'the stored password is left alone when the panel sends none',
        'encrypted:oldPass',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password']
    );
    same(
        'the link without a password still records the expiry',
        'Linked to existing line #43 (cliente3), expires 01/04/2027',
        \WhmcsXtreamAI\ServiceStore::$actions ? (string) \WhmcsXtreamAI\ServiceStore::$actions[0]['action'] : null
    );

    section('L. Checkout validation');

    \WHMCS\Database\Capsule::$rows['tblproducts'] = array(
        array('id' => 5001, 'servertype' => 'cpanel', 'configoption1' => '1', 'configoption5' => 'line', 'configoption10' => 'any', 'configoption11' => 'on'),
        array('id' => 5002, 'servertype' => 'xtreamai', 'configoption1' => '1', 'configoption5' => 'topup', 'configoption10' => 'any', 'configoption11' => 'off'),
        array('id' => 5003, 'servertype' => 'xtreamai', 'configoption1' => '1', 'configoption5' => 'topup', 'configoption10' => ' Linked ', 'configoption11' => 'off'),
        array('id' => 5004, 'servertype' => 'xtreamai', 'configoption1' => '1', 'configoption5' => 'line', 'configoption10' => 'any', 'configoption11' => 'off'),
        array('id' => 5005, 'servertype' => 'xtreamai', 'configoption1' => '1', 'configoption5' => 'line', 'configoption10' => 'any', 'configoption11' => ' on '),
        array('id' => 5006, 'servertype' => 'xtreamai', 'configoption1' => '0', 'configoption5' => 'line', 'configoption10' => 'any', 'configoption11' => 'on'),
        array('id' => 5007, 'servertype' => 'xtreamai', 'configoption1' => '1', 'configoption5' => 'reseller', 'configoption10' => 'any', 'configoption11' => 'on'),
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'] = array(
        array('id' => 9001, 'type' => 'product', 'relid' => 5002, 'fieldname' => 'Reseller username|Reseller account', 'fieldtype' => 'text'),
        array('id' => 9002, 'type' => 'product', 'relid' => 5003, 'fieldname' => 'Reseller username', 'fieldtype' => 'text'),
        array('id' => 9003, 'type' => 'product', 'relid' => 5004, 'fieldname' => 'Line username', 'fieldtype' => 'text'),
        array('id' => 9004, 'type' => 'product', 'relid' => 5005, 'fieldname' => 'Line username|Username', 'fieldtype' => 'text'),
        array('id' => 9005, 'type' => 'product', 'relid' => 5002, 'fieldname' => 'Credits', 'fieldtype' => 'text'),
        array('id' => 9006, 'type' => 'product', 'relid' => 5001, 'fieldname' => 'Panel username', 'fieldtype' => 'text'),
        array('id' => 9007, 'type' => 'product', 'relid' => 5006, 'fieldname' => 'Line username', 'fieldtype' => 'text'),
        array('id' => 9008, 'type' => 'product', 'relid' => 5005, 'fieldname' => 'Panel username', 'fieldtype' => 'text'),
    );

    $checkout = 'WhmcsXtreamAI\\CheckoutValidator';

    resetApi();
    same('another server type is ignored', array(), $checkout::validateProduct(5001, array(9006 => 'mel'), 44));
    same('an unknown product is ignored', array(), $checkout::validateProduct(5999, array(), 44));
    same('an empty product id is ignored', array(), $checkout::validateProduct(0, array(), 44));
    same('a sub-reseller product without a username field is ignored', array(), $checkout::validateProduct(5007, array(), 44));
    same('a top-up without a username field is ignored', array(), $checkout::validateProduct(5002, array(), 44));
    same('a top-up with an unrelated field is ignored', array(), $checkout::validateProduct(5002, array(9005 => 'mel'), 44));
    same('a top-up with an empty username is ignored', array(), $checkout::validateProduct(5002, array(9001 => '   '), 44));
    same('an ignored product never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 51, 'username' => 'no_panel_user', 'enabled' => true),
    );
    same('a product without a panel id is ignored', array(), $checkout::validateProduct(5006, array(9007 => 'no_panel_user'), 44));
    same('the product without a panel id never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'an unknown top-up reseller is refused with the typed name',
        array('The reseller username "ghost_reseller" was not found. Check the spelling and try again.'),
        $checkout::validateProduct(5002, array(9001 => '  ghost_reseller  '), 44)
    );
    same(
        'the unknown reseller is looked up on the panel',
        array(1, 'ghost_reseller'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '555', 'username' => 'mel_any', 'member_group_id' => 2);
    same('a top-up to an existing reseller passes', array(), $checkout::validateProduct(5002, array(9001 => 'mel_any'), 44));
    same('the existing reseller is looked up once', 1, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '556', 'username' => 'admin_group', 'member_group_id' => 1);
    same(
        'a reseller in the administrator group is refused',
        array('The reseller username "admin_group" cannot receive a top-up.'),
        $checkout::validateProduct(5002, array(9001 => 'admin_group'), 44)
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '260595', 'username' => 'owner_res', 'member_group_id' => 2);
    same(
        'the panel owner account is refused',
        array('The reseller username "owner_res" cannot receive a top-up.'),
        $checkout::validateProduct(5002, array(9001 => 'owner_res'), 44)
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same('a top-up on a reseller key is left to provisioning', array(), $checkout::validateProduct(5002, array(9001 => 'reskey_user'), 44));
    same('a reseller key never looks a reseller up', 0, count(apiCalls('findResellerByUsername')));

    resetApi();
    same('a linked top-up without a client session is left to provisioning', array(), $checkout::validateProduct(5003, array(9002 => 'linked_user'), 0));
    same('no sub-reseller list is read without a client session', array(), \WhmcsXtreamAI\ServiceStore::$resellerLookups);

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'Linked_User'),
    );
    same('a linked top-up accepts a sub-reseller of the client', array(), $checkout::validateProduct(5003, array(9002 => 'Linked_User'), 44));
    same(
        'the sub-reseller list is read for the session client',
        array(array('user_id' => 44, 'panel_id' => 1)),
        \WhmcsXtreamAI\ServiceStore::$resellerLookups
    );
    same('a linked top-up never looks a reseller up on the panel', 0, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'other_res'),
    );
    same(
        'a linked top-up refuses a username that is not linked',
        array('The reseller username "loose_user" does not match any of your Sub-Reseller accounts.'),
        $checkout::validateProduct(5003, array(9002 => 'loose_user'), 44)
    );
    same('a refused linked top-up never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    same('a line without customer usernames is ignored', array(), $checkout::validateProduct(5004, array(9003 => 'Any Name!'), 44));
    same('the line without customer usernames never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    same(
        'a short username is refused',
        array('The username "Ab" is not valid: use 3 to 32 letters, digits, dashes or underscores.'),
        $checkout::validateProduct(5005, array(9004 => 'Ab'), 44)
    );

    resetApi();
    same(
        'an invalid username is refused with the markup stripped',
        array('The username "scriptalert(1)/script" is not valid: use 3 to 32 letters, digits, dashes or underscores.'),
        $checkout::validateProduct(5005, array(9004 => '<script>alert(1)</script>'), 44)
    );
    same('the invalid username is never looked up on the panel', 0, count(apiCalls('lines')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 42, 'username' => 'Taken_Name', 'enabled' => true),
    );
    same(
        'a taken username is refused',
        array('The username "taken_name" is already taken. Choose another one.'),
        $checkout::validateProduct(5005, array(9004 => 'taken_name'), 44)
    );
    same(
        'the taken username is looked up on the panel with the typed value',
        array(1, 'taken_name'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same('a free username passes', array(), $checkout::validateProduct(5005, array(9004 => 'free_name'), 44));
    same('the free username is looked up once', 1, count(apiCalls('lines')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same('the panel username field is accepted for a line', array(), $checkout::validateProduct(5005, array(9008 => 'panel_alias'), 44));
    same(
        'the panel username field is checked on the panel',
        array(1, 'panel_alias'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesError = 'Panel unreachable';
    same('a panel failure never blocks the checkout', array(), $checkout::validateProduct(5005, array(9004 => 'boom_name'), 44));
    ok('the panel failure is written to the module log', in_array('checkout_validate:Panel unreachable', loggedActions(), true));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerError = 'Panel timeout';
    same('a top-up panel failure never blocks the checkout', array(), $checkout::validateProduct(5002, array(9001 => 'timeout_res'), 44));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $first = $checkout::validateProduct(5005, array(9004 => 'memo_name'), 44);
    $callsAfterFirst = count(\WhmcsXtreamAI\PanelApi::$calls);
    $second = $checkout::validateProduct(5005, array(9004 => 'memo_name'), 44);
    same('the first call of a repeated username asks the panel', 1, count(apiCalls('lines')));
    same('the first call of a repeated username returns no error', array(), $first);
    same('the second call of a repeated username returns no error', array(), $second);
    same('the second call of a repeated username is served from memory', $callsAfterFirst, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '557', 'username' => 'memo_res', 'member_group_id' => 2);
    $checkout::validateProduct(5002, array(9001 => 'memo_res'), 44);
    $callsAfterTopUp = count(\WhmcsXtreamAI\PanelApi::$calls);
    $checkout::validateProduct(5002, array(9001 => 'memo_res'), 44);
    same('a repeated top-up reseller is served from memory', $callsAfterTopUp, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    $productRows = \WHMCS\Database\Capsule::$rows['tblproducts'];
    \WHMCS\Database\Capsule::$rows['tblproducts'] = (static function () {
        throw new \RuntimeException('Products table unavailable');
        yield;
    })();
    same('a database failure never blocks the checkout', array(), $checkout::validateProduct(5005, array(9004 => 'db_boom'), 44));
    ok(
        'the database failure is written to the module log',
        in_array('checkout_validate:Products table unavailable', loggedActions(), true)
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'] = $productRows;

    $GLOBALS['hooks'] = array();

    function add_hook($name, $priority, $callable)
    {
        $GLOBALS['hooks'][(string) $name] = $callable;
    }

    require __DIR__ . '/../modules/addons/xtreamai/hooks.php';

    ok('the cart update hook is registered', isset($GLOBALS['hooks']['ShoppingCartValidateProductUpdate']));
    ok('the checkout hook is registered', isset($GLOBALS['hooks']['ShoppingCartValidateCheckout']));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 61, 'username' => 'hook_taken', 'enabled' => true),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'i' => 0,
        'customfield' => array(9004 => 'hook_taken'),
        'billingcycle' => 'monthly',
    ));
    same(
        'the cart update hook returns the validator errors',
        array('The username "hook_taken" is already taken. Choose another one.'),
        $errors
    );

    resetApi();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'i' => 0,
        'customfield' => array(9004 => 'free_name'),
    ));
    same('the cart update hook returns nothing when the validator passes', array(), $errors);

    resetApi();
    \WHMCSXtreamAI\PanelApi::$findResellerResult = array('id' => '777', 'username' => 'hook_res', 'member_group_id' => 3);
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5002,
        'customfield' => array(9001 => 'hook_res'),
    ));
    same('the cart update hook accepts a valid top-up reseller', array(), $errors);
    same(
        'the cart update hook passes the typed reseller to the panel API',
        array(1, 'hook_res'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'hook_linked'),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5003,
        'customfield' => array(9002 => 'hook_loose'),
    ));
    same(
        'the cart update hook reads the client from the session',
        array('The reseller username "hook_loose" does not match any of your Sub-Reseller accounts.'),
        $errors
    );

    resetApi();
    unset($_SESSION['uid']);
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5003,
        'customfield' => array(9002 => 'hook_guest'),
    ));
    same('the cart update hook leaves a linked top-up alone for a guest', array(), $errors);

    resetApi();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array('pid' => 5005));
    same('a cart update without custom fields returns nothing', array(), $errors);

    resetApi();
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                0 => array('pid' => 5004),
                1 => array('pid' => 5005, 'customfields' => array(9004 => 'session_index_user')),
            ),
        ),
    );
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'i' => 1,
        'customfield' => array(9004 => 'session_index_user'),
    ));
    same('a cart update without a pid resolves the product from the cart index', array(), $errors);
    same(
        'the product resolved from the cart index is checked on the panel',
        array(1, 'session_index_user'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );

    resetApi();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'customfield' => array(9004 => 'no_pid_user'),
    ));
    same('a cart update without a pid and without an index returns nothing', array(), $errors);
    same('a cart update without a pid never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'i' => 9,
        'customfield' => array(9004 => 'missing_index_user'),
    ));
    same('a cart update with an index outside the cart returns nothing', array(), $errors);
    same('a cart index outside the cart never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    $_SESSION = array('cart' => array('products' => array()));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('firstname' => 'Test'));
    same('an empty cart returns no errors', array(), $errors);

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 62, 'username' => 'Taken_In_Cart', 'enabled' => true),
    );
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5005, 'billingcycle' => 'monthly', 'customfields' => array(9004 => 'taken_in_cart')),
                array('pid' => 5002, 'billingcycle' => 'monthly', 'customfields' => array(9001 => 'ghost_in_cart')),
                array('pid' => 5001, 'billingcycle' => 'monthly', 'customfields' => array(9006 => 'ignored_cart_user')),
                array('pid' => 5004, 'billingcycle' => 'monthly'),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('firstname' => 'Test'));
    same(
        'the checkout hook collects one error per cart product',
        array(
            'The username "taken_in_cart" is already taken. Choose another one.',
            'The reseller username "ghost_in_cart" was not found. Check the spelling and try again.',
        ),
        $errors
    );
    same('the checkout hook looks at each cart line once', 1, count(apiCalls('lines')));
    same('the checkout hook looks the cart reseller up once', 1, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    $_SESSION['cart']['products'] = array(
        array('pid' => 5002, 'customfields' => array(9001 => 'repeat_in_cart')),
        array('pid' => 5002, 'customfields' => array(9001 => 'repeat_in_cart')),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array());
    same(
        'a repeated cart error is reported once',
        array('The reseller username "repeat_in_cart" was not found. Check the spelling and try again.'),
        $errors
    );
    same('a repeated cart product is served from memory', 1, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'Cart_Linked_User'),
    );
    $_SESSION = array(
        'cart' => array(
            'products' => array(
                array('pid' => 5003, 'customfields' => array(9002 => 'cart_linked_user')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array(
        'clientId' => 44,
        'customfield' => array(9002 => 'client_field_user'),
    ));
    same('the checkout hook accepts a linked top-up from the client in the form', array(), $errors);
    same(
        'the checkout hook reads the client id from the form',
        array(array('user_id' => 44, 'panel_id' => 1)),
        \WhmcsXtreamAI\ServiceStore::$resellerLookups
    );
    same('the checkout hook ignores the client custom fields of the form', 0, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'other_res'),
    );
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5003, 'customfields' => array(9002 => 'cart_uid_loose')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 0));
    same(
        'the checkout hook falls back to the session client when the form sends none',
        array('The reseller username "cart_uid_loose" does not match any of your Sub-Reseller accounts.'),
        $errors
    );
    same(
        'the checkout fallback reads the sub-reseller list for the session client',
        array(array('user_id' => 44, 'panel_id' => 1)),
        \WhmcsXtreamAI\ServiceStore::$resellerLookups
    );

    resetApi();
    unset($_SESSION['uid']);
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array();
    $_SESSION['cart']['products'] = array(
        array('pid' => 5003, 'customfields' => array(9002 => 'cart_guest_user')),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 0));
    same('the checkout hook leaves a linked top-up alone without a client', array(), $errors);
    same('a checkout without a client reads no sub-reseller list', array(), \WhmcsXtreamAI\ServiceStore::$resellerLookups);

    section('M. Checkout diagnostic logging');

    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5008,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'topup',
        'configoption10' => 'any',
        'configoption11' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9009,
        'type' => 'product',
        'relid' => 5008,
        'fieldname' => 'Reseller',
        'fieldtype' => 'text',
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'a top-up field named only Reseller falls back to the single product field',
        array('The reseller username "ghost_reseller" was not found. Check the spelling and try again.'),
        $checkout::validateProduct(5008, array(9009 => 'ghost_reseller'), 44)
    );
    same('the single product field writes exactly one log', 1, count(checkoutLogs()));
    same('the single product field logs the top-up check', array('topup_admin_check'), logDecisions());
    same(
        'the single product field answers with the decision and the error count',
        'decision=topup_admin_check errors=1',
        logResponse(checkoutLogs()[0])
    );
    same('the single product field names the fallback key', '*single*', (string) logField('matched_field_key'));
    same('the single product field logs the typed value length', strlen('ghost_reseller'), (int) logField('matched_field_value_len'));
    same('the single product field logs one error', 1, (int) logField('errors_count'));
    same(
        'the log lists the custom fields the product carries',
        array(array('name' => 'Reseller', 'keys' => array('reseller'))),
        logField('field_names_on_product')
    );
    same(
        'the single product field is looked up on the panel',
        array(1, 'ghost_reseller'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same('the log carries the product id', 5008, (int) logField('product_id'));
    same('the log carries the panel id', 1, (int) logField('panel_id'));
    same('the log carries the account type', 'topup', (string) logField('account_type'));
    same('the log carries the top-up scope', 'any', (string) logField('topup_scope'));
    same('the log carries the panel key type', 'admin', (string) logField('key_type'));
    same('the log carries the client id', 44, (int) logField('client_id'));
    same('the log carries the field ids the cart sent', array(9009), logField('input_field_ids'));
    same('the log never writes the typed username', false, strpos((string) checkoutLogs()[0][2], 'ghost_reseller'));
    same(
        'a top-up log carries no line flag',
        false,
        array_key_exists('customer_username_enabled', logRequest(checkoutLogs()[0]))
    );

    resetApi();
    same('an empty product id is still ignored', array(), $checkout::validateProduct(0, array(), 44));
    same('an empty product id logs one entry', array('no_pid'), logDecisions());

    resetApi();
    same('an unknown product is still ignored', array(), $checkout::validateProduct(5999, array(), 44));
    same('an unknown product logs one entry', array('no_product'), logDecisions());

    resetApi();
    same('another server type is still ignored', array(), $checkout::validateProduct(5001, array(9006 => 'mel'), 44));
    same('another server type logs one entry', array('not_xtreamai'), logDecisions());
    same('the log of an ignored server type carries the server type', 'cpanel', (string) logField('servertype'));

    resetApi();
    same('a sub-reseller product is still ignored without a field', array(), $checkout::validateProduct(5007, array(), 44));
    same('a sub-reseller product logs one entry', array('reseller_no_field'), logDecisions());
    same('the sub-reseller log flags customer usernames', 'on', (string) logField('customer_username_enabled'));

    resetApi();
    same(
        'a product without a panel id is still ignored',
        array(),
        $checkout::validateProduct(5006, array(9007 => 'no_panel_user'), 44)
    );
    same('a product without a panel id logs one entry', array('no_panel'), logDecisions());
    same('the log of a product without a panel carries no panel id', 0, (int) logField('panel_id'));
    same('the log of a product without a panel carries no field names', array(), logField('field_names_on_product'));

    resetApi();
    same(
        'a line without customer usernames is still ignored',
        array(),
        $checkout::validateProduct(5004, array(9003 => 'Any Name!'), 44)
    );
    same('a line without customer usernames logs one entry', array('line_disabled'), logDecisions());
    same('the disabled line log carries the raw flag', 'off', (string) logField('customer_username_enabled'));

    resetApi();
    same('a line with an empty username is still ignored', array(), $checkout::validateProduct(5005, array(9008 => '   '), 44));
    same('a line with an empty username logs one entry', array('line_no_field'), logDecisions());
    same('a line without a username never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same('a free username still passes', array(), $checkout::validateProduct(5005, array(9004 => 'diag_free_name'), 44));
    same('a passed line check logs one entry', array('line_check'), logDecisions());
    same('the passed line check names the matched field key', 'line_username', (string) logField('matched_field_key'));
    same('the passed line check logs the typed value length', strlen('diag_free_name'), (int) logField('matched_field_value_len'));
    same('the passed line check logs the error count', 0, (int) logField('errors_count'));
    same('the passed line check flags customer usernames', 'on', (string) logField('customer_username_enabled'));
    same(
        'a line log carries no top-up scope',
        false,
        array_key_exists('topup_scope', logRequest(checkoutLogs()[0]))
    );
    same('the passed line check asks the panel once', 1, count(apiCalls('lines')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 71, 'username' => 'Diag_Taken', 'enabled' => true),
    );
    same(
        'a taken username is still refused',
        array('The username "diag_taken" is already taken. Choose another one.'),
        $checkout::validateProduct(5005, array(9004 => 'diag_taken'), 44)
    );
    same('a refused line check logs one entry', array('line_check'), logDecisions());
    same('a refused line check logs the error count', 1, (int) logField('errors_count'));

    resetApi();
    same(
        'a panel username field is still accepted for a line',
        array(),
        $checkout::validateProduct(5005, array(9008 => 'diag_panel_alias'), 44)
    );
    same('the panel username alias logs the alias as the matched field key', 'panel_username', (string) logField('matched_field_key'));

    resetApi();
    same('a top-up without a username field is still ignored', array(), $checkout::validateProduct(5002, array(9005 => 'mel'), 44));
    same('a top-up without a username field logs one entry', array('topup_no_field'), logDecisions());
    same('the top-up without a username never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'a top-up on a reseller key is still left to provisioning',
        array(),
        $checkout::validateProduct(5002, array(9001 => 'diag_reskey'), 44)
    );
    same('a top-up on a reseller key logs one entry', array('topup_reseller_key'), logDecisions());
    same('the reseller key log marks the key type', 'reseller', (string) logField('key_type'));
    same('the reseller key log names the matched field key', 'reseller_username', (string) logField('matched_field_key'));
    same('a reseller key still never looks a reseller up', 0, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'an unknown top-up reseller is still refused',
        array('The reseller username "diag_ghost" was not found. Check the spelling and try again.'),
        $checkout::validateProduct(5002, array(9001 => 'diag_ghost'), 44)
    );
    same('a refused top-up check logs one entry', array('topup_admin_check'), logDecisions());
    same('a refused top-up check logs the error count', 1, (int) logField('errors_count'));
    same('a refused top-up check logs the typed value length', strlen('diag_ghost'), (int) logField('matched_field_value_len'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array('id' => '558', 'username' => 'Diag_Ok', 'member_group_id' => 2);
    same('a top-up to an existing reseller still passes', array(), $checkout::validateProduct(5002, array(9001 => 'diag_ok'), 44));
    same('a passed top-up check logs one entry', array('topup_admin_check'), logDecisions());
    same('a passed top-up check logs no error', 0, (int) logField('errors_count'));
    same(
        'the top-up log lists the product fields',
        array(
            array('name' => 'Reseller username|Reseller account', 'keys' => array('reseller_username', 'reseller_account')),
            array('name' => 'Credits', 'keys' => array('credits')),
        ),
        logField('field_names_on_product')
    );

    resetApi();
    same(
        'a linked top-up without a client session is still ignored',
        array(),
        $checkout::validateProduct(5003, array(9002 => 'diag_linked'), 0)
    );
    same('a linked top-up without a client logs one entry', array('topup_linked_no_client'), logDecisions());
    same('the linked log marks the scope', 'linked', (string) logField('topup_scope'));
    same('the linked log carries the unsigned client', 0, (int) logField('client_id'));

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'Diag_Linked_Ok'),
    );
    same(
        'a linked top-up of the client still passes',
        array(),
        $checkout::validateProduct(5003, array(9002 => 'diag_linked_ok'), 44)
    );
    same('a passed linked top-up logs one entry', array('topup_linked_check'), logDecisions());
    same('a passed linked top-up never looks a reseller up on the panel', 0, count(apiCalls('findResellerByUsername')));

    resetApi();
    \WhmcsXtreamAI\ServiceStore::$clientResellers = array(
        array('service_id' => 135, 'reseller_id' => '41', 'username' => 'other_diag'),
    );
    same(
        'a linked top-up of another reseller is still refused',
        array('The reseller username "diag_loose" does not match any of your Sub-Reseller accounts.'),
        $checkout::validateProduct(5003, array(9002 => 'diag_loose'), 44)
    );
    same('a refused linked top-up logs one entry', array('topup_linked_check'), logDecisions());
    same('a refused linked top-up logs the error count', 1, (int) logField('errors_count'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $checkout::validateProduct(5005, array(9004 => 'diag_memo_name'), 44);
    $checkout::validateProduct(5005, array(9004 => 'diag_memo_name'), 44);
    same('a repeated username still asks the panel once', 1, count(apiCalls('lines')));
    same('a repeated username logs one entry per fire', array('line_check', 'memo_line'), logDecisions());
    $request = logRequest(checkoutLogs()[1]);
    same('a memo entry still names the matched field key', 'line_username', (string) ($request['matched_field_key'] ?? ''));
    same(
        'a memo entry still carries the typed value length',
        strlen('diag_memo_name'),
        (int) ($request['matched_field_value_len'] ?? -1)
    );
    same('a memo entry carries no error count', 0, (int) ($request['errors_count'] ?? -1));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesError = 'Panel unreachable from the cart';
    same(
        'a panel failure still never blocks the checkout',
        array(),
        $checkout::validateProduct(5005, array(9004 => 'diag_boom'), 44)
    );
    same('a panel failure writes exactly one entry', array('checkout_validate:Panel unreachable from the cart'), loggedActions());

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerError = 'Panel timeout in the cart';
    same(
        'a top-up panel failure still never blocks the checkout',
        array(),
        $checkout::validateProduct(5002, array(9001 => 'diag_timeout'), 44)
    );
    same('a top-up panel failure writes exactly one entry', array('checkout_validate:Panel timeout in the cart'), loggedActions());

    resetApi();
    $productRows = \WHMCS\Database\Capsule::$rows['tblproducts'];
    \WHMCS\Database\Capsule::$rows['tblproducts'] = (static function () {
        throw new \RuntimeException('Products table unavailable again');
        yield;
    })();
    same('a database failure still never blocks the checkout', array(), $checkout::validateProduct(5005, array(9004 => 'diag_db'), 44));
    same('a database failure writes exactly one entry', array('checkout_validate:Products table unavailable again'), loggedActions());
    \WHMCS\Database\Capsule::$rows['tblproducts'] = $productRows;

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $GLOBALS['moduleLogThrows'] = true;
    same(
        'a broken module log still never blocks the checkout',
        array(),
        $checkout::validateProduct(5005, array(9004 => 'diag_log_boom'), 44)
    );
    same('a broken module log writes nothing', 0, count(checkoutLogs()));
    unset($GLOBALS['moduleLogThrows']);

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesError = 'Panel unreachable again';
    $GLOBALS['moduleLogThrows'] = true;
    same(
        'a broken module log never blocks a failed panel check',
        array(),
        $checkout::validateProduct(5005, array(9004 => 'diag_log_boom_2'), 44)
    );
    same('a broken module log writes nothing for a failed check', array(), loggedActions());
    unset($GLOBALS['moduleLogThrows']);

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 72, 'username' => 'Diag_Hook_Taken', 'enabled' => true),
    );
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5005, 'customfields' => array(9004 => 'diag_hook_taken')),
                array('pid' => 5008, 'customfields' => array(9009 => 'diag_hook_reseller')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 44));
    same(
        'the checkout hook reports the line error and the single field top-up error',
        array(
            'The username "diag_hook_taken" is already taken. Choose another one.',
            'The reseller username "diag_hook_reseller" was not found. Check the spelling and try again.',
        ),
        $errors
    );
    same('the checkout hook logs one entry per cart product', array('line_check', 'topup_admin_check'), logDecisions());

    resetApi();
    $_SESSION = array('uid' => 44);
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'customfield' => array(9004 => 'diag_hook_free'),
    ));
    same('the cart update hook still returns nothing when the validator passes', array(), $errors);
    same('one cart update writes exactly one log', array('line_check'), logDecisions());

    resetApi();
    $manyFields = array();
    for ($index = 0; $index < 25; $index++) {
        $manyFields[9100 + $index] = 'secret_value_' . $index;
    }
    same('a top-up with unrelated fields is still ignored', array(), $checkout::validateProduct(5002, $manyFields, 44));
    same('the log keeps the field id list short', 20, count((array) logField('input_field_ids')));
    same('the log keeps the first field ids', array(9100, 9101), array_slice((array) logField('input_field_ids'), 0, 2));
    same('the log never writes a typed value', false, strpos((string) checkoutLogs()[0][2], 'secret_value_'));

    section('N. Cart hook logging');

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'i' => 0,
        'customfield' => array(9004 => 'hook_log_name'),
        'billingcycle' => 'monthly',
    ));
    same('a cart update with a pid still returns no errors', array(), $errors);
    same('the cart update hook line comes before the validator line', array('checkout_hook', 'checkout_validate'), logActions());
    same('a cart update writes exactly one hook line', 1, count(checkoutHookLogs()));
    same('a cart update writes exactly one validator line', 1, count(checkoutLogs()));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the hook line names the cart update hook', 'product_update', (string) ($request['hook'] ?? ''));
    same('the hook line lists the keys of the form', array('pid', 'i', 'customfield', 'billingcycle'), $request['vars_keys'] ?? null);
    same('the hook line carries the pid of the form', 5005, (int) ($request['vars_pid'] ?? 0));
    same('the hook line carries the cart index', '0', (string) ($request['vars_i'] ?? ''));
    same('the hook line carries the session client', 44, (int) ($request['session_uid'] ?? 0));
    same('the hook line carries the field ids of the form', array(9004), $request['customfield_ids_in_vars'] ?? null);
    same('the hook line resolves the pid of the form', 5005, (int) ($request['resolved_pid'] ?? 0));
    same('the hook line flags the validator call', true, (bool) ($request['validator_called'] ?? false));
    same('a cart update hook line carries no form client', false, array_key_exists('vars_client_id', $request));
    same('a cart update hook line carries no product counter', false, array_key_exists('products_validated', $request));
    same(
        'the hook line summarises the cart update',
        'hook=product_update resolved_pid=5005 validator_called=1',
        logResponse(checkoutHookLogs()[0])
    );

    resetApi();
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                0 => array('pid' => 5004, 'customfields' => array(9003 => 'hook_cart_first')),
                1 => array('pid' => 5005, 'customfields' => array(9004 => 'hook_index_name')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'i' => 1,
        'customfield' => array(9004 => 'hook_index_name'),
    ));
    same('a cart update without a pid still resolves the cart index', array(), $errors);
    same('a cart index update writes the hook line first', array('checkout_hook', 'checkout_validate'), logActions());
    $request = logRequest(checkoutHookLogs()[0]);
    same('the hook line carries no pid from the form', 0, (int) ($request['vars_pid'] ?? -1));
    same('the hook line resolves the pid from the cart index', 5005, (int) ($request['resolved_pid'] ?? 0));
    same('the hook line of a cart index flags the validator call', true, (bool) ($request['validator_called'] ?? false));
    same('the hook line lists the cart products', array(
        array('index' => 0, 'pid' => 5004, 'customfield_ids' => array(9003)),
        array('index' => 1, 'pid' => 5005, 'customfield_ids' => array(9004)),
    ), $request['cart_products'] ?? null);

    resetApi();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'customfield' => array(9004 => 'hook_no_pid_name'),
    ));
    same('a cart update without a pid and index still returns nothing', array(), $errors);
    same('a cart update without a pid writes the hook line only', array('checkout_hook'), logActions());
    same('a cart update without a pid writes no validator line', 0, count(checkoutLogs()));
    same('a cart update without a pid never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the hook line of an unresolved cart update carries no pid', 0, (int) ($request['resolved_pid'] ?? -1));
    same('the hook line of an unresolved cart update flags no validator call', false, (bool) ($request['validator_called'] ?? true));
    same(
        'the hook line of an unresolved cart update summarises the skip',
        'hook=product_update resolved_pid=0 validator_called=0',
        logResponse(checkoutHookLogs()[0])
    );

    resetApi();
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5004, 'customfields' => array(9003 => 'hook_checkout_one')),
                array('pid' => 5005, 'customfields' => array(9004 => 'hook_checkout_two')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array(
        'clientId' => 44,
        'firstname' => 'Test',
    ));
    same('the checkout hook still returns no errors', array(), $errors);
    same(
        'the checkout hook writes the hook line before the validator lines',
        array('checkout_hook', 'checkout_validate', 'checkout_validate'),
        logActions()
    );
    $request = logRequest(checkoutHookLogs()[0]);
    same('the hook line names the checkout hook', 'checkout', (string) ($request['hook'] ?? ''));
    same('the checkout hook line counts the validated products', 2, (int) ($request['products_validated'] ?? -1));
    same('the checkout hook line flags the validator calls', true, (bool) ($request['validator_called'] ?? false));
    same('the checkout hook line carries the client of the form', 44, (int) ($request['vars_client_id'] ?? 0));
    same('the checkout hook line carries the session client', 44, (int) ($request['session_uid'] ?? 0));
    same('the checkout hook line carries no pid from the form', 0, (int) ($request['vars_pid'] ?? -1));
    same('the checkout hook line lists both cart products', array(
        array('index' => 0, 'pid' => 5004, 'customfield_ids' => array(9003)),
        array('index' => 1, 'pid' => 5005, 'customfield_ids' => array(9004)),
    ), $request['cart_products'] ?? null);
    same(
        'the checkout hook line summarises the run',
        'hook=checkout resolved_pid=0 validator_called=1',
        logResponse(checkoutHookLogs()[0])
    );

    resetApi();
    $_SESSION = array('uid' => 44);
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 0));
    same('a checkout without a cart still returns no errors', array(), $errors);
    same('a checkout without a cart writes the hook line only', array('checkout_hook'), logActions());
    $request = logRequest(checkoutHookLogs()[0]);
    same('the hook line of a missing cart carries no product list', null, array_key_exists('cart_products', $request) ? $request['cart_products'] : false);
    same('the hook line of a missing cart flags no validator call', false, (bool) ($request['validator_called'] ?? true));
    same('the hook line of a missing cart counts no product', 0, (int) ($request['products_validated'] ?? -1));
    same(
        'the hook line of a missing cart summarises the skip',
        'hook=checkout resolved_pid=0 validator_called=0',
        logResponse(checkoutHookLogs()[0])
    );

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => 'not-a-list'));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 0));
    same('a checkout with a broken cart list still returns no errors', array(), $errors);
    same('a checkout with a broken cart list writes one hook line', 1, count(checkoutHookLogs()));
    same('a checkout with a broken cart list writes no validator line', 0, count(checkoutLogs()));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the hook line of a broken cart list carries no products', null, array_key_exists('cart_products', $request) ? $request['cart_products'] : false);
    same('the hook line of a broken cart list flags no validator call', false, (bool) ($request['validator_called'] ?? true));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], 'not-an-array');
    same('a cart update with vars that are not a form still returns nothing', array(), $errors);
    same('a cart update with vars that are not a form writes one hook line', 1, count(checkoutHookLogs()));
    same('a cart update with vars that are not a form writes no validator line', 0, count(checkoutLogs()));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the broken form hook line carries no vars keys', array(), $request['vars_keys'] ?? null);
    same('the broken form hook line carries no pid', 0, (int) ($request['vars_pid'] ?? -1));
    same('the broken form hook line flags no validator call', false, (bool) ($request['validator_called'] ?? true));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], null);
    same('a checkout with vars that are not a form still returns no errors', array(), $errors);
    same('a checkout with vars that are not a form writes one hook line', 1, count(checkoutHookLogs()));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the broken checkout form hook line carries no vars keys', array(), $request['vars_keys'] ?? null);
    same('the broken checkout form hook line flags no validator call', false, (bool) ($request['validator_called'] ?? true));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'i' => 0,
        'customfield' => array(9004 => 'hook_secret_name'),
    ));
    same('the cart update with a typed value still returns no errors', array(), $errors);
    same('the hook log never writes the typed value', false, strpos((string) checkoutHookLogs()[0][2], 'hook_secret_name'));
    same('the hook log summary never writes the typed value', false, strpos(logResponse(checkoutHookLogs()[0]), 'hook_secret_name'));
    same('the validator log never writes the typed value', false, strpos((string) checkoutLogs()[0][2], 'hook_secret_name'));

    resetApi();
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5005, 'customfields' => array(9004 => 'hook_secret_cart')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 44));
    same('the checkout with a typed value still returns no errors', array(), $errors);
    same('the checkout hook log never writes the typed value', false, strpos((string) checkoutHookLogs()[0][2], 'hook_secret_cart'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 91, 'username' => 'Hook_Log_Boom', 'enabled' => true),
    );
    $GLOBALS['moduleLogThrows'] = true;
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'customfield' => array(9004 => 'hook_log_boom'),
    ));
    same(
        'a broken module log still returns the cart update errors',
        array('The username "hook_log_boom" is already taken. Choose another one.'),
        $errors
    );
    same('a broken module log writes nothing for the cart update', array(), $GLOBALS['moduleLog']);
    unset($GLOBALS['moduleLogThrows']);

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array(
        array('id' => 91, 'username' => 'Hook_Log_Boom', 'enabled' => true),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5005,
        'customfield' => array(9004 => 'hook_log_boom'),
    ));
    same(
        'the same cart update with a working module log returns the same errors',
        array('The username "hook_log_boom" is already taken. Choose another one.'),
        $errors
    );
    same('the same cart update with a working module log writes the hook line', 2, count($GLOBALS['moduleLog']));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    $manyFields = array();
    for ($index = 0; $index < 25; $index++) {
        $manyFields[9200 + $index] = 'hook_secret_' . $index;
    }
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5002,
        'customfield' => $manyFields,
    ));
    same('the hook log keeps the field id list short', 20, count((array) (logRequest(checkoutHookLogs()[0])['customfield_ids_in_vars'] ?? array())));
    same(
        'the hook log keeps the first field ids',
        array(9200, 9201),
        array_slice((array) (logRequest(checkoutHookLogs()[0])['customfield_ids_in_vars'] ?? array()), 0, 2)
    );
    same('the hook log never writes a typed value', false, strpos((string) checkoutHookLogs()[0][2], 'hook_secret_'));

    resetApi();
    $manyVars = array('pid' => 5005, 'customfield' => array(9004 => 'hook_many_keys_name'));
    for ($index = 0; $index < 45; $index++) {
        $manyVars['extra_' . $index] = 'value_' . $index;
    }
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], $manyVars);
    same('the hook log keeps the vars key list short', 40, count((array) (logRequest(checkoutHookLogs()[0])['vars_keys'] ?? array())));
    same(
        'the hook log keeps the first vars keys',
        array('pid', 'customfield'),
        array_slice((array) (logRequest(checkoutHookLogs()[0])['vars_keys'] ?? array()), 0, 2)
    );
    same('the hook log never writes a var value', false, strpos((string) checkoutHookLogs()[0][2], 'value_7'));

    resetApi();
    $manyProducts = array();
    for ($index = 0; $index < 25; $index++) {
        $manyProducts[] = array('pid' => 5004, 'customfields' => array(9003 => 'hook_many_cart_' . $index));
    }
    $_SESSION = array('uid' => 44, 'cart' => array('products' => $manyProducts));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 44));
    same('a long cart still returns no errors', array(), $errors);
    same('the hook log keeps the cart list short', 20, count((array) (logRequest(checkoutHookLogs()[0])['cart_products'] ?? array())));
    same('the hook log counts every validated cart product', 25, (int) (logRequest(checkoutHookLogs()[0])['products_validated'] ?? -1));

    resetApi();
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => new class implements ArrayAccess {
                public function offsetExists($offset)
                {
                    return true;
                }

                public function offsetGet($offset)
                {
                    throw new \RuntimeException('cart index exploded');
                }

                public function offsetSet($offset, $value)
                {
                }

                public function offsetUnset($offset)
                {
                }
            },
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'i' => 0,
        'customfield' => array(9004 => 'hook_exception_name'),
    ));
    same('a cart update with a throwing cart still returns nothing', array(), $errors);
    same('a cart update with a throwing cart writes one hook line', 1, count(checkoutHookLogs()));
    same('a cart update with a throwing cart writes no validator line', 0, count(checkoutLogs()));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the exception hook line carries no cart list', null, array_key_exists('cart_products', $request) ? $request['cart_products'] : false);
    same('the exception hook line flags no validator call', false, (bool) ($request['validator_called'] ?? true));
    same('the exception hook line names the exception', 'RuntimeException: cart index exploded', (string) ($request['exception'] ?? ''));
    same(
        'the exception hook line summarises the failure',
        'hook=product_update resolved_pid=0 validator_called=0 exception=RuntimeException: cart index exploded',
        logResponse(checkoutHookLogs()[0])
    );

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => new \stdClass());
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 44));
    same('a checkout with a throwing session cart still returns no errors', array(), $errors);
    same('a checkout with a throwing session cart writes one hook line', 1, count(checkoutHookLogs()));
    same('a checkout with a throwing session cart writes no validator line', 0, count(checkoutLogs()));
    $request = logRequest(checkoutHookLogs()[0]);
    same('the checkout exception line names the hook', 'checkout', (string) ($request['hook'] ?? ''));
    same('the checkout exception line carries no cart products', false, array_key_exists('cart_products', $request));
    same('the checkout exception line flags no validator call', false, (bool) ($request['validator_called'] ?? true));
    same(
        'the checkout exception line names the exception',
        'Error: Cannot use object of type stdClass as array',
        (string) ($request['exception'] ?? '')
    );
    same(
        'the checkout exception line summarises the failure',
        'hook=checkout resolved_pid=0 validator_called=0 exception=Error: Cannot use object of type stdClass as array',
        logResponse(checkoutHookLogs()[0])
    );

    section('O. Custom field name variants');

    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5009,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'topup',
        'configoption10' => 'any',
        'configoption11' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5010,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'topup',
        'configoption10' => 'any',
        'configoption11' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5011,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'topup',
        'configoption10' => 'any',
        'configoption11' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5012,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'line',
        'configoption10' => 'any',
        'configoption11' => 'on',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5013,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'line',
        'configoption10' => 'any',
        'configoption11' => 'on',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5014,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'line',
        'configoption10' => 'any',
        'configoption11' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9010,
        'type' => 'product',
        'relid' => 5009,
        'fieldname' => 'Reseller',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9011,
        'type' => 'product',
        'relid' => 5009,
        'fieldname' => 'Account name',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9012,
        'type' => 'product',
        'relid' => 5010,
        'fieldname' => 'topupuser|Reseller username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9013,
        'type' => 'product',
        'relid' => 5011,
        'fieldname' => 'Reseller username|Reseller username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9014,
        'type' => 'product',
        'relid' => 5012,
        'fieldname' => 'lineuser|Line username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9015,
        'type' => 'product',
        'relid' => 5013,
        'fieldname' => 'Account name',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9016,
        'type' => 'product',
        'relid' => 5014,
        'fieldname' => 'Account name',
        'fieldtype' => 'text',
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'a top-up field named internal|visible matches the visible name',
        array('The reseller username "variant_ghost" was not found. Check the spelling and try again.'),
        $checkout::validateProduct(5010, array(9012 => '  variant_ghost  '), 44)
    );
    same(
        'the internal|visible top-up field looks the reseller up on the panel',
        array(1, 'variant_ghost'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same('the internal|visible top-up field logs the visible key', 'reseller_username', (string) logField('matched_field_key'));
    same(
        'the log of the internal|visible field carries both keys',
        array(array('name' => 'topupuser|Reseller username', 'keys' => array('topupuser', 'reseller_username'))),
        logField('field_names_on_product')
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'a field named the same before and after the pipe matches the visible name',
        array('The reseller username "variant_same" was not found. Check the spelling and try again.'),
        $checkout::validateProduct(5011, array(9013 => 'variant_same'), 44)
    );
    same('the doubled field name reaches the panel once', 1, count(apiCalls('findResellerByUsername')));
    same('the doubled field name logs the visible key', 'reseller_username', (string) logField('matched_field_key'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'a single unrelated field carries the top-up username',
        array('The reseller username "variant_single" was not found. Check the spelling and try again.'),
        $checkout::validateProduct(5008, array(9009 => 'variant_single'), 44)
    );
    same('the single field logs the fallback key', '*single*', (string) logField('matched_field_key'));
    same(
        'the single field reaches the panel with the typed value',
        array(1, 'variant_single'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same('the single field logs the typed value length', strlen('variant_single'), (int) logField('matched_field_value_len'));

    resetApi();
    same('a blank single field stays a missing field', array(), $checkout::validateProduct(5008, array(9009 => '   '), 44));
    same('a blank single field logs the no-field decision', array('topup_no_field'), logDecisions());
    same('a blank single field never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    same(
        'two unrelated fields leave the top-up without a username',
        array(),
        $checkout::validateProduct(5009, array(9010 => 'variant_two', 9011 => 'variant_two'), 44)
    );
    same('two unrelated fields log the no-field decision', array('topup_no_field'), logDecisions());
    same('two unrelated fields name no matched field', '', (string) logField('matched_field_key'));
    same('two unrelated fields never touch the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same('a line field named internal|visible matches the visible name', array(), $checkout::validateProduct(5012, array(9014 => 'variant_line'), 44));
    same(
        'the line internal|visible field asks the panel',
        array(1, 'variant_line'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );
    same('the line internal|visible field logs the visible key', 'line_username', (string) logField('matched_field_key'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same('a line with one unrelated field falls back to it', array(), $checkout::validateProduct(5013, array(9015 => 'variant_one_line'), 44));
    same(
        'the line fallback asks the panel',
        array(1, 'variant_one_line'),
        apiCalls('lines') ? apiCalls('lines')[0]['args'] : null
    );
    same('the line fallback logs the fallback key', '*single*', (string) logField('matched_field_key'));

    resetApi();
    same(
        'a disabled line keeps the single field out of the checkout',
        array(),
        $checkout::validateProduct(5014, array(9016 => 'variant_off_line'), 44)
    );
    same('a disabled line with a single field logs the disabled decision', array('line_disabled'), logDecisions());
    same('a disabled line with a single field never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    section('P. Sub-Reseller customer username');

    $options = xtreamai_ConfigOptions();
    same('the customer password switch is configoption12', 'customer_password', array_keys($options)[11]);
    same('the switch is named Customer password', 'Customer password', $options['customer_password']['FriendlyName']);
    same('the customer password switch is a dropdown', 'dropdown', $options['customer_password']['Type']);
    same('the customer password switch defaults to off', 'off', $options['customer_password']['Default']);
    same(
        'the customer password switch keeps the generated passwords as the default',
        'Generated by the module (default)',
        $options['customer_password']['Options']['off']
    );
    same(
        'the customer password switch offers the customer password',
        'Customer types it in the "Panel password" custom field',
        $options['customer_password']['Options']['on']
    );
    same(
        'the customer password switch explains the feature',
        'Line and Sub-Reseller products. With "Customer types it", add a custom field to this product named "Panel password" (type Password, Show on Order Form). The customer chooses the password at checkout: 8 to 32 characters, no spaces and none of % & ? # / \\ +. After the account is created the module clears that field on the WHMCS service, and the password is kept on the service as usual. With the field empty, or with this option off, passwords are generated as today.',
        $options['customer_password']['Description']
    );

    $visibility = xtreamai_accountTypeVisibility();
    same(
        'the visibility script watches the customer password slot',
        1,
        substr_count($visibility, 'packageconfigoption[12]')
    );
    same(
        'the customer username slot is shown for lines and sub-resellers',
        1,
        substr_count($visibility, 'toggleCells($customer, !isTopUp);')
    );
    same(
        'the customer password slot is shown for lines and sub-resellers',
        1,
        substr_count($visibility, 'toggleCells($password, !isTopUp);')
    );

    same(
        'the reseller username field reads Reseller username',
        'resA',
        xtreamai_resellerUsernameField(array('customfields' => array('Reseller username' => ' resA ')))
    );
    same(
        'the reseller username field reads Sub-Reseller username',
        'resB',
        xtreamai_resellerUsernameField(array('customfields' => array('Sub-Reseller username' => 'resB')))
    );
    same(
        'the reseller username field reads Panel username',
        'resC',
        xtreamai_resellerUsernameField(array('customfields' => array('Panel username' => 'resC')))
    );
    same(
        'the reseller username field reads Username',
        'resD',
        xtreamai_resellerUsernameField(array('customfields' => array('Username' => 'resD')))
    );
    same(
        'the reseller username field reads the visible half of a piped name',
        'resE',
        xtreamai_resellerUsernameField(array('customfields' => array('reselleruser|Reseller username' => 'resE')))
    );
    same(
        'the reseller username field reads the internal half of a piped name',
        'resF',
        xtreamai_resellerUsernameField(array('customfields' => array('Reseller username|Reseller account' => 'resF')))
    );
    same('the reseller username field is empty without custom fields', '', xtreamai_resellerUsernameField(array()));
    same(
        'the reseller username field is empty with a blank value',
        '',
        xtreamai_resellerUsernameField(array('customfields' => array('Reseller username' => '   ')))
    );
    same(
        'the reseller username field ignores the line custom field',
        '',
        xtreamai_resellerUsernameField(array('customfields' => array('Line username' => 'cliente1')))
    );

    same(
        'the shared custom field helper trims by default',
        'userA',
        xtreamai_customFieldValue(array('customfields' => array('Any name' => ' userA ')), array('any_name'))
    );
    same(
        'the shared custom field helper can keep the raw value',
        ' userA ',
        xtreamai_customFieldValue(array('customfields' => array('Any name' => ' userA ')), array('any_name'), false)
    );
    same(
        'the shared custom field helper unwraps an array value',
        'userB',
        xtreamai_customFieldValue(array('customfields' => array('Any name' => array(' userB '))), array('any_name'))
    );
    same(
        'the shared custom field helper ignores an unknown key',
        '',
        xtreamai_customFieldValue(array('customfields' => array('Any name' => 'userA')), array('other_name'))
    );
    same(
        'the shared custom field helper tolerates a missing map',
        '',
        xtreamai_customFieldValue(array(), array('any_name'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'off',
        'customfields' => array('Reseller username' => 'cliente_res'),
    )));
    same('a reseller product with the option off still provisions', 'success', $result);
    same(
        'a reseller product with the option off generates the username',
        xtreamai_lineUsername(baseParams()),
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][1] : null
    );
    same(
        'a reseller product with the option off never searches the panel',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => '   '),
    )));
    same('an on switch with an empty reseller field still provisions', 'success', $result);
    same(
        'an on switch with an empty reseller field generates the username',
        xtreamai_lineUsername(baseParams()),
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][1] : null
    );
    same('an empty reseller field never searches the panel', 0, count(apiCalls('findResellerByUsername')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'no good!'),
    )));
    same(
        'an invalid reseller username fails with the exact message',
        'The username "no good!" is not valid: use 3 to 32 letters, digits, dashes or underscores.',
        $result
    );
    same('an invalid reseller username never creates the account', 0, count(apiCalls('createReseller')));
    same(
        'an invalid reseller username is never looked up on the panel',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '701',
        'username' => 'taken_res',
        'member_group_id' => 2,
    );
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'taken_res'),
    )));
    same(
        'a taken reseller username fails with the exact message',
        'The reseller username "taken_res" is already taken on this panel. Ask the customer to choose another one.',
        $result
    );
    same('a taken reseller username never creates the account', 0, count(apiCalls('createReseller')));
    same(
        'the taken reseller username is looked up on the panel',
        array(1, 'taken_res'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same('a taken reseller username links nothing', array(), \WhmcsXtreamAI\ServiceStore::$links);

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'cliente_res1'),
    )));
    same('a free reseller username provisions the account', 'success', $result);
    same(
        'the typed reseller username is sent to the panel',
        'cliente_res1',
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][1] : null
    );
    same('the free reseller username is looked up once', 1, count(apiCalls('findResellerByUsername')));
    same(
        'the generated reseller password reaches the panel',
        xtreamai_linePassword(baseParams()),
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][2] : null
    );
    same(
        'the reseller account is linked with the typed username',
        'cliente_res1',
        \WhmcsXtreamAI\ServiceStore::$links ? \WhmcsXtreamAI\ServiceStore::$links[0]['username'] : null
    );
    same(
        'the service keeps the typed reseller username',
        'cliente_res1',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['username']
    );
    same(
        'the service keeps the generated reseller password',
        'encrypted:' . xtreamai_linePassword(baseParams()),
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password']
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'cliente_res2'),
    )));
    same('a reseller key still provisions the typed username', 'success', $result);
    same('a reseller key never looks the reseller up', 0, count(apiCalls('findResellerByUsername')));
    same(
        'a reseller key still creates the reseller account',
        'cliente_res2',
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][1] : null
    );

    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5101,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'reseller',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5102,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'reseller',
        'configoption10' => 'any',
        'configoption11' => 'off',
        'configoption12' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5103,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'reseller',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5104,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'reseller',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9101,
        'type' => 'product',
        'relid' => 5101,
        'fieldname' => 'Reseller username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9102,
        'type' => 'product',
        'relid' => 5102,
        'fieldname' => 'Reseller username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9103,
        'type' => 'product',
        'relid' => 5103,
        'fieldname' => 'Account name',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9104,
        'type' => 'product',
        'relid' => 5104,
        'fieldname' => 'reselleruser|Reseller username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5105,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'reseller',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'on',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5106,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'line',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'on',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9105,
        'type' => 'product',
        'relid' => 5105,
        'fieldname' => 'Panel password',
        'fieldtype' => 'password',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9106,
        'type' => 'product',
        'relid' => 5106,
        'fieldname' => 'pwd|Password',
        'fieldtype' => 'password',
    );

    resetApi();
    same(
        'a reseller product with the option off is left alone',
        array(),
        $checkout::validateProduct(5102, array(9102 => 'resoff_user'), 44)
    );
    same('a reseller product with the option off logs the disabled decision', array('reseller_disabled'), logDecisions());
    same('a reseller product with the option off never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    same('a reseller product without a username field is left alone', array(), $checkout::validateProduct(5101, array(), 44));
    same('a reseller product without a username field logs the no-field decision', array('reseller_no_field'), logDecisions());
    same('a reseller product without a username field never touches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    same(
        'an invalid reseller username is refused with the shared message',
        array('The username "ab" is not valid: use 3 to 32 letters, digits, dashes or underscores.'),
        $checkout::validateProduct(5101, array(9101 => 'ab'), 44)
    );
    same('an invalid reseller username is never looked up on the panel', 0, count(apiCalls('findResellerByUsername')));
    same('an invalid reseller username keeps the check decision', array('reseller_check'), logDecisions());

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '702',
        'username' => 'taken_any',
        'member_group_id' => 2,
    );
    same(
        'a taken reseller username is refused in the cart',
        array('The reseller username "taken_any" is already taken. Choose another one.'),
        $checkout::validateProduct(5101, array(9101 => '  taken_any  '), 44)
    );
    same(
        'the taken reseller username is looked up with the trimmed value',
        array(1, 'taken_any'),
        apiCalls('findResellerByUsername') ? apiCalls('findResellerByUsername')[0]['args'] : null
    );
    same('a taken reseller username logs the check decision', array('reseller_check'), logDecisions());
    same('a taken reseller username logs the matched field key', 'reseller_username', (string) logField('matched_field_key'));
    same('a taken reseller username logs one error', 1, (int) logField('errors_count'));
    same('a taken reseller username flags customer usernames', 'on', (string) logField('customer_username_enabled'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same('a free reseller username passes the cart check', array(), $checkout::validateProduct(5101, array(9101 => 'free_any'), 44));
    same('the free reseller username is looked up once', 1, count(apiCalls('findResellerByUsername')));
    same('a free reseller username logs the check decision', array('reseller_check'), logDecisions());
    same('a free reseller username logs no error', 0, (int) logField('errors_count'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '703',
        'username' => 'reskey_any',
        'member_group_id' => 2,
    );
    same(
        'a reseller product on a reseller key is left to provisioning',
        array(),
        $checkout::validateProduct(5101, array(9101 => 'reskey_any'), 44)
    );
    same('a reseller key never looks a reseller up for a sub-reseller product', 0, count(apiCalls('findResellerByUsername')));
    same('a reseller key logs the reseller key decision', array('reseller_reseller_key'), logDecisions());
    same('the reseller key log marks the key type', 'reseller', (string) logField('key_type'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '704',
        'username' => 'single_any',
        'member_group_id' => 2,
    );
    same(
        'a single reseller field falls back to the only custom field',
        array('The reseller username "single_any" is already taken. Choose another one.'),
        $checkout::validateProduct(5103, array(9103 => 'single_any'), 44)
    );
    same('the single reseller field logs the fallback key', '*single*', (string) logField('matched_field_key'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '705',
        'username' => 'piped_any',
        'member_group_id' => 2,
    );
    same(
        'a piped reseller field matches the visible half',
        array('The reseller username "piped_any" is already taken. Choose another one.'),
        $checkout::validateProduct(5104, array(9104 => 'piped_any'), 44)
    );
    same('the piped reseller field logs the visible key', 'reseller_username', (string) logField('matched_field_key'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '706',
        'username' => 'Secret!Pass9',
        'member_group_id' => 2,
    );
    same(
        'a lone password field is never taken for the reseller username',
        array(),
        $checkout::validateProduct(5105, array(9105 => 'Secret!Pass9'), 44)
    );
    same('a lone password field logs the missing username field', array('reseller_no_field'), logDecisions());
    same('a lone password field never reaches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));
    same('a lone password field is still checked as a password', 'ok', (string) logField('password_check'));

    resetApi();
    same(
        'a lone password field on a line product is never taken for the line username',
        array(),
        $checkout::validateProduct(5106, array(9106 => 'Secret!Pass9'), 44)
    );
    same('a lone line password field logs the missing username field', array('line_no_field'), logDecisions());
    same('a lone line password field never reaches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));
    same(
        'a lone invalid password field on a line product answers the password message only',
        array('The password is not valid: use 8 to 32 characters without spaces and without % & ? # / \\ +'),
        $checkout::validateProduct(5106, array(9106 => 'bad pass'), 44)
    );
    same('a lone invalid password field never reaches the panel', 0, count(\WhmcsXtreamAI\PanelApi::$calls));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '706',
        'username' => 'memo_any',
        'member_group_id' => 2,
    );
    $checkout::validateProduct(5101, array(9101 => 'memo_any'), 44);
    $callsAfterReseller = count(\WhmcsXtreamAI\PanelApi::$calls);
    $checkout::validateProduct(5101, array(9101 => 'memo_any'), 44);
    same('a repeated reseller username is served from memory', $callsAfterReseller, count(\WhmcsXtreamAI\PanelApi::$calls));
    same('a repeated reseller username logs one entry per fire', array('reseller_check', 'memo_reseller'), logDecisions());

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerError = 'Panel reseller list unavailable';
    same(
        'a reseller panel failure never blocks the checkout',
        array(),
        $checkout::validateProduct(5101, array(9101 => 'boom_any'), 44)
    );
    same(
        'the reseller panel failure is written to the module log',
        array('checkout_validate:Panel reseller list unavailable'),
        loggedActions()
    );
    same('the reseller panel failure writes no decision line', array(''), logDecisions());

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5101,
        'customfield' => array(9101 => 'cart_res_ok'),
    ));
    same('the cart update hook accepts a free reseller username', array(), $errors);
    same('the cart update hook looks the reseller up', 1, count(apiCalls('findResellerByUsername')));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5101,
        'customfield' => array(9101 => 'ab'),
    ));
    same(
        'the cart update hook refuses a short reseller username',
        array('The username "ab" is not valid: use 3 to 32 letters, digits, dashes or underscores.'),
        $errors
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '708',
        'username' => 'cart_res_taken',
        'member_group_id' => 2,
    );
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5101, 'customfields' => array(9101 => 'cart_res_taken')),
                array('pid' => 5102, 'customfields' => array(9102 => 'cart_res_off')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 44));
    same(
        'the checkout hook reports the reseller error of the cart',
        array('The reseller username "cart_res_taken" is already taken. Choose another one.'),
        $errors
    );
    same('the checkout hook logs the reseller decisions', array('reseller_check', 'reseller_disabled'), logDecisions());

    section('Q. Customer password');

    $passwordMessage = 'The password is not valid: use 8 to 32 characters without spaces and without % & ? # / \\ +';

    same('a valid customer password passes', null, xtreamai_validateCustomerPassword('Abcdef12'));
    same(
        'the panel-safe special characters are allowed',
        null,
        xtreamai_validateCustomerPassword('@$=:;!*()\'~,.-_')
    );
    same('thirty two characters are accepted', null, xtreamai_validateCustomerPassword(str_repeat('a', 32)));
    same('seven characters are refused', $passwordMessage, xtreamai_validateCustomerPassword('Abcdef1'));
    same(
        'more than thirty two characters are refused',
        $passwordMessage,
        xtreamai_validateCustomerPassword(str_repeat('a', 33))
    );
    same('an inner space is refused', $passwordMessage, xtreamai_validateCustomerPassword('Abc def12'));
    same('a leading space is refused', $passwordMessage, xtreamai_validateCustomerPassword(' Abcdef12'));
    same('a trailing space is refused', $passwordMessage, xtreamai_validateCustomerPassword('Abcdef12 '));
    same('a tab is refused', $passwordMessage, xtreamai_validateCustomerPassword("Abcdef1\t2"));
    same('a newline is refused', $passwordMessage, xtreamai_validateCustomerPassword("Abcdef1\n2"));

    $blockedCharacters = array('%', '&', '?', '#', '/', '\\', '+');
    foreach ($blockedCharacters as $blockedCharacter) {
        same(
            'a password with ' . $blockedCharacter . ' is refused',
            $passwordMessage,
            xtreamai_validateCustomerPassword('Abcdef1' . $blockedCharacter)
        );
    }

    $passwordRule = new \ReflectionMethod('WhmcsXtreamAI\\CheckoutValidator', 'passwordError');
    $passwordRule->setAccessible(true);
    same(
        'the addon and the server module share the password message',
        xtreamai_validateCustomerPassword('bad pass1'),
        $passwordRule->invoke(null, 'bad pass1')
    );
    same(
        'the addon and the server module share the password rule',
        xtreamai_validateCustomerPassword('Abcdef12'),
        $passwordRule->invoke(null, 'Abcdef12')
    );

    same('the password helper reads the product switch', true, xtreamai_customerPasswordEnabled(array('configoption12' => 'on')));
    same('the password helper is case insensitive', true, xtreamai_customerPasswordEnabled(array('configoption12' => ' ON ')));
    same(
        'the password helper reads the config options map',
        true,
        xtreamai_customerPasswordEnabled(array('configoptions' => array('customer_password' => 'on')))
    );
    same(
        'the password helper unwraps an array value',
        true,
        xtreamai_customerPasswordEnabled(array('configoption12' => array('on')))
    );
    same('the password helper falls back to off', false, xtreamai_customerPasswordEnabled(array()));
    same('the password helper ignores the off value', false, xtreamai_customerPasswordEnabled(array('configoption12' => 'off')));
    same('the password helper ignores an unknown value', false, xtreamai_customerPasswordEnabled(array('configoption12' => 'maybe')));

    same(
        'the password field reads Panel password',
        'Passw0rd1',
        xtreamai_customerPasswordField(array('customfields' => array('Panel password' => 'Passw0rd1')))
    );
    same(
        'the password field reads Password',
        'Passw0rd2',
        xtreamai_customerPasswordField(array('customfields' => array('Password' => 'Passw0rd2')))
    );
    same(
        'the password field reads Line password',
        'Passw0rd3',
        xtreamai_customerPasswordField(array('customfields' => array('Line password' => 'Passw0rd3')))
    );
    same(
        'the password field reads Reseller password',
        'Passw0rd4',
        xtreamai_customerPasswordField(array('customfields' => array('Reseller password' => 'Passw0rd4')))
    );
    same(
        'the password field reads the visible half of a piped name',
        'Passw0rd5',
        xtreamai_customerPasswordField(array('customfields' => array('panelpass|Panel password' => 'Passw0rd5')))
    );
    same(
        'the password field keeps the spaces as typed',
        ' Passw0rd6 ',
        xtreamai_customerPasswordField(array('customfields' => array('Panel password' => ' Passw0rd6 ')))
    );
    same('the password field is empty without custom fields', '', xtreamai_customerPasswordField(array()));
    same(
        'the password field ignores the username field',
        '',
        xtreamai_customerPasswordField(array('customfields' => array('Line username' => 'cliente1')))
    );

    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9150,
        'type' => 'product',
        'relid' => 6001,
        'fieldname' => 'Panel password',
        'fieldtype' => 'password',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9151,
        'type' => 'product',
        'relid' => 6001,
        'fieldname' => 'Line username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues'] = array(
        array('id' => 7001, 'fieldid' => 9150, 'relid' => 135, 'value' => 'CustomerPass1'),
        array('id' => 7002, 'fieldid' => 9150, 'relid' => 136, 'value' => 'OtherServicePass'),
        array('id' => 7003, 'fieldid' => 9151, 'relid' => 135, 'value' => 'cliente135'),
        array('id' => 7004, 'fieldid' => 9150, 'relid' => 137, 'value' => 'OtherProductPass'),
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    setCustomFieldValueRow(7001, 'CustomerPass1');
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'pid' => 6001,
        'configoption12' => 'on',
        'customfields' => array('Panel password' => 'CustomerPass1'),
    )));
    same('a line with a customer password provisions', 'success', $result);
    same(
        'the customer password is sent to the panel',
        'CustomerPass1',
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][4] : null
    );
    same(
        'the customer password is stored on the service',
        'encrypted:CustomerPass1',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password']
    );
    same('the password custom field of the service is cleared', '', customFieldValueRow(7001));
    same('the password custom field of another service is kept', 'OtherServicePass', customFieldValueRow(7002));
    same('another custom field of the same service is kept', 'cliente135', customFieldValueRow(7003));
    same('the password custom field of another service id is kept', 'OtherProductPass', customFieldValueRow(7004));
    same('the customer password never reaches the module log', false, strpos(json_encode($GLOBALS['moduleLog']), 'CustomerPass1'));
    same('a successful cleanup writes no extra log line', array('create'), logActions());

    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'pid' => 6001,
        'configoption12' => 'on',
        'customfields' => array('Panel password' => 'Bad pass9'),
    )));
    same('an invalid customer password fails with the exact message', $passwordMessage, $result);
    same('an invalid customer password never creates a line', 0, count(apiCalls('createLine')));
    same('an invalid customer password never reaches the module log', false, strpos(json_encode($GLOBALS['moduleLog']), 'Bad pass9'));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    setCustomFieldValueRow(7001, 'LeftOverPass1');
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'pid' => 6001,
        'configoption12' => 'on',
        'customfields' => array('Panel password' => ''),
    )));
    same('an empty customer password still provisions', 'success', $result);
    same(
        'an empty customer password generates the password',
        xtreamai_linePassword(baseParams()),
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][4] : null
    );
    same('an empty customer password clears nothing', 'LeftOverPass1', customFieldValueRow(7001));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    setCustomFieldValueRow(7001, 'LeftOverPass2');
    resetApi();
    $result = xtreamai_CreateAccount(baseParams(array(
        'pid' => 6001,
        'configoption12' => 'off',
        'customfields' => array('Panel password' => 'CustomerPass9'),
    )));
    same('an off password switch still provisions', 'success', $result);
    same(
        'an off password switch generates the password',
        xtreamai_linePassword(baseParams()),
        apiCalls('createLine') ? apiCalls('createLine')[0]['args'][4] : null
    );
    same('an off password switch clears nothing', 'LeftOverPass2', customFieldValueRow(7001));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    setCustomFieldValueRow(7001, 'CustomerPass2');
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'pid' => 6001,
        'configoption11' => 'on',
        'configoption12' => 'on',
        'customfields' => array('Reseller username' => 'cliente_res3', 'Panel password' => 'CustomerPass2'),
    )));
    same('a reseller with a customer password provisions', 'success', $result);
    same(
        'the customer password reaches the reseller creation',
        'CustomerPass2',
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][2] : null
    );
    same(
        'the reseller password is stored on the service',
        'encrypted:CustomerPass2',
        \WHMCS\Database\Capsule::$rows['tblhosting'][0]['password']
    );
    same('the reseller password custom field is cleared', '', customFieldValueRow(7001));
    same('the customer password never reaches the reseller log', false, strpos(json_encode($GLOBALS['moduleLog']), 'CustomerPass2'));

    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption11' => 'on',
        'configoption12' => 'on',
        'customfields' => array('Reseller username' => 'cliente_res4', 'Panel password' => 'short12'),
    )));
    same('an invalid reseller customer password fails with the exact message', $passwordMessage, $result);
    same('an invalid reseller customer password never creates the account', 0, count(apiCalls('createReseller')));

    resetApi();
    setCustomFieldValueRow(7001, 'SafePass1');
    setCustomFieldValueRow(7002, 'OtherServicePass');
    $customFieldRows = \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues'];
    xtreamai_clearCustomFieldValue(array('serviceid' => 135), array('panel_password'));
    same('clearing without a pid changes no row', $customFieldRows, \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues']);
    xtreamai_clearCustomFieldValue(array('pid' => 6001), array('panel_password'));
    same('clearing without a service id changes no row', $customFieldRows, \WHMCS\Database\Capsule::$rows['tblcustomfieldsvalues']);
    xtreamai_clearCustomFieldValue(array('pid' => 0, 'serviceid' => 0), array('panel_password'));
    same('clearing without any id writes no log', array(), $GLOBALS['moduleLog']);
    xtreamai_clearCustomFieldValue(array('pid' => 6001, 'serviceid' => 135), array('panel_password', 'password'));
    same('clearing with both ids empties the field of the service', '', customFieldValueRow(7001));
    same('clearing with both ids keeps another service untouched', 'OtherServicePass', customFieldValueRow(7002));
    same('clearing with both ids writes no log', array(), $GLOBALS['moduleLog']);

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $customFieldDefinitions = \WHMCS\Database\Capsule::$rows['tblcustomfields'];
    \WHMCS\Database\Capsule::$rows['tblcustomfields'] = (static function () {
        throw new \RuntimeException('Custom fields table unavailable');
        yield;
    })();
    $result = xtreamai_CreateAccount(baseParams(array(
        'pid' => 6001,
        'configoption12' => 'on',
        'customfields' => array('Panel password' => 'CustomerPass3'),
    )));
    \WHMCS\Database\Capsule::$rows['tblcustomfields'] = $customFieldDefinitions;
    same('a failing custom field cleanup still provisions the line', 'success', $result);
    $cleanupLogs = array();
    foreach ($GLOBALS['moduleLog'] as $entry) {
        if ((string) $entry[1] === 'clear_custom_field') {
            $cleanupLogs[] = isset($entry[3]) ? (string) $entry[3] : '';
        }
    }
    same(
        'the failing cleanup is written to the module log',
        array('Custom fields table unavailable'),
        $cleanupLogs
    );
    same('the failing cleanup never logs the password', false, strpos(json_encode($GLOBALS['moduleLog']), 'CustomerPass3'));

    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5201,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'line',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'on',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5202,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'reseller',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'on',
    );
    \WHMCS\Database\Capsule::$rows['tblproducts'][] = array(
        'id' => 5203,
        'servertype' => 'xtreamai',
        'configoption1' => '1',
        'configoption5' => 'line',
        'configoption10' => 'any',
        'configoption11' => 'on',
        'configoption12' => 'off',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9201,
        'type' => 'product',
        'relid' => 5201,
        'fieldname' => 'Line username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9202,
        'type' => 'product',
        'relid' => 5201,
        'fieldname' => 'Panel password',
        'fieldtype' => 'password',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9203,
        'type' => 'product',
        'relid' => 5202,
        'fieldname' => 'Reseller username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9204,
        'type' => 'product',
        'relid' => 5202,
        'fieldname' => 'Panel password',
        'fieldtype' => 'password',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9205,
        'type' => 'product',
        'relid' => 5203,
        'fieldname' => 'Line username',
        'fieldtype' => 'text',
    );
    \WHMCS\Database\Capsule::$rows['tblcustomfields'][] = array(
        'id' => 9206,
        'type' => 'product',
        'relid' => 5203,
        'fieldname' => 'Panel password',
        'fieldtype' => 'password',
    );

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same(
        'a line with a valid customer password passes the cart',
        array(),
        $checkout::validateProduct(5201, array(9201 => 'pwd_line_a', 9202 => 'Customer9Ok'), 44)
    );
    same('a valid customer password logs the password check', 'ok', (string) logField('password_check'));
    same('a valid customer password logs the password field key', 'panel_password', (string) logField('password_field_key'));
    same('a valid customer password logs the password length', strlen('Customer9Ok'), (int) logField('password_len'));
    same('a valid customer password logs the raw switch', 'on', (string) logField('customer_password_enabled'));
    same('a valid customer password keeps the line decision', array('line_check'), logDecisions());
    same('a valid customer password never reaches the log request', false, strpos((string) checkoutLogs()[0][2], 'Customer9Ok'));
    same('a valid customer password never reaches the log response', false, strpos(logResponse(checkoutLogs()[0]), 'Customer9Ok'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $errors = $checkout::validateProduct(5201, array(9201 => 'pwd_line_b', 9202 => 'Bad pass9'), 44);
    same('an invalid customer password is refused in the cart', array($passwordMessage), $errors);
    same('an invalid customer password logs the invalid check', 'invalid', (string) logField('password_check'));
    same('an invalid customer password logs the password length', strlen('Bad pass9'), (int) logField('password_len'));
    same('an invalid customer password keeps the line decision', array('line_check'), logDecisions());
    same('an invalid customer password logs the merged error count', 1, (int) logField('errors_count'));
    same('an invalid customer password never reaches the log', false, strpos((string) checkoutLogs()[0][2], 'Bad pass9'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same(
        'a line with an empty customer password passes the cart',
        array(),
        $checkout::validateProduct(5201, array(9201 => 'pwd_line_c', 9202 => ''), 44)
    );
    same('an empty customer password logs the empty check', 'empty', (string) logField('password_check'));
    same('an empty customer password logs no length', 0, (int) logField('password_len'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $errors = $checkout::validateProduct(5201, array(9201 => 'pwd_line_e', 9202 => ' Customer9Ok '), 44);
    same('a padded customer password is refused by the cart', array($passwordMessage), $errors);
    same('a padded customer password logs the real length', strlen(' Customer9Ok '), (int) logField('password_len'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    same(
        'a line with the password option off ignores the field',
        array(),
        $checkout::validateProduct(5203, array(9205 => 'pwd_line_d', 9206 => 'Bad pass9'), 44)
    );
    same('the password option off logs the off check', 'off', (string) logField('password_check'));
    same('the password option off logs the raw switch', 'off', (string) logField('customer_password_enabled'));
    same('the password option off logs no password field key', '', (string) logField('password_field_key'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    same(
        'a reseller with a valid customer password passes the cart',
        array(),
        $checkout::validateProduct(5202, array(9203 => 'pwd_res_a', 9204 => 'Customer9Ok'), 44)
    );
    same('the reseller password check logs the ok state', 'ok', (string) logField('password_check'));
    same('a reseller with a valid customer password keeps the reseller decision', array('reseller_check'), logDecisions());

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = array(
        'id' => '710',
        'username' => 'pwd_res_b',
        'member_group_id' => 2,
    );
    $errors = $checkout::validateProduct(5202, array(9203 => 'pwd_res_b', 9204 => 'Bad pass9'), 44);
    same(
        'a taken reseller username and an invalid password report both',
        array(
            'The reseller username "pwd_res_b" is already taken. Choose another one.',
            $passwordMessage,
        ),
        $errors
    );
    same('the reseller password error logs both errors', 2, (int) logField('errors_count'));
    same('the reseller password error logs the invalid check', 'invalid', (string) logField('password_check'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    $checkout::validateProduct(5202, array(9203 => 'pwd_res_c', 9204 => 'Customer9Ok'), 44);
    $errors = $checkout::validateProduct(5202, array(9203 => 'pwd_res_c', 9204 => 'Bad pass9'), 44);
    same('the memo of the username does not cover the password', array($passwordMessage), $errors);
    same('the repeated reseller username is served from memory', 1, count(apiCalls('findResellerByUsername')));
    same('the second reseller password check is logged', 'invalid', (string) logField('password_check'));
    same('the repeated reseller password error logs one error', 1, (int) logField('errors_count'));

    resetApi();
    $_SESSION = array('uid' => 44, 'cart' => array('products' => array()));
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateProductUpdate'], array(
        'pid' => 5201,
        'customfield' => array(9201 => 'pwd_hook_a', 9202 => 'Bad pass9'),
    ));
    same('the cart update hook returns the password error', array($passwordMessage), $errors);
    same('the cart update hook never writes the password', false, strpos(json_encode($GLOBALS['moduleLog']), 'Bad pass9'));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$linesResult = array();
    \WhmcsXtreamAI\PanelApi::$findResellerResult = null;
    $_SESSION = array(
        'uid' => 44,
        'cart' => array(
            'products' => array(
                array('pid' => 5201, 'customfields' => array(9201 => 'pwd_cart_a', 9202 => 'Customer9Ok')),
                array('pid' => 5202, 'customfields' => array(9203 => 'pwd_cart_b', 9204 => 'Bad pass9')),
            ),
        ),
    );
    $errors = call_user_func($GLOBALS['hooks']['ShoppingCartValidateCheckout'], array('clientId' => 44));
    same('the checkout hook reports the invalid password of the reseller line', array($passwordMessage), $errors);
    same('the checkout hook never writes a password', false, strpos(json_encode($GLOBALS['moduleLog']), 'Customer9Ok'));

    section('R. Sub-Reseller Member Group ID');

    $memberGroupMessage = 'Set the Sub-Reseller Member Group ID in this product\'s Module Settings: with an Admin panel key the panel needs the numeric id of the member group the new account belongs to.';

    $options = xtreamai_ConfigOptions();
    same(
        'the Sub-Reseller Member Group ID keeps its name',
        'Sub-Reseller Member Group ID',
        $options['sub_reseller_member_group_id']['FriendlyName']
    );
    same(
        'the Sub-Reseller Member Group ID explains the requirement',
        'Numeric id of the panel member group this Sub-Reseller product creates accounts in (Member Groups page of the panel). Required when the panel Key type is Admin: without it the order fails with a clear message. Reseller keys ignore it and inherit the group from their sub-reseller setup.',
        $options['sub_reseller_member_group_id']['Description']
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption8' => '',
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'cliente_mg1'),
    )));
    same('an admin key with an empty member group fails with the exact message', $memberGroupMessage, $result);
    same('an admin key with an empty member group never creates the account', 0, count(apiCalls('createReseller')));
    same(
        'an admin key with an empty member group never searches the panel',
        0,
        count(apiCalls('findResellerByUsername'))
    );
    same('an admin key with an empty member group links nothing', array(), \WhmcsXtreamAI\ServiceStore::$links);
    same('an admin key with an empty member group logs the create call', array('create'), logActions());
    same(
        'the failed provisioning keeps the service status untouched',
        array(),
        \WhmcsXtreamAI\ServiceStore::$statuses
    );

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption8' => '0',
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'cliente_mg2'),
    )));
    same('an admin key with a zero member group fails with the exact message', $memberGroupMessage, $result);
    same('an admin key with a zero member group never creates the account', 0, count(apiCalls('createReseller')));

    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption8' => '0',
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'no good!'),
    )));
    same('the member group check runs before the username check', $memberGroupMessage, $result);
    same(
        'a missing member group never looks the username up on the panel',
        0,
        count(apiCalls('findResellerByUsername'))
    );

    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption8' => '0',
        'configoption12' => 'on',
        'customfields' => array('Panel password' => 'Bad pass9'),
    )));
    same('the member group check runs before the password check', $memberGroupMessage, $result);

    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption8' => 'group',
    )));
    same('a member group that is not a number fails with the exact message', $memberGroupMessage, $result);
    same('a member group that is not a number never creates the account', 0, count(apiCalls('createReseller')));

    \WhmcsXtreamAI\ServiceStore::$rows = array();
    \WHMCS\Database\Capsule::$rows['tblhosting'] = hostingRow();
    resetApi();
    $result = xtreamai_CreateAccount(resellerParams(array('configoption8' => '20')));
    same('an admin key with a member group provisions the account', 'success', $result);
    same(
        'the member group reaches the panel',
        20,
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][6] : null
    );
    same('an admin key with a member group creates the account once', 1, count(apiCalls('createReseller')));
    same('the panel key type is read once per creation', 1, count(apiCalls('keyType')));

    resetApi();
    \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
    $result = xtreamai_CreateAccount(resellerParams(array(
        'configoption8' => 'group',
        'configoption11' => 'on',
        'customfields' => array('Reseller username' => 'cliente_mg3'),
    )));
    same('a reseller key ignores a missing member group', 'success', $result);
    same('a reseller key creates the account once', 1, count(apiCalls('createReseller')));
    same(
        'a reseller key sends no member group to the panel',
        null,
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][6] : null
    );
    same(
        'a reseller key still sends the typed username',
        'cliente_mg3',
        apiCalls('createReseller') ? apiCalls('createReseller')[0]['args'][1] : null
    );

    echo "\nSUMMARY: passed=" . $GLOBALS['passed'] . " failed=" . $GLOBALS['failed'] . "\n";
    exit($GLOBALS['failed'] === 0 ? 0 : 1);

}
