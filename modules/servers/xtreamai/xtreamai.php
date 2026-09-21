<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

$xtreamaiBootstrap = __DIR__ . '/../../addons/xtreamai/lib/bootstrap.php';
if (is_file($xtreamaiBootstrap)) {
    require_once $xtreamaiBootstrap;
}

use WHMCS\Database\Capsule;

function xtreamai_libLoaded(): bool
{

    return class_exists('WhmcsXtreamAI\\Settings')
        || class_exists('WhmcsXtreamAI\\PanelApi');
}

function xtreamai_requireAddon(): void
{
    if (!xtreamai_libLoaded()) {
        throw new \RuntimeException('Addon not installed. Install and activate the Xtream AI Panel addon first.');
    }
}

function xtreamai_MetaData()
{
    return [
        'DisplayName' => 'Xtream AI Panel',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

function xtreamai_ConfigOptions()
{
    $panelOptions = [];
    $packageOptions = ['official' => [], 'trial' => []];
    $panelDescription = 'Select the panel that will provision lines for this product. Add panels in Addons → Xtream AI Panel.';
    $packageDescription = 'Choose the package used for this product.';
    $bouquetDescription = 'Select the bouquets to activate on lines created from this product.';
    $credits = null;
    $bouquets = [];

    try {
        xtreamai_requireAddon();
        \WhmcsXtreamAI\Settings::ensureTables();

        foreach (\WhmcsXtreamAI\PanelStore::allActive() as $panel) {
            if (isset($panel->id)) {
                $panelOptions[(string) $panel->id] = (string) $panel->name;
            }
        }

        $panelId = xtreamai_configPanelId();
        $packageType = xtreamai_configOptionValue(3, 'official');
        if ($packageType !== 'official' && $packageType !== 'trial') {
            $packageType = 'official';
        }

        if ($panelId > 0) {
            foreach (\WhmcsXtreamAI\PanelApi::packages($panelId) as $package) {
                $id = isset($package['id']) ? (string) $package['id'] : '';
                if ($id === '') {
                    continue;
                }
                $name = (isset($package['name']) && $package['name'] !== '')
                    ? (string) $package['name']
                    : 'Package #' . $id;
                $label = $name;
                if (!empty($package['duration'])) {
                    $label .= ' (' . $package['duration'] . ')';
                }
                if (!empty($package['is_official'])) {
                    $packageOptions['official'][$id] = $label;
                }
                if (!empty($package['is_trial'])) {
                    $packageOptions['trial'][$id] = $label;
                }
            }

            try {
                $bouquets = \WhmcsXtreamAI\PanelApi::bouquets($panelId);
            } catch (\Throwable $e) {
                $bouquets = [];
                $bouquetDescription .= ' Could not load bouquets: ' . $e->getMessage();
            }

            try {
                $credits = \WhmcsXtreamAI\PanelApi::credits($panelId);
            } catch (\Throwable $e) {
                $credits = null;
            }
        }
    } catch (\Throwable $e) {
        $panelDescription = 'Could not load panel data: ' . $e->getMessage();
    }

    if (!$panelOptions) {
        $panelOptions = ['0' => 'No panels — add one in Addons → Xtream AI Panel'];
    }

    $currentPackageOptions = isset($packageOptions[$packageType]) ? $packageOptions[$packageType] : $packageOptions['official'];
    if (!$currentPackageOptions) {
        $currentPackageOptions = ['0' => ($packageType === 'trial' ? 'No trial packages found' : 'No packages found')];
    }

    if ($credits !== null && $credits !== '') {
        $packageDescription .= xtreamai_creditsBadge((string) $credits);
    }

    $packageDescription .= xtreamai_packageFilter($packageOptions);

    $bouquetDescription .= xtreamai_bouquetPicker($bouquets);

    $accountTypeDescription = 'Choose the account type this product provisions. Sub-Reseller accounts use the Credits value instead of a package/bouquet and ignore those fields. Credit top-up adds the Credits value to a Sub-Reseller account the same client already has instead of creating a new one.';
    $accountTypeDescription .= xtreamai_accountTypeVisibility();

    return [
        'panel_id' => [
            'FriendlyName' => 'Panel',
            'Type' => 'dropdown',
            'Options' => $panelOptions,
            'Description' => $panelDescription,
        ],
        'package_id' => [
            'FriendlyName' => 'Package',
            'Type' => 'dropdown',
            'Options' => $currentPackageOptions,
            'Description' => $packageDescription,
        ],
        'package_type' => [
            'FriendlyName' => 'Package Type',
            'Type' => 'dropdown',
            'Options' => ['official' => 'Official', 'trial' => 'Trial'],
            'Default' => 'official',
            'Description' => 'Choose Official or Trial. The package list updates instantly without saving the page.',
        ],
        'bouquets' => [
            'FriendlyName' => 'Bouquets',
            'Type' => 'text',
            'Size' => '45',
            'Description' => $bouquetDescription,
        ],
        'account_type' => [
            'FriendlyName' => 'Account Type',
            'Type' => 'dropdown',
            'Options' => ['line' => 'Line (default)', 'reseller' => 'Sub-Reseller', 'topup' => 'Credit top-up (existing Sub-Reseller)'],
            'Default' => 'line',
            'Description' => $accountTypeDescription,
        ],
        'credits' => [
            'FriendlyName' => 'Credits',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '0',
            'Description' => 'Credits assigned on creation and on each renewal (Sub-Reseller accounts), or added to the existing account on each order and renewal (Credit top-up). For Credit top-up products a WHMCS configurable option named credits replaces this value.',
        ],
        'max_connections' => [
            'FriendlyName' => 'Max Connections',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0',
            'Description' => 'Maximum concurrent connections per line. Set to 0 to use the package value (0 never means unlimited). Customers can add connections through a WHMCS configurable option named extra_connections (added on top of this value or, when this is 0, on top of the package value); a configurable option named max_connections replaces this value outright. Only applies when the panel Key type is Admin (reseller keys will silently ignore this).',
        ],
        'sub_reseller_member_group_id' => [
            'FriendlyName' => 'Sub-Reseller Member Group ID',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0',
            'Description' => 'Numeric id of the panel member group this Sub-Reseller product creates accounts in (Member Groups page of the panel). Required when the panel Key type is Admin: without it the order fails with a clear message. Reseller keys ignore it and inherit the group from their sub-reseller setup.',
        ],
        'suspend_action' => [
            'FriendlyName' => 'Suspend action',
            'Type' => 'dropdown',
            'Options' => ['disable' => 'Disable the line on the panel', 'none' => 'Leave the line untouched, let it expire'],
            'Default' => 'disable',
            'Description' => 'What WHMCS does on the panel when this service is suspended, for example by an unpaid invoice. Disable the line on the panel (default) turns the line off so the customer cannot watch at once, and unsuspending turns it back on. Leave the line untouched does nothing to the panel: the line keeps working until its own expiry date, and unsuspending leaves it as it is. Line products only; Sub-Reseller products are not affected by this option.',
        ],
        'topup_scope' => [
            'FriendlyName' => 'Top-up scope',
            'Type' => 'dropdown',
            'Options' => ['any' => 'Any reseller on the panel', 'linked' => 'Only this client\'s linked Sub-Reseller accounts'],
            'Default' => 'any',
            'Description' => 'Credit top-up products only. Any reseller on the panel (default) lets the customer type the username of any reseller account in the Reseller username custom field, whether WHMCS created it or not. Only this client\'s linked Sub-Reseller accounts restricts the top-up to Sub-Reseller services of the same WHMCS client, so a customer cannot send credits to an account that is not theirs.',
        ],
        'customer_username' => [
            'FriendlyName' => 'Customer username',
            'Type' => 'dropdown',
            'Options' => [
                'off' => 'Generated by the module (default)',
                'on' => 'Customer types it in the "Line username" or "Reseller username" custom field',
            ],
            'Default' => 'off',
            'Description' => 'Line and Sub-Reseller products. With "Customer types it", add a custom field to this product named "Line username" (Line) or "Reseller username" (Sub-Reseller), Show on Order Form, Required if you want it mandatory. The module creates the account with that username when it is free on the panel; a username that is already taken fails the provisioning with a clear message. With the field empty, or with this option off, usernames are generated as today. Credit top-up products do not use this option.',
        ],
        'customer_password' => [
            'FriendlyName' => 'Customer password',
            'Type' => 'dropdown',
            'Options' => [
                'off' => 'Generated by the module (default)',
                'on' => 'Customer types it in the "Panel password" custom field',
            ],
            'Default' => 'off',
            'Description' => 'Line and Sub-Reseller products. With "Customer types it", add a custom field to this product named "Panel password" (type Password, Show on Order Form). The customer chooses the password at checkout: 8 to 32 characters, no spaces and none of % & ? # / \\ +. After the account is created the module clears that field on the WHMCS service, and the password is kept on the service as usual. With the field empty, or with this option off, passwords are generated as today.',
        ],
    ];
}

function xtreamai_creditsBadge(string $credits): string
{
    $data = htmlspecialchars($credits, ENT_QUOTES, 'UTF-8');
    $style = '<style>.xtai-credits-pill{display:flex;justify-content:flex-end;margin-bottom:9px}.xtai-credits-pill span{display:inline-flex;align-items:center;gap:7px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;padding:6px 12px;border-radius:999px;font-weight:700;font-size:12.5px}.xtai-credits-pill svg{flex:none}</style>';
    $script = <<<'JS'
<script>
(function () {
    function run() {
        if (!window.jQuery) { return; }
        var $ = window.jQuery;
        var $field = $('[name="packageconfigoption[2]"]').first();
        if (!$field.length) { return; }
        var $td = $field.closest('td');
        if (!$td.length || $td.find('.xtai-credits-pill').length) { return; }
        var credits = $td.find('.xtai-credits-source').first().attr('data-credits') || '';
        $td.prepend(
            '<div class="xtai-credits-pill"><span>' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5c0-1 1-1.5 2.5-1.5s2.5.5 2.5 1.5-1 1.5-2.5 1.5-2.5.5-2.5 1.5 1 1.5 2.5 1.5 2.5-.5 2.5-1.5"/></svg>' +
            'Credits: ' +
            $('<div>').text(credits).html() +
            '</span></div>'
        );
    }
    setTimeout(run, 60);
    setTimeout(run, 300);
})();
</script>
JS;
    return $style . '<span class="xtai-credits-source" data-credits="' . $data . '"></span>' . $script;
}

function xtreamai_packageFilter(array $packageOptions): string
{
    $payload = base64_encode(json_encode($packageOptions));
    $data = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
    $script = <<<'JS'
<script>
(function () {
    function boot() {
        if (!window.jQuery) { return; }
        var $ = window.jQuery;
        var $pkg = $('[name="packageconfigoption[2]"]').first();
        var $type = $('[name="packageconfigoption[3]"]').first();
        if (!$pkg.length || !$type.length || $pkg.data('xtai-package-ready')) { return; }
        $pkg.data('xtai-package-ready', 1);
        var $src = $pkg.closest('td').find('.xtai-package-source').first();
        var raw = $src.attr('data-packages') || '';
        var sets = { official: {}, trial: {} };
        try { sets = raw ? JSON.parse(atob(raw)) : sets; } catch (e) { sets = { official: {}, trial: {} }; }
        function render(type) {
            type = String(type || 'official').toLowerCase();
            if (type !== 'trial') { type = 'official'; }
            var list = sets[type] || {};
            var current = String($pkg.val() || '');
            $pkg.empty();
            var keys = Object.keys(list);
            if (!keys.length) {
                $pkg.append($('<option>', { value: '0', text: 'No ' + (type === 'trial' ? 'trial' : 'official') + ' packages found' }));
                return;
            }
            keys.forEach(function (id) {
                $pkg.append($('<option>', { value: id, text: list[id] }));
            });
            if (Object.prototype.hasOwnProperty.call(list, current)) {
                $pkg.val(current);
            } else {
                $pkg.val(keys[0]);
            }
            $pkg.trigger('change');
        }
        $type.on('change.xtaiPackages', function () { render($(this).val()); });
    }
    boot();
    setTimeout(boot, 60);
    setTimeout(boot, 300);
})();
</script>
JS;
    return '<span class="xtai-package-source" data-packages="' . $data . '"></span>' . $script;
}

function xtreamai_accountTypeVisibility(): string
{
    $script = <<<'JS'
<script>
(function () {
    function boot() {
        if (!window.jQuery) { return; }
        var $ = window.jQuery;
        var $type = $('[name="packageconfigoption[5]"]').first();
        var $credits = $('[name="packageconfigoption[6]"]').first();
        var $group = $('[name="packageconfigoption[8]"]').first();
        var $scope = $('[name="packageconfigoption[10]"]').first();
        var $customer = $('[name="packageconfigoption[11]"]').first();
        var $password = $('[name="packageconfigoption[12]"]').first();
        if (!$type.length || !$credits.length || $type.data('xtai-account-ready')) { return; }
        $type.data('xtai-account-ready', 1);
        function toggleCells($field, show) {
            var $inputTd = $field.closest('td');
            if (!$inputTd.length) { return; }
            var $labelTd = $inputTd.prev('td');
            $inputTd.toggle(show);
            $labelTd.toggle(show);
        }
        function apply() {
            var type = String($type.val() || '').toLowerCase();
            var isReseller = type === 'reseller';
            var isTopUp = type === 'topup';
            toggleCells($credits, isReseller || isTopUp);
            if ($group.length) { toggleCells($group, isReseller); }
            if ($scope.length) { toggleCells($scope, isTopUp); }
            if ($customer.length) { toggleCells($customer, !isTopUp); }
            if ($password.length) { toggleCells($password, !isTopUp); }
        }
        $type.on('change.xtaiAccount', apply);
        apply();
    }
    boot();
    setTimeout(boot, 60);
    setTimeout(boot, 300);
})();
</script>
JS;
    return $script;
}

function xtreamai_bouquetPicker(array $bouquets): string
{
    $payload = base64_encode(json_encode(array_values($bouquets)));
    $data = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
    $style = '<style>'
        . '.xtai-bouquet-box{border:1px solid #e1e5ed;border-radius:10px;background:#fff;overflow:hidden;margin-top:6px}'
        . '.xtai-bouquet-toolbar{display:flex;align-items:center;gap:10px;padding:8px 10px;border-bottom:1px solid #eef0f5;background:#f8fafc}'
        . '.xtai-bouquet-search{flex:1;min-width:0;font:inherit;font-size:13px;padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#1f2937;transition:border-color .16s ease,box-shadow .16s ease}'
        . '.xtai-bouquet-search:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.16)}'
        . '.xtai-bouquet-count{flex:none;display:inline-flex;align-items:center;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}'
        . '.xtai-bouquet-list{max-height:200px;overflow:auto;padding:6px 10px}'
        . '.xtai-bouquet-item{display:flex;gap:10px;align-items:center;padding:6px 4px;margin:0;cursor:pointer;border-radius:7px;transition:background .15s ease}'
        . '.xtai-bouquet-item:hover{background:#f3f4f6}'
        . '.xtai-bouquet-item input{position:absolute;opacity:0;width:1px;height:1px;overflow:hidden}'
        . '.xtai-checkmark{flex:none;width:18px;height:18px;border:1.5px solid #d1d5db;border-radius:5px;background:#fff;display:inline-flex;align-items:center;justify-content:center;transition:background .15s ease,border-color .15s ease}'
        . '.xtai-bouquet-item input:checked + .xtai-checkmark{background:#2563eb;border-color:#2563eb}'
        . '.xtai-bouquet-item input:checked + .xtai-checkmark::after{content:"";width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg);margin-top:-1px}'
        . '.xtai-bouquet-name{font-size:13px;color:#1f2937;line-height:1.4;min-width:0}'
        . '.xtai-bouquet-empty{padding:14px 6px;text-align:center;color:#6b7280;font-size:12.5px}'
        . '.xtai-bouquet-hint{margin-top:7px;color:#6d7890;font-size:12px}'
        . '</style>';
    $script = <<<'JS'
<script>
(function () {
    function boot() {
        if (!window.jQuery) { return; }
        var $ = window.jQuery;
        var $input = $('[name="packageconfigoption[4]"]').first();
        if (!$input.length || $input.data('xtai-bouquet-ready')) { return; }
        $input.data('xtai-bouquet-ready', 1);
        var raw = $input.closest('td').find('.xtai-bouquet-source').first().attr('data-bouquets') || '';
        var items = [];
        try { items = raw ? JSON.parse(atob(raw)) : []; } catch (e) { items = []; }
        function ids(v) {
            return String(v || '').split(/[^0-9]+/).filter(function (x) { return parseInt(x, 10) > 0; });
        }
        var selected = ids($input.val());
        $input.attr('type', 'hidden');
        var $box = $('<div class="xtai-bouquet-box"></div>');
        $input.after($box);

        function sync() {
            var vals = [];
            $box.find('input:checked').each(function () { vals.push($(this).val()); });
            $input.val(vals.join(','));
            var n = vals.length;
            $box.find('.xtai-bouquet-count').text(n + ' selected');
        }

        if (!items.length) {
            $box.html('<div class="alert alert-warning" style="margin:0">No bouquets were returned by this panel. Check the API permissions, then save and reload.</div>');
            return;
        }

        $box.append(
            '<div class="xtai-bouquet-toolbar">' +
            '<input type="text" class="xtai-bouquet-search" placeholder="Search bouquets..." aria-label="Search bouquets">' +
            '<span class="xtai-bouquet-count">' + selected.length + ' selected</span>' +
            '</div>' +
            '<div class="xtai-bouquet-list"></div>'
        );
        var $list = $box.find('.xtai-bouquet-list');

        function renderList(filter) {
            filter = String(filter || '').toLowerCase();
            var visible = 0;
            items.forEach(function (b) {
                var id = String(b.id);
                var name = b.name || ('Bouquet #' + id);
                var $existing = $list.find('.xtai-bouquet-item[data-id="' + id + '"]');
                if (filter && name.toLowerCase().indexOf(filter) === -1) {
                    if ($existing.length) { $existing.hide(); }
                    return;
                }
                visible++;
                if ($existing.length) { $existing.show(); return; }
                var checked = selected.indexOf(id) !== -1 ? ' checked' : '';
                var $label = $(
                    '<label class="xtai-bouquet-item" data-id="' + id + '">' +
                    '<input type="checkbox" value="' + id + '"' + checked + '>' +
                    '<span class="xtai-checkmark"></span>' +
                    '<span class="xtai-bouquet-name">' + $('<div>').text(name).html() + '</span>' +
                    '</label>'
                );
                $list.append($label);
            });
            var $empty = $list.find('.xtai-bouquet-empty');
            if (!visible) {
                if (!$empty.length) { $empty = $('<div class="xtai-bouquet-empty">No bouquets match your search.</div>'); $list.append($empty); }
                $empty.show();
            } else if ($empty.length) {
                $empty.hide();
            }
        }

        $box.on('input', '.xtai-bouquet-search', function () { renderList($(this).val()); });
        $box.on('change', 'input[type=checkbox]', sync);
        renderList('');
    }
    boot();
    setTimeout(boot, 60);
    setTimeout(boot, 300);
})();
</script>
JS;
    return $style . '<span class="xtai-bouquet-source" data-bouquets="' . $data . '"></span>'
        . '<div class="xtai-bouquet-hint">Select only the bouquets that should be activated for lines created from this product.</div>'
        . $script;
}

function xtreamai_configOptionValue(int $slot, string $default = ''): string
{
    if ($slot < 1) {
        return $default;
    }
    if (isset($_REQUEST['packageconfigoption'][$slot])) {
        return trim((string) $_REQUEST['packageconfigoption'][$slot]);
    }
    $productId = xtreamai_requestProductId();
    if ($productId > 0) {
        try {
            $product = Capsule::table('tblproducts')->where('id', $productId)->first();
            $field = 'configoption' . $slot;
            if ($product && isset($product->{$field}) && (string) $product->{$field} !== '') {
                return trim((string) $product->{$field});
            }
        } catch (\Throwable $e) {

        }
    }
    return $default;
}

function xtreamai_requestProductId(): int
{
    return !empty($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
}

function xtreamai_configPanelId(): int
{
    $raw = xtreamai_configOptionValue(1, '');
    if ($raw !== '') {
        if (preg_match('/^(\d+)\s*:/', $raw, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^[0-9]+$/', $raw) === 1) {
            return (int) $raw;
        }
        return (int) $raw;
    }
    try {
        $panel = \WhmcsXtreamAI\PanelStore::firstActive();
        return $panel ? (int) $panel->id : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

function xtreamai_parsePanelPackage(array $params): array
{
    $opt1 = $params['configoption1'] ?? '';
    if ($opt1 === '' || $opt1 === null) {
        $opt1 = $params['configoptions']['panel_id'] ?? '';
    }
    $opt2 = $params['configoption2'] ?? null;
    if ($opt2 === null || $opt2 === '') {
        $opt2 = $params['configoptions']['package_id'] ?? '';
    }
    $opt3 = $params['configoption3'] ?? null;
    if ($opt3 === null || $opt3 === '') {
        $opt3 = $params['configoptions']['package_type'] ?? 'official';
    }

    return [
        'panel_id' => (int) $opt1,
        'package_id' => (int) $opt2,
        'package_type' => strtolower(trim((string) $opt3)),
    ];
}

function xtreamai_accountType(array $params): string
{
    $type = strtolower(trim((string) ($params['configoption5'] ?? ($params['configoptions']['account_type'] ?? ''))));
    if ($type === 'reseller') {
        return 'reseller';
    }
    if ($type === 'topup') {
        return 'topup';
    }
    return 'line';
}

function xtreamai_resellerCredits(array $params): float
{
    $raw = $params['configoption6'] ?? ($params['configoptions']['credits'] ?? '0');
    if (is_array($raw)) {
        $raw = reset($raw);
    }
    $credits = (float) $raw;
    return $credits > 0 ? $credits : 0.0;
}

function xtreamai_formatCredits(float $credits): string
{
    return rtrim(rtrim(number_format($credits, 2, '.', ''), '0'), '.');
}

function xtreamai_topUpCredits(array $params): float
{
    $option = xtreamai_configurableOptionInt($params, ['credits', 'credit_amount', 'topup_credits']);
    if ($option !== null && $option > 0) {
        return (float) $option;
    }

    $credits = xtreamai_resellerCredits($params);
    if ($credits > 0) {
        return $credits;
    }

    throw new \RuntimeException('No credit amount configured for this top-up product. Set Credits on the product\'s Module Settings tab or add a configurable option named credits.');
}

function xtreamai_customFieldKeys(string $name): array
{
    $pipe = strpos($name, '|');
    if ($pipe === false) {
        return [xtreamai_configurableOptionKey($name)];
    }

    return [xtreamai_configurableOptionKey(substr($name, 0, $pipe)), xtreamai_configurableOptionKey(substr($name, $pipe + 1))];
}

function xtreamai_customFieldValue(array $params, array $keys, bool $trim = true): string
{
    $fields = $params['customfields'] ?? null;
    if (!is_array($fields)) {
        return '';
    }

    foreach ($fields as $name => $value) {
        $found = false;
        foreach (xtreamai_customFieldKeys((string) $name) as $key) {
            if ($key !== '' && in_array($key, $keys, true)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            continue;
        }
        if (is_array($value)) {
            $value = reset($value);
        }

        $raw = (string) $value;

        return $trim ? trim($raw) : $raw;
    }

    return '';
}

function xtreamai_topUpUsernameField(array $params): string
{
    return xtreamai_customFieldValue($params, ['reseller_username', 'panel_username', 'sub_reseller_username']);
}

function xtreamai_lineUsernameField(array $params): string
{
    return xtreamai_customFieldValue($params, ['line_username', 'username', 'panel_username']);
}

function xtreamai_resellerUsernameField(array $params): string
{
    return xtreamai_customFieldValue($params, ['reseller_username', 'sub_reseller_username', 'panel_username', 'username']);
}

function xtreamai_topUpUsernames(array $candidates): array
{
    $names = [];
    foreach ($candidates as $candidate) {
        $names[] = (string) $candidate['username'];
    }

    return $names;
}

function xtreamai_topUpTarget(array $params, int $panelId): array
{
    $candidates = \WhmcsXtreamAI\ServiceStore::resellerServicesForClient((int) ($params['userid'] ?? 0), $panelId);
    $wanted = xtreamai_topUpUsernameField($params);

    if ($wanted !== '') {
        foreach ($candidates as $candidate) {
            if (strcasecmp((string) $candidate['username'], $wanted) === 0) {
                return $candidate;
            }
        }

        if (xtreamai_topUpScope($params) === 'linked') {
            throw new \RuntimeException('The reseller username "' . $wanted . '" does not match any of this client\'s linked Sub-Reseller accounts on this panel.');
        }

        $found = \WhmcsXtreamAI\PanelApi::findResellerByUsername($panelId, $wanted);
        if ($found === null) {
            throw new \RuntimeException('The reseller username "' . $wanted . '" was not found on this panel.');
        }

        $adminOwnerId = \WhmcsXtreamAI\PanelApi::adminOwnerMemberId($panelId);
        if ((int) $found['member_group_id'] === 1
            || ($adminOwnerId !== null && (string) $found['id'] === (string) $adminOwnerId)) {
            throw new \RuntimeException('The reseller username "' . $wanted . '" belongs to a panel administrator and cannot receive a top-up.');
        }

        return [
            'service_id' => 0,
            'reseller_id' => (string) $found['id'],
            'username' => (string) $found['username'],
        ];
    }

    if (count($candidates) === 1) {
        return $candidates[0];
    }

    if ($candidates === []) {
        throw new \RuntimeException('This client has no active Sub-Reseller service on this panel to top up. Add a required custom field named "Reseller username" to the top-up product so the customer types the panel account, or order the Sub-Reseller product first.');
    }

    throw new \RuntimeException('This client has several Sub-Reseller accounts on this panel (' . implode(', ', xtreamai_topUpUsernames($candidates)) . '). Add a required custom field named "Reseller username" to the top-up product so the customer chooses the account.');
}

function xtreamai_panelIdForService(array $params): int
{
    $parsed = xtreamai_parsePanelPackage($params);
    if ($parsed['panel_id'] > 0) {
        return $parsed['panel_id'];
    }
    try {
        $row = \WhmcsXtreamAI\ServiceStore::find((int) ($params['serviceid'] ?? 0));
        if ($row && !empty($row->panel_id)) {
            return (int) $row->panel_id;
        }
    } catch (\Throwable $e) {

    }
    $panel = \WhmcsXtreamAI\PanelStore::firstActive();
    return $panel ? (int) $panel->id : 0;
}

function xtreamai_requirePanelId(array $params): int
{
    $panelId = xtreamai_panelIdForService($params);
    if ($panelId < 1) {
        throw new \RuntimeException('No panel found. Add and activate a panel in Addons → Xtream AI Panel.');
    }
    return $panelId;
}

function xtreamai_packageIdForService(array $params): int
{
    return xtreamai_parsePanelPackage($params)['package_id'];
}

function xtreamai_lineIdForService(array $params): string
{
    $row = \WhmcsXtreamAI\ServiceStore::find((int) ($params['serviceid'] ?? 0));
    if ($row && !empty($row->panel_account_id)) {
        return (string) $row->panel_account_id;
    }
    throw new \RuntimeException('This service has no panel line yet. Provision it first.');
}

function xtreamai_selectedBouquets(array $params): array
{
    $raw = $params['configoption4'] ?? ($params['configoptions']['bouquets'] ?? '');
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/[^0-9]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            $parts = [];
        }
    }
    $out = [];
    foreach ($parts as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function xtreamai_configurableOptionKey(string $name): string
{
    $key = strtolower(trim($name));
    $pipe = strpos($key, '|');
    if ($pipe !== false) {
        $key = substr($key, 0, $pipe);
    }
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    if ($key === null) {
        return '';
    }
    return trim($key, '_');
}

function xtreamai_configurableOptionInt(array $params, array $names): ?int
{
    $options = $params['configoptions'] ?? null;
    if (!is_array($options)) {
        return null;
    }
    $byKey = [];
    foreach ($options as $name => $value) {
        $key = xtreamai_configurableOptionKey((string) $name);
        if ($key !== '' && !array_key_exists($key, $byKey)) {
            $byKey[$key] = $value;
        }
    }
    foreach ($names as $name) {
        if (!array_key_exists($name, $byKey)) {
            continue;
        }
        $value = $byKey[$name];
        if (is_array($value)) {
            $value = reset($value);
        }
        if (preg_match('/\d+/', (string) $value, $m) !== 1) {
            return 0;
        }
        return (int) $m[0];
    }
    return null;
}

function xtreamai_maxConnectionsForService(array $params, int $panelId, int $packageId): int
{
    $productMax = xtreamai_clamp((int) trim((string) ($params['configoption7'] ?? '0')), 0, 100);

    $absolute = xtreamai_configurableOptionInt($params, ['max_connections', 'connections']);
    $extra = xtreamai_configurableOptionInt($params, ['extra_connections', 'additional_connections']);

    $base = ($absolute !== null && $absolute > 0) ? $absolute : $productMax;
    if ($extra === null) {
        return xtreamai_clamp($base, 0, 100);
    }
    if (\WhmcsXtreamAI\PanelApi::keyType($panelId) !== 'admin') {
        return 0;
    }
    if ($base < 1) {
        if ($packageId < 1) {
            throw new \RuntimeException('No package selected for this product.');
        }
        $base = \WhmcsXtreamAI\PanelApi::packageMaxConnections($panelId, $packageId);
    }
    return xtreamai_clamp($base + $extra, 1, 100);
}

function xtreamai_currentPackageIdForService(array $params): int
{
    try {
        $row = \WhmcsXtreamAI\ServiceStore::find((int) ($params['serviceid'] ?? 0));
        if ($row !== null && !empty($row->package_id)) {
            return (int) $row->package_id;
        }
    } catch (\Throwable $e) {

    }
    return xtreamai_packageIdForService($params);
}

function xtreamai_subResellerMemberGroupId(array $params): int
{
    $raw = $params['configoption8'] ?? ($params['configoptions']['sub_reseller_member_group_id'] ?? '0');
    if (is_array($raw)) {
        $raw = reset($raw);
    }
    $value = (int) trim((string) $raw);
    return $value > 0 ? $value : 0;
}

function xtreamai_suspendAction(array $params): string
{
    $raw = $params['configoption9'] ?? ($params['configoptions']['suspend_action'] ?? '');
    if (is_array($raw)) {
        $raw = reset($raw);
    }
    return strtolower(trim((string) $raw)) === 'none' ? 'none' : 'disable';
}

function xtreamai_topUpScope(array $params): string
{
    $raw = $params['configoption10'] ?? ($params['configoptions']['topup_scope'] ?? '');
    if (is_array($raw)) {
        $raw = reset($raw);
    }
    return strtolower(trim((string) $raw)) === 'linked' ? 'linked' : 'any';
}

function xtreamai_customerUsernameEnabled(array $params): bool
{
    $raw = $params['configoption11'] ?? ($params['configoptions']['customer_username'] ?? '');
    if (is_array($raw)) {
        $raw = reset($raw);
    }
    return strtolower(trim((string) $raw)) === 'on';
}

function xtreamai_customerPasswordEnabled(array $params): bool
{
    $raw = $params['configoption12'] ?? ($params['configoptions']['customer_password'] ?? '');
    if (is_array($raw)) {
        $raw = reset($raw);
    }
    return strtolower(trim((string) $raw)) === 'on';
}

function xtreamai_customerPasswordField(array $params): string
{
    return xtreamai_customFieldValue($params, ['panel_password', 'password', 'line_password', 'reseller_password'], false);
}

function xtreamai_validateCustomerPassword(string $password): ?string
{
    if (preg_match('/^[^\s%&?#\/\\\\+]{8,32}$/', $password) === 1) {
        return null;
    }

    return 'The password is not valid: use 8 to 32 characters without spaces and without % & ? # / \\ +';
}

function xtreamai_clearCustomFieldValue(array $params, array $fieldKeys): void
{
    $pid = (int) ($params['pid'] ?? 0);
    $serviceId = (int) ($params['serviceid'] ?? 0);
    if ($pid < 1 || $serviceId < 1) {
        return;
    }

    try {
        $rows = Capsule::table('tblcustomfields')->where('type', 'product')->where('relid', $pid)->get();
        foreach ($rows as $row) {
            $matched = false;
            foreach (xtreamai_customFieldKeys((string) ($row->fieldname ?? '')) as $key) {
                if ($key !== '' && in_array($key, $fieldKeys, true)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', (int) ($row->id ?? 0))
                ->where('relid', $serviceId)
                ->update(['value' => '']);
        }
    } catch (\Throwable $e) {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'xtreamai',
                'clear_custom_field',
                'service=' . $serviceId . ' field=' . implode(',', $fieldKeys),
                $e->getMessage()
            );
        }
    }
}

function xtreamai_clamp(int $value, int $min, int $max): int
{
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function xtreamai_randomString(int $length, string $type = 'alphanumeric'): string
{
    return \WhmcsXtreamAI\Settings::randomString($length, $type);
}

function xtreamai_lineUsername(array $params): string
{
    $settings = \WhmcsXtreamAI\Settings::credentialSettings();

    $existing = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($params['username'] ?? ''));
    if ($existing === null) {
        $existing = '';
    }

    if ($settings['username_auto'] !== '1' && strlen($existing) >= 3) {
        return substr($existing, 0, 32);
    }

    $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $settings['username_prefix']);
    if ($prefix === null) {
        $prefix = '';
    }
    $prefix = substr($prefix, 0, 10);

    $length = xtreamai_clamp((int) $settings['username_length'], 4, 32);

    if ($length < strlen($prefix)) {
        $prefix = substr($prefix, 0, max(0, $length - 3));
    }

    $randomLength = max(3, $length - strlen($prefix));

    return substr($prefix . xtreamai_randomString($randomLength, $settings['username_type']), 0, $length);
}

function xtreamai_notesEnabled(): bool
{
    return \WhmcsXtreamAI\Settings::get('notes_enabled', '1') !== '0';
}

function xtreamai_renderNotes(array $params): ?string
{
    if (!xtreamai_notesEnabled()) {
        return null;
    }

    $template = (string) \WhmcsXtreamAI\Settings::get('reseller_notes', '');
    if ($template === '') {
        $template = 'WHMCS:{service_id}';
    }
    $client = (isset($params['clientsdetails']) && is_array($params['clientsdetails']))
        ? $params['clientsdetails']
        : [];
    $tags = [
        '{service_id}' => (string) ($params['serviceid'] ?? ''),
        '{client_id}' => (string) ($params['userid'] ?? ''),
        '{client_name}' => trim((($client['firstname'] ?? '') . ' ') . ($client['lastname'] ?? '')),
        '{client_email}' => (string) ($client['email'] ?? ''),
        '{client_phonenumber}' => (string) ($client['phonenumber'] ?? ''),
        '{product_name}' => (string) ($params['productname'] ?? ''),
    ];
    return substr(strtr($template, $tags), 0, 2000);
}

function xtreamai_linePassword(array $params): string
{
    $settings = \WhmcsXtreamAI\Settings::credentialSettings();

    $existing = preg_replace('/[^A-Za-z0-9]/', '', trim((string) ($params['password'] ?? '')));
    if ($existing === null) {
        $existing = '';
    }

    if ($settings['password_auto'] !== '1' && strlen($existing) >= 8) {
        return substr($existing, 0, 32);
    }

    $length = xtreamai_clamp((int) $settings['password_length'], 8, 32);

    return xtreamai_randomString($length, $settings['password_type']);
}

function xtreamai_updateHostingCredentials(int $serviceId, string $username, string $password): void
{
    if ($serviceId < 1) {
        return;
    }
    $update = [];
    if ($username !== '') {
        $update['username'] = $username;
    }
    if ($password !== '') {
        $update['password'] = encrypt($password);
    }
    if ($update) {
        Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
    }
}

function xtreamai_formatDuration(int $seconds): string
{
    if ($seconds < 0) {
        $seconds = 0;
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    return $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
}

function xtreamai_formatDate(string $value): string
{
    $raw = trim($value);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    $ts = strtotime($raw);
    if ($ts) {
        return gmdate('d/m/Y', $ts);
    }
    return $raw;
}

function xtreamai_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function xtreamai_fillClientUrl(string $template, string $username, string $password): string
{
    if ($template === '') {
        return '';
    }

    $filled = preg_replace_callback(
        '/\{(username|password)\}/i',
        static function (array $matches) use ($username, $password): string {
            return strtolower($matches[1]) === 'username'
                ? rawurlencode($username)
                : rawurlencode($password);
        },
        $template
    );

    return is_string($filled) ? $filled : $template;
}

function xtreamai_expiryDate(array $line): string
{
    if (empty($line['expires_at'])) {
        return '';
    }
    $raw = trim((string) $line['expires_at']);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    $ts = strtotime($raw);
    if ($ts) {
        return gmdate('Y-m-d', $ts);
    }
    return '';
}

function xtreamai_isFreeBilling(string $billingCycle): bool
{
    $cycle = strtolower(str_replace([' ', '-', '_'], '', $billingCycle));
    return $cycle === 'free' || $cycle === 'freeaccount';
}

function xtreamai_hidesNextDueDate(string $billingCycle): bool
{
    $cycle = strtolower(str_replace([' ', '-', '_'], '', $billingCycle));
    return $cycle === 'free' || $cycle === 'freeaccount' || $cycle === 'onetime';
}

function xtreamai_updateNextDueDate(int $serviceId, array $line, array $params): void
{
    if ($serviceId < 1) {
        return;
    }
    $billingCycle = (string) ($params['billingcycle'] ?? '');
    if (xtreamai_hidesNextDueDate($billingCycle)) {
        return;
    }
    $expiry = xtreamai_expiryDate($line);
    if ($expiry === '') {
        return;
    }
    Capsule::table('tblhosting')->where('id', $serviceId)->update(['nextduedate' => $expiry]);
}

function xtreamai_serviceNextDueDate(int $serviceId): string
{
    if ($serviceId < 1) {
        return '';
    }

    try {
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    } catch (\Throwable $e) {
        return '';
    }

    if (!$hosting || empty($hosting->nextduedate)) {
        return '';
    }

    $value = trim((string) $hosting->nextduedate);
    if ($value === '' || strpos($value, '0000-00-00') === 0) {
        return '';
    }

    return $value;
}

function xtreamai_dateTimestamp(string $value, int $hour = 0): ?int
{
    $raw = trim($value);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return null;
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $matches) === 1) {
        $clock = str_pad((string) xtreamai_clamp($hour, 0, 23), 2, '0', STR_PAD_LEFT) . ':00:00 UTC';
        $timestamp = strtotime($matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $clock);

        return $timestamp === false ? null : (int) $timestamp;
    }

    $timestamp = strtotime($raw);

    return $timestamp === false ? null : (int) $timestamp;
}

function xtreamai_expiryDivergence(string $nextDue, string $panelExpiry): string
{
    $dueTs = xtreamai_dateTimestamp($nextDue);
    $panelTs = xtreamai_dateTimestamp($panelExpiry);
    if ($dueTs === null || $panelTs === null) {
        return '';
    }
    if (abs($panelTs - $dueTs) <= 86400) {
        return '';
    }

    return 'WHMCS next due date is ' . xtreamai_formatDate($nextDue)
        . ', the panel line expires ' . xtreamai_formatDate($panelExpiry)
        . '. Editing the next due date in WHMCS does not change the panel; use the buttons below.';
}

function xtreamai_shortError(string $message, int $limit = 120): string
{
    $clean = preg_replace('/\s+/', ' ', trim($message));
    if (!is_string($clean) || $clean === '') {
        return 'unknown error';
    }
    if (strlen($clean) > $limit) {
        $clean = rtrim(substr($clean, 0, $limit - 3)) . '...';
    }

    return $clean;
}

function xtreamai_clock(string $value): string
{
    $ts = strtotime(trim($value));
    if ($ts === false || $ts === 0) {
        return '';
    }

    return date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('d/m/Y H:i', $ts);
}

function xtreamai_panelCheckLabel(array $check, $row): string
{
    $source = (string) ($check['source'] ?? '');

    if ($source === 'live' || $source === 'cached') {
        $stamp = !empty($check['checked_at']) ? (string) $check['checked_at'] : (string) ($row->updated_at ?? '');
        $time = xtreamai_clock($stamp);

        return ($time !== '' ? $time . ' · ' : '') . $source;
    }

    if ($source === 'unlinked') {
        return 'Not linked yet';
    }

    if ($source === 'not_applicable') {
        return 'Not applicable to Sub-Reseller accounts';
    }

    $reason = (string) ($check['error'] ?? '');
    if ($reason === '') {
        $reason = 'unknown error';
    }

    $stamp = !empty($row->panel_checked_at) ? (string) $row->panel_checked_at : (string) ($row->updated_at ?? '');
    $time = xtreamai_clock($stamp);

    return $time === ''
        ? 'Panel check: failed (' . $reason . ') · showing the local record'
        : 'Panel check: failed (' . $reason . ') · showing data from ' . $time;
}

function xtreamai_lastActionLabel($row): string
{
    $action = trim((string) ($row->last_action ?? ''));
    if ($action === '') {
        return 'None yet';
    }

    $time = xtreamai_clock((string) ($row->last_action_at ?? ''));

    return $time === '' ? $action : $action . ' · ' . $time;
}

function xtreamai_panelSummary(array $line, string $status): string
{
    $summary = 'Panel check: ' . ($status !== '' ? $status : 'unknown');
    if (!empty($line['expires_at'])) {
        $summary .= ', expires ' . xtreamai_formatDate((string) $line['expires_at']);
    }

    return $summary;
}

function xtreamai_refreshFromPanel(array $params, bool $force = false): array
{
    $out = [
        'line' => null,
        'source' => 'failed',
        'status' => '',
        'checked_at' => null,
        'error' => '',
    ];

    try {
        xtreamai_requireAddon();
        \WhmcsXtreamAI\Settings::ensureTables();

        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId < 1) {
            $out['source'] = 'invalid';
            $out['error'] = 'No service id.';

            return $out;
        }

        if (xtreamai_accountType($params) === 'reseller') {
            $out['source'] = 'not_applicable';
            $out['error'] = 'Sub-Reseller product: no panel line to read.';

            return $out;
        }

        if (xtreamai_accountType($params) === 'topup') {
            $out['source'] = 'not_applicable';
            $out['error'] = 'Credit top-up product: no panel line to read.';

            return $out;
        }

        $row = \WhmcsXtreamAI\ServiceStore::find($serviceId);
        if (!$row || empty($row->panel_account_id)) {
            $out['source'] = 'unlinked';
            $out['error'] = 'Not linked to a panel line yet.';

            return $out;
        }

        $checkedAt = !empty($row->panel_checked_at) ? (string) $row->panel_checked_at : '';
        $checkedTs = $checkedAt === '' ? 0 : (int) strtotime($checkedAt);
        if (!$force && $checkedTs > 0 && (time() - $checkedTs) < 90) {
            $out['source'] = 'cached';
            $out['status'] = (string) $row->status;
            $out['checked_at'] = $checkedAt;

            return $out;
        }

        $panelId = xtreamai_requirePanelId($params);
        $line = \WhmcsXtreamAI\PanelApi::getLine($panelId, (string) $row->panel_account_id, true);
        if (!$line || empty($line['id'])) {
            $out['error'] = 'The panel did not return this line.';

            return $out;
        }

        $status = \WhmcsXtreamAI\LineStatus::fromPanel($line);
        \WhmcsXtreamAI\ServiceStore::updateStatus(
            $serviceId,
            $status,
            isset($line['expires_at']) ? (string) $line['expires_at'] : null
        );
        \WhmcsXtreamAI\ServiceStore::recordPanelCheck($serviceId);

        $out['line'] = $line;
        $out['source'] = 'live';
        $out['status'] = $status;
        $out['checked_at'] = date('Y-m-d H:i:s');

        return $out;
    } catch (\Throwable $e) {
        $out['source'] = 'failed';
        $out['error'] = xtreamai_shortError($e->getMessage());

        return $out;
    }
}

function xtreamai_logModuleCall(string $action, string $requestSummary, string $responseSummary, string $result): void
{
    if (!function_exists('logModuleCall')) {
        return;
    }
    logModuleCall('xtreamai', $action, $requestSummary, $responseSummary, $result, []);
}

function xtreamai_logRequestSummary(string $action, array $params): string
{
    $parts = [];

    $serviceId = (int) ($params['serviceid'] ?? 0);
    if ($serviceId > 0) {
        $parts[] = 'service=' . $serviceId;
    }

    try {
        $parts[] = 'panel=' . xtreamai_requirePanelId($params);
    } catch (\Throwable $e) {

    }

    $packageId = xtreamai_packageIdForService($params);
    if ($packageId > 0) {
        $parts[] = 'package=' . $packageId;
    }

    try {
        $lineId = xtreamai_lineIdForService($params);
        if ($lineId !== '') {
            $parts[] = 'line=' . $lineId;
        }
    } catch (\Throwable $e) {

    }

    return $action . ($parts ? ' ' . implode(' ', $parts) : '');
}

function xtreamai_execute(array $params, string $action, callable $fn): string
{
    $requestSummary = xtreamai_logRequestSummary($action, $params);

    try {
        xtreamai_requireAddon();
        \WhmcsXtreamAI\Settings::ensureTables();
        $result = $fn($params);
        xtreamai_logModuleCall($action, $requestSummary, 'success', (string) $result);
        return $result;
    } catch (\Throwable $e) {
        $message = $e->getMessage();
        xtreamai_logModuleCall($action, $requestSummary, $message, $message);
        return $message;
    }
}

function xtreamai_CreateAccount(array $params)
{
    return xtreamai_execute($params, 'create', static function (array $params): string {
        if (xtreamai_accountType($params) === 'reseller') {
            return xtreamai_createResellerAccount($params);
        }

        if (xtreamai_accountType($params) === 'topup') {
            return xtreamai_createTopUp($params);
        }

        $panelId = xtreamai_requirePanelId($params);
        $packageId = xtreamai_packageIdForService($params);
        if ($packageId < 1) {
            throw new \RuntimeException('No package selected for this product.');
        }
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId < 1) {
            throw new \RuntimeException('Invalid service id.');
        }

        $requested = xtreamai_customerUsernameEnabled($params) ? xtreamai_lineUsernameField($params) : '';
        if ($requested !== '') {
            if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $requested) !== 1) {
                $shown = substr((string) preg_replace('/[^\x20-\x7E]|[<>"\'&]/', '', $requested), 0, 40);
                throw new \RuntimeException(
                    'The username "' . $shown . '" is not valid: use 3 to 32 letters, digits, dashes or underscores.'
                );
            }
            foreach (\WhmcsXtreamAI\PanelApi::lines($panelId, $requested) as $existingLine) {
                if (strcasecmp((string) ($existingLine['username'] ?? ''), $requested) === 0) {
                    throw new \RuntimeException(
                        'The username "' . $requested . '" is already taken on this panel. Ask the customer to choose another one.'
                    );
                }
            }
        }

        $username = $requested !== '' ? $requested : xtreamai_lineUsername($params);

        $requestedPassword = xtreamai_customerPasswordEnabled($params) ? xtreamai_customerPasswordField($params) : '';
        if ($requestedPassword !== '') {
            $passwordError = xtreamai_validateCustomerPassword($requestedPassword);
            if ($passwordError !== null) {
                throw new \RuntimeException($passwordError);
            }
        }

        $password = $requestedPassword !== '' ? $requestedPassword : xtreamai_linePassword($params);
        $bouquets = xtreamai_selectedBouquets($params);
        $notes = xtreamai_renderNotes($params);

        $memberId = \WhmcsXtreamAI\PanelApi::keyType($panelId) === 'admin'
            ? \WhmcsXtreamAI\PanelApi::adminOwnerMemberId($panelId)
            : null;

        $maxConn = xtreamai_maxConnectionsForService($params, $panelId, $packageId);
        $line = \WhmcsXtreamAI\PanelApi::createLine(
            $panelId,
            $packageId,
            $memberId,
            $username,
            $password,
            $bouquets,
            $maxConn > 0 ? $maxConn : null,
            $notes
        );

        $finalUsername = !empty($line['username']) ? (string) $line['username'] : $username;
        $finalPassword = !empty($line['password']) ? (string) $line['password'] : $password;

        \WhmcsXtreamAI\ServiceStore::link($serviceId, $panelId, (string) $line['id'], $finalUsername, $packageId);
        \WhmcsXtreamAI\ServiceStore::updateStatus(
            $serviceId,
            'Active',
            isset($line['expires_at']) ? (string) $line['expires_at'] : null
        );

        xtreamai_updateHostingCredentials($serviceId, $finalUsername, $finalPassword);
        xtreamai_updateNextDueDate($serviceId, $line, $params);

        if ($requestedPassword !== '') {
            xtreamai_clearCustomFieldValue($params, ['panel_password', 'password', 'line_password', 'reseller_password']);
        }

        return 'success';
    });
}

function xtreamai_createResellerAccount(array $params): string
{
    $panelId = xtreamai_requirePanelId($params);
    $serviceId = (int) ($params['serviceid'] ?? 0);
    if ($serviceId < 1) {
        throw new \RuntimeException('Invalid service id.');
    }

    $email = trim((string) ($params['clientsdetails']['email'] ?? ''));
    if ($email === '') {
        throw new \RuntimeException('A client email address is required to provision a Sub-Reseller account.');
    }

    $credits = xtreamai_resellerCredits($params);

    $keyType = \WhmcsXtreamAI\PanelApi::keyType($panelId);
    $memberGroupId = xtreamai_subResellerMemberGroupId($params);
    if ($keyType === 'admin' && $memberGroupId < 1) {
        throw new \RuntimeException(
            'Set the Sub-Reseller Member Group ID in this product\'s Module Settings: with an Admin panel key the panel needs the numeric id of the member group the new account belongs to.'
        );
    }

    $requested = xtreamai_customerUsernameEnabled($params) ? xtreamai_resellerUsernameField($params) : '';
    if ($requested !== '') {
        if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $requested) !== 1) {
            $shown = substr((string) preg_replace('/[^\x20-\x7E]|[<>"\'&]/', '', $requested), 0, 40);
            throw new \RuntimeException(
                'The username "' . $shown . '" is not valid: use 3 to 32 letters, digits, dashes or underscores.'
            );
        }
        if ($keyType === 'admin'
            && \WhmcsXtreamAI\PanelApi::findResellerByUsername($panelId, $requested) !== null) {
            throw new \RuntimeException(
                'The reseller username "' . $requested . '" is already taken on this panel. Ask the customer to choose another one.'
            );
        }
    }

    $username = $requested !== '' ? $requested : xtreamai_lineUsername($params);

    $requestedPassword = xtreamai_customerPasswordEnabled($params) ? xtreamai_customerPasswordField($params) : '';
    if ($requestedPassword !== '') {
        $passwordError = xtreamai_validateCustomerPassword($requestedPassword);
        if ($passwordError !== null) {
            throw new \RuntimeException($passwordError);
        }
    }

    $password = $requestedPassword !== '' ? $requestedPassword : xtreamai_linePassword($params);

    $reseller = \WhmcsXtreamAI\PanelApi::createReseller(
        $panelId,
        $username,
        $password,
        $email,
        $credits > 0 ? $credits : null,
        xtreamai_renderNotes($params),
        $memberGroupId > 0 ? $memberGroupId : null
    );

    $finalUsername = !empty($reseller['username']) ? (string) $reseller['username'] : $username;

    \WhmcsXtreamAI\ServiceStore::link($serviceId, $panelId, (string) $reseller['id'], $finalUsername, 0);
    \WhmcsXtreamAI\ServiceStore::updateStatus($serviceId, 'Active');

    xtreamai_updateHostingCredentials($serviceId, $finalUsername, $password);

    if ($requestedPassword !== '') {
        xtreamai_clearCustomFieldValue($params, ['panel_password', 'password', 'line_password', 'reseller_password']);
    }

    return 'success';
}

