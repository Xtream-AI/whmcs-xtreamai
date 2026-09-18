<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

function xtreamai_config()
{
    return [
        'name'        => 'Xtream AI Panel',
        'description' => 'Provision and manage IPTV lines from Xtream AI panels.',
        'version'     => '1.7.1',
        'author'      => 'Xtream AI',
        'language'    => 'english',

        'fields'      => [],
    ];
}

function xtreamai_activate()
{
    try {
        xtreamai_require_bootstrap();
        \WhmcsXtreamAI\Settings::ensureTables();
    } catch (\Throwable $e) {
        return [
            'status'      => 'error',
            'description' => 'Could not activate the addon: ' . xtreamai_safe_message($e),
        ];
    }

    return [
        'status'      => 'success',
        'description' => 'Xtream AI Panel activated. Open the module and add a panel.',
    ];
}

function xtreamai_deactivate()
{
    return [
        'status'      => 'success',
        'description' => 'Xtream AI Panel deactivated. Stored data was kept.',
    ];
}

function xtreamai_upgrade($vars)
{
    xtreamai_require_bootstrap();
    \WhmcsXtreamAI\Settings::ensureTables();
    xtreamai_repair_panel_urls();
}

function xtreamai_output($vars)
{
    xtreamai_require_bootstrap();
    if (class_exists('WhmcsXtreamAI\\Settings')) {
        \WhmcsXtreamAI\Settings::ensureTables();
        xtreamai_repair_panel_urls();
    }

    $modulelink = isset($vars['modulelink']) ? (string) $vars['modulelink'] : 'addonmodules.php?module=xtreamai';
    $action     = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

    if ($action === 'ajax_test_connection') {
        xtreamai_ajax_test_connection();
    }

    if ($action === 'ajax_bulk_index') {
        xtreamai_ajax_bulk_index();
    }

    if ($action === 'ajax_bulk_link') {
        xtreamai_ajax_bulk_link();
    }

    if ($action === 'ajax_bulk_sync') {
        xtreamai_ajax_bulk_sync();
    }

    if ($action === 'ajax_bulk_expiry') {
        xtreamai_ajax_bulk_expiry();
    }

    if ($action === 'ajax_bulk_token') {
        xtreamai_ajax_bulk_token();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!xtreamai_check_csrf()) {
            xtreamai_flash('error', 'Invalid security token. Please try again.');
            xtreamai_redirect($modulelink);
        }
        xtreamai_handle_action($action, $modulelink);
    }

    $flash = xtreamai_consume_flash();

    $view = isset($_REQUEST['view']) ? (string) $_REQUEST['view'] : 'dashboard';
    $allowedViews = ['dashboard', 'list', 'settings', 'logs', 'resellers', 'lines', 'catalog', 'bulk'];
    if (!in_array($view, $allowedViews, true)) {
        $view = 'dashboard';
    }

    $editId  = isset($_REQUEST['edit']) ? (int) $_REQUEST['edit'] : 0;
    $panelId = isset($_REQUEST['panel_id']) ? (int) $_REQUEST['panel_id'] : 0;

    xtreamai_render($modulelink, $view, $flash, $editId, $panelId);
}

function xtreamai_require_bootstrap()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $bootstrap = __DIR__ . '/lib/bootstrap.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }
    $loaded = true;
}

function xtreamai_check_csrf()
{
    if (!function_exists('check_token')) {
        return false;
    }
    try {
        check_token('WHMCS.admin.default');
    } catch (\Throwable $e) {
        return false;
    }
    return true;
}

function xtreamai_token_field()
{
    if (function_exists('generate_token')) {
        return generate_token();
    }
    return '';
}

function xtreamai_token_plain()
{
    if (function_exists('generate_token')) {
        return (string) generate_token('plain');
    }
    return '';
}

function xtreamai_handle_action($action, $modulelink)
{
    $redirect = $modulelink;
    try {
        switch ($action) {
            case 'save_settings':
                xtreamai_save_settings();
                xtreamai_flash('success', 'Settings saved.');
                $redirect .= '&view=settings';
                break;

            case 'save_panel':
                xtreamai_save_panel();
                xtreamai_flash('success', 'Panel saved.');
                $redirect .= '&view=list';
                break;

            case 'toggle_active':
                xtreamai_toggle_active();
                xtreamai_flash('success', 'Panel status updated.');
                $redirect .= '&view=list';
                break;

            case 'delete_panel':
                xtreamai_delete_panel();
                xtreamai_flash('success', 'Panel deleted.');
                $redirect .= '&view=list';
                break;

            case 'adjust_credits':
                $redirect .= '&view=resellers&panel_id=' . (int) ($_POST['panel_id'] ?? 0);
                $balance = xtreamai_adjust_credits();
                xtreamai_flash('success', $balance === '' ? 'Credits adjusted.' : 'Credits adjusted. New balance: ' . $balance);
                break;

            case 'check_update':
                $redirect .= '&view=dashboard';
                xtreamai_check_update();
                break;

            case 'self_update':
                $redirect .= '&view=dashboard';
                xtreamai_self_update($modulelink);
                break;

            default:
                xtreamai_flash('error', 'Unknown action.');
        }
    } catch (\Throwable $e) {
        xtreamai_flash('error', xtreamai_safe_message($e));
    }

    xtreamai_redirect($redirect);
}

function xtreamai_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function xtreamai_substr(string $value, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
}

function xtreamai_save_settings()
{
    $allowed = array_keys(\WhmcsXtreamAI\Settings::credentialDefaults());
    $values  = [];

    foreach ($allowed as $key) {
        $values[$key] = \WhmcsXtreamAI\Settings::sanitizeCredential($key, (string) ($_POST[$key] ?? ''));
    }

    $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $values['username_prefix']);
    if ($prefix === null) {
        $prefix = '';
    }
    $values['username_prefix'] = substr($prefix, 0, 10);

    $notes = trim((string) $values['reseller_notes']);
    if (xtreamai_strlen($notes) > 2000) {
        $notes = xtreamai_substr($notes, 0, 2000);
    }
    $values['reseller_notes'] = $notes;

    foreach ($values as $key => $value) {
        \WhmcsXtreamAI\Settings::set($key, $value);
    }
}

function xtreamai_save_panel()
{
    $id      = (int) ($_POST['id'] ?? 0);
    $name    = trim((string) ($_POST['name'] ?? ''));
    $apiUrl  = xtreamai_normalize_url((string) ($_POST['api_url'] ?? ''));
    $m3uUrl  = xtreamai_normalize_url_entities(trim((string) ($_POST['m3u_url'] ?? '')));
    if ($m3uUrl !== '' && !preg_match('#^https?://#i', $m3uUrl)) {
        throw new \RuntimeException('M3U URL must start with http:// or https://');
    }
    $epgUrl  = xtreamai_normalize_url_entities(trim((string) ($_POST['epg_url'] ?? '')));
    if ($epgUrl !== '' && !preg_match('#^https?://#i', $epgUrl)) {
        throw new \RuntimeException('EPG URL must start with http:// or https://');
    }
    $password = (string) ($_POST['password'] ?? '');
    $verifySsl = empty($_POST['verify_ssl']) ? 0 : 1;
    $active   = empty($_POST['active']) ? 0 : 1;

    $keyType = ((string) ($_POST['key_type'] ?? 'reseller')) === 'admin' ? 'admin' : 'reseller';
    $adminOwner = null;
    if ($keyType === 'admin') {
        $adminOwnerRaw = trim((string) ($_POST['admin_owner_member_id'] ?? ''));
        if ($adminOwnerRaw !== '') {
            if (!ctype_digit($adminOwnerRaw) || (int) $adminOwnerRaw <= 0) {
                throw new \RuntimeException('Admin owner member_id must be a positive integer.');
            }
            $adminOwner = (int) $adminOwnerRaw;
        }
    }

    if ($name === '') {
        throw new \RuntimeException('Panel name is required.');
    }
    if ($apiUrl === '') {
        throw new \RuntimeException('Panel URL is required.');
    }

    $data = [
        'name'       => $name,
        'api_url'    => $apiUrl,
        'm3u_url'    => $m3uUrl,
        'epg_url'    => $epgUrl,
        'verify_ssl' => $verifySsl,
        'active'     => $active,
        'key_type'   => $keyType,
        'admin_owner_member_id' => $adminOwner,
    ];

    if ($id > 0) {
        $existing = \WhmcsXtreamAI\PanelStore::find($id);
        if ($existing === null) {
            throw new \RuntimeException('Panel not found.');
        }

        if ($password !== '') {
            $data['password'] = $password;
        }
        \WhmcsXtreamAI\PanelStore::update($id, $data);
    } else {
        if ($password === '') {
            throw new \RuntimeException('API access key is required for a new panel.');
        }
        $data['password'] = $password;
        \WhmcsXtreamAI\PanelStore::create($data);
    }
}

function xtreamai_toggle_active()
{
    $id = (int) ($_POST['id'] ?? 0);
    $panel = \WhmcsXtreamAI\PanelStore::find($id);
    if ($panel === null) {
        throw new \RuntimeException('Panel not found.');
    }
    \WhmcsXtreamAI\PanelStore::update($id, ['active' => $panel->active ? 0 : 1]);
}

function xtreamai_delete_panel()
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id < 1) {
        throw new \RuntimeException('Panel not found.');
    }
    \WhmcsXtreamAI\PanelStore::delete($id);
    \WHMCS\Database\Capsule::table('mod_xtreamai_line_index')->where('panel_id', $id)->delete();
    \WhmcsXtreamAI\Settings::set('line_index_complete_' . $id, '0');
}

function xtreamai_bulk_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'This action requires a POST request.']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function xtreamai_adjust_credits(): string
{
    $panelId    = (int) ($_POST['panel_id'] ?? 0);
    $resellerId = (int) ($_POST['reseller_id'] ?? 0);

    if ($panelId < 1) {
        throw new \RuntimeException('Panel not found.');
    }
    if ($resellerId < 1) {
        throw new \RuntimeException('Reseller not found.');
    }

    $deltaRaw = (string) ($_POST['delta'] ?? '');
    if ($deltaRaw === '' || !is_numeric($deltaRaw)) {
        throw new \RuntimeException('Credit adjustment must be a number.');
    }
    $delta = (float) $deltaRaw;
    if ($delta == 0.0 || abs($delta) > 100000.0) {
        throw new \RuntimeException('Credit adjustment must be non-zero and within ±100000.');
    }

    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($reason === '') {
        $reason = 'WHMCS admin adjustment';
    }
    if (xtreamai_strlen($reason) > 120) {
        $reason = xtreamai_substr($reason, 0, 120);
    }

    return \WhmcsXtreamAI\PanelApi::adjustResellerCredits(
        $panelId,
        (string) $resellerId,
        $delta,
        $reason
    );
}

function xtreamai_updater_ready()
{
    if (!class_exists('WhmcsXtreamAI\\Updater')) {
        throw new \RuntimeException('The update helper is not available in this installation.');
    }
}

function xtreamai_check_update()
{
    xtreamai_updater_ready();

    $release   = \WhmcsXtreamAI\Updater::latestRelease(true);
    $installed = \WhmcsXtreamAI\Updater::currentVersion();
    $label     = $installed === '' ? 'unknown' : $installed;

    if ($release === null) {
        xtreamai_flash('error', 'Could not check for updates. GitHub did not answer and no saved result is available.');

        return;
    }

    $version = (string) ($release['version'] ?? '');

    if (\WhmcsXtreamAI\Updater::isNewer($version, xtreamai_update_compare_version($installed))) {
        xtreamai_flash('success', 'Version ' . $version . ' is available. Installed version: ' . $label . '.');
    } else {
        xtreamai_flash('success', 'You are running the latest version (' . $label . ').');
    }
}

function xtreamai_self_update($modulelink)
{
    xtreamai_updater_ready();

    $release = \WhmcsXtreamAI\Updater::latestRelease();

    if ($release === null) {
        xtreamai_flash('error', 'No release information available. Press Check for updates first.');

        return;
    }

    $result  = \WhmcsXtreamAI\Updater::apply($release);
    $message = (string) ($result['message'] ?? 'The update did not finish.');

    if (empty($result['ok'])) {
        xtreamai_flash('error', $message);

        return;
    }

    if (!empty($result['backups']) && is_array($result['backups'])) {
        $message .= ' Backups kept: ' . implode(', ', $result['backups']) . '.';
    }

    xtreamai_flash('success', $message . ' Dashboard: ' . $modulelink);
}

function xtreamai_ajax_test_connection()
{
    header('Content-Type: application/json; charset=utf-8');

    if (!xtreamai_check_csrf()) {
        xtreamai_json(['ok' => false, 'message' => 'Invalid security token.']);
    }

    $id       = (int) ($_REQUEST['id'] ?? 0);
    $apiUrl   = trim((string) ($_REQUEST['api_url'] ?? ''));
    $password = (string) ($_REQUEST['password'] ?? '');

    if ($id > 0) {
        try {
            $health = \WhmcsXtreamAI\PanelApi::health($id);
            xtreamai_json([
                'ok'      => !empty($health['ok']),
                'message' => isset($health['message']) && $health['message'] !== ''
                    ? (string) $health['message']
                    : 'Connection successful.',
            ]);
        } catch (\Throwable $e) {
            xtreamai_json(['ok' => false, 'message' => xtreamai_safe_message($e)]);
        }
    }

    if ($apiUrl === '') {
        xtreamai_json(['ok' => false, 'message' => 'Panel URL is required.']);
    }
    if ($password === '') {
        xtreamai_json(['ok' => false, 'message' => 'API key is required.']);
    }

    try {
        $health = \WhmcsXtreamAI\PanelApi::healthFor($apiUrl, $password, true);
        xtreamai_json([
            'ok'      => !empty($health['ok']),
            'message' => isset($health['message']) && $health['message'] !== ''
                ? (string) $health['message']
                : 'Connection successful.',
        ]);
    } catch (\Throwable $e) {
        xtreamai_json(['ok' => false, 'message' => xtreamai_safe_message($e)]);
    }
}

function xtreamai_ajax_bulk_token()
{
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['adminid'])) {
        xtreamai_json([
            'ok'      => false,
            'message' => 'Your WHMCS session has ended. Log in again, open Bulk tools and click the button: the run resumes from the saved position.',
        ]);
    }

    xtreamai_json(['ok' => true, 'token' => xtreamai_token_plain()]);
}

function xtreamai_ajax_bulk_index()
{
    header('Content-Type: application/json; charset=utf-8');

    if (!xtreamai_check_csrf()) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Invalid security token.']);
    }

    xtreamai_bulk_require_post();

    $panelId = (int) ($_REQUEST['panel_id'] ?? 0);
    $cursor  = xtreamai_clean_cursor($_REQUEST['cursor'] ?? '');

    if ($panelId < 1) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Select a panel first.']);
    }

    try {
        $page = \WhmcsXtreamAI\PanelApi::linesPage($panelId, null, null, $cursor !== '' ? $cursor : null);

        if ($cursor === '') {
            \WHMCS\Database\Capsule::table('mod_xtreamai_line_index')
                ->where('panel_id', $panelId)
                ->delete();
            \WhmcsXtreamAI\Settings::set('line_index_complete_' . $panelId, '0');
        }

        $indexed = 0;
        $now     = date('Y-m-d H:i:s');
        foreach ($page['items'] as $line) {
            $lineId = isset($line['id']) ? (string) $line['id'] : '';
            if ($lineId === '' || (int) $lineId < 1) {
                continue;
            }

            $expDate = (isset($line['exp_date']) && $line['exp_date'] !== null)
                ? (int) $line['exp_date']
                : null;

            \WHMCS\Database\Capsule::table('mod_xtreamai_line_index')->updateOrInsert(
                ['panel_id' => $panelId, 'line_id' => $lineId],
                [
                    'username'    => (string) ($line['username'] ?? ''),
                    'service_tag' => \WhmcsXtreamAI\Settings::serviceTagFromNotes((string) ($line['notes'] ?? '')),
                    'exp_date'    => $expDate,
                    'enabled'     => !empty($line['enabled']) ? 1 : 0,
                    'indexed_at'  => $now,
                ]
            );
            $indexed++;
        }

        $next  = $page['next_cursor'];
        $total = (int) \WHMCS\Database\Capsule::table('mod_xtreamai_line_index')
            ->where('panel_id', $panelId)
            ->count();

        if ($next === null) {
            \WhmcsXtreamAI\Settings::set('line_index_complete_' . $panelId, '1');
        }

        xtreamai_json([
            'ok'            => true,
            'done'          => $next === null,
            'next_cursor'   => $next,
            'indexed'       => $indexed,
            'total_indexed' => $total,
        ]);
    } catch (\Throwable $e) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => xtreamai_safe_message($e)]);
    }
}

