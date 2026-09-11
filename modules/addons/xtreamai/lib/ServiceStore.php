<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

use WHMCS\Database\Capsule;

final class ServiceStore
{
    private const TABLE = 'mod_xtreamai_services';

    

    public static function ensureTable(): void
    {
        Settings::ensureTables();
    }

    

    public static function find(int $serviceId): ?object
    {
        return Capsule::table(self::TABLE)->where('service_id', $serviceId)->first();
    }

    

    public static function link(
        int $serviceId,
        int $panelId,
        string $panelAccountId,
        string $username,
        int $packageId
    ): void {
        Capsule::table(self::TABLE)->updateOrInsert(
            ['service_id' => $serviceId],
            [
                'panel_id' => $panelId,
                'panel_account_id' => $panelAccountId,
                'username' => $username,
                'package_id' => $packageId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    

    public static function updateStatus(int $serviceId, string $status, ?string $expiresAt = null): void
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($expiresAt !== null) {
            $data['expires_at'] = $expiresAt;
        }

        Capsule::table(self::TABLE)->where('service_id', $serviceId)->update($data);
    }

    

    public static function unlink(int $serviceId): void
    {
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->delete();
    }
}