function xtreamai_createTopUp(array $params): string
{
    $panelId = xtreamai_requirePanelId($params);
    $serviceId = (int) ($params['serviceid'] ?? 0);
    if ($serviceId < 1) {
        throw new \RuntimeException('Invalid service id.');
    }

    if (\WhmcsXtreamAI\PanelApi::keyType($panelId) !== 'admin') {
        throw new \RuntimeException('Credit top-ups require an admin panel key on this panel entry.');
    }

    $row = \WhmcsXtreamAI\ServiceStore::find($serviceId);
    if ($row && !empty($row->panel_account_id)) {
        $lastAction = trim((string) ($row->last_action ?? ''));
        if (strpos($lastAction, 'Topped up') === 0) {
            throw new \RuntimeException('This top-up was already applied (' . $lastAction . '). Use a renewal or the Sub-Resellers tab to add more credits.');
        }
    }

    $credits = xtreamai_topUpCredits($params);
    $target = xtreamai_topUpTarget($params, $panelId);

    $balance = \WhmcsXtreamAI\PanelApi::adjustResellerCredits(
        $panelId,
        $target['reseller_id'],
        $credits,
        'WHMCS top-up service #' . $serviceId
    );

    \WhmcsXtreamAI\ServiceStore::link($serviceId, $panelId, $target['reseller_id'], $target['username'], 0);
    \WhmcsXtreamAI\ServiceStore::updateStatus($serviceId, 'Active');
    \WhmcsXtreamAI\ServiceStore::recordAction(
        $serviceId,
        'Topped up +' . xtreamai_formatCredits($credits) . ' credits to ' . $target['username']
        . ($balance !== '' ? ' (balance ' . $balance . ')' : '')
    );

    xtreamai_updateHostingCredentials($serviceId, $target['username'], '');

    return 'success';
}

