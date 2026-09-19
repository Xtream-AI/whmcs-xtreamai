<?php

namespace WHMCS\Database {
    final class CapsuleQuery
    {
        private $table;

        public function __construct($table)
        {
            $this->table = (string) $table;
        }

        public function join($table, $first, $operator = null, $second = null)
        {
            return $this;
        }

        public function leftJoin($table, $first, $operator = null, $second = null)
        {
            return $this;
        }

        public function where($column, $value)
        {
            return $this;
        }

        public function whereIn($column, array $values)
        {
            return $this;
        }

        public function whereRaw($sql, array $bindings = array())
        {
            return $this;
        }

        public function orderBy($column, $direction = 'asc')
        {
            return $this;
        }

        public function limit($value)
        {
            return $this;
        }

        public function select($columns)
        {
            return $this;
        }

        public function get()
        {
            return $this->table === 'mod_xtreamai_services' ? Capsule::$selectRows : array();
        }

        public function count()
        {
            return $this->table === 'mod_xtreamai_services' ? Capsule::$suspendedCount : 0;
        }

        public function first()
        {
            if ($this->table === 'tbladmins') {
                return (object) array('id' => 1, 'username' => 'xtadmin');
            }

            return null;
        }
    }

    final class Capsule
    {
        public static $selectRows = array();
        public static $suspendedCount = 0;

        public static function table($name)
        {
            return new CapsuleQuery($name);
        }
    }
}

namespace WhmcsXtreamAI {
    final class PanelApi
    {
        public static $keyTypeValue = 'admin';

        public static function keyType($panelId)
        {
            return self::$keyTypeValue;
        }
    }
}

namespace {
    define('WHMCS', true);

    $GLOBALS['passed'] = 0;
    $GLOBALS['failed'] = 0;
    $GLOBALS['moduleCalls'] = array();
    $GLOBALS['moduleResults'] = array();

    function check_token($namespace)
    {
        return true;
    }

    function moduleAnswer($serviceId)
    {
        if (isset($GLOBALS['moduleResults'][$serviceId])) {
            $answer = $GLOBALS['moduleResults'][$serviceId];

            if ($answer instanceof \Exception) {
                throw $answer;
            }

            return $answer;
        }

        return array('result' => 'success');
    }

    function testRunModule($serviceId)
    {
        $GLOBALS['moduleCalls'][] = array('service_id' => (int) $serviceId);

        return moduleAnswer((int) $serviceId);
    }

    function localAPI($command, array $params, $adminUsername = '')
    {
        if ((string) $command !== 'ModuleCustom'
            || !isset($params['func_name'])
            || (string) $params['func_name'] !== 'push_expiry'
            || !isset($params['accountid'])
            || !isset($params['serviceid'])
            || (int) $params['accountid'] !== (int) $params['serviceid']
        ) {
            return array('result' => 'error', 'message' => 'unexpected module call');
        }

        return moduleAnswer((int) $params['serviceid']);
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

    function childRow($serviceId, $type, $nextDue)
    {
        return (object) array(
            'service_id' => $serviceId,
            'line_username' => 'line_' . $serviceId,
            'firstname' => 'Client',
            'lastname' => (string) $serviceId,
            'nextduedate' => $nextDue,
            'billingcycle' => 'Monthly',
            'type_option' => $type,
        );
    }

    function childMain($scenario)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST = array(
            'panel_id' => '1',
            'after_id' => '0',
            'include_all' => '0',
            'worker' => '0',
            'workers' => '1',
        );

        if ($scenario === 'gate') {
            \WhmcsXtreamAI\PanelApi::$keyTypeValue = 'reseller';
            $_REQUEST['include_all'] = '1';
            \WHMCS\Database\Capsule::$selectRows = array(childRow(101, 'line', '2026-10-01'));
        } elseif ($scenario === 'rows') {
            \WHMCS\Database\Capsule::$selectRows = array(
                childRow(101, 'reseller', '2026-10-01'),
                childRow(102, 'line', ''),
                childRow(103, 'line', '0000-00-00'),
                childRow(104, 'line', '2026-10-01'),
            );
            \WHMCS\Database\Capsule::$suspendedCount = 3;
        } elseif ($scenario === 'full') {
            $_REQUEST['include_all'] = '1';
            \WHMCS\Database\Capsule::$selectRows = array(
                childRow(201, 'line', '2026-11-01'),
                childRow(202, 'line', '2026-11-02'),
                childRow(203, 'line', '2026-11-03'),
                childRow(204, 'line', '2026-11-04'),
                childRow(205, 'line', '2026-11-05'),
            );
            $GLOBALS['moduleResults'] = array(
                202 => new \RuntimeException('The line was not found on the panel.'),
                203 => array('result' => 'error', 'message' => 'The panel rejected the expiry.'),
            );
        } elseif ($scenario === 'no_panel') {
            $_REQUEST['panel_id'] = '0';
        } elseif ($scenario === 'bad_workers') {
            $_REQUEST['workers'] = '5';
        } else {
            echo json_encode(array('ok' => false, 'done' => true, 'message' => 'unknown scenario'));
            exit(2);
        }

        xtreamai_ajax_bulk_expiry();
    }

