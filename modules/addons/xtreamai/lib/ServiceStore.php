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

    

    public static function setPackageId(int $serviceId, int $packageId): void
    {
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->update([
            'package_id' => $packageId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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

    

    public static function updateFromLine(int $serviceId, array $line): void
    {
        self::updateStatus(
            $serviceId,
            LineStatus::fromPanel($line),
            isset($line['expires_at']) ? (string) $line['expires_at'] : null
        );
    }

    

    public static function recordPanelCheck(int $serviceId, ?string $checkedAt = null): void
    {
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->update([
            'panel_checked_at' => $checkedAt === null ? date('Y-m-d H:i:s') : $checkedAt,
        ]);
    }

    

    public static function invalidatePanelCheck(int $serviceId): void
    {
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->update([
            'panel_checked_at' => null,
        ]);
    }

    

    public static function recordAction(int $serviceId, string $action): void
    {
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->update([
            'last_action' => substr($action, 0, 255),
            'last_action_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    

    public static function unlink(int $serviceId): void
    {
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->delete();
    }
}
