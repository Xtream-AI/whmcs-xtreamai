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

        public static function renewLine($panelId, $lineId, $packageId, $bouquets = null)
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
        public static function ensureTables()
        {
        }

        public static function set($key, $value)
        {
        }

        public static function get($key, $default = '')
        {
            return $default;
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
        \WhmcsXtreamAI\ServiceStore::$statuses = array();
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

    echo "\nSUMMARY: passed=" . $GLOBALS['passed'] . " failed=" . $GLOBALS['failed'] . "\n";
    exit($GLOBALS['failed'] === 0 ? 0 : 1);
}
