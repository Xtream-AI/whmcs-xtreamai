<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

use WHMCS\Database\Capsule;

final class CheckoutValidator
{
    private const PASSWORD_FIELDS = ['panel_password', 'password', 'line_password', 'reseller_password'];

    private static $memo = [];

    public static function validateProduct(int $pid, array $customFieldsById, int $clientId): array
    {
        $ctx = self::logContext($customFieldsById, $clientId);

        if ($pid < 1) {
            return self::report($pid, 'no_pid', [], $ctx);
        }

        try {
            $product = Capsule::table('tblproducts')->where('id', $pid)->first();
            if ($product === null) {
                return self::report($pid, 'no_product', [], $ctx);
            }

            $ctx['servertype'] = strtolower(trim((string) ($product->servertype ?? '')));
            if ($ctx['servertype'] !== 'xtreamai') {
                return self::report($pid, 'not_xtreamai', [], $ctx);
            }

            $panelId = (int) ($product->configoption1 ?? 0);
            $ctx['panel_id'] = $panelId;
            if ($panelId < 1) {
                return self::report($pid, 'no_panel', [], $ctx);
            }

            $ctx['key_type'] = self::panelKeyType($panelId);

            $fields = self::productFields($pid, $customFieldsById);
            $ctx['field_names_on_product'] = self::productFieldNames($pid);

            $accountType = strtolower(trim((string) ($product->configoption5 ?? '')));
            $ctx['account_type'] = $accountType;

            if ($accountType === 'topup') {
                $ctx['topup_scope'] = self::scope($product);
                $outcome = self::validateTopUp($pid, $product, $panelId, $fields, $customFieldsById, $clientId, $ctx);

                return self::report($pid, $outcome['decision'], $outcome['errors'], $outcome['ctx'], $outcome['logged']);
            }

            if ($accountType === 'reseller') {
                $ctx['customer_username_enabled'] = strtolower(trim((string) ($product->configoption11 ?? '')));
                $outcome = self::validateReseller($pid, $product, $panelId, $fields, $customFieldsById, $ctx);
                $outcome = self::withPassword($product, $fields, $outcome);

                return self::report($pid, $outcome['decision'], $outcome['errors'], $outcome['ctx'], $outcome['logged']);
            }

            $ctx['customer_username_enabled'] = strtolower(trim((string) ($product->configoption11 ?? '')));
            $outcome = self::validateLine($pid, $product, $panelId, $fields, $customFieldsById, $ctx);
            $outcome = self::withPassword($product, $fields, $outcome);

            return self::report($pid, $outcome['decision'], $outcome['errors'], $outcome['ctx'], $outcome['logged']);
        } catch (\Throwable $e) {
            self::logFailure($pid, $e);

            return [];
        }
    }

    private static function productFields(int $pid, array $values): array
    {
        $rows = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $pid)
            ->get();

