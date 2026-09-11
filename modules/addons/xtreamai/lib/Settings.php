<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

use WHMCS\Database\Capsule;

final class Settings
{
    private const TABLE = 'mod_xtreamai_settings';
    private const SERVICES_TABLE = 'mod_xtreamai_services';
    private const PANELS_TABLE = 'mod_xtreamai_panels';

    

    public static function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $schema = Capsule::schema();

        if (!$schema->hasTable(self::TABLE)) {
            $schema->create(self::TABLE, static function ($table): void {
                $table->string('setting', 64);
                $table->text('value')->nullable();
                $table->primary('setting');
            });
        }

        if (!$schema->hasTable(self::SERVICES_TABLE)) {
            $schema->create(self::SERVICES_TABLE, static function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('service_id');
                $table->unsignedInteger('panel_id')->nullable();
                $table->string('panel_account_id', 64)->nullable();
                $table->string('username', 64)->default('');
                $table->string('status', 32)->nullable();
                $table->string('expires_at', 64)->nullable();
                $table->integer('package_id')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('service_id');
            });
        }

        if (!$schema->hasTable(self::PANELS_TABLE)) {
            $schema->create(self::PANELS_TABLE, static function ($table): void {
                $table->increments('id');
                $table->string('name', 120);
                $table->string('api_url', 255);
                $table->text('m3u_url')->nullable();
                $table->text('password')->nullable();
                $table->tinyInteger('verify_ssl')->default(1);
                $table->tinyInteger('active')->default(1);
                $table->string('key_type', 16)->default('reseller');
                $table->integer('admin_owner_member_id')->nullable();
                $table->tinyInteger('last_ok')->default(0);
                $table->text('last_message')->nullable();
                $table->string('last_checked', 32)->nullable();
                $table->string('last_user', 190)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if ($schema->hasTable(self::PANELS_TABLE)) {
            $schema->table(self::PANELS_TABLE, static function ($table) use ($schema): void {
                if (!$schema->hasColumn(self::PANELS_TABLE, 'key_type')) {
                    $table->string('key_type', 16)->default('reseller');
                }
                if (!$schema->hasColumn(self::PANELS_TABLE, 'admin_owner_member_id')) {
                    $table->integer('admin_owner_member_id')->nullable();
                }
            });
        }

        $done = true;
    }

    

    public static function get(string $key, ?string $default = null): ?string
    {
        self::ensureTables();

        $row = Capsule::table(self::TABLE)->where('setting', $key)->first();

        if ($row !== null && $row->value !== null) {
            return (string) $row->value;
        }

        return $default;
    }

    

    public static function set(string $key, string $value): void
    {
        self::ensureTables();

        Capsule::table(self::TABLE)->updateOrInsert(
            ['setting' => $key],
            ['value' => $value]
        );
    }

    

    public static function all(): array
    {
        self::ensureTables();

        $out = [];
        foreach (Capsule::table(self::TABLE)->get() as $row) {
            $out[(string) $row->setting] = (string) $row->value;
        }

        return $out;
    }

    

    public static function credentialDefaults(): array
    {
        return [
            'username_auto'   => '1',
            'username_prefix' => '',
            'username_length' => '8',
            'username_type'   => 'numeric',
            'password_auto'   => '1',
            'password_length' => '10',
            'password_type'   => 'numeric',
            'reseller_notes'  => 'WHMCS:{service_id}',
        ];
    }

    

    public static function sanitizeCredential(string $key, string $value): string
    {
        switch ($key) {
            case 'username_length':
                return (string) self::clamp((int) $value, 4, 32);

            case 'password_length':
                return (string) self::clamp((int) $value, 8, 32);

            case 'username_auto':
            case 'password_auto':
                return $value === '1' ? '1' : '0';

            case 'username_type':
            case 'password_type':
                return in_array($value, ['numeric', 'alpha', 'alphanumeric'], true)
                    ? $value
                    : 'numeric';

            default:
                return $value;
        }
    }

    

    public static function credentialSettings(): array
    {
        $out = self::credentialDefaults();

        foreach ($out as $key => $default) {
            $value = self::get($key, $default);
            if ($value === null) {
                $value = $default;
            }
            $out[$key] = self::sanitizeCredential($key, (string) $value);
        }

        return $out;
    }

    

    public static function randomString(int $length, string $type = 'alphanumeric'): string
    {
        $length = max(1, $length);

        $numeric = '0123456789';
        $alpha   = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

        switch ($type) {
            case 'numeric':
                $charset = $numeric;
                break;

            case 'alpha':
                $charset = $alpha;
                break;

            default:
                $charset = $alpha . '23456789';
        }

        $max = strlen($charset) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $charset[self::randomIndex($max)];
        }

        return $out;
    }

    

    private static function randomIndex(int $max): int
    {
        try {
            return random_int(0, $max);
        } catch (\Throwable $e) {
            return mt_rand(0, $max);
        }
    }

    

    private static function clamp(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }
}
