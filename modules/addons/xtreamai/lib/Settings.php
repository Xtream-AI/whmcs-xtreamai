<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

use WHMCS\Database\Capsule;

final class Settings
{
    private const TABLE = 'mod_xtreamai_settings';
    private const SERVICES_TABLE = 'mod_xtreamai_services';
    private const PANELS_TABLE = 'mod_xtreamai_panels';
    private const LINE_INDEX_TABLE = 'mod_xtreamai_line_index';

    

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

        if (!$schema->hasTable(self::LINE_INDEX_TABLE)) {
            $schema->create(self::LINE_INDEX_TABLE, static function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('panel_id');
                $table->string('line_id', 64);
                $table->string('username', 64)->default('');
                $table->unsignedInteger('service_tag')->nullable();
                $table->bigInteger('exp_date')->nullable();
                $table->tinyInteger('enabled')->default(0);
                $table->timestamp('indexed_at')->nullable();
                $table->unique(['panel_id', 'line_id']);
            });
        }

        if ($schema->hasTable(self::SERVICES_TABLE)) {
            $schema->table(self::SERVICES_TABLE, static function ($table) use ($schema): void {
                if (!$schema->hasColumn(self::SERVICES_TABLE, 'panel_checked_at')) {
                    $table->timestamp('panel_checked_at')->nullable();
                }
                if (!$schema->hasColumn(self::SERVICES_TABLE, 'last_action')) {
                    $table->string('last_action', 255)->nullable();
                }
                if (!$schema->hasColumn(self::SERVICES_TABLE, 'last_action_at')) {
                    $table->timestamp('last_action_at')->nullable();
                }
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
                if (!$schema->hasColumn(self::PANELS_TABLE, 'epg_url')) {
                    $table->text('epg_url')->nullable();
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
            'notes_enabled'   => '1',
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
            case 'notes_enabled':
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

    

    public static function serviceTagPattern(?string $template = null): ?string
    {
        if ($template === null) {
            $template = self::get('reseller_notes', self::credentialDefaults()['reseller_notes']);
        }

        $template = trim((string) $template);
        if ($template === '') {
            $template = self::credentialDefaults()['reseller_notes'];
        }

        $tagOpen  = preg_quote('{', '/');
        $tagClose = preg_quote('}', '/');

        $pattern = preg_quote($template, '/');
        $pattern = str_replace($tagOpen . 'service_id' . $tagClose, '(\d+)', $pattern);
        $pattern = preg_replace(
            '/' . preg_quote($tagOpen, '/') . '[^{}]*' . preg_quote($tagClose, '/') . '/',
            '.*?',
            $pattern
        );

        if ($pattern === null || strpos($pattern, '(\d+)') === false) {
            return null;
        }

        return $pattern;
    }

    

    public static function serviceTagFromNotes(string $notes, ?string $template = null): ?int
    {
        $pattern = self::serviceTagPattern($template);
        if ($pattern === null) {
            return null;
        }

        if (preg_match('/' . $pattern . '/', $notes, $matches) !== 1) {
            return null;
        }

        if (!isset($matches[1]) || !ctype_digit($matches[1])) {
            return null;
        }

        return (int) $matches[1];
    }

    

    public static function preferredLineMatch(array $matches, string $username): ?array
    {
        if ($matches === []) {
            return null;
        }

        $username = trim($username);
        if ($username !== '') {
            foreach ($matches as $match) {
                $candidate = isset($match['username']) ? $match['username'] : '';
                if (trim((string) $candidate) === $username) {
                    return $match;
                }
            }
        }

        $best = null;
        foreach ($matches as $match) {
            if ($best === null) {
                $best = $match;
                continue;
            }
            $current = isset($match['exp_date']) ? (int) $match['exp_date'] : 0;
            $bestExp = isset($best['exp_date']) ? (int) $best['exp_date'] : 0;
            if ($current > $bestExp) {
                $best = $match;
            }
        }

        return $best;
    }

    

    public static function chooseLineMatch(array $tagMatches, array $usernameMatches, string $username): ?array
    {
        if (count($tagMatches) > 1) {
            $sameUser = false;
            $username = trim($username);
            if ($username !== '') {
                foreach ($tagMatches as $candidate) {
                    if (trim((string) ($candidate['username'] ?? '')) === $username) {
                        $sameUser = true;
                        break;
                    }
                }
            }
            if (!$sameUser) {
                return ['line' => null, 'source' => 'ambiguous', 'candidates' => $tagMatches];
            }
        }

        $match = self::preferredLineMatch($tagMatches, $username);
        if ($match !== null) {
            return ['line' => $match, 'source' => 'tag'];
        }

        $match = self::preferredLineMatch($usernameMatches, $username);
        if ($match !== null) {
            return ['line' => $match, 'source' => 'username'];
        }

        return null;
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