        $fields = [];
        foreach ($rows as $row) {
            $value = $values[(int) ($row->id ?? 0)] ?? '';
            if (is_array($value)) {
                $value = reset($value);
            }

            foreach (self::fieldKeys((string) ($row->fieldname ?? '')) as $key) {
                if ($key === '' || array_key_exists($key, $fields)) {
                    continue;
                }

                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    private static function fieldKeys(string $name): array
    {
        $pipe = strpos($name, '|');
        if ($pipe === false) {
            return [self::optionKey($name)];
        }

        return [self::optionKey(substr($name, 0, $pipe)), self::optionKey(substr($name, $pipe + 1))];
    }

    private static function singleField(int $pid, array $values): array
    {
        $rows = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $pid)
            ->get();

        if (count($rows) !== 1) {
            return ['key' => '', 'value' => ''];
        }

        foreach (self::fieldKeys((string) ($rows[0]->fieldname ?? '')) as $key) {
            if (in_array($key, self::PASSWORD_FIELDS, true)) {
                return ['key' => '', 'value' => ''];
            }
        }

        $value = $values[(int) ($rows[0]->id ?? 0)] ?? '';
        if (is_array($value)) {
            $value = reset($value);
        }

        $typed = trim((string) $value);
        if ($typed === '') {
            return ['key' => '', 'value' => ''];
        }

        return ['key' => '*single*', 'value' => $typed];
    }

    private static function fieldMatch(array $fields, array $names): array
    {
        foreach ($fields as $key => $value) {
            if (!in_array($key, $names, true)) {
                continue;
            }

            $typed = trim((string) $value);
            if ($typed !== '') {
                return ['key' => (string) $key, 'value' => $typed];
            }
        }

        return ['key' => '', 'value' => ''];
    }

    private static function optionKey(string $name): string
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

    private static function shown(string $username): string
    {
        $clean = preg_replace('/[^\x20-\x7E]|[<>"\'&]/', '', $username);
        if ($clean === null) {
            return '';
        }

        return substr($clean, 0, 40);
    }

    private static function validateTopUp(
        int $pid,
        object $product,
        int $panelId,
        array $fields,
        array $values,
        int $clientId,
        array $ctx
    ): array {
        $match = self::fieldMatch($fields, ['reseller_username', 'panel_username', 'sub_reseller_username']);
        if ($match['value'] === '') {
            $match = self::singleField($pid, $values);
        }

        if ($match['value'] === '') {
            return self::outcome('topup_no_field', [], $ctx);
        }

        $cacheKey = self::cacheKey($pid, 'reseller_username', $match['value']);
        if (array_key_exists($cacheKey, self::$memo)) {
            return self::outcome(
                'memo_topup',
                self::$memo[$cacheKey],
                self::matchedContext($ctx, $match['key'], $match['value'])
            );
        }

        try {
            $outcome = self::topUpErrors($product, $panelId, $match['value'], $clientId, $ctx);
        } catch (\Throwable $e) {
            self::logFailure($pid, $e);
            self::$memo[$cacheKey] = [];

            return self::outcome('topup_error', [], $ctx, true);
        }

        self::$memo[$cacheKey] = $outcome['errors'];

        return self::outcome(
            $outcome['decision'],
            $outcome['errors'],
            self::matchedContext($outcome['ctx'], $match['key'], $match['value'])
        );
    }

    private static function topUpErrors(object $product, int $panelId, string $username, int $clientId, array $ctx): array
    {
        if (self::scope($product) === 'linked') {
            if ($clientId < 1) {
                return self::outcome('topup_linked_no_client', [], $ctx);
            }

            foreach (ServiceStore::resellerServicesForClient($clientId, $panelId) as $candidate) {
                if (strcasecmp((string) ($candidate['username'] ?? ''), $username) === 0) {
                    return self::outcome('topup_linked_check', [], $ctx);
                }
            }

            return self::outcome('topup_linked_check', [
                'The reseller username "' . self::shown($username)
                . '" does not match any of your Sub-Reseller accounts.',
            ], $ctx);
        }

        if (PanelApi::keyType($panelId) !== 'admin') {
            return self::outcome('topup_reseller_key', [], $ctx);
        }

        $reseller = PanelApi::findResellerByUsername($panelId, $username);
        if ($reseller === null) {
            return self::outcome('topup_admin_check', [
                'The reseller username "' . self::shown($username)
                . '" was not found. Check the spelling and try again.',
            ], $ctx);
        }

        $adminOwnerId = PanelApi::adminOwnerMemberId($panelId);
        if ((int) ($reseller['member_group_id'] ?? 0) === 1
            || ($adminOwnerId !== null && (string) ($reseller['id'] ?? '') === (string) $adminOwnerId)) {
            return self::outcome('topup_admin_check', [
                'The reseller username "' . self::shown($username) . '" cannot receive a top-up.',
            ], $ctx);
        }

        return self::outcome('topup_admin_check', [], $ctx);
    }

    private static function validateReseller(
        int $pid,
        object $product,
        int $panelId,
        array $fields,
        array $values,
        array $ctx
    ): array {
        if (!self::customerUsernameEnabled($product)) {
            return self::outcome('reseller_disabled', [], $ctx);
        }

        $match = self::fieldMatch($fields, ['reseller_username', 'sub_reseller_username', 'panel_username', 'username']);
        if ($match['value'] === '') {
            $match = self::singleField($pid, $values);
        }

        if ($match['value'] === '') {
            return self::outcome('reseller_no_field', [], $ctx);
        }

        $cacheKey = self::cacheKey($pid, 'reseller_username', $match['value']);
        if (array_key_exists($cacheKey, self::$memo)) {
            return self::outcome(
                'memo_reseller',
                self::$memo[$cacheKey],
                self::matchedContext($ctx, $match['key'], $match['value'])
            );
        }

        try {
            $outcome = self::resellerErrors($panelId, $match['value'], $ctx);
        } catch (\Throwable $e) {
            self::logFailure($pid, $e);
            self::$memo[$cacheKey] = [];

            return self::outcome('reseller_error', [], $ctx, true);
        }

        self::$memo[$cacheKey] = $outcome['errors'];

        return self::outcome(
            $outcome['decision'],
            $outcome['errors'],
            self::matchedContext($outcome['ctx'], $match['key'], $match['value'])
        );
    }

    private static function resellerErrors(int $panelId, string $username, array $ctx): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username) !== 1) {
            return self::outcome('reseller_check', [
                'The username "' . self::shown($username)
                . '" is not valid: use 3 to 32 letters, digits, dashes or underscores.',
            ], $ctx);
        }

        if (PanelApi::keyType($panelId) !== 'admin') {
            return self::outcome('reseller_reseller_key', [], $ctx);
        }

        if (PanelApi::findResellerByUsername($panelId, $username) !== null) {
            return self::outcome('reseller_check', [
                'The reseller username "' . self::shown($username) . '" is already taken. Choose another one.',
            ], $ctx);
        }

        return self::outcome('reseller_check', [], $ctx);
    }

    private static function withPassword(object $product, array $fields, array $outcome): array
    {
        $check = self::passwordCheck($product, $fields);
        foreach ($check['ctx'] as $key => $value) {
            $outcome['ctx'][$key] = $value;
        }
        if ($check['error'] !== '') {
            $outcome['errors'][] = $check['error'];
        }

        return $outcome;
    }

    private static function passwordCheck(object $product, array $fields): array
    {
        $raw = strtolower(trim((string) ($product->configoption12 ?? '')));
        $ctx = [
            'customer_password_enabled' => $raw,
            'password_field_key' => '',
            'password_len' => 0,
            'password_check' => 'off',
        ];
        if ($raw !== 'on') {
            return ['ctx' => $ctx, 'error' => ''];
        }

        $value = '';
        foreach ($fields as $key => $fieldValue) {
            if (!in_array((string) $key, self::PASSWORD_FIELDS, true)) {
                continue;
            }
            if ($ctx['password_field_key'] === '') {
                $ctx['password_field_key'] = (string) $key;
            }
            $typed = (string) $fieldValue;
            if ($typed === '') {
                continue;
            }
            $value = $typed;
            $ctx['password_field_key'] = (string) $key;
            break;
        }

        $ctx['password_len'] = strlen($value);
        if ($value === '') {
            $ctx['password_check'] = 'empty';

            return ['ctx' => $ctx, 'error' => ''];
        }

        $error = self::passwordError($value);
        $ctx['password_check'] = $error === null ? 'ok' : 'invalid';

        return ['ctx' => $ctx, 'error' => $error === null ? '' : $error];
    }

    private static function passwordError(string $password): ?string
    {
        if (preg_match('/^[^\s%&?#\/\\\\+]{8,32}$/', $password) === 1) {
            return null;
        }

        return 'The password is not valid: use 8 to 32 characters without spaces and without % & ? # / \ +';
    }

    private static function scope(object $product): string
    {
        $raw = strtolower(trim((string) ($product->configoption10 ?? '')));

        return $raw === 'linked' ? 'linked' : 'any';
    }

    private static function customerUsernameEnabled(object $product): bool
    {
        return strtolower(trim((string) ($product->configoption11 ?? ''))) === 'on';
    }

    private static function validateLine(int $pid, object $product, int $panelId, array $fields, array $values, array $ctx): array
    {
        if (!self::customerUsernameEnabled($product)) {
            return self::outcome('line_disabled', [], $ctx);
        }

        $match = self::fieldMatch($fields, ['line_username', 'username', 'panel_username']);
        if ($match['value'] === '') {
            $match = self::singleField($pid, $values);
        }

        if ($match['value'] === '') {
            return self::outcome('line_no_field', [], $ctx);
        }

        $cacheKey = self::cacheKey($pid, 'line_username', $match['value']);
        if (array_key_exists($cacheKey, self::$memo)) {
            return self::outcome(
                'memo_line',
                self::$memo[$cacheKey],
                self::matchedContext($ctx, $match['key'], $match['value'])
            );
        }

        try {
            $errors = self::lineErrors($panelId, $match['value']);
        } catch (\Throwable $e) {
            self::logFailure($pid, $e);
            self::$memo[$cacheKey] = [];

            return self::outcome('line_error', [], $ctx, true);
        }

        self::$memo[$cacheKey] = $errors;

        return self::outcome('line_check', $errors, self::matchedContext($ctx, $match['key'], $match['value']));
    }

    private static function lineErrors(int $panelId, string $username): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username) !== 1) {
            return [
                'The username "' . self::shown($username)
                . '" is not valid: use 3 to 32 letters, digits, dashes or underscores.',
            ];
        }

        foreach (PanelApi::lines($panelId, $username) as $line) {
            if (strcasecmp((string) ($line['username'] ?? ''), $username) === 0) {
                return ['The username "' . self::shown($username) . '" is already taken. Choose another one.'];
            }
        }

        return [];
    }

    private static function cacheKey(int $pid, string $field, string $username): string
    {
        return $pid . '|' . $field . '|' . $username;
    }

    private static function logContext(array $customFieldsById, int $clientId): array
    {
        $ids = [];
        foreach (array_keys($customFieldsById) as $key) {
            $ids[] = (int) $key;
        }

        return [
            'servertype' => '',
            'panel_id' => 0,
            'account_type' => '',
            'key_type' => '',
            'client_id' => $clientId,
            'input_field_ids' => self::shortList($ids),
            'field_names_on_product' => [],
            'matched_field_key' => '',
            'matched_field_value_len' => 0,
            'errors_count' => 0,
        ];
    }

    private static function panelKeyType(int $panelId): string
    {
        try {
            $panel = PanelStore::find($panelId);
            if ($panel === null) {
                return '';
            }

            return (string) ($panel->key_type ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function productFieldNames(int $pid): array
    {
        try {
            $rows = Capsule::table('tblcustomfields')
                ->where('type', 'product')
                ->where('relid', $pid)
                ->get();

            $names = [];
            foreach ($rows as $row) {
                $name = (string) ($row->fieldname ?? '');
                $keys = [];
                foreach (self::fieldKeys($name) as $key) {
                    if ($key !== '' && !in_array($key, $keys, true)) {
                        $keys[] = $key;
                    }
                }

                $names[] = ['name' => $name, 'keys' => $keys];
            }

            return self::shortList($names);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function shortList(array $values): array
    {
        return array_slice($values, 0, 20);
    }

    private static function matchedContext(array $ctx, string $matchedKey, string $username): array
    {
        $ctx['matched_field_key'] = $matchedKey;
        $ctx['matched_field_value_len'] = strlen($username);

        return $ctx;
    }

    private static function outcome(string $decision, array $errors, array $ctx, bool $logged = false): array
    {
        return ['decision' => $decision, 'errors' => $errors, 'ctx' => $ctx, 'logged' => $logged];
    }

    private static function report(int $pid, string $decision, array $errors, array $ctx, bool $logged = false): array
    {
        if (!$logged) {
            $ctx['errors_count'] = count($errors);
            self::logDecision($pid, $decision, $ctx);
        }

        return $errors;
    }

    private static function logDecision(int $pid, string $decision, array $ctx): void
    {
        if (!function_exists('logModuleCall')) {
            return;
        }

        try {
            $request = json_encode(['product_id' => $pid] + $ctx);
            if (!is_string($request)) {
                $request = 'product_id=' . $pid;
            }

            logModuleCall(
                'xtreamai',
                'checkout_validate',
                $request,
                'decision=' . $decision . ' errors=' . (int) ($ctx['errors_count'] ?? 0)
            );
        } catch (\Throwable $ignored) {
        }
    }

    private static function logFailure(int $pid, \Throwable $e): void
    {
        if (!function_exists('logModuleCall')) {
            return;
        }

        try {
            logModuleCall(
                'xtreamai',
                'checkout_validate',
                'product=' . $pid,
                $e->getMessage(),
                $e->getMessage(),
                []
            );
        } catch (\Throwable $ignored) {
        }
    }
}