function xtreamai_ajax_bulk_link()
{
    header('Content-Type: application/json; charset=utf-8');

    if (!xtreamai_check_csrf()) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Invalid security token.']);
    }

    xtreamai_bulk_require_post();

    $panelId    = (int) ($_REQUEST['panel_id'] ?? 0);
    $afterId    = (int) ($_REQUEST['after_id'] ?? 0);
    $includeAll = ((string) ($_REQUEST['include_all'] ?? '0')) === '1';

    if ($panelId < 1) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Select a panel first.']);
    }

    try {
        $indexCount = (int) \WHMCS\Database\Capsule::table('mod_xtreamai_line_index')
            ->where('panel_id', $panelId)
            ->count();
        if ($indexCount < 1) {
            xtreamai_json([
                'ok'      => false,
                'done'    => true,
                'message' => 'No indexed lines for this panel. Run Index panel lines first.',
            ]);
        }
        if (\WhmcsXtreamAI\Settings::get('line_index_complete_' . $panelId, '0') !== '1') {
            xtreamai_json([
                'ok'      => false,
                'done'    => true,
                'message' => 'The index of this panel is incomplete (the last run did not reach the end). Run Index panel lines again before linking.',
            ]);
        }

        $batchSize = 100;

        $query = \WHMCS\Database\Capsule::table('tblhosting')
            ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
            ->leftJoin('tblclients', 'tblclients.id', '=', 'tblhosting.userid')
            ->leftJoin('mod_xtreamai_services', 'mod_xtreamai_services.service_id', '=', 'tblhosting.id')
            ->where('tblproducts.servertype', 'xtreamai')
            ->where('tblhosting.id', '>', $afterId)
            ->where(function ($nested) {
                $nested->whereNull('mod_xtreamai_services.service_id')
                    ->orWhereNull('mod_xtreamai_services.panel_account_id')
                    ->orWhere('mod_xtreamai_services.panel_account_id', '');
            });

        if (!$includeAll) {
            $query->whereIn('tblhosting.domainstatus', ['Active', 'Suspended']);
        }

        $rows = $query->orderBy('tblhosting.id', 'asc')
            ->limit($batchSize)
            ->select([
                'tblhosting.id as service_id',
                'tblhosting.username as hosting_username',
                'tblhosting.domainstatus as domainstatus',
                'tblclients.firstname as firstname',
                'tblclients.lastname as lastname',
                'tblproducts.configoption1 as panel_option',
                'tblproducts.configoption2 as package_option',
                'tblproducts.configoption5 as type_option',
            ])
            ->get();

        $counts  = [];
        $results = [];
        $lastId  = $afterId;

        foreach ($rows as $row) {
            $serviceId   = (int) $row->service_id;
            $lastId      = $serviceId;
            $client      = trim(((string) $row->firstname) . ' ' . ((string) $row->lastname));
            $hostingUser = trim((string) $row->hosting_username);
            $outcome     = 'not_found';
            $message     = 'No indexed line matched this service.';
            $username    = $hostingUser;

            try {
                if (strtolower(trim((string) $row->type_option)) === 'reseller') {
                    $outcome = 'skipped_sub_reseller';
                    $message = 'Sub-Reseller product: nothing to link.';
                } else {
                    $servicePanelId = (int) $row->panel_option;
                    if ($servicePanelId < 1) {
                        $servicePanelId = xtreamai_bulk_first_panel_id();
                    }

                    if ($servicePanelId !== $panelId) {
                        $outcome = 'skipped_other_panel';
                        $message = 'This product belongs to another panel.';
                    } else {
                        $tagMatches  = xtreamai_bulk_index_rows($panelId, 'service_tag', $serviceId);
                        $userMatches = $hostingUser !== ''
                            ? xtreamai_bulk_index_rows($panelId, 'username', $hostingUser)
                            : [];

                        $match = \WhmcsXtreamAI\Settings::chooseLineMatch($tagMatches, $userMatches, $hostingUser);

                        if ($match === null) {
                            $outcome = 'not_found';
                            $message = 'No indexed line matched this service.';
                        } elseif ($match['source'] === 'ambiguous') {
                            $ids = [];
                            foreach ($match['candidates'] as $candidate) {
                                $ids[] = (string) ($candidate['line_id'] ?? '');
                            }
                            $outcome = 'ambiguous_tag';
                            $message = 'Several lines carry this service tag (' . implode(', ', $ids) . ') and none matches the service username. Set the username on the service or fix the notes on the panel, then run again.';
                        } else {
                            $line         = $match['line'];
                            $lineId       = (string) ($line['line_id'] ?? '');
                            $lineUsername = (string) ($line['username'] ?? '');
                            $credentialNote = '';

                            if ($hostingUser === '' && $lineId !== '') {
                                $panelLine = \WhmcsXtreamAI\PanelApi::getLine($panelId, $lineId);
                                $credentialNote = xtreamai_bulk_hosting_credentials(
                                    $serviceId,
                                    (string) ($panelLine['username'] ?? ''),
                                    (string) ($panelLine['password'] ?? '')
                                );
                            }

                            \WhmcsXtreamAI\ServiceStore::link(
                                $serviceId,
                                $panelId,
                                $lineId,
                                $lineUsername,
                                (int) $row->package_option
                            );
                            \WhmcsXtreamAI\ServiceStore::updateStatus(
                                $serviceId,
                                strtolower((string) $row->domainstatus) === 'suspended' ? 'Suspended' : 'Active',
                                xtreamai_bulk_expiry($line['exp_date'] ?? null)
                            );

                            $username = $lineUsername;
                            if ($match['source'] === 'tag') {
                                $outcome = 'linked_by_tag';
                                $message = 'Matched the notes tag to line ' . $lineId . '.' . $credentialNote;
                            } else {
                                $outcome = 'linked_by_username';
                                $message = 'Matched the panel username to line ' . $lineId . '.' . $credentialNote;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $outcome = 'error';
                $message = xtreamai_safe_message($e);
            }

            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            $results[] = [
                'service_id' => $serviceId,
                'client'     => $client,
                'username'   => $username,
                'outcome'    => $outcome,
                'message'    => $message,
            ];
        }

        xtreamai_json([
            'ok'        => true,
            'done'      => count($rows) < $batchSize,
            'next'      => $lastId,
            'processed' => count($results),
            'counts'    => $counts,
            'rows'      => $results,
        ]);
    } catch (\Throwable $e) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => xtreamai_safe_message($e)]);
    }
}

function xtreamai_ajax_bulk_sync()
{
    header('Content-Type: application/json; charset=utf-8');

    if (!xtreamai_check_csrf()) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Invalid security token.']);
    }

    if (!function_exists('localAPI')) {
        xtreamai_json([
            'ok'      => false,
            'done'    => true,
            'message' => 'The WHMCS local API is not available on this installation.',
        ]);
    }

    xtreamai_bulk_require_post();

    $panelId    = (int) ($_REQUEST['panel_id'] ?? 0);
    $afterId    = (int) ($_REQUEST['after_id'] ?? 0);
    $includeAll = ((string) ($_REQUEST['include_all'] ?? '0')) === '1';
    $worker     = (int) ($_REQUEST['worker'] ?? 0);
    $workers    = isset($_REQUEST['workers']) ? (int) $_REQUEST['workers'] : 1;

    if ($panelId < 1) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Select a panel first.']);
    }

    if ($workers < 1 || $workers > 4 || $worker < 0 || $worker >= $workers) {
        xtreamai_json([
            'ok'      => false,
            'done'    => true,
            'message' => 'Invalid parallel request selection. Use 1 to 4 workers and a worker index below the total.',
        ]);
    }

    try {
        $adminUsername = xtreamai_bulk_admin_username();
        $batchSize     = 5;
        $statuses      = $includeAll ? ['Active', 'Suspended'] : ['Active'];

        $query = \WHMCS\Database\Capsule::table('mod_xtreamai_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_xtreamai_services.service_id')
            ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
            ->leftJoin('tblclients', 'tblclients.id', '=', 'tblhosting.userid')
            ->where('mod_xtreamai_services.panel_id', $panelId)
            ->where('mod_xtreamai_services.panel_account_id', '<>', '')
            ->where('tblproducts.servertype', 'xtreamai')
            ->whereIn('tblhosting.domainstatus', $statuses)
            ->where('mod_xtreamai_services.service_id', '>', $afterId);

        if ($workers > 1) {
            $query->whereRaw('MOD(mod_xtreamai_services.service_id, ?) = ?', [$workers, $worker]);
        }

        $rows = $query->orderBy('mod_xtreamai_services.service_id', 'asc')
            ->limit($batchSize)
            ->select([
                'mod_xtreamai_services.service_id as service_id',
                'mod_xtreamai_services.username as line_username',
                'tblclients.firstname as firstname',
                'tblclients.lastname as lastname',
                'tblproducts.configoption5 as type_option',
            ])
            ->get();

        $counts  = [];
        $results = [];
        $lastId  = $afterId;
        $done    = count($rows) < $batchSize;

        foreach ($rows as $row) {
            $serviceId = (int) $row->service_id;
            $lastId    = $serviceId;
            $client    = trim(((string) $row->firstname) . ' ' . ((string) $row->lastname));
            $outcome   = 'error';
            $message   = '';

            try {
                if (strtolower(trim((string) $row->type_option)) === 'reseller') {
                    $outcome = 'skipped_sub_reseller';
                    $message = 'Sub-Reseller product: nothing to sync.';
                    $result  = null;
                } else {
                    $result = localAPI('ModuleCustom', ['accountid' => $serviceId, 'serviceid' => $serviceId, 'func_name' => 'sync'], $adminUsername);
                }

                if ($outcome !== 'skipped_sub_reseller') {
                    if (is_array($result) && isset($result['result']) && $result['result'] === 'success') {
                        $outcome = 'synced';
                        $message = '';
                    } else {
                        $outcome = 'error';
                        $message = (is_array($result) && isset($result['message']) && is_scalar($result['message']))
                            ? (string) $result['message']
                            : 'The module did not report success.';
                    }
                }
            } catch (\Throwable $e) {
                $outcome = 'error';
                $message = xtreamai_safe_message($e);
            }

            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            $results[] = [
                'service_id' => $serviceId,
                'client'     => $client,
                'username'   => (string) $row->line_username,
                'outcome'    => $outcome,
                'message'    => $message,
            ];
        }

        $skippedSuspended = $includeAll
            ? 0
            : xtreamai_bulk_skipped_suspended($panelId, $afterId, $lastId, $done, $workers, $worker);
        if ($skippedSuspended > 0) {
            $counts['skipped_suspended'] = ($counts['skipped_suspended'] ?? 0) + $skippedSuspended;
        }

        xtreamai_json([
            'ok'                => true,
            'done'              => $done,
            'next'              => $lastId,
            'processed'         => count($results),
            'skipped_suspended' => $skippedSuspended,
            'counts'            => $counts,
            'rows'              => $results,
        ]);
    } catch (\Throwable $e) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => xtreamai_safe_message($e)]);
    }
}

function xtreamai_ajax_bulk_expiry()
{
    header('Content-Type: application/json; charset=utf-8');

    if (!xtreamai_check_csrf()) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Invalid security token.']);
    }

    if (!function_exists('localAPI')) {
        xtreamai_json([
            'ok'      => false,
            'done'    => true,
            'message' => 'The WHMCS local API is not available on this installation.',
        ]);
    }

    xtreamai_bulk_require_post();

    $panelId    = (int) ($_REQUEST['panel_id'] ?? 0);
    $afterId    = (int) ($_REQUEST['after_id'] ?? 0);
    $includeAll = ((string) ($_REQUEST['include_all'] ?? '0')) === '1';
    $worker     = (int) ($_REQUEST['worker'] ?? 0);
    $workers    = isset($_REQUEST['workers']) ? (int) $_REQUEST['workers'] : 1;

    if ($panelId < 1) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => 'Select a panel first.']);
    }

    if ($workers < 1 || $workers > 4 || $worker < 0 || $worker >= $workers) {
        xtreamai_json([
            'ok'      => false,
            'done'    => true,
            'message' => 'Invalid parallel request selection. Use 1 to 4 workers and a worker index below the total.',
        ]);
    }

    try {
        if (\WhmcsXtreamAI\PanelApi::keyType($panelId) !== 'admin') {
            xtreamai_json([
                'ok'      => false,
                'done'    => true,
                'message' => 'Setting the panel expiry requires an admin panel key on this panel entry.',
            ]);
        }

        $adminUsername = xtreamai_bulk_admin_username();
        $batchSize     = 5;
        $statuses      = $includeAll ? ['Active', 'Suspended'] : ['Active'];

        $query = \WHMCS\Database\Capsule::table('mod_xtreamai_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_xtreamai_services.service_id')
            ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
            ->leftJoin('tblclients', 'tblclients.id', '=', 'tblhosting.userid')
            ->where('mod_xtreamai_services.panel_id', $panelId)
            ->where('mod_xtreamai_services.panel_account_id', '<>', '')
            ->where('tblproducts.servertype', 'xtreamai')
            ->whereIn('tblhosting.domainstatus', $statuses)
            ->where('mod_xtreamai_services.service_id', '>', $afterId);

        if ($workers > 1) {
            $query->whereRaw('MOD(mod_xtreamai_services.service_id, ?) = ?', [$workers, $worker]);
        }

        $rows = $query->orderBy('mod_xtreamai_services.service_id', 'asc')
            ->limit($batchSize)
            ->select([
                'mod_xtreamai_services.service_id as service_id',
                'mod_xtreamai_services.username as line_username',
                'tblclients.firstname as firstname',
                'tblclients.lastname as lastname',
                'tblhosting.nextduedate as nextduedate',
                'tblproducts.configoption5 as type_option',
            ])
            ->get();

        $counts  = [];
        $results = [];
        $lastId  = $afterId;
        $done    = count($rows) < $batchSize;

        foreach ($rows as $row) {
            $serviceId = (int) $row->service_id;
            $lastId    = $serviceId;
            $client    = trim(((string) $row->firstname) . ' ' . ((string) $row->lastname));
            $outcome   = 'error';
            $message   = '';

            try {
                $pair = xtreamai_bulk_expiry_outcome(
                    [
                        'service_id'  => $serviceId,
                        'type_option' => (string) $row->type_option,
                        'nextduedate' => (string) $row->nextduedate,
                    ],
                    static function (int $id) use ($adminUsername) {
                        return localAPI('ModuleCustom', ['accountid' => $id, 'serviceid' => $id, 'func_name' => 'push_expiry'], $adminUsername);
                    }
                );

                $outcome = (string) $pair[0];
                $message = (string) $pair[1];
            } catch (\Throwable $e) {
                $outcome = 'error';
                $message = xtreamai_safe_message($e);
            }

            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            $results[] = [
                'service_id' => $serviceId,
                'client'     => $client,
                'username'   => (string) $row->line_username,
                'outcome'    => $outcome,
                'message'    => $message,
            ];
        }

        $skippedSuspended = $includeAll
            ? 0
            : xtreamai_bulk_skipped_suspended($panelId, $afterId, $lastId, $done, $workers, $worker);
        if ($skippedSuspended > 0) {
            $counts['skipped_suspended'] = ($counts['skipped_suspended'] ?? 0) + $skippedSuspended;
        }

        xtreamai_json([
            'ok'                => true,
            'done'              => $done,
            'next'              => $lastId,
            'processed'         => count($results),
            'skipped_suspended' => $skippedSuspended,
            'counts'            => $counts,
            'rows'              => $results,
        ]);
    } catch (\Throwable $e) {
        xtreamai_json(['ok' => false, 'done' => true, 'message' => xtreamai_safe_message($e)]);
    }
}

function xtreamai_bulk_expiry_outcome(array $row, callable $runModule): array
{
    $type = strtolower(trim((string) ($row['type_option'] ?? ($row['configoption5'] ?? ''))));

    if ($type === 'reseller') {
        return ['skipped_sub_reseller', 'Sub-Reseller product: no line expiry to set.'];
    }

    $nextDue = trim((string) ($row['nextduedate'] ?? ''));

    if ($nextDue === '' || strpos($nextDue, '0000-00-00') === 0) {
        return ['skipped_no_due_date', 'The service has no next due date in WHMCS.'];
    }

    $result = $runModule((int) ($row['service_id'] ?? 0));

    if (is_array($result) && isset($result['result']) && $result['result'] === 'success') {
        return ['aligned', 'Panel expiry set to ' . xtreamai_bulk_expiry_date($nextDue)];
    }

    return [
        'error',
        (is_array($result) && isset($result['message']) && is_scalar($result['message']))
            ? (string) $result['message']
            : 'The module did not report success.',
    ];
}

function xtreamai_bulk_expiry_date(string $value): string
{
    $raw = trim($value);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $matches) === 1) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    return $raw;
}

function xtreamai_bulk_skipped_suspended(
    int $panelId,
    int $afterId,
    int $lastId,
    bool $done,
    int $workers,
    int $worker
): int {
    try {
        $query = \WHMCS\Database\Capsule::table('mod_xtreamai_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_xtreamai_services.service_id')
            ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
            ->where('mod_xtreamai_services.panel_id', $panelId)
            ->where('mod_xtreamai_services.panel_account_id', '<>', '')
            ->where('tblproducts.servertype', 'xtreamai')
            ->where('tblhosting.domainstatus', 'Suspended')
            ->where('mod_xtreamai_services.service_id', '>', $afterId);

        if (!$done) {
            $query->where('mod_xtreamai_services.service_id', '<=', $lastId);
        }
        if ($workers > 1) {
            $query->whereRaw('MOD(mod_xtreamai_services.service_id, ?) = ?', [$workers, $worker]);
        }

        return (int) $query->count();
    } catch (\Throwable $e) {
        return 0;
    }
}