function xtreamai_SuspendAccount(array $params)
{
    return xtreamai_execute($params, 'suspend', static function (array $params): string {
        if (xtreamai_accountType($params) === 'topup') {
            \WhmcsXtreamAI\ServiceStore::updateStatus((int) ($params['serviceid'] ?? 0), 'Suspended');
            return 'success';
        }
        if (xtreamai_accountType($params) !== 'reseller' && xtreamai_suspendAction($params) === 'none') {
            \WhmcsXtreamAI\ServiceStore::updateStatus((int) ($params['serviceid'] ?? 0), 'Suspended');
            return 'success';
        }
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        if (xtreamai_accountType($params) === 'reseller') {
            \WhmcsXtreamAI\PanelApi::setResellerStatus($panelId, $lineId, false);
        } else {
            \WhmcsXtreamAI\PanelApi::setLineEnabled($panelId, $lineId, false);
        }
        \WhmcsXtreamAI\ServiceStore::updateStatus((int) ($params['serviceid'] ?? 0), 'Suspended');
        return 'success';
    });
}

function xtreamai_UnsuspendAccount(array $params)
{
    return xtreamai_execute($params, 'unsuspend', static function (array $params): string {
        if (xtreamai_accountType($params) === 'topup') {
            \WhmcsXtreamAI\ServiceStore::updateStatus((int) ($params['serviceid'] ?? 0), 'Active');
            return 'success';
        }
        if (xtreamai_accountType($params) !== 'reseller' && xtreamai_suspendAction($params) === 'none') {
            \WhmcsXtreamAI\ServiceStore::updateStatus((int) ($params['serviceid'] ?? 0), 'Active');
            return 'success';
        }
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        if (xtreamai_accountType($params) === 'reseller') {
            \WhmcsXtreamAI\PanelApi::setResellerStatus($panelId, $lineId, true);
        } else {
            \WhmcsXtreamAI\PanelApi::setLineEnabled($panelId, $lineId, true);
        }
        \WhmcsXtreamAI\ServiceStore::updateStatus((int) ($params['serviceid'] ?? 0), 'Active');
        return 'success';
    });
}

