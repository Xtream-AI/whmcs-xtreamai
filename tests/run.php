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
        \WhmcsXtreamAI\ServiceStore::$statuses = array();
        \WhmcsXtreamAI\ServiceStore::$actions = array();
        \WhmcsXtreamAI\ServiceStore::$checks = array();
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
    same('the product has nine config options', 9, count($keys));
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
        ),
        $keys
    );
    same('Max Connections is still configoption7', 'max_connections', $keys[6]);
    same(
        'the Sub-Reseller Member Group ID is still configoption8',
        'sub_reseller_member_group_id',
        $keys[7]
    );
    same('the new option is configoption9', 'suspend_action', $keys[8]);
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
    \WHMCS\Database\Capsule::$rows['tblhosting'][0]['nextduedate'] = '2026-10-01';
    \WhmcsXtreamAI\PanelApi::$getLineResult = array(
        'id' => '987654',
        'username' => 'line_user',
        'enabled' => true,
        'admin_enabled' => true,
        'exp_date' => 1790000000,
        'expires_at' => '2026-10-01',
    );

    $fields = xtreamai_AdminServicesTabFields(baseParams());
    same('the tab names the panel line id', '987654', isset($fields['Panel line ID']) ? $fields['Panel line ID'] : null);
    same('the tab names the panel username', 'line_user', isset($fields['Panel username']) ? $fields['Panel username'] : null);
    same('the tab shows the panel status', 'Active', isset($fields['Line status']) ? $fields['Line status'] : null);
    same('the tab shows the WHMCS next due date', '01/10/2026', isset($fields['WHMCS next due date']) ? $fields['WHMCS next due date'] : null);
    same('the tab shows the panel expiry', '01/10/2026', isset($fields['Panel expiry']) ? $fields['Panel expiry'] : null);
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
        strpos((string) $fields['Warning'], 'WHMCS next due date is 01/10/2026, the panel line expires 01/09/2027') !== false,
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

    echo "\nSUMMARY: passed=" . $GLOBALS['passed'] . " failed=" . $GLOBALS['failed'] . "\n";
    exit($GLOBALS['failed'] === 0 ? 0 : 1);
}