    function childResult($scenario)
    {
        $output = array();
        $status = 0;

        exec(
            escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__FILE__)
            . ' ' . escapeshellarg('--child=' . $scenario) . ' 2>&1',
            $output,
            $status
        );

        $body = trim(implode("\n", $output));
        $data = json_decode($body, true);

        return array('status' => $status, 'body' => $body, 'data' => is_array($data) ? $data : null);
    }

    function childKeys($data)
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys;
    }

    function parentMain()
    {
        echo "WHMCS Xtream AI bulk expiry tests (no WHMCS, no network)\n";

        section('A. The per row classification helper');

        $GLOBALS['moduleCalls'] = array();

        same(
            'a Sub-Reseller product is skipped',
            array('skipped_sub_reseller', 'Sub-Reseller product: no line expiry to set.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 135, 'type_option' => 'reseller', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );
        same('a Sub-Reseller row never calls the module', array(), $GLOBALS['moduleCalls']);

        same(
            'the raw product column is read too and is case insensitive',
            array('skipped_sub_reseller', 'Sub-Reseller product: no line expiry to set.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 135, 'configoption5' => ' Reseller ', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );
        same('the raw product column row never calls the module', array(), $GLOBALS['moduleCalls']);

        same(
            'a Credit top-up product is skipped',
            array('skipped_top_up', 'Credit top-up product: no line expiry to set.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 135, 'type_option' => 'topup', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );
        same('a Credit top-up row never calls the module', array(), $GLOBALS['moduleCalls']);

        same(
            'an empty next due date is skipped',
            array('skipped_no_due_date', 'The service has no next due date in WHMCS.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 135, 'type_option' => 'line', 'nextduedate' => ''),
                'testRunModule'
            )
        );

        same(
            'a zero next due date is skipped',
            array('skipped_no_due_date', 'The service has no next due date in WHMCS.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 135, 'type_option' => 'line', 'nextduedate' => '0000-00-00 00:00:00'),
                'testRunModule'
            )
        );

        same(
            'a missing next due date key is skipped',
            array('skipped_no_due_date', 'The service has no next due date in WHMCS.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 135, 'type_option' => 'line'),
                'testRunModule'
            )
        );
        same('a skipped row never calls the module', array(), $GLOBALS['moduleCalls']);

        section('B. The module answer is mapped to an outcome');

        $GLOBALS['moduleResults'] = array();
        $GLOBALS['moduleCalls'] = array();

        same(
            'a successful module call is aligned',
            array('aligned', 'Panel expiry set to 2026-10-01'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );
        same('the module receives the service id', array(array('service_id' => 137)), $GLOBALS['moduleCalls']);

        $GLOBALS['moduleCalls'] = array();

        same(
            'a date with a time keeps the day',
            array('aligned', 'Panel expiry set to 2026-10-01'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01 00:00:00'),
                'testRunModule'
            )
        );

        $GLOBALS['moduleResults'] = array(
            137 => array('result' => 'error', 'message' => 'This service has no next due date in WHMCS to copy to the panel.'),
        );

        same(
            'a module error keeps the module message',
            array('error', 'This service has no next due date in WHMCS to copy to the panel.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );

        $GLOBALS['moduleResults'] = array(137 => array('result' => 'error'));

        same(
            'a module error without a message uses the fallback',
            array('error', 'The module did not report success.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );

        $GLOBALS['moduleResults'] = array(137 => array('result' => 'success'));

        same(
            'an explicit success is aligned',
            array('aligned', 'Panel expiry set to 2026-10-01'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );

        $GLOBALS['moduleResults'] = array(137 => 'not an array');

        same(
            'an answer that is not an array is an error',
            array('error', 'The module did not report success.'),
            xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            )
        );

        $GLOBALS['moduleResults'] = array(137 => new \RuntimeException('The line was not found on the panel.'));
        $thrown = '';
        $outcome = array();

        try {
            $outcome = xtreamai_bulk_expiry_outcome(
                array('service_id' => 137, 'type_option' => 'line', 'nextduedate' => '2026-10-01'),
                'testRunModule'
            );
        } catch (\Throwable $e) {
            $thrown = $e->getMessage();
        }

        same('a module exception is left to the caller', 'The line was not found on the panel.', $thrown);
        same('a module exception produces no outcome', array(), $outcome);

        section('C. The due date formatter');

        same('a plain date is kept', '2026-10-01', xtreamai_bulk_expiry_date('2026-10-01'));
        same('a date with a time is cut to the day', '2026-10-01', xtreamai_bulk_expiry_date(' 2026-10-01 00:00:00 '));
        same('a value that is not a date is kept as it is', 'later', xtreamai_bulk_expiry_date('later'));

        section('D. The admin key gate fails fast');

        $child = childResult('gate');

        ok('the gate answers with JSON', $child['data'] !== null, $child['body']);
        same('the gate exits cleanly', 0, $child['status']);
        same('the gate refuses the run', false, $child['data']['ok']);
        same('the gate marks the batch as finished', true, $child['data']['done']);
        same(
            'the gate explains the admin key',
            'Setting the panel expiry requires an admin panel key on this panel entry.',
            $child['data']['message']
        );
        same('the gate answers before the first batch', array('done', 'message', 'ok'), childKeys($child['data']));

        section('E. Per row outcomes and the batch payload');

        $child = childResult('rows');

        ok('the batch answers with JSON', $child['data'] !== null, $child['body']);
        same(
            'the payload keys match the sync card',
            array('counts', 'done', 'next', 'ok', 'processed', 'rows', 'skipped_suspended'),
            childKeys($child['data'])
        );
        same('the run is complete with four rows', true, $child['data']['done']);
        same('the run reports its last service', 104, $child['data']['next']);
        same('the run processed four services', 4, $child['data']['processed']);
        same('the suspended services are counted once', 3, $child['data']['skipped_suspended']);
        same(
            'the counters collect every outcome',
            array(
                'skipped_sub_reseller' => 1,
                'skipped_no_due_date' => 2,
                'aligned' => 1,
                'skipped_suspended' => 3,
            ),
            $child['data']['counts']
        );

        $rows = $child['data']['rows'];
        same('one row per service is reported', 4, count($rows));
        same(
            'the Sub-Reseller row is skipped',
            array(
                'service_id' => 101,
                'client' => 'Client 101',
                'username' => 'line_101',
                'outcome' => 'skipped_sub_reseller',
                'message' => 'Sub-Reseller product: no line expiry to set.',
            ),
            $rows[0]
        );
        same(
            'the empty due date row is skipped',
            array(
                'service_id' => 102,
                'client' => 'Client 102',
                'username' => 'line_102',
                'outcome' => 'skipped_no_due_date',
                'message' => 'The service has no next due date in WHMCS.',
            ),
            $rows[1]
        );
        same(
            'the zero due date row is skipped',
            array(
                'service_id' => 103,
                'client' => 'Client 103',
                'username' => 'line_103',
                'outcome' => 'skipped_no_due_date',
                'message' => 'The service has no next due date in WHMCS.',
            ),
            $rows[2]
        );
        same(
            'the aligned row carries the next due date',
            array(
                'service_id' => 104,
                'client' => 'Client 104',
                'username' => 'line_104',
                'outcome' => 'aligned',
                'message' => 'Panel expiry set to 2026-10-01',
            ),
            $rows[3]
        );

        $GLOBALS['moduleResults'] = array(
            202 => new \RuntimeException('The line was not found on the panel.'),
            203 => array('result' => 'error', 'message' => 'The panel rejected the expiry.'),
        );

        section('F. A full batch keeps going and reports the errors');

        $child = childResult('full');

        ok('the full batch answers with JSON', $child['data'] !== null, $child['body']);
        same('the full batch is not finished yet', false, $child['data']['done']);
        same('the full batch reports its last service', 205, $child['data']['next']);
        same('the full batch processed five services', 5, $child['data']['processed']);
        same('no suspended service is counted when they are included', 0, $child['data']['skipped_suspended']);
        same(
            'the full batch counts the module errors',
            array('aligned' => 3, 'error' => 2),
            $child['data']['counts']
        );
        same('the exception message reaches the results table', 'The line was not found on the panel.', $child['data']['rows'][1]['message']);
        same('the module error message reaches the results table', 'The panel rejected the expiry.', $child['data']['rows'][2]['message']);

        section('G. The guards answer before any work');

        $child = childResult('no_panel');

        ok('the guard answers with JSON', $child['data'] !== null, $child['body']);
        same('a missing panel is refused', 'Select a panel first.', $child['data']['message']);
        same('the refused run is marked as finished', true, $child['data']['done']);

        $child = childResult('bad_workers');

        ok('the worker guard answers with JSON', $child['data'] !== null, $child['body']);
        same(
            'an out of range worker selection is refused',
            'Invalid parallel request selection. Use 1 to 4 workers and a worker index below the total.',
            $child['data']['message']
        );
        same('the refused run is marked as finished', true, $child['data']['done']);

        if ($GLOBALS['failed'] === 0) {
            echo "\nSUMMARY: passed=" . $GLOBALS['passed'] . " failed=" . $GLOBALS['failed'] . "\n";

            return 0;
        }

        echo "\nSUMMARY: passed=" . $GLOBALS['passed'] . " failed=" . $GLOBALS['failed'] . "\n";

        return 1;
    }

    $scenario = '';

    if (isset($argv[1]) && strpos((string) $argv[1], '--child=') === 0) {
        $scenario = substr((string) $argv[1], 8);
    }

    require __DIR__ . '/../modules/addons/xtreamai/xtreamai.php';

    if ($scenario !== '') {
        childMain($scenario);
        exit(0);
    }

    exit(parentMain());
}