function xtreamai_TerminateAccount(array $params)
{
    return xtreamai_execute($params, 'terminate', static function (array $params): string {
        if (xtreamai_accountType($params) === 'topup') {
            \WhmcsXtreamAI\ServiceStore::unlink((int) ($params['serviceid'] ?? 0));
            return 'success';
        }
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        if (xtreamai_accountType($params) === 'reseller') {
            \WhmcsXtreamAI\PanelApi::setResellerStatus($panelId, $lineId, false);
            \WhmcsXtreamAI\ServiceStore::unlink((int) ($params['serviceid'] ?? 0));
        } else {
            \WhmcsXtreamAI\PanelApi::deleteLine($panelId, $lineId);
            \WhmcsXtreamAI\ServiceStore::unlink((int) ($params['serviceid'] ?? 0));
        }
        return 'success';
    });
}

function xtreamai_Renew(array $params)
{
    return xtreamai_execute($params, 'renew', static function (array $params): string {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);

        if (xtreamai_accountType($params) === 'reseller') {
            $credits = xtreamai_resellerCredits($params);
            if ($credits > 0) {
                \WhmcsXtreamAI\PanelApi::adjustResellerCredits(
                    $panelId,
                    $lineId,
                    $credits,
                    'WHMCS renewal service #' . $serviceId
                );
            }
            \WhmcsXtreamAI\ServiceStore::updateStatus($serviceId, 'Active');
            return 'success';
        }

        if (xtreamai_accountType($params) === 'topup') {
            if (\WhmcsXtreamAI\PanelApi::keyType($panelId) !== 'admin') {
                throw new \RuntimeException('Credit top-ups require an admin panel key on this panel entry.');
            }
            $credits = xtreamai_topUpCredits($params);
            $balance = \WhmcsXtreamAI\PanelApi::adjustResellerCredits(
                $panelId,
                $lineId,
                $credits,
                'WHMCS top-up renewal service #' . $serviceId
            );
            $row = \WhmcsXtreamAI\ServiceStore::find($serviceId);
            $username = ($row && !empty($row->username)) ? (string) $row->username : $lineId;
            \WhmcsXtreamAI\ServiceStore::updateStatus($serviceId, 'Active');
            \WhmcsXtreamAI\ServiceStore::recordAction(
                $serviceId,
                'Topped up +' . xtreamai_formatCredits($credits) . ' credits to ' . $username
                . ($balance !== '' ? ' (balance ' . $balance . ')' : '')
            );
            return 'success';
        }

        $packageId = xtreamai_packageIdForService($params);
        if ($packageId < 1) {
            throw new \RuntimeException(
                'No package selected for service #' . $serviceId
                . ' (product "' . (string) ($params['productname'] ?? '') . '"):'
                . ' set the package in the product\'s Module Settings.'
            );
        }

        $before = [];
        try {
            $before = \WhmcsXtreamAI\PanelApi::getLine($panelId, $lineId);
        } catch (\Throwable $e) {
            $before = [];
        }

        $panelExpiryBefore = isset($before['exp_date']) ? (int) $before['exp_date'] : 0;
        $idempotencyKey = hash('sha256', 'whmcs-renew|' . $serviceId . '|' . $panelExpiryBefore . '|' . $packageId);

        $result = \WhmcsXtreamAI\PanelApi::renewLine($panelId, $lineId, $packageId, null, $idempotencyKey);

        try {
            $maxConn = \WhmcsXtreamAI\PanelApi::keyType($panelId) === 'admin'
                ? xtreamai_maxConnectionsForService($params, $panelId, $packageId)
                : 0;
            if ($maxConn > 0 && $maxConn !== (int) ($result['max_connections'] ?? 0)) {
                \WhmcsXtreamAI\PanelApi::updateLine($panelId, $lineId, ['max_connections' => $maxConn]);
            }
        } catch (\Throwable $e) {
            xtreamai_logModuleCall(
                'renew_connections',
                xtreamai_logRequestSummary('renew_connections', $params),
                $e->getMessage(),
                'error'
            );
        }

        \WhmcsXtreamAI\ServiceStore::updateStatus(
            $serviceId,
            'Active',
            isset($result['expires_at']) ? (string) $result['expires_at'] : null
        );
        xtreamai_updateNextDueDate($serviceId, $result, $params);

        $action = 'Renewed: '
            . (!empty($before['expires_at']) ? xtreamai_formatDate((string) $before['expires_at']) : 'unknown')
            . ' -> '
            . (!empty($result['expires_at']) ? xtreamai_formatDate((string) $result['expires_at']) : 'unknown')
            . ' (package #' . $packageId . ')';

        if ($before !== []) {
            $statusBefore = \WhmcsXtreamAI\LineStatus::fromPanel($before);
            if ($statusBefore === \WhmcsXtreamAI\LineStatus::DISABLED || $statusBefore === \WhmcsXtreamAI\LineStatus::BLOCKED) {
                $action .= ' · line was ' . $statusBefore . ' and the panel enabled it again';
                xtreamai_logModuleCall(
                    'renew_state',
                    xtreamai_logRequestSummary('renew_state', $params),
                    'Line was ' . $statusBefore . ' before the renewal; the panel enables the line again on renew.',
                    'info'
                );
            }
        }

        \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, $action);

        return 'success';
    });
}

