<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

use WHMCS\Database\Capsule;

final class PanelStore
{
    private const TABLE = 'mod_xtreamai_panels';

    

    public static function ensureTable(): void
    {
        Settings::ensureTables();
    }

    

    public static function all(): array
    {
        return Capsule::table(self::TABLE)->orderBy('id', 'asc')->get()->all();
    }

    

    public static function allActive(): array
    {
        return Capsule::table(self::TABLE)
            ->where('active', 1)
            ->orderBy('id', 'asc')
            ->get()
            ->all();
    }

    

    public static function find(int $id): ?object
    {
        return Capsule::table(self::TABLE)->where('id', $id)->first();
    }

    

    public static function firstActive(): ?object
    {
        $row = Capsule::table(self::TABLE)
            ->where('active', 1)
            ->orderBy('id', 'asc')
            ->first();

        if ($row !== null) {
            return $row;
        }

        return Capsule::table(self::TABLE)->orderBy('id', 'asc')->first();
    }

    

    public static function create(array $data): int
    {
        $data = self::prepare($data);

        if (!array_key_exists('created_at', $data)) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return (int) Capsule::table(self::TABLE)->insertGetId($data);
    }

    

    public static function update(int $id, array $data): void
    {
        Capsule::table(self::TABLE)->where('id', $id)->update(self::prepare($data));
    }

    

    public static function delete(int $id): void
    {
        Capsule::table(self::TABLE)->where('id', $id)->delete();
    }

    

    private static function prepare(array $data): array
    {
        if (array_key_exists('password', $data)) {
            $data['password'] = self::encryptToken((string) $data['password']);
        }

        if (array_key_exists('verify_ssl', $data)) {
            $data['verify_ssl'] = self::toFlag($data['verify_ssl']);
        }

        if (array_key_exists('active', $data)) {
            $data['active'] = self::toFlag($data['active']);
        }

        if (array_key_exists('key_type', $data)) {
            $data['key_type'] = in_array($data['key_type'], ['reseller', 'admin'], true) ? $data['key_type'] : 'reseller';
        }
        if (array_key_exists('admin_owner_member_id', $data)) {
            $data['admin_owner_member_id'] = ($data['admin_owner_member_id'] === null || $data['admin_owner_member_id'] === '')
                ? null
                : (int) $data['admin_owner_member_id'];
        }

        return $data;
    }

    

    private static function encryptToken(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('encrypt')) {
            try {
                $encrypted = encrypt($value);
                if (is_string($encrypted) && $encrypted !== '') {
                    return $encrypted;
                }
            } catch (\Throwable $e) {
                
            }
        }

        return $value;
    }

    

    private static function toFlag($value): int
    {
        return (int) $value ? 1 : 0;
    }
}