function xtreamai_bulk_first_panel_id(): int
{
    try {
        $panel = \WhmcsXtreamAI\PanelStore::firstActive();

        return $panel !== null ? (int) $panel->id : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

function xtreamai_bulk_index_rows(int $panelId, string $field, $value): array
{
    if ($field !== 'service_tag' && $field !== 'username') {
        return [];
    }
    if ($value === null || $value === '') {
        return [];
    }

    $out  = [];
    $rows = \WHMCS\Database\Capsule::table('mod_xtreamai_line_index')
        ->where('panel_id', $panelId)
        ->where($field, $value)
        ->orderBy('id', 'asc')
        ->get();

    foreach ($rows as $row) {
        $out[] = (array) $row;
    }

    return $out;
}

function xtreamai_bulk_expiry($expDate): ?string
{
    $timestamp = (int) $expDate;
    if ($timestamp < 1) {
        return null;
    }

    return gmdate('Y-m-d', $timestamp);
}

function xtreamai_bulk_hosting_credentials(int $serviceId, string $username, string $password): string
{
    if ($serviceId < 1) {
        return '';
    }

    $note   = '';
    $update = [];
    if ($username !== '') {
        $update['username'] = $username;
    }
    if ($password !== '') {
        if (function_exists('encrypt')) {
            $update['password'] = encrypt($password);
        } else {
            $note = ' The password could not be stored on the service (WHMCS encrypt() unavailable).';
        }
    }

    if ($update) {
        \WHMCS\Database\Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
        $note = ' Username and password copied to the service.' . $note;
    }

    return $note;
}

function xtreamai_bulk_admin_username(): string
{
    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    if ($adminId > 0) {
        $row = \WHMCS\Database\Capsule::table('tbladmins')->where('id', $adminId)->first();
        if ($row !== null && !empty($row->username)) {
            return (string) $row->username;
        }
    }

    $row = \WHMCS\Database\Capsule::table('tbladmins')->orderBy('id', 'asc')->first();
    if ($row !== null && !empty($row->username)) {
        return (string) $row->username;
    }

    throw new \RuntimeException('No WHMCS admin account was found to run the module command.');
}

function xtreamai_normalize_url_entities(string $value): string
{
    if ($value === '' || strpos($value, '&') === false) {
        return $value;
    }
    for ($i = 0; $i < 8; $i++) {
        $decoded = htmlspecialchars_decode($value, ENT_QUOTES);
        if ($decoded === $value) {
            return $decoded;
        }
        $value = $decoded;
    }
    return $value;
}

function xtreamai_repair_panel_urls(): void
{
    if (!class_exists('WhmcsXtreamAI\\Settings') || !class_exists('WhmcsXtreamAI\\PanelStore')) {
        return;
    }
    try {
        if (\WhmcsXtreamAI\Settings::get('url_htmlentities_cleanup_v1') === '1') {
            return;
        }
        foreach (\WhmcsXtreamAI\PanelStore::all() as $panel) {
            $original = [
                'm3u_url' => isset($panel->m3u_url) ? (string) $panel->m3u_url : '',
                'epg_url' => isset($panel->epg_url) ? (string) $panel->epg_url : '',
            ];
            $fixed = [
                'm3u_url' => xtreamai_normalize_url_entities($original['m3u_url']),
                'epg_url' => xtreamai_normalize_url_entities($original['epg_url']),
            ];
            if ($fixed !== $original) {
                \WhmcsXtreamAI\PanelStore::update((int) $panel->id, $fixed);
            }
        }
        \WhmcsXtreamAI\Settings::set('url_htmlentities_cleanup_v1', '1');
    } catch (\Throwable $e) {

    }
}

function xtreamai_render($modulelink, $view, $flash, $editId, $panelId)
{
    $h = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $token = xtreamai_token_field();

    $q             = xtreamai_clean_search($_REQUEST['q'] ?? '');
    $sq            = xtreamai_clean_search($_REQUEST['sq'] ?? '');
    $vq            = xtreamai_clean_search($_REQUEST['vq'] ?? '');
    $enabledFilter = isset($_REQUEST['enabled']) && is_scalar($_REQUEST['enabled'])
        ? (string) $_REQUEST['enabled']
        : 'all';
    if ($enabledFilter !== '1' && $enabledFilter !== '0') {
        $enabledFilter = 'all';
    }

    $cursor  = xtreamai_clean_cursor($_REQUEST['cursor'] ?? '');
    $cstack  = xtreamai_cursor_stack($_REQUEST['cstack'] ?? '');
    $vcursor = xtreamai_clean_cursor($_REQUEST['vcursor'] ?? '');
    $vcstack = xtreamai_cursor_stack($_REQUEST['vcstack'] ?? '');

    $panels = [];
    if (class_exists('WhmcsXtreamAI\\PanelStore')) {
        try {
            $panels = \WhmcsXtreamAI\PanelStore::all();
        } catch (\Throwable $e) {
            $panels = [];
        }
    }

    $form = [
        'id'         => 0,
        'name'       => '',
        'api_url'    => '',
        'm3u_url'    => '',
        'epg_url'    => '',
        'verify_ssl' => 1,
        'active'     => 1,
        'key_type'   => 'reseller',
        'admin_owner_member_id' => '',
    ];
    $editing = false;
    if ($editId > 0) {
        foreach ($panels as $panel) {
            if ((int) $panel->id === $editId) {
                $form = [
                    'id'         => (int) $panel->id,
                    'name'       => (string) $panel->name,
                    'api_url'    => (string) $panel->api_url,
                    'm3u_url'    => isset($panel->m3u_url) ? (string) $panel->m3u_url : '',
                    'epg_url'    => isset($panel->epg_url) ? (string) $panel->epg_url : '',
                    'verify_ssl' => (int) $panel->verify_ssl,
                    'active'     => (int) $panel->active,
                    'key_type'   => (string) ($panel->key_type ?? 'reseller'),
                    'admin_owner_member_id' => $panel->admin_owner_member_id ?? '',
                ];
                $editing = true;
                break;
            }
        }
    }

    $fId        = (int) $form['id'];
    $fName      = $h($form['name']);
    $fApiUrl    = $h($form['api_url']);
    $fM3uUrl    = $h($form['m3u_url']);
    $fEpgUrl    = $h($form['epg_url']);
    $fVerifySsl = $form['verify_ssl'] ? ' checked' : '';
    $fActive    = $form['active'] ? ' checked' : '';
    $fKeyType   = (string) ($form['key_type'] ?? 'reseller');
    $fKtRes     = $fKeyType === 'reseller' ? ' selected' : '';
    $fKtAdm     = $fKeyType === 'admin' ? ' selected' : '';
    $fAdminOwner = $h((string) ($form['admin_owner_member_id'] ?? ''));

    $dashboardActive = $view === 'dashboard' ? ' class="active"' : '';
    $listActive      = $view === 'list' ? ' class="active"' : '';
    $settingsActive  = $view === 'settings' ? ' class="active"' : '';
    $logsActive      = $view === 'logs' ? ' class="active"' : '';
    $resellersActive = $view === 'resellers' ? ' class="active"' : '';
    $linesActive     = $view === 'lines' ? ' class="active"' : '';
    $catalogActive   = $view === 'catalog' ? ' class="active"' : '';
    $bulkActive      = $view === 'bulk' ? ' class="active"' : '';

    $linkDashboard  = $h($modulelink);
    $linkList       = $h(xtreamai_link($modulelink, ['view' => 'list']));
    $linkSettings   = $h(xtreamai_link($modulelink, ['view' => 'settings']));
    $linkLogs       = $h(xtreamai_link($modulelink, ['view' => 'logs']));
    $linkResellers  = $h(xtreamai_link($modulelink, ['view' => 'resellers']));
    $linkLines      = $h(xtreamai_link($modulelink, ['view' => 'lines']));
    $linkCatalog    = $h(xtreamai_link($modulelink, ['view' => 'catalog']));
    $linkBulk       = $h(xtreamai_link($modulelink, ['view' => 'bulk']));
    $linkEditBase   = $h(xtreamai_link($modulelink, ['view' => 'list', 'edit' => '']));
    $flashHtml = '';
    if (!empty($flash['message'])) {
        $cls = $flash['type'] === 'success' ? 'success' : 'error';
        $flashHtml = '<div class="xtai-flash xtai-flash--' . $cls . '" role="alert">'
            . '<span class="xtai-flash__icon" aria-hidden="true">' . ($cls === 'success' ? '&#10003;' : '&#10005;') . '</span>'
            . '<span class="xtai-flash__text">' . $h($flash['message']) . '</span>'
            . '<button type="button" class="xtai-flash__close" aria-label="Dismiss">&times;</button>'
            . '</div>';
    }

    echo '<div class="xtai-wrap">
<style>
.xtai-wrap{
--xtai-primary:#2563eb;--xtai-primary-hover:#1d4ed8;--xtai-primary-soft:#eff6ff;--xtai-primary-border:#bfdbfe;
--xtai-success:#16a34a;--xtai-success-strong:#166534;--xtai-success-soft:#ecfdf5;--xtai-success-border:#bbf7d0;
--xtai-danger:#dc2626;--xtai-danger-strong:#991b1b;--xtai-danger-soft:#fef2f2;--xtai-danger-border:#fecaca;
--xtai-warning:#d97706;--xtai-warning-strong:#92400e;--xtai-warning-soft:#fffbeb;--xtai-warning-border:#fde68a;
--xtai-neutral-soft:#f3f4f6;--xtai-neutral-strong:#4b5563;--xtai-neutral-border:#e5e7eb;
--xtai-text:#1f2937;--xtai-muted:#6b7280;--xtai-border:#e5e7eb;--xtai-border-strong:#d1d5db;
--xtai-surface:#ffffff;--xtai-bg:#f8fafc;--xtai-radius:12px;--xtai-radius-sm:9px;--xtai-radius-pill:999px;
--xtai-shadow-sm:0 1px 2px rgba(16,24,40,.06);
--xtai-shadow:0 1px 2px rgba(16,24,40,.05),0 4px 12px rgba(16,24,40,.07);
--xtai-shadow-lg:0 16px 32px -12px rgba(16,24,40,.16);
--xtai-ring:0 0 0 3px rgba(37,99,235,.16);
--xtai-mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
--xtai-font:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
font-family:var(--xtai-font);font-size:14px;line-height:1.55;color:var(--xtai-text);padding:24px;max-width:1120px;box-sizing:border-box
}
.xtai-wrap *,.xtai-wrap *::before,.xtai-wrap *::after{box-sizing:border-box}
.xtai-wrap a:focus-visible,.xtai-wrap button:focus-visible,.xtai-wrap select:focus-visible{outline:none;box-shadow:var(--xtai-ring)}
.xtai-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;border-bottom:1px solid var(--xtai-border);padding-bottom:16px;margin-bottom:20px}
.xtai-head h1{margin:0;font-size:20px;font-weight:700;letter-spacing:-.01em}
.xtai-head small{color:var(--xtai-muted);font-size:12.5px;display:block;margin-top:2px}
.xtai-nav{display:inline-flex;background:var(--xtai-neutral-soft);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm);padding:3px;gap:2px}
.xtai-nav a{display:inline-block;padding:6px 14px;border-radius:7px;color:var(--xtai-muted);text-decoration:none;font-size:13px;font-weight:600;transition:background .16s ease,color .16s ease}
.xtai-nav a:hover{color:var(--xtai-text)}
.xtai-nav a.active{background:var(--xtai-surface);color:var(--xtai-primary);box-shadow:var(--xtai-shadow-sm)}
.xtai-flash{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:var(--xtai-radius-sm);margin-bottom:18px;border:1px solid;border-left-width:4px;font-size:13.5px}
.xtai-flash__icon{flex:none;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.xtai-flash__text{flex:1;min-width:0}
.xtai-flash__close{flex:none;border:none;background:transparent;color:inherit;font-size:20px;line-height:1;cursor:pointer;padding:0 4px;opacity:.6;transition:opacity .15s ease}
.xtai-flash__close:hover{opacity:1}
.xtai-flash--success{background:var(--xtai-success-soft);border-color:var(--xtai-success-border);color:var(--xtai-success-strong)}
.xtai-flash--success .xtai-flash__icon{background:var(--xtai-success);color:#fff}
.xtai-flash--error{background:var(--xtai-danger-soft);border-color:var(--xtai-danger-border);color:var(--xtai-danger-strong)}
.xtai-flash--error .xtai-flash__icon{background:var(--xtai-danger);color:#fff}
.xtai-card{background:var(--xtai-surface);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius);padding:20px;margin-bottom:20px;box-shadow:var(--xtai-shadow-sm)}
.xtai-card h2{margin:0 0 4px;font-size:16px;font-weight:700}
.xtai-card p.xtai-sub{color:var(--xtai-muted);margin:0 0 16px;font-size:13px}
.xtai-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;align-items:start;margin-bottom:2px}
.xtai-card--full{grid-column:1/-1}
.xtai-table-wrap{overflow:auto;max-height:560px;border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm);background:var(--xtai-surface)}
.xtai-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13.5px}
.xtai-table th,.xtai-table td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--xtai-border);vertical-align:top}
.xtai-table thead th{position:sticky;top:0;background:var(--xtai-bg);font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--xtai-muted);border-bottom:1px solid var(--xtai-border);z-index:1}
.xtai-table tbody tr{transition:background .15s ease}
.xtai-table tbody tr:nth-child(even){background:rgba(243,244,246,.45)}
.xtai-table tbody tr:hover{background:var(--xtai-primary-soft)}
.xtai-table tbody tr:last-child td{border-bottom:none}
.xtai-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:var(--xtai-radius-pill);font-size:12px;font-weight:600;line-height:1.6;white-space:nowrap}
.xtai-badge::before{content:"";width:7px;height:7px;border-radius:50%;flex:none;background:currentColor}
.xtai-badge--success{background:var(--xtai-success-soft);color:var(--xtai-success-strong)}
.xtai-badge--danger{background:var(--xtai-danger-soft);color:var(--xtai-danger-strong)}
.xtai-badge--warning{background:var(--xtai-warning-soft);color:var(--xtai-warning-strong)}
.xtai-badge--neutral{background:var(--xtai-neutral-soft);color:var(--xtai-neutral-strong)}
.xtai-meta{color:var(--xtai-muted);font-size:12px;margin-top:3px;word-break:break-word}
.xtai-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.xtai-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.xtai-field--full{grid-column:1/-1}
.xtai-field label,.xtai-field>span:first-child{font-weight:600;font-size:13px;color:#374151}
.xtai-field input[type=text],.xtai-field input[type=password],.xtai-field input[type=number],.xtai-field select,.xtai-field textarea{font:inherit;font-size:14px;padding:9px 12px;border:1px solid var(--xtai-border-strong);border-radius:var(--xtai-radius-sm);background:var(--xtai-surface);color:var(--xtai-text);transition:border-color .16s ease,box-shadow .16s ease;width:100%}
.xtai-field input:focus,.xtai-field select:focus,.xtai-field textarea:focus{outline:none;border-color:var(--xtai-primary);box-shadow:var(--xtai-ring)}
.xtai-field textarea{min-height:90px;resize:vertical}
.xtai-field select{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%2016%2016%22%3E%3Cpath%20fill=%22none%22%20stroke=%22%236b7280%22%20stroke-width=%222%22%20d=%22M4%206l4%204%204-4%22/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:34px}
.xtai-switch{display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-weight:500;user-select:none}
.xtai-switch input{position:absolute;opacity:0;width:1px;height:1px;overflow:hidden}
.xtai-switch__track{position:relative;flex:none;width:40px;height:22px;background:#cbd5e1;border-radius:var(--xtai-radius-pill);transition:background .18s ease}
.xtai-switch__track::after{content:"";position:absolute;top:2px;left:2px;width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 1px 2px rgba(16,24,40,.35);transition:transform .18s ease}
.xtai-switch input:checked + .xtai-switch__track{background:var(--xtai-success)}
.xtai-switch input:checked + .xtai-switch__track::after{transform:translateX(18px)}
.xtai-switch input:focus-visible + .xtai-switch__track{box-shadow:var(--xtai-ring)}
.xtai-switch__label{color:var(--xtai-text)}
.xtai-help{color:var(--xtai-muted);font-size:12px;margin:0}
.xtai-actions{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-top:20px}
.xtai-actions .xtai-foot-note{flex-basis:100%;margin:0 0 4px}
.xtai-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;border:1px solid var(--xtai-border-strong);border-radius:var(--xtai-radius-sm);background:var(--xtai-surface);color:#374151;cursor:pointer;font:inherit;font-size:13.5px;font-weight:600;text-decoration:none;transition:background .16s ease,border-color .16s ease,color .16s ease,box-shadow .16s ease,transform .16s ease;white-space:nowrap}
.xtai-btn:hover{background:var(--xtai-bg);border-color:#c2c8d0}
.xtai-btn:active{transform:translateY(1px)}
.xtai-btn--primary{background:var(--xtai-primary);border-color:var(--xtai-primary);color:#fff}
.xtai-btn--primary:hover{background:var(--xtai-primary-hover);border-color:var(--xtai-primary-hover)}
.xtai-btn--danger{color:var(--xtai-danger);border-color:var(--xtai-danger-border)}
.xtai-btn--danger:hover{background:var(--xtai-danger-soft);border-color:var(--xtai-danger)}
.xtai-btn--ghost{background:transparent;border-color:transparent;color:var(--xtai-muted)}
.xtai-btn--ghost:hover{background:var(--xtai-neutral-soft);color:var(--xtai-text)}
.xtai-btn--primary-ghost{color:var(--xtai-primary);border-color:var(--xtai-primary-border);background:var(--xtai-primary-soft)}
.xtai-btn--primary-ghost:hover{border-color:var(--xtai-primary)}
.xtai-btn--danger-ghost{color:var(--xtai-danger);border-color:var(--xtai-danger-border);background:var(--xtai-surface)}
.xtai-btn--danger-ghost:hover{background:var(--xtai-danger-soft);border-color:var(--xtai-danger)}
.xtai-btn--sm{padding:5px 10px;font-size:12.5px;border-radius:7px}
.xtai-btn.is-loading{pointer-events:none;opacity:.7}
.xtai-btn.is-loading::before{content:"";width:12px;height:12px;border:2px solid rgba(16,24,40,.25);border-top-color:currentColor;border-radius:50%;animation:xtai-spin .6s linear infinite}
@keyframes xtai-spin{to{transform:rotate(360deg)}}
.xtai-btn--regenerate .xtai-icon-refresh{display:inline-block;transition:transform .2s ease}
.xtai-btn--regenerate:hover .xtai-icon-refresh{transform:rotate(180deg)}
.xtai-inline-form{display:inline-flex}
.xtai-row-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.xtai-test-result{font-size:12.5px;color:var(--xtai-muted)}
.xtai-test-result--ok{color:var(--xtai-success-strong)}
.xtai-test-result--err{color:var(--xtai-danger-strong)}
.xtai-log{white-space:pre-wrap;word-break:break-all;margin:0;font-family:var(--xtai-mono);font-size:12px;line-height:1.5;color:var(--xtai-text);background:var(--xtai-bg);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm);padding:8px 10px;max-width:460px;overflow:auto;max-height:130px}
.xtai-preview{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.xtai-preview-code{font-family:var(--xtai-mono);background:var(--xtai-bg);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm);padding:7px 12px;min-width:150px;min-height:20px;color:var(--xtai-text);word-break:break-all;font-size:13px}
.xtai-tags{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 12px}
.xtai-tag{display:inline-block;background:var(--xtai-primary-soft);color:#3730a3;border:1px solid var(--xtai-primary-border);border-radius:var(--xtai-radius-pill);padding:3px 11px;font-size:12px;font-family:var(--xtai-mono);cursor:default;transition:background .16s ease,transform .16s ease}
.xtai-tag:hover{background:#e0e7ff;transform:translateY(-1px)}
.xtai-example{display:flex;flex-wrap:wrap;align-items:center;gap:8px;background:var(--xtai-primary-soft);border:1px dashed var(--xtai-primary-border);border-radius:var(--xtai-radius-sm);padding:12px 14px;color:#1e3a8a;font-size:13px}
.xtai-example__value{font-family:var(--xtai-mono);font-weight:600}
.xtai-empty{text-align:center;padding:44px 20px;color:var(--xtai-muted)}
.xtai-empty__icon{font-size:34px;line-height:1;margin-bottom:10px;display:block}
.xtai-empty__title{font-weight:600;color:var(--xtai-text);margin:0 0 4px}
.xtai-empty p{margin:0;font-size:13.5px}
.xtai-foot-note{color:var(--xtai-muted);font-size:12px;margin-top:14px}
.xtai-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.xtai-panel-switch{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--xtai-muted)}
.xtai-panel-switch select{font:inherit;font-size:13px;padding:7px 32px 7px 12px;border:1px solid var(--xtai-border-strong);border-radius:var(--xtai-radius-sm);background:var(--xtai-surface) url("data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%2016%2016%22%3E%3Cpath%20fill=%22none%22%20stroke=%22%236b7280%22%20stroke-width=%222%22%20d=%22M4%206l4%204%204-4%22/%3E%3C/svg%3E") no-repeat right 12px center;color:var(--xtai-text);appearance:none;-webkit-appearance:none;cursor:pointer;max-width:240px}
.xtai-credits{font-family:var(--xtai-mono);font-size:13px}
.xtai-actions-inline{display:inline-flex;flex-wrap:wrap;gap:6px;align-items:center}
.xtai-actions-inline input[type=number],.xtai-actions-inline input[type=text]{font:inherit;font-size:12.5px;padding:5px 8px;border:1px solid var(--xtai-border-strong);border-radius:7px;background:var(--xtai-surface);color:var(--xtai-text)}
.xtai-actions-inline input[type=number]{width:90px}
.xtai-actions-inline input[type=text]{width:130px}
.xtai-error{display:flex;align-items:center;gap:10px;padding:14px;border-radius:var(--xtai-radius-sm);background:var(--xtai-danger-soft);border:1px solid var(--xtai-danger-border);border-left-width:4px;color:var(--xtai-danger-strong);font-size:13.5px}
.xtai-error__icon{flex:none;width:20px;height:20px;border-radius:50%;background:var(--xtai-danger);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.xtai-stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px}
.xtai-stat{background:var(--xtai-surface);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius);padding:18px 20px;box-shadow:var(--xtai-shadow-sm)}
.xtai-stat__label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--xtai-muted);margin:0 0 8px}
.xtai-stat__value{font-family:var(--xtai-mono);font-size:26px;font-weight:700;line-height:1.1;color:var(--xtai-text);word-break:break-word}
.xtai-stat__sub{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12.5px;color:var(--xtai-muted);margin-top:10px}
.xtai-quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.xtai-quick{display:block;background:var(--xtai-surface);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius);padding:16px 18px;text-decoration:none;color:var(--xtai-text);box-shadow:var(--xtai-shadow-sm);transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}
.xtai-quick:hover{border-color:var(--xtai-primary-border);box-shadow:var(--xtai-shadow);transform:translateY(-1px)}
.xtai-quick__title{display:block;font-weight:700;font-size:14px;color:var(--xtai-text)}
.xtai-quick__desc{display:block;color:var(--xtai-muted);font-size:12.5px;margin-top:3px}
.xtai-catalog-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;align-items:start}
.xtai-catalog-grid .xtai-card{margin-bottom:0}
.xtai-filter-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 18px;padding:12px 14px;background:var(--xtai-bg);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm)}
.xtai-filter-bar input[type=text]{font:inherit;font-size:13.5px;padding:8px 12px;border:1px solid var(--xtai-border-strong);border-radius:var(--xtai-radius-sm);background:var(--xtai-surface);color:var(--xtai-text);flex:1 1 180px;min-width:120px}
.xtai-filter-bar select{font:inherit;font-size:13.5px;padding:8px 32px 8px 12px;border:1px solid var(--xtai-border-strong);border-radius:var(--xtai-radius-sm);background:var(--xtai-surface) url("data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%2016%2016%22%3E%3Cpath%20fill=%22none%22%20stroke=%22%236b7280%22%20stroke-width=%222%22%20d=%22M4%206l4%204%204-4%22/%3E%3C/svg%3E") no-repeat right 12px center;color:var(--xtai-text);appearance:none;-webkit-appearance:none;cursor:pointer}
.xtai-thumb{width:24px;height:24px;object-fit:contain;border:1px solid var(--xtai-border);border-radius:4px;background:var(--xtai-neutral-soft);display:inline-block;vertical-align:middle;line-height:24px;text-align:center;color:var(--xtai-muted);font-size:11px}
.xtai-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:14px;padding-top:12px;border-top:1px solid var(--xtai-border)}
.xtai-pagination__count{color:var(--xtai-muted);font-size:12.5px}
.xtai-pagination__more{color:var(--xtai-primary);font-weight:600}
.xtai-pagination__nav{display:flex;gap:8px;align-items:center}
.xtai-warn{display:flex;align-items:flex-start;gap:10px;margin:0 0 16px;padding:12px 14px;border:1px solid var(--xtai-warning-border);border-left-width:4px;border-radius:var(--xtai-radius-sm);background:var(--xtai-warning-soft);color:var(--xtai-warning-strong);font-size:13px}
.xtai-warn__icon{flex:none;width:20px;height:20px;border-radius:50%;background:var(--xtai-warning);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.xtai-bulk-progress{margin:16px 0 0}
.xtai-bulk-bar{position:relative;height:8px;border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-pill);background:var(--xtai-neutral-soft);overflow:hidden}
.xtai-bulk-bar-fill{display:block;height:100%;width:0;border-radius:var(--xtai-radius-pill);background:var(--xtai-primary);transition:width .25s ease,background .25s ease}
.xtai-bulk-bar.is-running .xtai-bulk-bar-fill{width:38%;animation:xtai-bulk-slide 1.1s ease-in-out infinite}
.xtai-bulk-bar.is-done .xtai-bulk-bar-fill{width:100%;background:var(--xtai-success)}
.xtai-bulk-bar.is-error .xtai-bulk-bar-fill{width:100%;background:var(--xtai-danger)}
@keyframes xtai-bulk-slide{0%{margin-left:0}50%{margin-left:62%}100%{margin-left:0}}
.xtai-bulk-progress-text{margin:8px 0 0;color:var(--xtai-muted);font-size:12.5px}
.xtai-bulk-counters{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 0}
.xtai-bulk-counter{display:inline-flex;align-items:center;gap:6px;padding:3px 11px;border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-pill);background:var(--xtai-neutral-soft);color:var(--xtai-neutral-strong);font-size:12px}
.xtai-bulk-counter strong{font-family:var(--xtai-mono);color:var(--xtai-text)}
.xtai-table-wrap--bulk{max-height:320px;margin-top:14px}
.xtai-bulk-empty{color:var(--xtai-muted);font-size:13px;text-align:center}
.xtai-update__notes{white-space:pre-wrap;word-break:break-word;font-family:var(--xtai-mono);font-size:12px;line-height:1.55;color:var(--xtai-text);background:var(--xtai-bg);border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm);padding:10px 12px;margin:0 0 16px;max-height:220px;overflow:auto}
.xtai-update__manual{margin:14px 0 0;padding:12px 14px;border:1px solid var(--xtai-border);border-radius:var(--xtai-radius-sm);background:var(--xtai-bg);font-size:12.5px;color:var(--xtai-muted)}
.xtai-update__manual code{font-family:var(--xtai-mono);color:var(--xtai-text);word-break:break-all}
@media (max-width:900px){
.xtai-wrap{padding:16px}
.xtai-settings-grid{grid-template-columns:1fr}
.xtai-form-grid{grid-template-columns:1fr}
.xtai-head{flex-direction:column;align-items:flex-start}
.xtai-nav{width:100%;overflow-x:auto}
.xtai-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.xtai-quick-grid{grid-template-columns:1fr}
.xtai-catalog-grid{grid-template-columns:1fr}
}
@media (max-width:560px){
.xtai-stats-grid{grid-template-columns:1fr}
}
</style>
<div class="xtai-head">
<div><h1>Xtream AI Panel</h1><small>Manage panels, connections and provisioning settings</small></div>
<nav class="xtai-nav"><a href="' . $linkDashboard . '"' . $dashboardActive . '>Dashboard</a><a href="' . $linkList . '"' . $listActive . '>Panels</a><a href="' . $linkResellers . '"' . $resellersActive . '>Sub-Resellers</a><a href="' . $linkLines . '"' . $linesActive . '>Lines</a><a href="' . $linkBulk . '"' . $bulkActive . '>Bulk tools</a><a href="' . $linkCatalog . '"' . $catalogActive . '>Catalog</a><a href="' . $linkLogs . '"' . $logsActive . '>Module Logs</a><a href="' . $linkSettings . '"' . $settingsActive . '>General Settings</a></nav>
</div>
' . $flashHtml;

    if ($view === 'dashboard') {
        echo xtreamai_update_panel($modulelink, $token, $h);

        $activePanels = [];
        if (class_exists('WhmcsXtreamAI\\PanelStore')) {
            try {
                $activePanels = \WhmcsXtreamAI\PanelStore::allActive();
            } catch (\Throwable $e) {
                $activePanels = [];
            }
        }
        $firstActiveId = !empty($activePanels) ? (int) $activePanels[0]->id : 0;

        $creditsValue = '-';
        if ($firstActiveId > 0) {
            try {
                $credits = \WhmcsXtreamAI\PanelApi::credits($firstActiveId);
                $creditsValue = $credits !== null ? $credits : '-';
            } catch (\Throwable $e) {
                $creditsValue = '-';
            }
        }

        $resellersValue = '-';
        if ($firstActiveId > 0) {
            try {
                $resellersPage = \WhmcsXtreamAI\PanelApi::resellersPage($firstActiveId);
                $resellersValue = (string) count($resellersPage['items']);
                if ($resellersPage['next_cursor'] !== null) {
                    $resellersValue .= '+';
                }
            } catch (\Throwable $e) {
                $resellersValue = '-';
            }
        }

        $linesValue = '-';
        if ($firstActiveId > 0) {
            try {
                $linesPage = \WhmcsXtreamAI\PanelApi::linesPage($firstActiveId);
                $linesValue = (string) count($linesPage['items']);
                if ($linesPage['next_cursor'] !== null) {
                    $linesValue .= '+';
                }
            } catch (\Throwable $e) {
                $linesValue = '-';
            }
        }

        $panelsTotal = count($panels);
        $panelsOk    = 0;
        foreach ($panels as $panel) {
            if ((int) $panel->last_ok === 1) {
                $panelsOk++;
            }
        }
        $panelsErr = $panelsTotal - $panelsOk;

        echo '<div class="xtai-stats-grid">'
            . '<div class="xtai-stat"><p class="xtai-stat__label">Credits</p><div class="xtai-stat__value">' . $h($creditsValue) . '</div><div class="xtai-stat__sub">First active panel</div></div>'
            . '<div class="xtai-stat"><p class="xtai-stat__label">Panels</p><div class="xtai-stat__value">' . $panelsTotal . '</div><div class="xtai-stat__sub"><span class="xtai-badge xtai-badge--success">' . $panelsOk . ' ok</span><span class="xtai-badge xtai-badge--danger">' . $panelsErr . ' error</span></div></div>'
            . '<div class="xtai-stat"><p class="xtai-stat__label">Sub-Resellers</p><div class="xtai-stat__value">' . $h($resellersValue) . '</div><div class="xtai-stat__sub">First active panel</div></div>'
            . '<div class="xtai-stat"><p class="xtai-stat__label">Lines</p><div class="xtai-stat__value">' . $h($linesValue) . '</div><div class="xtai-stat__sub">First active panel</div></div>'
            . '</div>';

        echo '<section class="xtai-card"><h2>Panels status</h2><p class="xtai-sub">Health of each connected panel.</p>';
        if (count($panels) === 0) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No panels yet</p><p>Add your first panel to start monitoring.</p></div>';
        } else {
            echo '<div class="xtai-table-wrap"><table class="xtai-table">'
                . '<thead><tr><th>Panel</th><th>Status</th><th>Last message</th><th>Last check</th><th></th></tr></thead>'
                . '<tbody>';
            foreach ($panels as $panel) {
                $pid       = (int) $panel->id;
                $pname     = $h($panel->name);
                $lastOk    = (int) $panel->last_ok;
                $pchecked  = $h($panel->last_checked ?? '');
                $pmessage  = $h($panel->last_message ?? '');

                if ($pchecked === '') {
                    $statusHtml = '<span class="xtai-badge xtai-badge--neutral">Not tested</span>';
                } else {
                    $statusHtml = $lastOk === 1
                        ? '<span class="xtai-badge xtai-badge--success">Connected</span>'
                        : '<span class="xtai-badge xtai-badge--danger">Error</span>';
                }

                echo '<tr>'
                    . '<td><strong>' . $pname . '</strong></td>'
                    . '<td>' . $statusHtml . '</td>'
                    . '<td class="xtai-meta">' . $pmessage . '</td>'
                    . '<td class="xtai-meta">' . $pchecked . '</td>'
                    . '<td><button type="button" class="xtai-btn xtai-btn--sm xtai-btn--ghost xtai-test-btn" data-id="' . $pid . '">Test</button> <span class="xtai-test-result" id="xtai-result-' . $pid . '"></span></td>'
                    . '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '<section class="xtai-card"><h2>Quick links</h2><p class="xtai-sub">Jump to the most used sections.</p>'
            . '<div class="xtai-quick-grid">'
            . '<a class="xtai-quick" href="' . $linkList . '"><span class="xtai-quick__title">Panels</span><span class="xtai-quick__desc">Manage connected panels</span></a>'
            . '<a class="xtai-quick" href="' . $linkResellers . '"><span class="xtai-quick__title">Sub-Resellers</span><span class="xtai-quick__desc">Manage sub-resellers and credits</span></a>'
            . '<a class="xtai-quick" href="' . $linkLines . '"><span class="xtai-quick__title">Lines</span><span class="xtai-quick__desc">Browse and filter lines</span></a>'
            . '<a class="xtai-quick" href="' . $linkCatalog . '"><span class="xtai-quick__title">Catalog</span><span class="xtai-quick__desc">Live streams and VOD</span></a>'
            . '<a class="xtai-quick" href="' . $linkLogs . '"><span class="xtai-quick__title">Module Logs</span><span class="xtai-quick__desc">Recent API activity</span></a>'
            . '<a class="xtai-quick" href="' . $linkSettings . '"><span class="xtai-quick__title">General Settings</span><span class="xtai-quick__desc">Credential generation settings</span></a>'
            . '</div></section>';
    } elseif ($view === 'settings') {
        $cred = [
            'username_auto'   => '1',
            'username_prefix' => '',
            'username_length' => '8',
            'username_type'   => 'numeric',
            'password_auto'   => '1',
            'password_length' => '10',
            'password_type'   => 'numeric',
            'reseller_notes'  => 'WHMCS:{service_id}',
        ];
        if (class_exists('WhmcsXtreamAI\\Settings')) {
            try {
                $cred = \WhmcsXtreamAI\Settings::credentialSettings();
            } catch (\Throwable $e) {

            }
        }

        $usernameAutoChecked = $cred['username_auto'] === '1' ? ' checked' : '';
        $passwordAutoChecked = $cred['password_auto'] === '1' ? ' checked' : '';
        $usernameLengthE = $h($cred['username_length']);
        $passwordLengthE = $h($cred['password_length']);
        $usernamePrefixE = $h($cred['username_prefix']);
        $notesE          = $h($cred['reseller_notes']);

        $typeOptions = static function (string $current) {
            $options = [
                'numeric'      => 'Numeric (0-9)',
                'alpha'        => 'Alphabetic (a-zA-Z)',
                'alphanumeric' => 'Alphanumeric',
            ];
            $out = '';
            foreach ($options as $value => $label) {
                $selected = $current === $value ? ' selected' : '';
                $out .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
            }
            return $out;
        };

        $sampleTags = [
            '{service_id}'         => '135',
            '{client_id}'          => '42',
            '{client_name}'        => 'John Doe',
            '{client_email}'       => 'john@example.com',
            '{client_phonenumber}' => '+1 555 0100',
            '{product_name}'       => 'IPTV Basic',
        ];
        $exampleInitial = $h(strtr((string) $cred['reseller_notes'], $sampleTags));

        echo '<form method="post" action="' . $linkList . '">'
            . $token
            . '<input type="hidden" name="action" value="save_settings">'
            . '<div class="xtai-settings-grid">'
            . '<section class="xtai-card">'
            . '<h2>Username Generator</h2>'
            . '<p class="xtai-sub">Configure how usernames for new lines are created.</p>'
            . '<div class="xtai-form-grid">'
            . '<div class="xtai-field"><label for="xtai-username-length">Username Length</label>'
            . '<input type="number" id="xtai-username-length" name="username_length" min="4" max="32" value="' . $usernameLengthE . '">'
            . '<span class="xtai-help">Suggested 8 to 12</span></div>'
            . '<div class="xtai-field"><label for="xtai-username-type">Character Type</label>'
            . '<select id="xtai-username-type" name="username_type">' . $typeOptions($cred['username_type']) . '</select></div>'
            . '<div class="xtai-field xtai-field--full"><label>Preview</label>'
            . '<div class="xtai-preview"><span class="xtai-preview-code" id="xtai-username-preview"></span>'
            . '<button type="button" class="xtai-btn xtai-btn--sm xtai-btn--regenerate" data-preview="username"><span class="xtai-icon-refresh" aria-hidden="true">&#8635;</span> Regenerate</button></div></div>'
            . '<div class="xtai-field"><label>Auto Generate</label>'
            . '<input type="hidden" name="username_auto" value="0">'
            . '<label class="xtai-switch"><input type="checkbox" name="username_auto" value="1"' . $usernameAutoChecked . '><span class="xtai-switch__track"></span><span class="xtai-switch__label">Yes</span></label>'
            . '<span class="xtai-help">When No, an existing username is reused when it is long enough.</span></div>'
            . '<div class="xtai-field"><label for="xtai-username-prefix">Prefix (optional)</label>'
            . '<input type="text" id="xtai-username-prefix" name="username_prefix" maxlength="10" value="' . $usernamePrefixE . '" placeholder="e.g. ip">'
            . '<span class="xtai-help">Letters, digits, dash and underscore only.</span></div>'
            . '</div>'
            . '</section>'
            . '<section class="xtai-card">'
            . '<h2>Password Generator</h2>'
            . '<p class="xtai-sub">Configure how passwords for new lines are created.</p>'
            . '<div class="xtai-form-grid">'
            . '<div class="xtai-field"><label for="xtai-password-length">Password Length</label>'
            . '<input type="number" id="xtai-password-length" name="password_length" min="8" max="32" value="' . $passwordLengthE . '">'
            . '<span class="xtai-help">Recommended 10+ characters</span></div>'
            . '<div class="xtai-field"><label for="xtai-password-type">Character Type</label>'
            . '<select id="xtai-password-type" name="password_type">' . $typeOptions($cred['password_type']) . '</select></div>'
            . '<div class="xtai-field xtai-field--full"><label>Preview</label>'
            . '<div class="xtai-preview"><span class="xtai-preview-code" id="xtai-password-preview"></span>'
            . '<button type="button" class="xtai-btn xtai-btn--sm xtai-btn--regenerate" data-preview="password"><span class="xtai-icon-refresh" aria-hidden="true">&#8635;</span> Regenerate</button></div></div>'
            . '<div class="xtai-field"><label>Auto Generate</label>'
            . '<input type="hidden" name="password_auto" value="0">'
            . '<label class="xtai-switch"><input type="checkbox" name="password_auto" value="1"' . $passwordAutoChecked . '><span class="xtai-switch__track"></span><span class="xtai-switch__label">Yes</span></label>'
            . '<span class="xtai-help">When No, an existing password is reused when it is long enough.</span></div>'
            . '</div>'
            . '</section>'
            . '<section class="xtai-card xtai-card--full">'
            . '<h2>Line Notes Template</h2>'
            . '<p class="xtai-sub">Template used for the notes attached to each new line.</p>'
            . '<div class="xtai-field xtai-field--full"><label for="xtai-reseller-notes">Reseller notes</label>'
            . '<textarea id="xtai-reseller-notes" name="reseller_notes" maxlength="2000">' . $notesE . '</textarea></div>'
            . '<div class="xtai-tags">'
            . '<span class="xtai-tag">{service_id}</span><span class="xtai-tag">{client_id}</span>'
            . '<span class="xtai-tag">{client_name}</span><span class="xtai-tag">{client_email}</span>'
            . '<span class="xtai-tag">{client_phonenumber}</span><span class="xtai-tag">{product_name}</span>'
            . '</div>'
            . '<div class="xtai-example"><strong>Example:</strong> <span class="xtai-tag">WHMCS:{service_id}</span> &rarr; <span id="xtai-notes-example" class="xtai-example__value">' . $exampleInitial . '</span></div>'
            . '</section>'
            . '</div>'
            . '<div class="xtai-actions"><p class="xtai-foot-note">These settings affect future generated usernames/passwords.</p>'
            . '<button type="submit" class="xtai-btn xtai-btn--primary">Save Settings</button></div>'
            . '</form>';

        echo <<<'JS'
<script>
(function () {
    var alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    var charsets = {
        numeric: '0123456789',
        alpha: alpha,
        alphanumeric: alpha + '23456789'
    };
    var noteTags = {
        '{service_id}': '135',
        '{client_id}': '42',
        '{client_name}': 'John Doe',
        '{client_email}': 'john@example.com',
        '{client_phonenumber}': '+1 555 0100',
        '{product_name}': 'IPTV Basic'
    };

    function previewRandom(len, type) {
        var set = charsets[type] || charsets.alphanumeric;
        var out = '';
        for (var i = 0; i < len; i++) {
            out += set.charAt(Math.floor(Math.random() * set.length));
        }
        return out;
    }

    function renderPreview(prefix) {
        var lenEl = document.getElementById('xtai-' + prefix + '-length');
        var typeEl = document.getElementById('xtai-' + prefix + '-type');
        var outEl = document.getElementById('xtai-' + prefix + '-preview');
        if (!lenEl || !typeEl || !outEl) { return; }
        var len = parseInt(lenEl.value, 10);
        if (isNaN(len) || len < 1) { len = 1; }
        outEl.textContent = previewRandom(len, typeEl.value);
    }

    function renderNotes() {
        var ta = document.getElementById('xtai-reseller-notes');
        var out = document.getElementById('xtai-notes-example');
        if (!ta || !out) { return; }
        var t = ta.value || '';
        Object.keys(noteTags).forEach(function (k) {
            t = t.split(k).join(noteTags[k]);
        });
        out.textContent = t;
    }

    function bindPreview(prefix) {
        var lenEl = document.getElementById('xtai-' + prefix + '-length');
        var typeEl = document.getElementById('xtai-' + prefix + '-type');
        if (lenEl) { lenEl.addEventListener('input', function () { renderPreview(prefix); }); }
        if (typeEl) { typeEl.addEventListener('change', function () { renderPreview(prefix); }); }
        var btn = document.querySelector('[data-preview="' + prefix + '"]');
        if (btn) { btn.addEventListener('click', function () { renderPreview(prefix); }); }
    }

    bindPreview('username');
    bindPreview('password');
    var notesEl = document.getElementById('xtai-reseller-notes');
    if (notesEl) { notesEl.addEventListener('input', renderNotes); }

    renderPreview('username');
    renderPreview('password');
    renderNotes();
})();
</script>
JS;
    } elseif ($view === 'logs') {
        $before = isset($_REQUEST['before']) && is_scalar($_REQUEST['before']) ? (int) $_REQUEST['before'] : 0;

        $logs = [];
        try {
            $logsQuery = \WHMCS\Database\Capsule::table('tblmodulelog')
                ->where('module', 'xtreamai');
            if ($before > 0) {
                $logsQuery->where('id', '<', $before);
            }
            $logs = $logsQuery->orderBy('id', 'desc')->limit(100)->get();
        } catch (\Throwable $e) {
            $logs = [];
        }

        $logsOlder = 0;
        $logsNewer = 0;
        if (count($logs) > 0) {
            $logsFirstId = (int) $logs[0]->id;
            $logsLastId  = (int) $logs[count($logs) - 1]->id;
            if (count($logs) === 100) {
                $logsOlder = $logsLastId;
            }
            $logsNewer = $logsFirstId + 100;
        }

        echo '<div class="xtai-card">
<h2>Module Logs</h2>
<p class="xtai-sub">Latest Xtream AI API activity recorded by WHMCS.</p>';
        if (count($logs) === 0) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128231;</span><p class="xtai-empty__title">No logs yet</p><p>No Xtream AI logs found yet. Activity will appear here once the module makes its first API call.</p></div>';
        } else {
            echo '<div class="xtai-table-wrap"><table class="xtai-table">
<thead><tr><th>Date</th><th>Action</th><th>Request</th><th>Response</th></tr></thead>
<tbody>';
            foreach ($logs as $row) {
                $actionClass = 'neutral';
                $actionLower  = strtolower((string) ($row->action ?? ''));
                if (strpos($actionLower, 'error') !== false || strpos($actionLower, 'fail') !== false) {
                    $actionClass = 'danger';
                } elseif (in_array($actionLower, ['create', 'suspend', 'unsuspend', 'terminate', 'renew'], true)) {
                    $actionClass = 'success';
                }
                echo '<tr><td class="xtai-meta">' . $h($row->date ?? '') . '</td>'
                    . '<td><span class="xtai-badge xtai-badge--' . $actionClass . '">' . $h($row->action ?? '') . '</span></td>'
                    . '<td><pre class="xtai-log">' . $h(xtreamai_truncate((string) ($row->request ?? ''), 500)) . '</pre></td>'
                    . '<td><pre class="xtai-log">' . $h(xtreamai_truncate((string) ($row->response ?? ''), 500)) . '</pre></td></tr>';
            }
            echo '</tbody></table></div>';

            $logsPrev = '';
            $logsNext = '';
            if ($logsNewer > 0) {
                $logsPrev = '<a class="xtai-btn xtai-btn--sm" href="' . $h(xtreamai_link($modulelink, ['view' => 'logs', 'before' => $logsNewer])) . '">&larr; Newer</a>';
            }
            if ($logsOlder > 0) {
                $logsNext = '<a class="xtai-btn xtai-btn--sm xtai-btn--primary-ghost" href="' . $h(xtreamai_link($modulelink, ['view' => 'logs', 'before' => $logsOlder])) . '">Older &rarr;</a>';
            }
            echo '<div class="xtai-pagination"><span class="xtai-pagination__count">Showing ' . count($logs) . ' entries</span>'
                . '<nav class="xtai-pagination__nav">' . $logsPrev . $logsNext . '</nav></div>';
        }
        echo '</div>';
    } elseif ($view === 'resellers') {
        $activePanels = [];
        foreach ($panels as $panel) {
            if ((int) $panel->active === 1) {
                $activePanels[] = $panel;
            }
        }

        $selectedPanelId = 0;
        if ($panelId > 0) {
            foreach ($activePanels as $panel) {
                if ((int) $panel->id === $panelId) {
                    $selectedPanelId = $panelId;
                    break;
                }
            }
        }
        if ($selectedPanelId === 0 && !empty($activePanels)) {
            $selectedPanelId = (int) $activePanels[0]->id;
        }

        $panelOptions = '';
        foreach ($activePanels as $panel) {
            $pid = (int) $panel->id;
            $selected = $pid === $selectedPanelId ? ' selected' : '';
            $panelOptions .= '<option value="' . $pid . '"' . $selected . '>' . $h($panel->name) . '</option>';
        }

        echo '<div class="xtai-card">'
            . '<div class="xtai-card-head">'
            . '<div><h2>Sub-Resellers</h2><p class="xtai-sub">Sub-resellers and their credit balances on the selected panel.</p></div>';

        if (!empty($activePanels)) {
            echo '<form method="get" action="' . $linkList . '" class="xtai-panel-switch">'
                . '<input type="hidden" name="view" value="resellers">'
                . '<label for="xtai-panel-select">Panel</label>'
                . '<select id="xtai-panel-select" name="panel_id" onchange="this.form.submit()">'
                . $panelOptions
                . '</select>'
                . '</form>';
        }

        echo '</div>';

        if (empty($activePanels)) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No active panels</p><p>Activate a panel first to manage its sub-resellers.</p></div>';
        } elseif ($selectedPanelId < 1) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No active panels</p><p>Activate a panel first to manage its sub-resellers.</p></div>';
        } else {
            $resellers    = [];
            $resellersNext = null;
            $loadError     = '';
            try {
                $page = \WhmcsXtreamAI\PanelApi::resellersPage($selectedPanelId, $cursor !== '' ? $cursor : null);
                $resellers     = $page['items'];
                $resellersNext = $page['next_cursor'];
            } catch (\Throwable $e) {
                $loadError = xtreamai_safe_message($e);
            }

            if ($loadError !== '') {
                echo '<div class="xtai-error" role="alert"><span class="xtai-error__icon" aria-hidden="true">&#10005;</span><span>Could not load sub-resellers: ' . $h($loadError) . '</span></div>';
            } elseif (count($resellers) === 0) {
                echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128101;</span><p class="xtai-empty__title">No sub-resellers on this panel yet</p><p>Create a sub-reseller on the panel to manage it here.</p></div>';
            } else {
                echo '<div class="xtai-table-wrap"><table class="xtai-table">'
                    . '<thead><tr><th>Username</th><th>Email</th><th>Group</th><th>Status</th><th>Credits</th><th>Actions</th></tr></thead>'
                    . '<tbody>';
                foreach ($resellers as $row) {
                    $rid       = (int) ($row['id'] ?? 0);
                    $rusername = $h((string) ($row['username'] ?? ''));
                    $remail    = $h((string) ($row['email'] ?? ''));
                    $rgroup    = $h((string) ($row['group'] ?? ''));
                    $rcredits  = (string) ($row['credits'] ?? '');
                    $rstatus   = !empty($row['status']);

                    $statusHtml = $rstatus
                        ? '<span class="xtai-badge xtai-badge--success">Active</span>'
                        : '<span class="xtai-badge xtai-badge--neutral">Disabled</span>';

                    $creditsHtml = $rcredits !== ''
                        ? '<span class="xtai-credits">' . $h($rcredits) . '</span>'
                        : '<span class="xtai-meta">—</span>';

                    $groupHtml = $rgroup !== '' ? $rgroup : '<span class="xtai-meta">—</span>';

                    echo '<tr>'
                        . '<td><strong>' . $rusername . '</strong></td>'
                        . '<td>' . $remail . '</td>'
                        . '<td>' . $groupHtml . '</td>'
                        . '<td>' . $statusHtml . '</td>'
                        . '<td>' . $creditsHtml . '</td>'
                        . '<td><form method="post" action="' . $linkList . '" class="xtai-inline-form xtai-actions-inline">'
                        . $token
                        . '<input type="hidden" name="action" value="adjust_credits">'
                        . '<input type="hidden" name="panel_id" value="' . $selectedPanelId . '">'
                        . '<input type="hidden" name="reseller_id" value="' . $rid . '">'
                        . '<input type="number" name="delta" step="0.01" required aria-label="Credit delta" placeholder="± credits">'
                        . '<input type="text" name="reason" placeholder="Reason" maxlength="120" aria-label="Reason">'
                        . '<button type="submit" class="xtai-btn xtai-btn--sm xtai-btn--primary">Apply</button>'
                        . '</form></td>'
                        . '</tr>';
                }
                echo '</tbody></table></div>';
                echo xtreamai_pager(
                    $modulelink,
                    ['view' => 'resellers', 'panel_id' => $selectedPanelId],
                    'cursor',
                    'cstack',
                    $cursor,
                    $cstack,
                    $resellersNext,
                    count($resellers),
                    $h
                );
            }
        }
        echo '</div>';
    } elseif ($view === 'lines') {
        [$activePanels, $selectedPanelId, $panelOptions] = xtreamai_panel_selector($panels, $panelId, $h);

        $qE         = $h($q);
        $enabledAll = $enabledFilter === 'all' ? ' selected' : '';
        $enabledOn  = $enabledFilter === '1' ? ' selected' : '';
        $enabledOff = $enabledFilter === '0' ? ' selected' : '';

        echo '<div class="xtai-card">'
            . '<div class="xtai-card-head">'
            . '<div><h2>Lines</h2><p class="xtai-sub">Lines provisioned on the selected panel.</p></div>';

        if (!empty($activePanels)) {
            echo '<form method="get" action="' . $linkList . '" class="xtai-panel-switch">'
                . '<input type="hidden" name="view" value="lines">'
                . '<label for="xtai-panel-select">Panel</label>'
                . '<select id="xtai-panel-select" name="panel_id" onchange="this.form.submit()">'
                . $panelOptions
                . '</select>'
                . '</form>';
        }

        echo '</div>';

        if (empty($activePanels) || $selectedPanelId < 1) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No active panels</p><p>Activate a panel first to browse its lines.</p></div>';
        } else {
            echo '<form method="get" action="' . $linkList . '" class="xtai-filter-bar">'
                . '<input type="hidden" name="view" value="lines">'
                . '<input type="hidden" name="panel_id" value="' . $selectedPanelId . '">'
                . '<input type="text" name="q" value="' . $qE . '" placeholder="Username contains…" maxlength="80" aria-label="Username filter">'
                . '<select name="enabled" aria-label="Status filter">'
                . '<option value="all"' . $enabledAll . '>All statuses</option>'
                . '<option value="1"' . $enabledOn . '>Enabled</option>'
                . '<option value="0"' . $enabledOff . '>Disabled</option>'
                . '</select>'
                . '<button type="submit" class="xtai-btn xtai-btn--sm">Filter</button>'
                . '</form>';

            $lines     = [];
            $linesNext = null;
            $loadError = '';
            try {
                $page = \WhmcsXtreamAI\PanelApi::linesPage(
                    $selectedPanelId,
                    $q !== '' ? $q : null,
                    $enabledFilter === 'all' ? null : ($enabledFilter === '1'),
                    $cursor !== '' ? $cursor : null
                );
                $lines     = $page['items'];
                $linesNext = $page['next_cursor'];
            } catch (\Throwable $e) {
                $loadError = xtreamai_safe_message($e);
            }

            if ($loadError !== '') {
                echo '<div class="xtai-error" role="alert"><span class="xtai-error__icon" aria-hidden="true">&#10005;</span><span>Could not load lines: ' . $h($loadError) . '</span></div>';
            } elseif (count($lines) === 0) {
                echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128279;</span><p class="xtai-empty__title">No lines found</p><p>No lines match the current filter on this panel.</p></div>';
            } else {
                echo '<div class="xtai-table-wrap"><table class="xtai-table">'
                    . '<thead><tr><th>Username</th><th>Status</th><th>Trial</th><th>Max Conn</th><th>Expires</th></tr></thead>'
                    . '<tbody>';
                foreach ($lines as $row) {
                    $lusername = $h((string) ($row['username'] ?? ''));
                    $ltrial    = !empty($row['is_trial']);
                    $lmax      = (string) ($row['max_connections'] ?? '');
                    $lexpires  = $h((string) ($row['expires_at'] ?? ''));

                    $lstatus    = \WhmcsXtreamAI\LineStatus::fromPanel($row);
                    $statusHtml = '<span class="xtai-badge ' . \WhmcsXtreamAI\LineStatus::badgeClass($lstatus) . '">' . $h($lstatus) . '</span>';

                    $trialHtml = $ltrial
                        ? '<span class="xtai-badge xtai-badge--warning">Trial</span>'
                        : '<span class="xtai-meta">—</span>';

                    $maxHtml     = $lmax !== '' ? $lmax : '<span class="xtai-meta">—</span>';
                    $expiresHtml = $lexpires !== '' ? $lexpires : '<span class="xtai-meta">—</span>';

                    echo '<tr>'
                        . '<td><strong class="xtai-credits">' . $lusername . '</strong></td>'
                        . '<td>' . $statusHtml . '</td>'
                        . '<td>' . $trialHtml . '</td>'
                        . '<td>' . $maxHtml . '</td>'
                        . '<td>' . $expiresHtml . '</td>'
                        . '</tr>';
                }
                echo '</tbody></table></div>';
                $linesBase = ['view' => 'lines', 'panel_id' => $selectedPanelId];
                if ($q !== '') {
                    $linesBase['q'] = $q;
                }
                if ($enabledFilter !== 'all') {
                    $linesBase['enabled'] = $enabledFilter;
                }
                echo xtreamai_pager(
                    $modulelink,
                    $linesBase,
                    'cursor',
                    'cstack',
                    $cursor,
                    $cstack,
                    $linesNext,
                    count($lines),
                    $h
                );
            }
        }
        echo '</div>';
    } elseif ($view === 'bulk') {
        [$activePanels, $selectedPanelId, $panelOptions] = xtreamai_panel_selector($panels, $panelId, $h);

        echo '<div class="xtai-card">'
            . '<div class="xtai-card-head">'
            . '<div><h2>Bulk tools</h2><p class="xtai-sub">Run migration and maintenance operations on the selected panel in batches. Every operation is safe to run again.</p></div>';

        if (!empty($activePanels)) {
            echo '<form method="get" action="' . $linkList . '" class="xtai-panel-switch">'
                . '<input type="hidden" name="view" value="bulk">'
                . '<label for="xtai-panel-select">Panel</label>'
                . '<select id="xtai-panel-select" name="panel_id" onchange="this.form.submit()">'
                . $panelOptions
                . '</select>'
                . '</form>';
        }

        echo '</div>';

        if (empty($activePanels) || $selectedPanelId < 1) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No active panels</p><p>Activate a panel first to use the bulk tools.</p></div>';
        }

        echo '</div>';

        if (!empty($activePanels) && $selectedPanelId > 0) {
            $resultColumns = ['Service ID', 'Client', 'Username', 'Outcome', 'Message'];

            echo xtreamai_bulk_card(
                'index',
                '1. Index panel lines',
                'Reads every line of the selected panel and stores it locally: line id, username, expiry, status and the WHMCS service tag parsed from the line notes with the Line Notes Template. The other two tools use this index. It only reads from the panel and can be run again at any time; the first batch of a run replaces the previous index of this panel.',
                'Index lines',
                '',
                '',
                '',
                ['Batch', 'Lines indexed', 'Total indexed'],
                $h
            );

            echo xtreamai_bulk_card(
                'link',
                '2. Link existing services',
                'Matches WHMCS services that have no panel line yet against the local index, first by the service tag in the line notes and then by the panel username. Use it after migrating services from another WHMCS module: it restores the link to the existing line without touching the panel.',
                'Link services',
                'Include Pending, Terminated and Cancelled services',
                '',
                '',
                $resultColumns,
                $h
            );

            echo xtreamai_bulk_card(
                'sync',
                '3. Sync all services',
                'Runs the same line sync as the Sync bouquets, notes & connections button on every linked service of the panel: bouquets, notes and connection count are recalculated from each product, including the extra_connections configurable option. Services that are Suspended in WHMCS are left out unless you tick Include Suspended services, and each batch reports how many were skipped for that reason.',
                'Sync services',
                'Include Suspended services',
                'Parallel requests',
                'This writes to every linked line of the selected panel. Run it when the product configuration is final. Each parallel request takes its own share of the services; keep the same value to resume a run that failed.',
                $resultColumns,
                $h
            );

            echo xtreamai_bulk_card(
                'expiry',
                '4. Align panel expiry to WHMCS',
                'Runs, for every linked service of the panel, the same action as the Set panel expiry to WHMCS next due date button on the service page: the expiry of the line on the panel is set to the next due date of the service in WHMCS, at 12:00 UTC. It requires an admin panel key. Services without a next due date are skipped and counted.',
                'Align expiry dates',
                'Include Suspended services',
                'Parallel requests',
                'This overwrites the expiry of every linked active line of the panel with the WHMCS next due date. If the WHMCS dates are wrong, the panel lines will be wrong too: run Refresh from panel on a few services first to compare. Keep the same Parallel requests value to resume a run that stopped.',
                $resultColumns,
                $h
            );

            $bulkUrls = json_encode([
                'index'  => xtreamai_link($modulelink, ['action' => 'ajax_bulk_index']),
                'link'   => xtreamai_link($modulelink, ['action' => 'ajax_bulk_link']),
                'sync'   => xtreamai_link($modulelink, ['action' => 'ajax_bulk_sync']),
                'expiry' => xtreamai_link($modulelink, ['action' => 'ajax_bulk_expiry']),
                'token'  => xtreamai_link($modulelink, ['action' => 'ajax_bulk_token']),
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            $bulkScript = <<<'JS'
<script>
(function () {
    var token = TOKEN_PLACEHOLDER;
    var urls = URLS_PLACEHOLDER;
    var outcomes = {
        index: ['indexed', 'total'],
        link: ['linked_by_tag', 'linked_by_username', 'not_found', 'ambiguous_tag', 'skipped_sub_reseller', 'skipped_other_panel', 'error'],
        sync: ['synced', 'skipped_suspended', 'skipped_sub_reseller', 'error'],
        expiry: ['aligned', 'skipped_no_due_date', 'skipped_suspended', 'skipped_sub_reseller', 'error']
    };
    var labels = {
        indexed: 'Lines indexed',
        total: 'Total in index',
        linked_by_tag: 'Linked by tag',
        linked_by_username: 'Linked by username',
        not_found: 'Not found',
        ambiguous_tag: 'Ambiguous tag',
        skipped_sub_reseller: 'Sub-Reseller skipped',
        skipped_other_panel: 'Other panel skipped',
        skipped_suspended: 'Skipped (Suspended)',
        skipped_no_due_date: 'Skipped (no due date)',
        error: 'Errors',
        synced: 'Synced',
        aligned: 'Aligned'
    };
    var running = false;
    var resumeFrom = {};
    var keepAlive = null;
    var failRun = null;
    var sessionMessage = 'Your WHMCS session has ended. Log in again, open Bulk tools and click the button: the run resumes from the saved position.';
    var tokenError = 'Invalid security token';

    function byId(id) {
        return document.getElementById(id);
    }

    function card(op) {
        return byId('xtai-bulk-' + op);
    }

    function panelIdNow() {
        var panelEl = byId('xtai-panel-select');
        return panelEl ? String(panelEl.value || '') : '';
    }

    function resumeKey(op) {
        return 'xtai_bulk_resume:' + panelIdNow() + ':' + op;
    }

    function storeResume(op, value) {
        try {
            window.localStorage.setItem(resumeKey(op), JSON.stringify(value));
        } catch (e) {
            return;
        }
    }

    function loadResume(op) {
        try {
            var raw = window.localStorage.getItem(resumeKey(op));
            if (!raw) { return null; }
            var parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function dropResume(op) {
        try {
            window.localStorage.removeItem(resumeKey(op));
        } catch (e) {
            return;
        }
    }

    function includeKey(op) {
        return 'xtai_bulk_include:' + op;
    }

    function bindInclude(box) {
        var op = String(box.getAttribute('data-op') || '');
        if (op === '') { return; }
        try {
            box.checked = window.localStorage.getItem(includeKey(op)) === '1';
        } catch (e) {
            box.checked = false;
        }
        box.addEventListener('change', function () {
            try {
                window.localStorage.setItem(includeKey(op), box.checked ? '1' : '0');
            } catch (e) {
                return;
            }
        });
    }

    function restoreInclude() {
        var boxes = document.querySelectorAll('.xtai-bulk-include');
        for (var i = 0; i < boxes.length; i++) {
            bindInclude(boxes[i]);
        }
    }

    function refreshToken() {
        return fetch(urls.token, { method: 'GET', credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (body) {
                var data = null;
                try {
                    data = JSON.parse(body);
                } catch (e) {
                    data = null;
                }
                if (!data || !data.ok || !data.token) { return 'expired'; }
                token = String(data.token);
                return 'ok';
            })
            .catch(function () { return 'network'; });
    }

    function stopForSession() {
        if (!failRun) { return; }
        failRun(sessionMessage);
    }

    function restoreResume() {
        var currentPanel = panelIdNow();
        if (currentPanel === '' || currentPanel === '0') { return; }

        var linkState = loadResume('link');
        var linkMarker = (linkState && linkState.marker !== undefined && linkState.marker !== null) ? String(linkState.marker) : '';
        if (linkMarker !== '' && linkMarker !== '0') {
            resumeFrom.link = linkMarker;
            setBar('link', 'error', 'A previous run stopped after service #' + linkMarker + '. Click the button to resume.');
        }

        ['sync', 'expiry'].forEach(function (op) {
            var state = loadResume(op);
            if (!state || !Array.isArray(state.markers) || state.markers.length < 1) { return; }

            var parts = [];
            var markers = [];
            for (var i = 0; i < state.markers.length; i++) {
                var marker = String(state.markers[i]);
                markers.push(marker);
                if (marker !== '0') { parts.push(marker); }
            }

            resumeFrom[op] = { workers: parseInt(state.workers, 10) || 1, markers: markers };
            if (parts.length > 0) {
                setBar(op, 'error', 'A previous run stopped after service #' + parts.join(', #') + '. Click the button to resume.');
            }
        });
    }

    function pick(root, selector) {
        return root ? root.querySelector(selector) : null;
    }

    function setBar(op, state, text) {
        var root = card(op);
        var bar = pick(root, '.xtai-bulk-bar');
        var label = pick(root, '.xtai-bulk-progress-text');
        var cls = 'xtai-bulk-bar';
        if (state === 'running') { cls += ' is-running'; }
        if (state === 'done') { cls += ' is-done'; }
        if (state === 'error') { cls += ' is-error'; }
        if (bar) { bar.className = cls; }
        if (label) { label.textContent = text; }
    }

    function renderCounters(op, counts) {
        var box = pick(card(op), '.xtai-bulk-counters');
        if (!box) { return; }
        var keys = (outcomes[op] || []).slice();
        Object.keys(counts).forEach(function (key) {
            if (keys.indexOf(key) === -1) { keys.push(key); }
        });
        box.innerHTML = '';
        keys.forEach(function (key) {
            var value = counts[key] || 0;
            if (value < 1) { return; }
            var chip = document.createElement('span');
            chip.className = 'xtai-bulk-counter';
            var name = document.createElement('span');
            name.textContent = labels[key] || key;
            var number = document.createElement('strong');
            number.textContent = String(value);
            chip.appendChild(name);
            chip.appendChild(number);
            box.appendChild(chip);
        });
    }

    function resetTable(op) {
        var root = card(op);
        var body = pick(root, '.xtai-bulk-rows');
        if (!body) { return; }
        var columns = root.querySelectorAll('.xtai-table thead th').length;
        body.innerHTML = '<tr class="xtai-bulk-empty"><td colspan="' + columns + '">No results yet.</td></tr>';
    }

    function addRows(op, rows, keys) {
        var body = pick(card(op), '.xtai-bulk-rows');
        if (!body) { return; }
        var empty = pick(card(op), '.xtai-bulk-empty');
        if (empty && empty.parentNode) { empty.parentNode.removeChild(empty); }
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            keys.forEach(function (key) {
                var td = document.createElement('td');
                var value = row[key];
                td.textContent = (value === null || value === undefined) ? '' : String(value);
                if (key !== 'client' && key !== 'username') { td.className = 'xtai-meta'; }
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });
        while (body.children.length > 500) { body.removeChild(body.firstChild); }
    }

    function setBusy(state) {
        var buttons = document.querySelectorAll('.xtai-bulk-run');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = state;
            if (state) { buttons[i].classList.add('is-loading'); } else { buttons[i].classList.remove('is-loading'); }
        }
        var panel = byId('xtai-panel-select');
        if (panel) { panel.disabled = state; }
        var parallels = document.querySelectorAll('.xtai-bulk-workers');
        for (var j = 0; j < parallels.length; j++) {
            parallels[j].disabled = state;
        }
    }

    function readWorkers(root) {
        var select = pick(root, '.xtai-bulk-workers');
        var value = select ? parseInt(select.value, 10) : 1;
        if (isNaN(value) || value < 1 || value > 4) { value = 1; }
        return value;
    }

    function workerLabel(count) {
        return count === 1 ? '1 worker' : count + ' workers';
    }

    function run(op) {
        if (running) { return; }
        var panelEl = byId('xtai-panel-select');
        var panelId = panelEl ? String(panelEl.value || '') : '';
        if (panelId === '' || panelId === '0') {
            setBar(op, 'error', 'Select a panel first.');
            return;
        }
        var root = card(op);
        if (!root) { return; }

        var includeEl = pick(root, '.xtai-bulk-include');
        var includeAll = (includeEl && includeEl.checked) ? '1' : '0';
        var keys = (op === 'index')
            ? ['batch', 'indexed', 'total']
            : ['service_id', 'client', 'username', 'outcome', 'message'];
        var counts = {};
        var batches = 0;
        var processedTotal = 0;

        running = true;
        setBusy(true);
        resetTable(op);
        renderCounters(op, counts);

        if (keepAlive) {
            clearInterval(keepAlive);
        }
        keepAlive = setInterval(function () {
            refreshToken().then(function (result) {
                if (result === 'expired') { stopForSession(); }
            });
        }, 240000);

        function finish() {
            running = false;
            setBusy(false);
            if (keepAlive) {
                clearInterval(keepAlive);
                keepAlive = null;
            }
        }

        function runParallel(op) {
            var workers = readWorkers(root);
            var markers = [];
            var previous = [];
            var retries = [];
            var finished = [];
            var active = 0;
            var failed = false;
            var reported = false;
            var failText = '';
            var note = '';
            var stored = resumeFrom[op];
            var resuming = false;
            var tokenRetried = [];

            if (stored && typeof stored === 'object' && stored.workers === workers && Array.isArray(stored.markers) && stored.markers.length === workers) {
                resuming = true;
            } else if (stored) {
                note = 'The last run used ' + workerLabel(stored.workers) + ' and this one uses ' + workerLabel(workers) + ': starting over from the beginning. ';
                delete resumeFrom[op];
                dropResume(op);
            }

            for (var i = 0; i < workers; i++) {
                markers[i] = resuming ? String(stored.markers[i]) : '0';
                previous[i] = null;
                retries[i] = 0;
                tokenRetried[i] = false;
                finished[i] = false;
            }

            if (resuming) {
                var resumeParts = [];
                for (var r = 0; r < workers; r++) {
                    if (markers[r] !== '0') { resumeParts.push(markers[r]); }
                }
                if (resumeParts.length > 0) {
                    note = 'Resuming from service #' + resumeParts.join(', #') + '. ';
                }
            }

            function progressText() {
                return note + workerLabel(workers) + ', ' + processedTotal + ' services processed';
            }

            function allDone() {
                for (var d = 0; d < workers; d++) {
                    if (!finished[d]) { return false; }
                }
                return true;
            }

            function stopWhenIdle() {
                if (active > 0 || !failed || reported) { return; }
                reported = true;
                setBar(op, 'error', failText);
                finish();
            }

            function fail(message) {
                if (failed) { return; }
                failed = true;
                failText = note + (message || 'The operation failed.');
                var parts = [];
                for (var f = 0; f < workers; f++) {
                    if (markers[f] !== '0') { parts.push(markers[f]); }
                }
                if (parts.length > 0) {
                    resumeFrom[op] = { workers: workers, markers: markers.slice() };
                    storeResume(op, resumeFrom[op]);
                    failText += ' Stopped after service #' + parts.join(', #') + '. Click the button again with ' + workerLabel(workers) + ' to resume from there.';
                }
                stopWhenIdle();
            }
            failRun = fail;

            function step(w) {
                if (failed || finished[w]) { return; }
                var body = new FormData();
                body.set('token', token);
                body.set('panel_id', panelId);
                body.set('include_all', includeAll);
                body.set('after_id', markers[w]);
                body.set('worker', String(w));
                body.set('workers', String(workers));

                active++;
                fetch(urls[op], { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (failed) { active--; stopWhenIdle(); return; }
                        if (!data || !data.ok) {
                            var message = (data && data.message) ? String(data.message) : 'The operation failed.';
                            if (message.indexOf(tokenError) !== -1 && !tokenRetried[w]) {
                                tokenRetried[w] = true;
                                refreshToken().then(function (result) {
                                    active--;
                                    if (failed) { stopWhenIdle(); return; }
                                    if (result !== 'ok') {
                                        fail(sessionMessage);
                                        return;
                                    }
                                    step(w);
                                });
                                return;
                            }
                            active--;
                            fail(message);
                            return;
                        }
                        active--;
                        batches++;
                        retries[w] = 0;
                        tokenRetried[w] = false;
                        processedTotal += parseInt(data.processed, 10) || 0;
                        addRows(op, data.rows || [], keys);
                        if (data.counts && typeof data.counts === 'object') {
                            Object.keys(data.counts).forEach(function (key) {
                                counts[key] = (counts[key] || 0) + (parseInt(data.counts[key], 10) || 0);
                            });
                        }
                        renderCounters(op, counts);
                        if (data.done) {
                            finished[w] = true;
                            if (data.next !== undefined && data.next !== null && String(data.next) !== '') {
                                markers[w] = String(data.next);
                            }
                            if (allDone()) {
                                delete resumeFrom[op];
                                dropResume(op);
                                setBar(op, 'done', note + 'Done. ' + processedTotal + ' services processed in ' + batches + ' batches.');
                                finish();
                                return;
                            }
                            setBar(op, 'running', progressText());
                            return;
                        }
                        var nextId = parseInt(data.next, 10);
                        if (isNaN(nextId) || (previous[w] !== null && nextId <= previous[w])) {
                            fail('The batch marker did not advance.');
                            return;
                        }
                        previous[w] = nextId;
                        markers[w] = String(nextId);
                        setBar(op, 'running', progressText());
                        step(w);
                    })
                    .catch(function () {
                        active--;
                        if (failed) { stopWhenIdle(); return; }
                        if (retries[w] < 2) {
                            retries[w]++;
                            setBar(op, 'running', 'Worker ' + (w + 1) + ' request failed, retrying in 10 seconds (attempt ' + (retries[w] + 1) + ' of 3)...');
                            setTimeout(function () {
                                if (failed) { stopWhenIdle(); return; }
                                step(w);
                            }, 10000);
                            return;
                        }
                        fail('The request failed three times. Check the connection.');
                    });
            }

            setBar(op, 'running', progressText());
            for (var s = 0; s < workers; s++) {
                step(s);
            }
        }

        if (op === 'sync' || op === 'expiry') {
            runParallel(op);
            return;
        }

        var resuming = (op !== 'index' && resumeFrom[op]) ? String(resumeFrom[op]) : '';
        var marker = (op === 'index') ? '' : (resuming !== '' ? resuming : '0');
        var previous = null;
        var retries = 0;
        var tokenRetried = false;

        setBar(op, 'running', resuming !== '' ? 'Resuming after service #' + resuming + '...' : 'Starting...');

        function fail(message) {
            var text = message || 'The operation failed.';
            if (op !== 'index' && marker !== '0') {
                resumeFrom[op] = marker;
                storeResume(op, { marker: marker });
                text += ' Stopped after service #' + marker + '. Click the button again to resume from there.';
            }
            setBar(op, 'error', text);
            finish();
        }
        failRun = fail;

        function step() {
            var body = new FormData();
            body.set('token', token);
            body.set('panel_id', panelId);
            body.set('include_all', includeAll);
            if (op === 'index') { body.set('cursor', marker); } else { body.set('after_id', marker); }

            fetch(urls[op], { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        var message = (data && data.message) ? String(data.message) : 'The operation failed.';
                        if (message.indexOf(tokenError) !== -1 && !tokenRetried) {
                            tokenRetried = true;
                            refreshToken().then(function (result) {
                                if (result !== 'ok') {
                                    fail(sessionMessage);
                                    return;
                                }
                                step();
                            });
                            return;
                        }
                        fail(message);
                        return;
                    }
                    batches++;
                    retries = 0;
                    tokenRetried = false;

                    if (op === 'index') {
                        var indexed = parseInt(data.indexed, 10);
                        if (isNaN(indexed)) { indexed = 0; }
                        var total = parseInt(data.total_indexed, 10);
                        if (isNaN(total)) { total = 0; }
                        counts.indexed = (counts.indexed || 0) + indexed;
                        counts.total = total;
                        addRows(op, [{ batch: batches, indexed: indexed, total: total }], keys);
                        renderCounters(op, counts);
                        if (data.done) {
                            setBar(op, 'done', 'Done. ' + counts.indexed + ' lines indexed in ' + batches + ' batches, ' + total + ' lines in the index.');
                            finish();
                            return;
                        }
                        var nextCursor = data.next_cursor ? String(data.next_cursor) : '';
                        if (nextCursor === '' || nextCursor === previous) {
                            fail('The panel did not return a usable cursor.');
                            return;
                        }
                        previous = nextCursor;
                        marker = nextCursor;
                        setBar(op, 'running', 'Batch ' + batches + ', ' + counts.indexed + ' lines indexed, ' + total + ' in index');
                        step();
                        return;
                    }

                    processedTotal += parseInt(data.processed, 10) || 0;
                    addRows(op, data.rows || [], keys);
                    if (data.counts && typeof data.counts === 'object') {
                        Object.keys(data.counts).forEach(function (key) {
                            counts[key] = (counts[key] || 0) + (parseInt(data.counts[key], 10) || 0);
                        });
                    }
                    renderCounters(op, counts);
                    if (data.done) {
                        delete resumeFrom[op];
                        dropResume(op);
                        setBar(op, 'done', 'Done. ' + processedTotal + ' services processed in ' + batches + ' batches.');
                        finish();
                        return;
                    }
                    var nextId = parseInt(data.next, 10);
                    if (isNaN(nextId) || (previous !== null && nextId <= previous)) {
                        fail('The batch marker did not advance.');
                        return;
                    }
                    previous = nextId;
                    marker = String(nextId);
                    setBar(op, 'running', 'Batch ' + batches + ', ' + processedTotal + ' services processed');
                    step();
                })
                .catch(function () {
                    if (retries < 2) {
                        retries++;
                        setBar(op, 'running', 'The request failed, retrying in 10 seconds (attempt ' + (retries + 1) + ' of 3)...');
                        setTimeout(step, 10000);
                        return;
                    }
                    fail('The request failed three times. Check the connection.');
                });
        }

        step();
    }

    restoreResume();
    restoreInclude();

    var runButtons = document.querySelectorAll('.xtai-bulk-run');
    for (var i = 0; i < runButtons.length; i++) {
        runButtons[i].addEventListener('click', function () {
            run(this.getAttribute('data-op'));
        });
    }
})();
</script>
JS;

            echo str_replace(
                ['TOKEN_PLACEHOLDER', 'URLS_PLACEHOLDER'],
                [
                    json_encode(xtreamai_token_plain(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                    $bulkUrls,
                ],
                $bulkScript
            );
        }
    } elseif ($view === 'catalog') {
        [$activePanels, $selectedPanelId, $panelOptions] = xtreamai_panel_selector($panels, $panelId, $h);

        $sqE = $h($sq);
        $vqE = $h($vq);

        echo '<div class="xtai-card">'
            . '<div class="xtai-card-head">'
            . '<div><h2>Catalog</h2><p class="xtai-sub">Live streams and VOD catalog of the selected panel.</p></div>';

        if (!empty($activePanels)) {
            echo '<form method="get" action="' . $linkList . '" class="xtai-panel-switch">'
                . '<input type="hidden" name="view" value="catalog">'
                . '<label for="xtai-panel-select">Panel</label>'
                . '<select id="xtai-panel-select" name="panel_id" onchange="this.form.submit()">'
                . $panelOptions
                . '</select>'
                . '</form>';
        }

        echo '</div>';

        if (empty($activePanels) || $selectedPanelId < 1) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No active panels</p><p>Activate a panel first to browse its catalog.</p></div>';
        } else {
            echo '<div class="xtai-catalog-grid">';

            echo '<section class="xtai-card">'
                . '<h2>Live Streams</h2>'
                . '<form method="get" action="' . $linkList . '" class="xtai-filter-bar">'
                . '<input type="hidden" name="view" value="catalog">'
                . '<input type="hidden" name="panel_id" value="' . $selectedPanelId . '">'
                . '<input type="text" name="sq" value="' . $sqE . '" placeholder="Search streams…" maxlength="80" aria-label="Search streams">'
                . '<button type="submit" class="xtai-btn xtai-btn--sm">Search</button>'
                . '</form>';

            $streams     = [];
            $streamNext  = null;
            $streamError = '';
            try {
                $page = \WhmcsXtreamAI\PanelApi::streamsPage($selectedPanelId, $sq !== '' ? $sq : null, $cursor !== '' ? $cursor : null);
                $streams     = $page['items'];
                $streamNext  = $page['next_cursor'];
            } catch (\Throwable $e) {
                $streamError = xtreamai_safe_message($e);
            }

            if ($streamError !== '') {
                echo '<div class="xtai-error" role="alert"><span class="xtai-error__icon" aria-hidden="true">&#10005;</span><span>Could not load streams: ' . $h($streamError) . '</span></div>';
            } elseif (count($streams) === 0) {
                echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128250;</span><p class="xtai-empty__title">No live streams</p><p>No streams found on this panel.</p></div>';
            } else {
                echo '<div class="xtai-table-wrap"><table class="xtai-table">'
                    . '<thead><tr><th>#</th><th></th><th>Name</th><th>Categories</th></tr></thead>'
                    . '<tbody>';
                foreach ($streams as $row) {
                    $sid    = (int) ($row['id'] ?? 0);
                    $sname  = $h((string) ($row['name'] ?? ''));
                    $sicon  = (string) ($row['icon'] ?? '');
                    $scats  = $h(implode(', ', (array) ($row['categories'] ?? [])));
                    $iconHtml = xtreamai_catalog_icon($sicon, $h);

                    echo '<tr>'
                        . '<td class="xtai-meta">#' . $sid . '</td>'
                        . '<td>' . $iconHtml . '</td>'
                        . '<td><strong>' . $sname . '</strong></td>'
                        . '<td class="xtai-meta">' . $scats . '</td>'
                        . '</tr>';
                }
                echo '</tbody></table></div>';
                $streamsBase = ['view' => 'catalog', 'panel_id' => $selectedPanelId];
                if ($sq !== '') {
                    $streamsBase['sq'] = $sq;
                }
                if ($vq !== '') {
                    $streamsBase['vq'] = $vq;
                }
                echo xtreamai_pager(
                    $modulelink,
                    $streamsBase,
                    'cursor',
                    'cstack',
                    $cursor,
                    $cstack,
                    $streamNext,
                    count($streams),
                    $h
                );
            }
            echo '</section>';

            echo '<section class="xtai-card">'
                . '<h2>VOD</h2>'
                . '<form method="get" action="' . $linkList . '" class="xtai-filter-bar">'
                . '<input type="hidden" name="view" value="catalog">'
                . '<input type="hidden" name="panel_id" value="' . $selectedPanelId . '">'
                . '<input type="text" name="vq" value="' . $vqE . '" placeholder="Search VOD…" maxlength="80" aria-label="Search VOD">'
                . '<button type="submit" class="xtai-btn xtai-btn--sm">Search</button>'
                . '</form>';

            $vods     = [];
            $vodNext  = null;
            $vodError = '';
            try {
                $page = \WhmcsXtreamAI\PanelApi::vodsPage($selectedPanelId, $vq !== '' ? $vq : null, $vcursor !== '' ? $vcursor : null);
                $vods     = $page['items'];
                $vodNext  = $page['next_cursor'];
            } catch (\Throwable $e) {
                $vodError = xtreamai_safe_message($e);
            }

            if ($vodError !== '') {
                echo '<div class="xtai-error" role="alert"><span class="xtai-error__icon" aria-hidden="true">&#10005;</span><span>Could not load VOD: ' . $h($vodError) . '</span></div>';
            } elseif (count($vods) === 0) {
                echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#127916;</span><p class="xtai-empty__title">No VOD</p><p>No VOD entries found on this panel.</p></div>';
            } else {
                echo '<div class="xtai-table-wrap"><table class="xtai-table">'
                    . '<thead><tr><th>#</th><th></th><th>Name</th><th>Meta</th></tr></thead>'
                    . '<tbody>';
                foreach ($vods as $row) {
                    $vid      = (int) ($row['id'] ?? 0);
                    $vname    = $h((string) ($row['name'] ?? ''));
                    $vicon    = (string) ($row['icon'] ?? '');
                    $vyear    = $row['year'] ?? null;
                    $vrating  = $row['rating'] ?? null;
                    $visSerie = !empty($row['is_serie']);
                    $iconHtml = xtreamai_catalog_icon($vicon, $h);

                    $metaParts = [];
                    if ($vyear !== null && $vyear !== '') {
                        $metaParts[] = (string) $vyear;
                    }
                    if ($vrating !== null && $vrating !== '') {
                        $metaParts[] = (string) $vrating;
                    }
                    $metaHtml = $h(implode(' · ', $metaParts));
                    if ($visSerie) {
                        $metaHtml .= ' <span class="xtai-badge xtai-badge--warning">Serie</span>';
                    }
                    if ($metaHtml === '') {
                        $metaHtml = '<span class="xtai-meta">—</span>';
                    }

                    echo '<tr>'
                        . '<td class="xtai-meta">#' . $vid . '</td>'
                        . '<td>' . $iconHtml . '</td>'
                        . '<td><strong>' . $vname . '</strong></td>'
                        . '<td>' . $metaHtml . '</td>'
                        . '</tr>';
                }
                echo '</tbody></table></div>';
                $vodsBase = ['view' => 'catalog', 'panel_id' => $selectedPanelId];
                if ($sq !== '') {
                    $vodsBase['sq'] = $sq;
                }
                if ($vq !== '') {
                    $vodsBase['vq'] = $vq;
                }
                echo xtreamai_pager(
                    $modulelink,
                    $vodsBase,
                    'vcursor',
                    'vcstack',
                    $vcursor,
                    $vcstack,
                    $vodNext,
                    count($vods),
                    $h
                );
            }
            echo '</section>';

            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="xtai-card">
<h2>Panels</h2>
<p class="xtai-sub">Connected Xtream AI panels and their last health check.</p>';
        if (count($panels) === 0) {
            echo '<div class="xtai-empty"><span class="xtai-empty__icon" aria-hidden="true">&#128421;</span><p class="xtai-empty__title">No panels yet</p><p>No panels yet. Use the form below to add your first Xtream AI panel.</p></div>';
        } else {
            echo '<div class="xtai-table-wrap"><table class="xtai-table">
<thead><tr><th>#</th><th>Panel</th><th>Status</th><th>SSL</th><th>Last check</th><th>Last message</th><th>Actions</th></tr></thead>
<tbody>';
            foreach ($panels as $panel) {
                $pid       = (int) $panel->id;
                $pname     = $h($panel->name);
                $papi      = $h($panel->api_url);
                $pactive   = (int) $panel->active === 1;
                $pverify   = (int) $panel->verify_ssl === 1;
                $lastOk    = (int) $panel->last_ok;
                $pchecked  = $h($panel->last_checked ?? '');
                $pmessage  = $h($panel->last_message ?? '');

                $statusHtml = $pactive
                    ? '<span class="xtai-badge xtai-badge--success">Active</span>'
                    : '<span class="xtai-badge xtai-badge--neutral">Disabled</span>';

                $sslHtml = $pverify
                    ? '<span class="xtai-badge xtai-badge--success">SSL On</span>'
                    : '<span class="xtai-badge xtai-badge--warning">SSL Off</span>';

                if ($pchecked === '') {
                    $lastHtml = '<span class="xtai-badge xtai-badge--neutral">Not tested</span>';
                } else {
                    $lastHtml = ($lastOk === 1 ? '<span class="xtai-badge xtai-badge--success">Connected</span>' : '<span class="xtai-badge xtai-badge--danger">Error</span>')
                        . '<div class="xtai-meta">' . $pchecked . '</div>';
                }

                echo '<tr>
<td class="xtai-meta">#' . $pid . '</td>
<td><strong>' . $pname . '</strong><div class="xtai-meta">' . $papi . '</div></td>
<td>' . $statusHtml . '</td>
<td>' . $sslHtml . '</td>
<td>' . $lastHtml . '</td>
<td class="xtai-meta">' . $pmessage . '</td>
<td><div class="xtai-row-actions">
<button type="button" class="xtai-btn xtai-btn--sm xtai-btn--ghost xtai-test-btn" data-id="' . $pid . '">Test</button>
<a class="xtai-btn xtai-btn--sm xtai-btn--primary-ghost" href="' . $linkEditBase . $pid . '">Edit</a>
<form method="post" action="' . $linkList . '" class="xtai-inline-form">' . $token . '<input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="' . $pid . '"><button type="submit" class="xtai-btn xtai-btn--sm xtai-btn--ghost">' . ($pactive ? 'Deactivate' : 'Activate') . '</button></form>
<form method="post" action="' . $linkList . '" class="xtai-inline-form" onsubmit="return confirm(\'Delete this panel?\');">' . $token . '<input type="hidden" name="action" value="delete_panel"><input type="hidden" name="id" value="' . $pid . '"><button type="submit" class="xtai-btn xtai-btn--sm xtai-btn--danger-ghost">Delete</button></form>
<span class="xtai-test-result" id="xtai-result-' . $pid . '"></span>
</div></td>
</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';

        echo '<div class="xtai-card">
<h2>' . ($editing ? 'Edit Panel' : 'Add Panel') . '</h2>
<p class="xtai-sub">' . ($editing ? 'Update the panel details. Leave the access key blank to keep the current one.' : 'Connect a new Xtream AI panel.') . '</p>
<form method="post" action="' . $linkList . '">
' . $token . '
<input type="hidden" name="action" value="save_panel">
<input type="hidden" name="id" value="' . $fId . '">
<div class="xtai-form-grid">
<div class="xtai-field"><label>Name</label><input type="text" name="name" value="' . $fName . '" required><span class="xtai-help">A friendly label for this panel.</span></div>
<div class="xtai-field"><label>API URL</label><input type="text" name="api_url" value="' . $fApiUrl . '" placeholder="https://panel.example.com" required><span class="xtai-help">The panel base URL, without a trailing slash.</span></div>
<div class="xtai-field"><label>M3U URL</label><input type="text" name="m3u_url" value="' . $fM3uUrl . '" placeholder="optional"><span class="xtai-help">Optional M3U URL shared with clients. {username} and {password} are replaced with each client\'s credentials.</span></div>
<div class="xtai-field"><label>EPG URL</label><input type="text" name="epg_url" value="' . $fEpgUrl . '" placeholder="optional"><span class="xtai-help">Optional EPG (XMLTV) URL shared with clients. Both URLs accept {username} and {password}, replaced with each client\'s credentials.</span></div>
<div class="xtai-field"><label>Access key</label><input type="password" name="password" autocomplete="new-password"' . ($editing ? '' : ' required') . '><span class="xtai-help">' . ($editing ? 'Leave blank to keep the current access key.' : 'API access key (stored encrypted).') . '</span></div>
<div class="xtai-field xtai-field--full">
<label>SSL verification</label>
<label class="xtai-switch"><input type="checkbox" name="verify_ssl" value="1"' . $fVerifySsl . '><span class="xtai-switch__track"></span><span class="xtai-switch__label">Verify SSL certificate</span></label>
<span class="xtai-help">Keep this enabled. Disabling it allows insecure connections.</span>
</div>
<div class="xtai-field xtai-field--full">
<label>Panel status</label>
<label class="xtai-switch"><input type="checkbox" name="active" value="1"' . $fActive . '><span class="xtai-switch__track"></span><span class="xtai-switch__label">Panel active</span></label>
</div>
<div class="xtai-field xtai-field--full"><label>Key type</label><select name="key_type" id="xtai-key-type"><option value="reseller"' . $fKtRes . '>Reseller (owner inferred from key)</option><option value="admin"' . $fKtAdm . '>Admin (specify owner member_id below)</option></select><span class="xtai-help">Admin keys require member_id on every line creation. Reseller keys infer the owner.</span></div>
<div class="xtai-field xtai-field--full" id="xtai-admin-owner-row"><label>Admin owner member_id</label><input type="number" name="admin_owner_member_id" min="1" value="' . $fAdminOwner . '"><span class="xtai-help">Numeric id of the reseller in the panel that will own lines created by this WHMCS. Only used when key type is Admin.</span></div>
</div>
<div class="xtai-actions"><button type="button" class="xtai-btn xtai-btn--ghost" id="xtai-form-test">Test Connection</button> <button type="submit" class="xtai-btn xtai-btn--primary">' . ($editing ? 'Save Changes' : 'Add Panel') . '</button>' . ($editing ? ' <a class="xtai-btn" href="' . $linkList . '">Cancel</a>' : '') . ' <span class="xtai-test-result" id="xtai-form-result"></span></div>
</form>
<script>(function(){var t=document.getElementById("xtai-key-type"),r=document.getElementById("xtai-admin-owner-row");if(!t||!r)return;function s(){r.style.display=t.value==="admin"?"":"none";}t.addEventListener("change",s);s();})();</script>
</div>';
    }

    $ajaxBase = json_encode(xtreamai_link($modulelink, ['action' => 'ajax_test_connection']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>
(function(){
var token=' . json_encode(xtreamai_token_plain(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
function setLoading(btn,on){if(btn){btn.classList.toggle("is-loading",on);btn.disabled=on;}}
document.querySelectorAll(".xtai-test-btn").forEach(function(btn){
btn.addEventListener("click",function(){
var id=btn.getAttribute("data-id");
var out=document.getElementById("xtai-result-"+id);
if(out){out.className="xtai-test-result";out.textContent="Testing...";}
setLoading(btn,true);
var url=' . $ajaxBase . ' + "&id=" + encodeURIComponent(id) + "&token=" + encodeURIComponent(token);
fetch(url,{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){
if(out){out.className="xtai-test-result " + (j.ok?"xtai-test-result--ok":"xtai-test-result--err");out.textContent=j.message||"";}
}).catch(function(e){if(out){out.className="xtai-test-result xtai-test-result--err";out.textContent="Connection test failed.";}})
.finally(function(){setLoading(btn,false);});
});
});
var formTest=document.getElementById("xtai-form-test");
if(formTest){
formTest.addEventListener("click",function(){
var form=formTest.closest("form");
var idEl=form?form.querySelector("input[name=id]"):null;
var urlEl=form?form.querySelector("input[name=api_url]"):null;
var passEl=form?form.querySelector("input[name=password]"):null;
var id=idEl?idEl.value.trim():"0";
var url=urlEl?urlEl.value.trim():"";
var pass=passEl?passEl.value:"";
var out=document.getElementById("xtai-form-result");
if(!url){if(out){out.className="xtai-test-result xtai-test-result--err";out.textContent="Panel URL is required.";}if(urlEl){urlEl.focus();}return;}
if(id==="0"&&!pass){if(out){out.className="xtai-test-result xtai-test-result--err";out.textContent="API key is required.";}if(passEl){passEl.focus();}return;}
if(out){out.className="xtai-test-result";out.textContent="Testing...";}
setLoading(formTest,true);
var body=new URLSearchParams();
body.set("action","ajax_test_connection");
body.set("token",token);
body.set("id",id);
body.set("api_url",url);
body.set("password",pass);
fetch(' . $ajaxBase . ',{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:body.toString(),credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){
if(out){out.className="xtai-test-result "+(j.ok?"xtai-test-result--ok":"xtai-test-result--err");out.textContent=j.message||"";}
}).catch(function(e){if(out){out.className="xtai-test-result xtai-test-result--err";out.textContent="Connection test failed.";}})
.finally(function(){setLoading(formTest,false);});
});
}
document.querySelectorAll(".xtai-flash__close").forEach(function(btn){
btn.addEventListener("click",function(){
var f=btn.closest(".xtai-flash");
if(f){f.style.transition="opacity .18s ease";f.style.opacity="0";setTimeout(function(){f.remove();},180);}
});
});
})();
</script>
</div>';
}

function xtreamai_safe_message(\Throwable $e)
{
    $msg = trim((string) $e->getMessage());
    return $msg === '' ? 'Unexpected error.' : $msg;
}

function xtreamai_normalize_url($url)
{
    $url = trim((string) $url);
    return rtrim($url, '/');
}

function xtreamai_truncate($value, $length)
{
    $value  = (string) $value;
    $length = (int) $length;

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $length, '…');
    }

    if (strlen($value) <= $length) {
        return $value;
    }

    return substr($value, 0, $length) . '…';
}

function xtreamai_clean_search($value): string
{
    if (!is_scalar($value)) {
        return '';
    }
    $value = trim((string) $value);
    if (xtreamai_strlen($value) > 80) {
        $value = xtreamai_substr($value, 0, 80);
    }

    return $value;
}

function xtreamai_clean_cursor($value): string
{
    if (!is_scalar($value)) {
        return '';
    }
    $value = trim((string) $value);
    $value = preg_replace('/[^A-Za-z0-9_=-]/', '', $value);
    if ($value === null || $value === '') {
        return '';
    }
    if (strlen($value) > 120) {
        $value = substr($value, 0, 120);
    }

    return $value;
}

function xtreamai_cursor_stack($value): array
{
    if (!is_scalar($value)) {
        return [];
    }
    $stack = [];
    foreach (explode(',', (string) $value) as $part) {
        $clean = xtreamai_clean_cursor($part);
        if ($clean !== '') {
            $stack[] = $clean;
        }
    }

    return $stack;
}

function xtreamai_pager(
    string $modulelink,
    array $baseParams,
    string $cursorParam,
    string $stackParam,
    string $currentCursor,
    array $stack,
    ?string $nextCursor,
    int $count,
    callable $h
): string {
    $prevLink = '';
    if (!empty($stack) || $currentCursor !== '') {
        $prevStack  = $stack;
        if (!empty($prevStack)) {
            $prevCursor = array_pop($prevStack);
        } else {

            $prevCursor = '';
        }
        $params = $baseParams;
        if ($prevCursor !== '') {
            $params[$cursorParam] = $prevCursor;
        }
        if (!empty($prevStack)) {
            $params[$stackParam] = implode(',', $prevStack);
        }
        $prevLink = '<a class="xtai-btn xtai-btn--sm" href="' . $h(xtreamai_link($modulelink, $params)) . '" rel="prev">&larr; Previous</a>';
    }

    $nextLink = '';
    if ($nextCursor !== null) {
        $nextStack = $stack;
        if ($currentCursor !== '') {
            $nextStack[] = $currentCursor;
        }
        $params = $baseParams;
        $params[$cursorParam] = xtreamai_clean_cursor($nextCursor);
        if (!empty($nextStack)) {
            $params[$stackParam] = implode(',', $nextStack);
        }
        $nextLink = '<a class="xtai-btn xtai-btn--sm xtai-btn--primary-ghost" href="' . $h(xtreamai_link($modulelink, $params)) . '" rel="next">Next &rarr;</a>';
    }

    $countText = 'Showing ' . $count . ' items';
    if ($nextCursor !== null) {
        $countText .= ' <span class="xtai-pagination__more">(more available)</span>';
    }

    return '<div class="xtai-pagination"><span class="xtai-pagination__count">' . $countText . '</span>'
        . '<nav class="xtai-pagination__nav">' . $prevLink . $nextLink . '</nav></div>';
}

function xtreamai_panel_selector(array $panels, int $panelId, callable $h): array
{
    $activePanels = [];
    foreach ($panels as $panel) {
        if ((int) $panel->active === 1) {
            $activePanels[] = $panel;
        }
    }

    $selectedPanelId = 0;
    if ($panelId > 0) {
        foreach ($activePanels as $panel) {
            if ((int) $panel->id === $panelId) {
                $selectedPanelId = $panelId;
                break;
            }
        }
    }
    if ($selectedPanelId === 0 && !empty($activePanels)) {
        $selectedPanelId = (int) $activePanels[0]->id;
    }

    $options = '';
    foreach ($activePanels as $panel) {
        $pid      = (int) $panel->id;
        $selected = $pid === $selectedPanelId ? ' selected' : '';
        $options .= '<option value="' . $pid . '"' . $selected . '>' . $h($panel->name) . '</option>';
    }

    return [$activePanels, $selectedPanelId, $options];
}

function xtreamai_bulk_card(
    string $op,
    string $title,
    string $sub,
    string $button,
    string $includeLabel,
    string $workersLabel,
    string $warning,
    array $columns,
    callable $h
): string {
    $html = '<section class="xtai-card xtai-bulk" id="xtai-bulk-' . $op . '">'
        . '<h2>' . $h($title) . '</h2>'
        . '<p class="xtai-sub">' . $h($sub) . '</p>';

    if ($warning !== '') {
        $html .= '<div class="xtai-warn" role="alert"><span class="xtai-warn__icon" aria-hidden="true">&#33;</span>'
            . '<span>' . $h($warning) . '</span></div>';
    }

    $html .= '<div class="xtai-actions">'
        . '<button type="button" class="xtai-btn xtai-btn--primary xtai-bulk-run" data-op="' . $op . '">' . $h($button) . '</button>';

    if ($includeLabel !== '') {
        $html .= '<label class="xtai-switch">'
            . '<input type="checkbox" class="xtai-bulk-include" data-op="' . $op . '" value="1">'
            . '<span class="xtai-switch__track"></span>'
            . '<span class="xtai-switch__label">' . $h($includeLabel) . '</span>'
            . '</label>';
    }

    if ($workersLabel !== '') {
        $html .= '<span class="xtai-panel-switch">'
            . '<label for="xtai-bulk-' . $op . '-workers">' . $h($workersLabel) . '</label>'
            . '<select class="xtai-bulk-workers" id="xtai-bulk-' . $op . '-workers">';

        for ($parallel = 1; $parallel <= 4; $parallel++) {
            $selected = $parallel === 3 ? ' selected' : '';
            $html .= '<option value="' . $parallel . '"' . $selected . '>' . $parallel . '</option>';
        }

        $html .= '</select></span>';
    }

    $html .= '<span class="xtai-test-result xtai-bulk-status"></span>'
        . '</div>'
        . '<div class="xtai-bulk-progress">'
        . '<div class="xtai-bulk-bar"><span class="xtai-bulk-bar-fill"></span></div>'
        . '<p class="xtai-bulk-progress-text">Idle.</p>'
        . '</div>'
        . '<div class="xtai-bulk-counters"></div>'
        . '<div class="xtai-table-wrap xtai-table-wrap--bulk"><table class="xtai-table">'
        . '<thead><tr>';

    foreach ($columns as $column) {
        $html .= '<th>' . $h($column) . '</th>';
    }

    $html .= '</tr></thead>'
        . '<tbody class="xtai-bulk-rows">'
        . '<tr class="xtai-bulk-empty"><td colspan="' . count($columns) . '">No results yet.</td></tr>'
        . '</tbody></table></div>'
        . '</section>';

    return $html;
}

function xtreamai_catalog_icon(string $icon, callable $h): string
{
    $icon = trim($icon);

    if ($icon === '') {
        return '<span class="xtai-thumb" aria-hidden="true">&#9654;</span>';
    }

    return '<img class="xtai-thumb" src="' . $h($icon) . '" alt="" loading="lazy" onerror="this.style.display=\'none\'">';
}

function xtreamai_update_compare_version($current): string
{
    $current = trim((string) $current);

    return $current === '' ? '0.0.0' : $current;
}

function xtreamai_update_panel($modulelink, $token, callable $h): string
{
    if (!class_exists('WhmcsXtreamAI\\Updater')) {
        return '';
    }

    try {
        $release     = \WhmcsXtreamAI\Updater::latestRelease();
        $installed   = \WhmcsXtreamAI\Updater::currentVersion();
        $environment = \WhmcsXtreamAI\Updater::environment();
    } catch (\Throwable $e) {
        return '';
    }

    $label   = $installed === '' ? 'unknown' : $installed;
    $postTo  = $h($modulelink);
    $check   = '<form method="post" action="' . $postTo . '" class="xtai-inline-form">'
        . $token
        . '<input type="hidden" name="action" value="check_update">'
        . '<button type="submit" class="xtai-btn xtai-btn--sm">Check for updates</button>'
        . '</form>';

    if ($release === null) {
        return '<section class="xtai-card">'
            . '<div class="xtai-card-head"><div><h2>Module updates</h2>'
            . '<p class="xtai-sub">Installed version ' . $h($label) . '.</p></div>'
            . '<span class="xtai-badge xtai-badge--neutral">Not checked</span></div>'
            . '<p class="xtai-help">The last check did not return release information. Confirm that this server can reach api.github.com over HTTPS.</p>'
            . '<div class="xtai-actions">' . $check . '</div>'
            . '</section>';
    }

    $version = (string) ($release['version'] ?? '');

    if (!\WhmcsXtreamAI\Updater::isNewer($version, xtreamai_update_compare_version($installed))) {
        return '<section class="xtai-card">'
            . '<div class="xtai-card-head"><div><h2>Module updates</h2>'
            . '<p class="xtai-sub">Installed version ' . $h($label) . ' · latest release ' . $h($version) . '.</p></div>'
            . '<span class="xtai-badge xtai-badge--success">Up to date</span></div>'
            . '<div class="xtai-actions">' . $check . '</div>'
            . '</section>';
    }

    $notes      = trim((string) ($release['notes'] ?? ''));
    $releaseUrl = trim((string) ($release['release_url'] ?? ''));
    $name       = trim((string) ($release['name'] ?? ''));
    $published  = trim((string) ($release['published_at'] ?? ''));

    if (xtreamai_strlen($notes) > 600) {
        $notes = rtrim(xtreamai_substr($notes, 0, 600)) . '…';
    }

    $meta = 'Installed version ' . $label;

    if ($name !== '') {
        $meta .= ' · ' . $name;
    }

    if ($published !== '') {
        $meta .= ' · published ' . $published;
    }

    $html = '<section class="xtai-card">'
        . '<div class="xtai-card-head"><div><h2>Version ' . $h($version) . ' available</h2>'
        . '<p class="xtai-sub">' . $h($meta) . '</p></div>'
        . '<span class="xtai-badge xtai-badge--warning">Update available</span></div>';

    if ($notes !== '') {
        $html .= '<pre class="xtai-update__notes">' . $h($notes) . '</pre>';
    }

    $html .= '<div class="xtai-actions">';

    if ($releaseUrl !== '') {
        $html .= '<a class="xtai-btn" href="' . $h($releaseUrl) . '" target="_blank" rel="noopener noreferrer">Release notes</a>';
    }

    if (!empty($environment['ok'])) {
        $html .= '<form method="post" action="' . $postTo . '" class="xtai-inline-form">'
            . $token
            . '<input type="hidden" name="action" value="self_update">'
            . '<button type="submit" class="xtai-btn xtai-btn--primary">Update now</button>'
            . '</form>';
    }

    $html .= $check . '</div>';

    if (!empty($environment['ok'])) {
        $html .= '<p class="xtai-help">Update now downloads the release from GitHub, verifies its SHA256 checksum and replaces the two module folders. The previous version is kept as a backup.</p>';
    } else {
        $html .= '<div class="xtai-update__manual">'
            . '<strong>Update by hand:</strong> ' . $h((string) ($environment['message'] ?? ''))
            . ' Download <code>whmcs-xtreamai-' . $h($version) . '.tar.gz</code> from the release page and replace '
            . '<code>modules/servers/xtreamai</code> and <code>modules/addons/xtreamai</code> with the two folders it contains.'
            . '</div>';
    }

    return $html . '</section>';
}

function xtreamai_link($base, array $params)
{
    $sep = strpos($base, '?') !== false ? '&' : '?';
    $pairs = [];
    foreach ($params as $key => $value) {
        $pairs[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
    }
    return $base . $sep . implode('&', $pairs);
}

function xtreamai_flash($type, $message)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['xtreamai_flash'] = ['type' => $type, 'message' => $message];
    }
}

function xtreamai_consume_flash()
{
    $flash = ['type' => '', 'message' => ''];
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['xtreamai_flash'])) {
        $flash = $_SESSION['xtreamai_flash'];
        unset($_SESSION['xtreamai_flash']);
    }
    return $flash;
}

function xtreamai_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function xtreamai_json(array $payload)
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}