function xtreamai_ChangePassword(array $params)
{
    return xtreamai_execute($params, 'change_password', static function (array $params): string {
        if (xtreamai_accountType($params) === 'topup') {
            throw new \RuntimeException('Password changes are not supported for Credit top-up products: the password belongs to the Sub-Reseller service.');
        }
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        $supplied = preg_replace('/[^A-Za-z0-9]/', '', (string) ($params['password'] ?? ''));
        $newPassword = strlen($supplied) >= 8 ? substr($supplied, 0, 32) : xtreamai_linePassword($params);
        if (xtreamai_accountType($params) === 'reseller') {
            \WhmcsXtreamAI\PanelApi::resetResellerPassword($panelId, $lineId, $newPassword);
        } else {
            \WhmcsXtreamAI\PanelApi::resetLinePassword($panelId, $lineId, $newPassword);
        }
        xtreamai_updateHostingCredentials((int) ($params['serviceid'] ?? 0), '', $newPassword);
        return 'success';
    });
}

function xtreamai_AdminCustomButtonArray(array $params)
{
    $type = xtreamai_accountType($params);
    if ($type === 'reseller' || $type === 'topup') {
        return [];
    }
    return [
        'Link existing line' => 'link_existing',
        'Sync bouquets, notes & connections' => 'sync',
        'Refresh from panel' => 'refresh',
        'Set panel expiry to WHMCS next due date' => 'push_expiry',
        'Set WHMCS next due date to panel expiry' => 'pull_expiry',
    ];
}

