<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

use WHMCS\Database\Capsule;

final class CheckoutValidator
{
    private static $memo = [];

    public static function validateProduct(int $pid, array $customFieldsById, int $clientId): array
    {
        if ($pid < 1) {
            return [];
        }

        try {
            $product = Capsule::table('tblproducts')->where('id', $pid)->first();
            if ($product === null) {
                return [];
            }

            if (strtolower(trim((string) ($product->servertype ?? ''))) !== 'xtreamai') {
                return [];
            }

            $panelId = (int) ($product->configoption1 ?? 0);
            if ($panelId < 1) {
                return [];
            }

            $fields = self::productFields($pid, $customFieldsById);
            $accountType = strtolower(trim((string) ($product->configoption5 ?? '')));

            if ($accountType === 'topup') {
                return self::validateTopUp($pid, $product, $panelId, $fields, $clientId);
            }

            if ($accountType === 'reseller') {
                return [];
            }

            return self::validateLine($pid, $product, $panelId, $fields);
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
            $key = self::optionKey((string) ($row->fieldname ?? ''));
            if ($key === '' || array_key_exists($key, $fields)) {
                continue;
            }

            $value = $values[(int) ($row->id ?? 0)] ?? '';
            if (is_array($value)) {
                $value = reset($value);
            }

            $fields[$key] = $value;
        }

        return $fields;
    }

    private static function fieldValue(array $fields, array $names): string
    {
        foreach ($fields as $key => $value) {
            if (!in_array($key, $names, true)) {
                continue;
            }

            $typed = trim((string) $value);
            if ($typed !== '') {
                return $typed;
            }
        }

        return '';
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

    private static function validateTopUp(int $pid, object $product, int $panelId, array $fields, int $clientId): array
    {
        $username = self::fieldValue($fields, ['reseller_username', 'panel_username', 'sub_reseller_username']);
        if ($username === '') {
            return [];
        }

        $cacheKey = self::cacheKey($pid, 'reseller_username', $username);
        if (array_key_exists($cacheKey, self::$memo)) {
            return self::$memo[$cacheKey];
        }

        try {
            $errors = self::topUpErrors($product, $panelId, $username, $clientId);
        } catch (\Throwable $e) {
            self::logFailure($pid, $e);
            $errors = [];
        }

        self::$memo[$cacheKey] = $errors;

        return $errors;
    }

    private static function topUpErrors(object $product, int $panelId, string $username, int $clientId): array
    {
        if (self::scope($product) === 'linked') {
            if ($clientId < 1) {
                return [];
            }

            foreach (ServiceStore::resellerServicesForClient($clientId, $panelId) as $candidate) {
                if (strcasecmp((string) ($candidate['username'] ?? ''), $username) === 0) {
                    return [];
                }
            }

            return [
                'The reseller username "' . self::shown($username)
                . '" does not match any of your Sub-Reseller accounts.',
            ];
        }

        if (PanelApi::keyType($panelId) !== 'admin') {
            return [];
        }

        $reseller = PanelApi::findResellerByUsername($panelId, $username);
        if ($reseller === null) {
            return [
                'The reseller username "' . self::shown($username)
                . '" was not found. Check the spelling and try again.',
            ];
        }

        $adminOwnerId = PanelApi::adminOwnerMemberId($panelId);
        if ((int) ($reseller['member_group_id'] ?? 0) === 1
            || ($adminOwnerId !== null && (string) ($reseller['id'] ?? '') === (string) $adminOwnerId)) {
            return ['The reseller username "' . self::shown($username) . '" cannot receive a top-up.'];
        }

        return [];
    }

    private static function scope(object $product): string
    {
        $raw = strtolower(trim((string) ($product->configoption10 ?? '')));

        return $raw === 'linked' ? 'linked' : 'any';
    }

    private static function validateLine(int $pid, object $product, int $panelId, array $fields): array
    {
        if (strtolower(trim((string) ($product->configoption11 ?? ''))) !== 'on') {
            return [];
        }

        $username = self::fieldValue($fields, ['line_username', 'username', 'panel_username']);
        if ($username === '') {
            return [];
        }

        $cacheKey = self::cacheKey($pid, 'line_username', $username);
        if (array_key_exists($cacheKey, self::$memo)) {
            return self::$memo[$cacheKey];
        }

        try {
            $errors = self::lineErrors($panelId, $username);
        } catch (\Throwable $e) {
            self::logFailure($pid, $e);
            $errors = [];
        }

        self::$memo[$cacheKey] = $errors;

        return $errors;
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