function xtreamai_link_existing(array $params)
{
    return xtreamai_execute($params, 'link_existing', static function (array $params): string {
        if (xtreamai_accountType($params) !== 'line') {
            throw new \RuntimeException('Link existing line is only available for Line products.');
        }

        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId < 1) {
            throw new \RuntimeException('Invalid service id.');
        }

        $row = \WhmcsXtreamAI\ServiceStore::find($serviceId);
        if ($row && !empty($row->panel_account_id)) {
            throw new \RuntimeException(
                'This service is already linked to line #' . (string) $row->panel_account_id
                . ' (' . (string) $row->username . ').'
            );
        }

        $username = trim((string) ($params['username'] ?? ''));
        if ($username === '') {
            throw new \RuntimeException('Type the panel username in the Username field of this service, save, then press Link existing line.');
        }

        $panelId = xtreamai_requirePanelId($params);

        $line = null;
        foreach (\WhmcsXtreamAI\PanelApi::lines($panelId, $username) as $candidate) {
            if (strcasecmp((string) ($candidate['username'] ?? ''), $username) === 0) {
                $line = $candidate;
                break;
            }
        }
        if ($line === null) {
            throw new \RuntimeException('No line with username "' . $username . '" was found on this panel.');
        }

        $lineId = (string) $line['id'];
        $lineUsername = (string) $line['username'];

        \WhmcsXtreamAI\ServiceStore::link(
            $serviceId,
            $panelId,
            $lineId,
            $lineUsername,
            xtreamai_packageIdForService($params)
        );
        \WhmcsXtreamAI\ServiceStore::updateStatus(
            $serviceId,
            (string) ($params['status'] ?? '') === 'Suspended' ? 'Suspended' : 'Active',
            isset($line['expires_at']) ? (string) $line['expires_at'] : null
        );
        if ((string) ($params['status'] ?? '') !== 'Suspended'
            && (array_key_exists('enabled', $line) || array_key_exists('admin_enabled', $line))
        ) {
            \WhmcsXtreamAI\ServiceStore::updateFromLine($serviceId, $line);
        }

        xtreamai_updateHostingCredentials(
            $serviceId,
            $lineUsername,
            isset($line['password']) ? (string) $line['password'] : ''
        );

        $action = 'Linked to existing line #' . $lineId . ' (' . $lineUsername . ')';
        if (!empty($line['expires_at'])) {
            $action .= ', expires ' . xtreamai_formatDate((string) $line['expires_at']);
        }
        \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, $action);

        return 'success';
    });
}

function xtreamai_sync(array $params)
{
    return xtreamai_execute($params, 'sync', static function (array $params): string {
        if (xtreamai_accountType($params) === 'reseller') {
            throw new \RuntimeException('Sync is not supported for Sub-Reseller products.');
        }
        if (xtreamai_accountType($params) === 'topup') {
            throw new \RuntimeException('Sync is not supported for Credit top-up products.');
        }
        $serviceId = (int) ($params['serviceid'] ?? 0);
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        $bouquets = xtreamai_selectedBouquets($params);
        $notes = xtreamai_renderNotes($params);
        $maxConn = xtreamai_maxConnectionsForService($params, $panelId, xtreamai_currentPackageIdForService($params));

        $fields = [];
        $parts = [];
        if ($notes !== null) {
            $fields['notes'] = $notes;
            $parts[] = 'notes';
        }
        if ($bouquets !== []) {
            $fields['bouquets'] = $bouquets;
            $parts[] = 'bouquets (' . count($bouquets) . ')';
        }
        if ($maxConn > 0) {
            $fields['max_connections'] = $maxConn;
            $parts[] = 'connections=' . $maxConn;
        }

        if ($fields === []) {
            $line = \WhmcsXtreamAI\PanelApi::getLine($panelId, $lineId);
            $summary = 'Nothing to push';
        } else {
            $line = \WhmcsXtreamAI\PanelApi::updateLine($panelId, $lineId, $fields);
            $summary = 'Synced ' . implode(', ', $parts);
        }

        if (is_array($line) && !empty($line['id'])) {
            \WhmcsXtreamAI\ServiceStore::updateFromLine($serviceId, $line);
            $summary .= ' · panel: ' . \WhmcsXtreamAI\LineStatus::fromPanel($line);
            if (!empty($line['expires_at'])) {
                $summary .= ', expires ' . xtreamai_formatDate((string) $line['expires_at']);
            }
        }

        \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, $summary);

        return 'success';
    });
}

function xtreamai_refresh(array $params)
{
    return xtreamai_execute($params, 'refresh', static function (array $params): string {
        if (xtreamai_accountType($params) === 'reseller') {
            throw new \RuntimeException('Refresh from panel is not supported for Sub-Reseller products.');
        }
        if (xtreamai_accountType($params) === 'topup') {
            throw new \RuntimeException('Refresh from panel is not supported for Credit top-up products.');
        }
        $serviceId = (int) ($params['serviceid'] ?? 0);
        $check = xtreamai_refreshFromPanel($params, true);

        if ((string) ($check['source'] ?? '') !== 'live') {
            $reason = (string) ($check['error'] ?? '');
            if ($reason === '') {
                $reason = 'unknown error';
            }
            \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, 'Panel check failed: ' . $reason);

            throw new \RuntimeException('Panel check failed: ' . $reason);
        }

        $line = isset($check['line']) && is_array($check['line']) ? $check['line'] : [];
        \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, xtreamai_panelSummary($line, (string) ($check['status'] ?? '')));

        return 'success';
    });
}

function xtreamai_push_expiry(array $params)
{
    return xtreamai_execute($params, 'push_expiry', static function (array $params): string {
        if (xtreamai_accountType($params) === 'reseller') {
            throw new \RuntimeException('Setting the panel expiry is not supported for Sub-Reseller products.');
        }
        if (xtreamai_accountType($params) === 'topup') {
            throw new \RuntimeException('Expiry alignment is not supported for Credit top-up products.');
        }

        $serviceId = (int) ($params['serviceid'] ?? 0);
        $panelId = xtreamai_requirePanelId($params);

        if (\WhmcsXtreamAI\PanelApi::keyType($panelId) !== 'admin') {
            throw new \RuntimeException(
                'Setting the panel expiry requires an admin panel key.'
                . ' Renew the service, or change the expiry on the panel and use Set WHMCS next due date to panel expiry.'
            );
        }

        $nextDue = xtreamai_serviceNextDueDate($serviceId);
        if ($nextDue === '') {
            throw new \RuntimeException('This service has no next due date in WHMCS to copy to the panel.');
        }

        $epoch = xtreamai_dateTimestamp($nextDue, 12);
        if ($epoch === null) {
            throw new \RuntimeException('The next due date of this service is not a valid date.');
        }

        $lineId = xtreamai_lineIdForService($params);
        \WhmcsXtreamAI\PanelApi::updateLine($panelId, $lineId, ['exp_date' => $epoch]);
        \WhmcsXtreamAI\ServiceStore::invalidatePanelCheck($serviceId);

        $action = 'Panel expiry set to ' . xtreamai_formatDate($nextDue) . ' from the WHMCS next due date';
        \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, $action);
        xtreamai_logModuleCall('push_expiry', xtreamai_logRequestSummary('push_expiry', $params), $action, 'success');

        return 'success';
    });
}

function xtreamai_pull_expiry(array $params)
{
    return xtreamai_execute($params, 'pull_expiry', static function (array $params): string {
        if (xtreamai_accountType($params) === 'reseller') {
            throw new \RuntimeException('Copying the panel expiry is not supported for Sub-Reseller products.');
        }
        if (xtreamai_accountType($params) === 'topup') {
            throw new \RuntimeException('Expiry alignment is not supported for Credit top-up products.');
        }
        if (xtreamai_hidesNextDueDate((string) ($params['billingcycle'] ?? ''))) {
            throw new \RuntimeException('This product has a one-time or free billing cycle: WHMCS keeps no next due date to set.');
        }

        $serviceId = (int) ($params['serviceid'] ?? 0);
        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        $line = \WhmcsXtreamAI\PanelApi::getLine($panelId, $lineId);
        if (!$line || empty($line['id'])) {
            throw new \RuntimeException('The panel did not return this line.');
        }

        $expiry = xtreamai_expiryDate($line);
        if ($expiry === '') {
            throw new \RuntimeException('The panel line has no expiry date to copy to WHMCS.');
        }

        xtreamai_updateNextDueDate($serviceId, $line, $params);
        \WhmcsXtreamAI\ServiceStore::invalidatePanelCheck($serviceId);

        $action = 'WHMCS next due date set to ' . xtreamai_formatDate($expiry) . ' from the panel expiry';
        \WhmcsXtreamAI\ServiceStore::recordAction($serviceId, $action);

        return 'success';
    });
}

function xtreamai_ChangePackage(array $params)
{
    return xtreamai_execute($params, 'change_package', static function (array $params): string {
        if (xtreamai_accountType($params) === 'reseller') {
            throw new \RuntimeException('Package changes are not supported for Sub-Reseller products.');
        }
        if (xtreamai_accountType($params) === 'topup') {
            throw new \RuntimeException('Package changes are not supported for Credit top-up products.');
        }

        $panelId = xtreamai_requirePanelId($params);
        $lineId = xtreamai_lineIdForService($params);
        $newPackageId = xtreamai_packageIdForService($params);
        if ($newPackageId < 1) {
            throw new \RuntimeException('No package selected for this product.');
        }

        $currentPackageId = 0;
        try {
            $row = \WhmcsXtreamAI\ServiceStore::find((int) ($params['serviceid'] ?? 0));
            if ($row !== null && isset($row->package_id)) {
                $currentPackageId = (int) $row->package_id;
            }
        } catch (\Throwable $e) {
            $currentPackageId = 0;
        }

        $packageChanged = $currentPackageId > 0 && $currentPackageId !== $newPackageId;

        if ($packageChanged && \WhmcsXtreamAI\PanelApi::keyType($panelId) !== 'admin') {
            throw new \RuntimeException('Changing the panel package requires an admin panel key. With a reseller key, terminate and re-provision the service.');
        }

        $bouquets = xtreamai_selectedBouquets($params);
        $notes = xtreamai_renderNotes($params);
        $maxConn = xtreamai_maxConnectionsForService($params, $panelId, $newPackageId);

        $fields = [];
        if ($notes !== null) {
            $fields['notes'] = $notes;
        }
        if ($bouquets !== []) {
            $fields['bouquets'] = $bouquets;
        }
        if ($packageChanged) {
            $fields['package_id'] = $newPackageId;
        }
        if ($maxConn > 0) {
            $fields['max_connections'] = $maxConn;
        }

        if ($fields !== []) {
            \WhmcsXtreamAI\PanelApi::updateLine($panelId, $lineId, $fields);
        }

        if ($packageChanged) {
            \WhmcsXtreamAI\ServiceStore::setPackageId((int) ($params['serviceid'] ?? 0), $newPackageId);
        }

        return 'success';
    });
}

function xtreamai_Suspend(array $params)
{
    return xtreamai_SuspendAccount($params);
}

function xtreamai_Unsuspend(array $params)
{
    return xtreamai_UnsuspendAccount($params);
}

function xtreamai_AdminServicesTabFields(array $params)
{
    try {
        xtreamai_requireAddon();
        \WhmcsXtreamAI\Settings::ensureTables();

        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId < 1) {
            return [];
        }

        $row = \WhmcsXtreamAI\ServiceStore::find($serviceId);
        $isTopUp = xtreamai_accountType($params) === 'topup';

        if (!$row) {
            if ($isTopUp) {
                return [
                    'Top-up target' => xtreamai_esc('Not applied yet. The top-up runs when the service is created.'),
                ];
            }

            return [
                'Panel line' => xtreamai_esc('Not linked yet. Provision the service or use Bulk tools > Link existing services.'),
            ];
        }

        if ($isTopUp) {
            $topUpCredits = '-';
            try {
                $topUpCredits = xtreamai_formatCredits(xtreamai_topUpCredits($params));
            } catch (\Throwable $e) {
                $topUpCredits = '-';
            }

            $balance = '-';
            if (!empty($row->panel_id) && !empty($row->panel_account_id)) {
                try {
                    $balance = \WhmcsXtreamAI\PanelApi::resellerCredits(
                        (int) $row->panel_id,
                        (string) $row->panel_account_id
                    );
                } catch (\Throwable $e) {
                    $balance = '-';
                }
            }

            return [
                'Product' => xtreamai_esc('Credit top-up'),
                'Sub-Reseller account' => xtreamai_esc(
                    (string) $row->username . ' (id ' . (string) $row->panel_account_id . ')'
                ),
                'Credits per order' => xtreamai_esc($topUpCredits),
                'Current balance' => xtreamai_esc($balance),
                'Last module action' => xtreamai_esc(xtreamai_lastActionLabel($row)),
            ];
        }

        $panelName = '';
        if (!empty($row->panel_id)) {
            $panel = \WhmcsXtreamAI\PanelStore::find((int) $row->panel_id);
            if ($panel && !empty($panel->name)) {
                $panelName = (string) $panel->name;
            }
        }

        $check = xtreamai_refreshFromPanel($params);
        $line = isset($check['line']) && is_array($check['line']) ? $check['line'] : [];

        $stored = \WhmcsXtreamAI\ServiceStore::find($serviceId);
        if (!$stored) {
            return [];
        }
        $row = $stored;

        $status = (string) ($check['status'] !== '' ? $check['status'] : $row->status);

        $expiryRaw = '';
        if (!empty($line['expires_at'])) {
            $expiryRaw = (string) $line['expires_at'];
        } elseif (!empty($row->expires_at)) {
            $expiryRaw = (string) $row->expires_at;
        }
        $expiry = $expiryRaw !== '' ? xtreamai_formatDate($expiryRaw) : '-';

        $nextDueRaw = xtreamai_serviceNextDueDate($serviceId);
        $nextDue = $nextDueRaw !== '' ? xtreamai_formatDate($nextDueRaw) : '-';

        $activeConnections = '-';
        if (xtreamai_accountType($params) === 'line'
            && !empty($row->panel_id)
            && !empty($row->panel_account_id)
        ) {
            try {
                $activeConnections = (string) count(
                    \WhmcsXtreamAI\PanelApi::lineConnections(
                        (int) $row->panel_id,
                        (string) $row->panel_account_id,
                        true
                    )
                );
            } catch (\Throwable $e) {
                $activeConnections = '-';
            }
        }

        $warnings = [];
        $divergence = xtreamai_expiryDivergence($nextDueRaw, $expiryRaw);
        if ($divergence !== '') {
            $warnings[] = $divergence;
        }

        $updateWarning = trim((string) \WhmcsXtreamAI\Settings::get('last_update_warning', ''));
        if ($updateWarning !== '') {
            $warnings[] = $updateWarning;
            \WhmcsXtreamAI\Settings::set('last_update_warning', '');
        }

        $fields = [
            'Panel' => xtreamai_esc($panelName !== '' ? $panelName : (string) $row->panel_id),
            'Panel line ID' => xtreamai_esc((string) $row->panel_account_id),
            'Panel username' => xtreamai_esc((string) $row->username),
            'Line status' => xtreamai_esc($status),
            'Active connections' => xtreamai_esc($activeConnections),
            'Panel expiry' => xtreamai_esc($expiry),
            'WHMCS next due date' => xtreamai_esc($nextDue),
            'Panel checked' => xtreamai_esc(xtreamai_panelCheckLabel($check, $row)),
            'Last module action' => xtreamai_esc(xtreamai_lastActionLabel($row)),
        ];

        if ($warnings !== []) {
            $fields['Warning'] = xtreamai_esc(implode(' ', $warnings));
        }

        return $fields;
    } catch (\Throwable $e) {
        return [];
    }
}

function xtreamai_ClientArea(array $params)
{
    $username = (string) ($params['username'] ?? '');
    $password = (string) ($params['password'] ?? '');
    $status = (string) ($params['status'] ?? '');
    $expires = '—';
    $m3uUrl = '';
    $epgUrl = '';
    $credits = '';
    $connections = [];
    $connectionsCount = 0;
    $topupUsername = '';
    $topupCredits = '';
    $accountType = xtreamai_accountType($params);
    $row = null;

    try {
        xtreamai_requireAddon();

        xtreamai_refreshFromPanel($params);

        $serviceId = (int) ($params['serviceid'] ?? 0);
        $row = \WhmcsXtreamAI\ServiceStore::find($serviceId);

        if ($row) {
            if (!empty($row->username)) {
                $username = (string) $row->username;
            }
            if (!empty($row->status)) {
                $status = (string) $row->status;
            }
            if (!empty($row->expires_at)) {
                $expires = xtreamai_formatDate((string) $row->expires_at);
            }
            if ($accountType !== 'topup' && !empty($row->panel_id)) {
                $panel = \WhmcsXtreamAI\PanelStore::find((int) $row->panel_id);
                if ($panel && !empty($panel->m3u_url)) {
                    $m3uUrl = (string) $panel->m3u_url;
                }
                if ($panel && !empty($panel->epg_url)) {
                    $epgUrl = (string) $panel->epg_url;
                }
            }
        }

        if ($accountType !== 'topup' && ($m3uUrl === '' || $epgUrl === '')) {
            $panelId = xtreamai_panelIdForService($params);
            if ($panelId > 0) {
                $panel = \WhmcsXtreamAI\PanelStore::find($panelId);
                if ($panel && !empty($panel->m3u_url) && $m3uUrl === '') {
                    $m3uUrl = (string) $panel->m3u_url;
                }
                if ($panel && !empty($panel->epg_url) && $epgUrl === '') {
                    $epgUrl = (string) $panel->epg_url;
                }
            }
        }

        if ($accountType === 'reseller') {
            try {
                $panelId = xtreamai_panelIdForService($params);
                $lineId = xtreamai_lineIdForService($params);
                $credits = \WhmcsXtreamAI\PanelApi::resellerCredits($panelId, $lineId);
            } catch (\Throwable $e) {
                $credits = '';
            }
        }

        if ($accountType === 'topup') {
            if ($row && !empty($row->username)) {
                $topupUsername = (string) $row->username;
            }
            if ($row && !empty($row->panel_id) && !empty($row->panel_account_id)) {
                try {
                    $credits = \WhmcsXtreamAI\PanelApi::resellerCredits(
                        (int) $row->panel_id,
                        (string) $row->panel_account_id
                    );
                } catch (\Throwable $e) {
                    $credits = '';
                }
            }
            try {
                $topupCredits = xtreamai_formatCredits(xtreamai_topUpCredits($params));
            } catch (\Throwable $e) {
                $topupCredits = '';
            }
        }

        if ($accountType === 'line' && $row && !empty($row->panel_id) && !empty($row->panel_account_id)) {
            try {
                $connections = \WhmcsXtreamAI\PanelApi::lineConnections(
                    (int) $row->panel_id,
                    (string) $row->panel_account_id,
                    true
                );
                foreach ($connections as $index => $connection) {
                    $connections[$index]['duration'] = xtreamai_formatDuration(
                        (int) ($connection['elapsed_sec'] ?? 0)
                    );
                }
            } catch (\Throwable $e) {
                $connections = [];
            }
        }
        $connectionsCount = count($connections);
    } catch (\Throwable $e) {

    }

    $m3uUrl = xtreamai_fillClientUrl($m3uUrl, $username, $password);
    $epgUrl = xtreamai_fillClientUrl($epgUrl, $username, $password);

    return [
        'tabOverviewReplacementTemplate' => 'templates/overview.tpl',
        'templateVariables' => [
            'username' => $username,
            'password' => $password,
            'status' => $status,
            'expires' => $expires,
            'm3u_url' => $m3uUrl,
            'epg_url' => $epgUrl,
            'credits' => $credits,
            'account_type' => $accountType,
            'topup_username' => $topupUsername,
            'topup_credits' => $topupCredits,
            'connections' => $connections,
            'connections_count' => $connectionsCount,
        ],
    ];
}
