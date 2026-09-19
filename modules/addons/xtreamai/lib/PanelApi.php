<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

final class PanelApi
{
    

    public static function client(int $panelId): PanelHttpClient
    {
        list($client) = self::buildClient($panelId);

        return $client;
    }

    

    public static function health(int $panelId): array
    {
        $token = '';

        try {
            list($client, $token) = self::buildClient($panelId);
            $identity = self::me($client);

            return [
                'ok' => true,
                'message' => self::connectionMessage($identity),
                'user' => self::userLabel($identity),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => self::translate($e, $token)->getMessage(),
                'user' => '',
            ];
        }
    }

    

    public static function healthFor(string $apiUrl, string $token, bool $verifySsl = true): array
    {
        $baseUrl = rtrim(trim($apiUrl), '/');

        try {
            if ($baseUrl === '') {
                throw new \RuntimeException('Panel API URL is not configured.');
            }
            if ($token === '') {
                throw new \RuntimeException('Panel API token is not configured.');
            }

            $client = new PanelHttpClient($baseUrl, $token, $verifySsl);
            $identity = self::me($client);

            return [
                'ok' => true,
                'message' => self::connectionMessage($identity),
                'user' => self::userLabel($identity),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => self::translate($e, $token)->getMessage(),
                'user' => '',
            ];
        }
    }

    

    public static function packages(int $panelId): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client): array {
            $out = [];

            foreach (self::items($client->request('GET', '/panel-api/v1/packages')) as $package) {
                $out[] = [
                    'id' => (int) self::value($package, 'id', 0),
                    'name' => (string) self::value($package, 'package_name', ''),
                    'duration' => self::packageDuration($package),
                    'is_official' => (bool) self::value($package, 'is_official', false),
                    'is_trial' => (bool) self::value($package, 'is_trial', false),
                    'max_connections' => (int) self::value($package, 'max_connections', 0),
                ];
            }

            return $out;
        });
    }

    public static function packageMaxConnections(int $panelId, int $packageId): int
    {
        static $cache = [];
        if (!isset($cache[$panelId])) {
            $cache[$panelId] = self::packages($panelId);
        }
        foreach ($cache[$panelId] as $package) {
            if ((int) $package['id'] === $packageId) {
                return (int) $package['max_connections'];
            }
        }

        throw new \RuntimeException('Package #' . $packageId . ' was not found on the panel.');
    }

    

    public static function bouquets(int $panelId): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client): array {
            $out = [];

            foreach (self::items($client->request('GET', '/panel-api/v1/bouquets')) as $bouquet) {
                $out[] = [
                    'id' => (int) self::value($bouquet, 'id', 0),
                    'name' => (string) self::value($bouquet, 'name', ''),
                ];
            }

            return $out;
        });
    }

    

    public static function credits(int $panelId): ?string
    {
        return self::withClient($panelId, static function (PanelHttpClient $client): ?string {
            $credits = self::billingCredits(self::me($client));

            if ($credits === null) {
                return null;
            }

            return self::formatCredits($credits);
        });
    }

    

    public static function createLine(
        int $panelId,
        int $packageId,
        ?int $memberId,
        string $username,
        string $password,
        array $bouquetIds,
        ?int $maxConnections,
        ?string $notes
    ): array {
        $keyType = self::keyType($panelId);

        return self::withClient($panelId, static function (PanelHttpClient $client) use (
            $packageId,
            $memberId,
            $username,
            $password,
            $bouquetIds,
            $maxConnections,
            $notes,
            $keyType
        ): array {
            $body = [
                'package_id' => $packageId,
                'username' => $username,
                'password' => $password,
                'bouquets' => array_values(array_map('intval', $bouquetIds)),
            ];
            if ($memberId !== null) {
                $body['member_id'] = $memberId;
            }
            if ($maxConnections !== null && $keyType === 'admin') {
                $body['max_connections'] = $maxConnections;
            }
            if ($notes !== null) {
                $body['notes'] = $notes;
            }

            $line = $client->request('POST', '/panel-api/v1/lines', null, $body);

            return self::normalizeCreatedLine($line);
        });
    }

    public static function updateLine(int $panelId, string $lineId, array $fields): array
    {
        $keyType = self::keyType($panelId);
        $adminOnly = ['package_id', 'max_connections', 'exp_date', 'is_restreamer', 'allowed_ips', 'allowed_ua', 'is_isplock'];
        $bothTypes = ['bouquets', 'notes'];

        $body = [];
        $dropped = [];
        $ignored = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, $bothTypes, true)) {
                if ($key === 'bouquets') {
                    if (!is_array($value)) {
                        $ignored[] = $key;
                        continue;
                    }
                    $body['bouquets'] = array_values(array_map('intval', $value));
                } else {
                    $body['notes'] = (string) $value;
                }
                continue;
            }
            if (!in_array($key, $adminOnly, true)) {
                $ignored[] = $key;
                continue;
            }
            if ($keyType !== 'admin') {
                $dropped[] = $key;
                continue;
            }
            switch ($key) {
                case 'package_id':
                    $body['package_id'] = (int) $value;
                    break;
                case 'max_connections':
                    $body['max_connections'] = (int) $value;
                    break;
                case 'exp_date':
                    $body['exp_date'] = $value === null ? null : (int) $value;
                    break;
                case 'is_restreamer':
                case 'is_isplock':
                    $body[$key] = (bool) $value;
                    break;
                case 'allowed_ips':
                case 'allowed_ua':
                    if (is_array($value)) {
                        $body[$key] = array_values(array_map('strval', $value));
                    } else {
                        $ignored[] = $key;
                    }
                    break;
            }
        }

        $warnings = [];
        if ($dropped !== []) {
            $warnings[] = 'Dropped admin-only fields on reseller key: ' . implode(',', $dropped);
        }
        if ($ignored !== []) {
            $warnings[] = 'Ignored unsupported fields: ' . implode(',', $ignored);
        }
        if ($warnings !== []) {
            Settings::set('last_update_warning', implode('. ', $warnings));
        }

        if ($body === []) {
            return self::getLine($panelId, $lineId);
        }

        return self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId, $body): array {
            $line = $client->request(
                'POST',
                '/panel-api/v1/lines/' . (int) $lineId . '/update',
                null,
                $body
            );

            return [
                'id' => (string) self::value($line, 'id', ''),
                'username' => (string) self::value($line, 'username', ''),
                'enabled' => (bool) self::value($line, 'enabled', false),
                'expires_at' => self::expiryFrom($line),
            ];
        });
    }

    

    public static function setLineEnabled(int $panelId, string $lineId, bool $enabled): void
    {
        self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId, $enabled): void {
            $id = (int) $lineId;
            $path = $enabled
                ? '/panel-api/v1/lines/' . $id . '/enable'
                : '/panel-api/v1/lines/' . $id . '/disable';

            $client->request('POST', $path, null, []);
        });
    }

    

    public static function renewLine(
        int $panelId,
        string $lineId,
        int $packageId,
        ?array $bouquets = null,
        ?string $idempotencyKey = null
    ): array {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId, $packageId, $bouquets, $idempotencyKey): array {
            $body = ['package_id' => $packageId];
            if ($bouquets !== null) {
                $body['bouquets'] = array_values(array_map('intval', $bouquets));
            }

            $line = $client->request(
                'POST',
                '/panel-api/v1/lines/' . (int) $lineId . '/renew',
                null,
                $body,
                $idempotencyKey
            );

            return [
                'expires_at' => self::expiryFrom($line),
                'max_connections' => (int) self::value($line, 'max_connections', 0),
            ];
        });
    }

    

    public static function getLine(int $panelId, string $lineId, bool $quick = false): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId): array {
            $line = $client->request('GET', '/panel-api/v1/lines/' . (int) $lineId);

            return [
                'id' => (string) self::value($line, 'id', ''),
                'username' => (string) self::value($line, 'username', ''),
                'password' => (string) self::value($line, 'password', ''),
                'enabled' => (bool) self::value($line, 'enabled', false),
                'admin_enabled' => (bool) self::value($line, 'admin_enabled', true),
                'is_trial' => (bool) self::value($line, 'is_trial', false),
                'max_connections' => (int) self::value($line, 'max_connections', 0),
                'exp_date' => self::nullableInt($line, 'exp_date'),
                'expires_at' => self::expiryFrom($line),
                'notes' => (string) self::value($line, 'notes', ''),
                'email' => (string) self::value($line, 'email', ''),
            ];
        }, $quick);
    }

    

    public static function resetLinePassword(int $panelId, string $lineId, string $newPassword): void
    {
        self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId, $newPassword): void {
            $client->request(
                'POST',
                '/panel-api/v1/lines/' . (int) $lineId . '/reset-password',
                null,
                ['password' => $newPassword]
            );
        });
    }

    

    public static function deleteLine(int $panelId, string $lineId): void
    {
        self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId): void {
            $client->request('POST', '/panel-api/v1/lines/' . (int) $lineId . '/delete', null, []);
        });
    }

    

    public static function resellers(int $panelId): array
    {
        $page = self::resellersPage($panelId);

        return $page['items'];
    }

    

    public static function resellersPage(int $panelId, ?string $cursor = null): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($cursor): array {
            $out = [];

            $query = ['limit' => '100'];
            if ($cursor !== null && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $body = $client->request('GET', '/panel-api/v1/resellers', $query);

            foreach (self::items($body) as $reseller) {
                $credits = self::resellerCreditsFromJson($reseller);
                $out[] = [
                    'id' => (int) self::value($reseller, 'id', 0),
                    'username' => (string) self::value($reseller, 'username', ''),
                    'email' => (string) self::value($reseller, 'email', ''),
                    'status' => self::resellerStatus($reseller),
                    'group' => self::nullableString($reseller, 'member_group_name', ''),
                    'credits' => $credits !== null ? self::formatCredits($credits) : '',
                ];
            }

            return ['items' => $out, 'next_cursor' => self::nextCursor($body)];
        });
    }

    

    public static function findResellerByUsername(int $panelId, string $username): ?array
    {
        $wanted = trim($username);
        if ($wanted === '') {
            return null;
        }

        return self::withClient($panelId, static function (PanelHttpClient $client) use ($wanted): ?array {
            $query = ['username' => $wanted, 'limit' => '2'];
            $body = $client->request('GET', '/panel-api/v1/resellers', $query);

            foreach (self::items($body) as $reseller) {
                $found = (string) self::value($reseller, 'username', '');
                if (strcasecmp($found, $wanted) !== 0) {
                    continue;
                }

                return [
                    'id' => (string) self::value($reseller, 'id', ''),
                    'username' => $found,
                    'member_group_id' => (int) self::value($reseller, 'member_group_id', 0),
                ];
            }

            return null;
        });
    }

    

    public static function createReseller(
        int $panelId,
        string $username,
        string $password,
        string $email,
        ?float $credits,
        ?string $notes,
        ?int $memberGroupId = null
    ): array {
        return self::withClient($panelId, static function (PanelHttpClient $client) use (
            $username,
            $password,
            $email,
            $credits,
            $notes,
            $memberGroupId
        ): array {
            $body = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
            ];
            if ($credits !== null) {
                $body['credits'] = $credits;
            }
            if ($notes !== null) {
                $body['notes'] = $notes;
            }
            if ($memberGroupId !== null && $memberGroupId > 0) {
                $body['member_group_id'] = $memberGroupId;
            }

            $reseller = $client->request('POST', '/panel-api/v1/resellers', null, $body);
            $resellerCredits = self::resellerCreditsFromJson($reseller);

            return [
                'id' => (string) self::value($reseller, 'id', ''),
                'username' => (string) self::value($reseller, 'username', ''),
                'credits' => $resellerCredits !== null ? self::formatCredits($resellerCredits) : '',
            ];
        });
    }

    

    public static function getReseller(int $panelId, string $resellerId): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($resellerId): array {
            $reseller = $client->request('GET', '/panel-api/v1/resellers/' . (int) $resellerId);
            $credits = self::resellerCreditsFromJson($reseller);

            return [
                'id' => (int) self::value($reseller, 'id', 0),
                'username' => (string) self::value($reseller, 'username', ''),
                'email' => (string) self::value($reseller, 'email', ''),
                'status' => self::resellerStatus($reseller),
                'group' => self::nullableString($reseller, 'member_group_name', ''),
                'credits' => $credits !== null ? self::formatCredits($credits) : '',
            ];
        });
    }



    public static function updateReseller(int $panelId, string $resellerId, array $fields): array
    {
        if (self::keyType($panelId) !== 'admin') {
            throw new \RuntimeException('Reseller lifecycle operations require an admin panel key');
        }

        $allowed = ['status', 'password', 'member_group_id', 'notes', 'email', 'owner_id'];
        $body = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            if ($key === 'status') {
                $body['status'] = (int) (bool) $value;
            } elseif ($key === 'member_group_id' || $key === 'owner_id') {
                $body[$key] = (int) $value;
            } else {
                $body[$key] = (string) $value;
            }
        }
        if ($body === []) {
            return self::getReseller($panelId, $resellerId);
        }

        return self::withClient($panelId, static function (PanelHttpClient $client) use ($resellerId, $body): array {
            $reseller = $client->request(
                'POST',
                '/panel-api/v1/resellers/' . (int) $resellerId . '/update',
                null,
                $body
            );

            $credits = self::resellerCreditsFromJson($reseller);
            return [
                'id' => (int) self::value($reseller, 'id', 0),
                'username' => (string) self::value($reseller, 'username', ''),
                'email' => (string) self::value($reseller, 'email', ''),
                'status' => self::resellerStatus($reseller),
                'group' => self::nullableString($reseller, 'member_group_name', ''),
                'credits' => $credits !== null ? self::formatCredits($credits) : '',
            ];
        });
    }



    public static function setResellerStatus(int $panelId, string $resellerId, bool $enabled): void
    {
        self::updateReseller($panelId, $resellerId, ['status' => $enabled]);
        $observed = self::getReseller($panelId, $resellerId);
        $observedEnabled = !empty($observed['status']);
        if ($observedEnabled !== $enabled) {
            throw new \RuntimeException('The panel API does not support changing a reseller status. Please disable this account manually from the panel and try again.');
        }
    }



    public static function resetResellerPassword(int $panelId, string $resellerId, string $newPassword): void
    {
        self::updateReseller($panelId, $resellerId, ['password' => $newPassword]);
    }



    public static function getStream(int $panelId, string $streamId): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($streamId): array {
            $stream = $client->request('GET', '/panel-api/v1/streams/' . (int) $streamId);

            return [
                'id' => (int) self::value($stream, 'id', 0),
                'name' => (string) self::value($stream, 'name', ''),
                'icon' => (string) self::value($stream, 'icon', ''),
                'categories' => self::categoryNames($stream),
            ];
        });
    }



    public static function getVod(int $panelId, string $vodId): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($vodId): array {
            $vod = $client->request('GET', '/panel-api/v1/vods/' . (int) $vodId);

            return [
                'id' => (int) self::value($vod, 'id', 0),
                'name' => (string) self::value($vod, 'name', ''),
                'icon' => (string) self::value($vod, 'icon', ''),
                'year' => self::nullableInt($vod, 'year'),
                'rating' => self::nullableFloat($vod, 'rating'),
                'is_serie' => (bool) self::value($vod, 'is_serie', false),
                'categories' => self::categoryNames($vod),
            ];
        });
    }

    

    public static function adjustResellerCredits(
        int $panelId,
        string $resellerId,
        float $delta,
        string $reason
    ): string {
        return self::withClient($panelId, static function (PanelHttpClient $client) use (
            $resellerId,
            $delta,
            $reason
        ): string {
            $billing = $client->request(
                'POST',
                '/panel-api/v1/resellers/' . (int) $resellerId . '/billing/adjust',
                null,
                ['delta' => $delta, 'reason' => $reason]
            );

            return self::billingCreditsFromSnapshot($billing);
        });
    }

    

    public static function resellerCredits(int $panelId, string $resellerId): string
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($resellerId): string {
            $billing = $client->request('GET', '/panel-api/v1/resellers/' . (int) $resellerId . '/billing');

            return self::billingCreditsFromSnapshot($billing);
        });
    }

    

    public static function lineConnections(int $panelId, string $lineId, bool $quick = false): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($lineId): array {
            $out = [];

            $body = $client->request('GET', '/panel-api/v1/lines/' . (int) $lineId . '/connections');

            foreach (self::items($body) as $connection) {
                $out[] = [
                    'content' => (string) self::value($connection, 'content_name', ''),
                    'ip' => (string) self::value($connection, 'client_ip', ''),
                    'country' => (string) self::value($connection, 'client_country', ''),
                    'elapsed_sec' => (int) self::value($connection, 'elapsed_sec', 0),
                ];
            }

            return $out;
        }, $quick);
    }

    

    public static function lines(int $panelId, ?string $username = null, ?bool $enabled = null): array
    {
        $page = self::linesPage($panelId, $username, $enabled);

        return $page['items'];
    }

    

    public static function linesPage(
        int $panelId,
        ?string $username = null,
        ?bool $enabled = null,
        ?string $cursor = null
    ): array {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($username, $enabled, $cursor): array {
            $out = [];

            $query = ['limit' => '100'];
            $usernameFilter = ($username !== null && trim($username) !== '') ? trim($username) : null;
            if ($usernameFilter !== null) {
                $query['username'] = $usernameFilter;
            }
            if ($enabled !== null) {
                $query['enabled'] = $enabled ? 'true' : 'false';
            }
            if ($cursor !== null && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $body = $client->request('GET', '/panel-api/v1/lines', $query);

            foreach (self::items($body) as $line) {
                $out[] = [
                    'id' => (int) self::value($line, 'id', 0),
                    'username' => (string) self::value($line, 'username', ''),
                    'enabled' => (bool) self::value($line, 'enabled', false),
                    'is_trial' => (bool) self::value($line, 'is_trial', false),
                    'max_connections' => (int) self::value($line, 'max_connections', 0),
                    'expires_at' => self::expiryFrom($line),
                    'exp_date' => self::nullableInt($line, 'exp_date'),
                    'notes' => (string) self::value($line, 'notes', ''),
                    'email' => (string) self::value($line, 'email', ''),
                ];
            }

            return ['items' => $out, 'next_cursor' => self::nextCursor($body)];
        });
    }

    

    public static function streams(int $panelId, ?string $search = null): array
    {
        $page = self::streamsPage($panelId, $search);

        return $page['items'];
    }

    

    public static function streamsPage(int $panelId, ?string $search = null, ?string $cursor = null): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($search, $cursor): array {
            $out = [];

            $query = ['limit' => '100'];
            $searchFilter = ($search !== null && trim($search) !== '') ? trim($search) : null;
            if ($searchFilter !== null) {
                $query['q'] = $searchFilter;
            }
            if ($cursor !== null && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $body = $client->request('GET', '/panel-api/v1/streams', $query);

            foreach (self::items($body) as $stream) {
                $out[] = [
                    'id' => (int) self::value($stream, 'id', 0),
                    'name' => (string) self::value($stream, 'name', ''),
                    'icon' => (string) self::value($stream, 'icon', ''),
                    'categories' => self::categoryNames($stream),
                ];
            }

            return ['items' => $out, 'next_cursor' => self::nextCursor($body)];
        });
    }

    

    public static function vods(int $panelId, ?string $search = null): array
    {
        $page = self::vodsPage($panelId, $search);

        return $page['items'];
    }

    

    public static function vodsPage(int $panelId, ?string $search = null, ?string $cursor = null): array
    {
        return self::withClient($panelId, static function (PanelHttpClient $client) use ($search, $cursor): array {
            $out = [];

            $query = ['limit' => '100'];
            $searchFilter = ($search !== null && trim($search) !== '') ? trim($search) : null;
            if ($searchFilter !== null) {
                $query['q'] = $searchFilter;
            }
            if ($cursor !== null && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $body = $client->request('GET', '/panel-api/v1/vods', $query);

            foreach (self::items($body) as $vod) {
                $out[] = [
                    'id' => (int) self::value($vod, 'id', 0),
                    'name' => (string) self::value($vod, 'name', ''),
                    'icon' => (string) self::value($vod, 'icon', ''),
                    'year' => self::nullableInt($vod, 'year'),
                    'rating' => self::nullableFloat($vod, 'rating'),
                    'is_serie' => (bool) self::value($vod, 'is_serie', false),
                    'categories' => self::categoryNames($vod),
                ];
            }

            return ['items' => $out, 'next_cursor' => self::nextCursor($body)];
        });
    }

    

    private static function buildClient(int $panelId, bool $quick = false): array
    {
        $panel = PanelStore::find($panelId);

        if ($panel === null) {
            throw new \RuntimeException('Panel not found.');
        }

        if ((int) $panel->active !== 1) {
            throw new \RuntimeException('Panel is disabled.');
        }

        $baseUrl = rtrim(trim((string) $panel->api_url), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('Panel API URL is not configured.');
        }

        $token = self::decryptToken((string) $panel->password);
        if ($token === '') {
            throw new \RuntimeException('Panel API token is not configured.');
        }

        $verifySsl = (int) ($panel->verify_ssl ?? 1) !== 0;
        $client = $quick
            ? PanelHttpClient::quick($baseUrl, $token, $verifySsl)
            : new PanelHttpClient($baseUrl, $token, $verifySsl);

        return [$client, $token];
    }

    

    private static function withClient(int $panelId, callable $callback, bool $quick = false)
    {
        list($client, $token) = self::buildClient($panelId, $quick);

        try {
            return $callback($client);
        } catch (\Throwable $e) {
            throw self::translate($e, $token);
        }
    }

    

    private static function decryptToken(string $stored): string
    {
        $stored = trim($stored);

        if ($stored === '') {
            return '';
        }

        if (function_exists('decrypt')) {
            try {
                $plain = decrypt($stored);
                if (is_string($plain) && $plain !== '') {
                    return $plain;
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException('Stored API token could not be decrypted. Re-enter the access key for this panel.');
            }
        }

        throw new \RuntimeException('Stored API token could not be decrypted. Re-enter the access key for this panel.');
    }

    

    private static function translate(\Throwable $e, string $token = ''): \RuntimeException
    {
        if ($e instanceof PanelApiRequestException) {
            $message = self::friendly($e);
            $detail = trim((string) $e->getMessage());

            if ($detail !== '' && $detail !== $message) {
                $message .= ' ' . $detail;
            }
        } else {
            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = 'Panel request failed.';
            }
        }

        if ($token !== '') {
            $message = str_replace($token, '***', $message);
        }

        return new \RuntimeException($message, (int) $e->getCode(), $e);
    }

    

    private static function friendly(PanelApiRequestException $e): string
    {
        switch ($e->getErrorType()) {
            case 'authentication':
                return 'Panel authentication failed.';
            case 'authorization':
                return 'The API token is not permitted to perform this action.';
            case 'insufficient_credits':
                return 'The reseller does not have enough credits or user slots.';
            case 'not_found':
                return 'The requested resource was not found on the panel.';
            case 'validation':
                return 'The panel rejected the request values.';
            case 'conflict':
                return 'The request conflicts with the current panel state.';
            case 'rate_limit':
                return 'The panel rate limit was reached; retry later.';
            case 'service_unavailable':
                return 'The panel is temporarily unavailable.';
            case 'server':
                return 'The panel server returned an error.';
            case 'bad_request':
                return 'The panel rejected the request.';
            case 'network':
                return 'Could not reach the panel.';
            default:
                return 'The panel request failed.';
        }
    }

    

    private static function me(PanelHttpClient $client): array
    {
        return $client->request('GET', '/panel-api/v1/me');
    }

    

    private static function userLabel(array $identity): string
    {
        $group = self::nullableString($identity, 'member_group_name', null);

        if ($group !== null && $group !== '') {
            $label = $group;
            if (isset($identity['reg_user_id']) && $identity['reg_user_id'] !== null) {
                $label .= ' #' . $identity['reg_user_id'];
            }

            return $label;
        }

        $type = (string) self::value($identity, 'type', '');
        if ($type !== '') {
            return ucfirst($type);
        }

        return 'Connected';
    }

    

    private static function connectionMessage(array $identity): string
    {
        $message = 'Connected';
        $parts = [];

        $group = self::nullableString($identity, 'member_group_name', null);
        if ($group !== null && $group !== '') {
            $parts[] = 'Group: ' . $group;
        }

        $credits = self::billingCredits($identity);
        if ($credits !== null) {
            $parts[] = 'Credits: ' . self::formatCredits($credits);
        }

        if ($parts !== []) {
            $message .= ' (' . implode(', ', $parts) . ')';
        }

        return $message;
    }

    

    private static function packageDuration(array $package): string
    {
        $officialDuration = (int) self::value($package, 'official_duration', 0);
        $officialDurationIn = (string) self::value($package, 'official_duration_in', '');

        if ($officialDuration > 0 && $officialDurationIn !== '') {
            return $officialDuration . ' ' . $officialDurationIn;
        }

        $trialDuration = (int) self::value($package, 'trial_duration', 0);
        $trialDurationIn = (string) self::value($package, 'trial_duration_in', '');

        if ($trialDuration > 0 && $trialDurationIn !== '') {
            return $trialDuration . ' ' . $trialDurationIn;
        }

        return '';
    }

    

    private static function normalizeCreatedLine(array $line): array
    {
        return [
            'id' => (string) self::value($line, 'id', ''),
            'username' => (string) self::value($line, 'username', ''),
            'password' => (string) self::value($line, 'password', ''),
            'expires_at' => self::expiryFrom($line),
        ];
    }

    

    private static function expiryFrom(array $data, string $key = 'exp_date'): string
    {
        if (!isset($data[$key]) || $data[$key] === null) {
            return '';
        }

        $ts = (int) $data[$key];

        return $ts > 0 ? gmdate('Y-m-d', $ts) : '';
    }

    

    private static function formatCredits(float $credits): string
    {
        $formatted = rtrim(rtrim(number_format($credits, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    

    private static function billingCredits(array $identity): ?float
    {
        if (!isset($identity['billing']) || !is_array($identity['billing'])) {
            return null;
        }

        return self::nullableFloat($identity['billing'], 'credits');
    }

    

    private static function billingCreditsFromSnapshot(array $billing): string
    {
        $credits = self::nullableFloat($billing, 'credits');

        return $credits !== null ? self::formatCredits($credits) : '';
    }

    

    private static function resellerCreditsFromJson(array $reseller): ?float
    {
        if (isset($reseller['billing']) && is_array($reseller['billing'])) {
            return self::nullableFloat($reseller['billing'], 'credits');
        }

        if (array_key_exists('billing_mode', $reseller)) {
            return self::nullableFloat($reseller, 'credits');
        }

        return null;
    }

    

    private static function resellerStatus(array $reseller): bool
    {
        if (array_key_exists('status', $reseller)) {
            return is_bool($reseller['status'])
                ? $reseller['status']
                : ((int) $reseller['status']) === 1;
        }

        return true;
    }

    

    private static function categoryNames(array $data): array
    {
        $out = [];
        foreach ((array) self::value($data, 'categories', []) as $category) {
            $out[] = (string) self::value($category, 'name', '');
        }

        return $out;
    }

    

    private static function items(array $body): array
    {
        return isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
    }

    

    private static function nextCursor(array $body): ?string
    {
        if (isset($body['next_cursor']) && $body['next_cursor'] !== null && $body['next_cursor'] !== '') {
            return (string) $body['next_cursor'];
        }

        return null;
    }

    

    private static function value(array $data, string $key, $default)
    {
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    

    private static function nullableString(array $data, string $key, ?string $default): ?string
    {
        if (!isset($data[$key]) || $data[$key] === null) {
            return $default;
        }

        return (string) $data[$key];
    }

    

    private static function nullableInt(array $data, string $key): ?int
    {
        if (!isset($data[$key]) || $data[$key] === null) {
            return null;
        }

        return (int) $data[$key];
    }

    

    private static function nullableFloat(array $data, string $key): ?float
    {
        if (!isset($data[$key]) || $data[$key] === null) {
            return null;
        }

        return (float) $data[$key];
    }

    public static function keyType(int $panelId): string
    {
        $panel = PanelStore::find($panelId);
        if ($panel === null) {
            return 'reseller';
        }
        $type = (string) ($panel->key_type ?? 'reseller');
        return $type === 'admin' ? 'admin' : 'reseller';
    }

    public static function adminOwnerMemberId(int $panelId): ?int
    {
        $panel = PanelStore::find($panelId);
        if ($panel === null) {
            return null;
        }
        if ((string) ($panel->key_type ?? 'reseller') !== 'admin') {
            return null;
        }
        $id = $panel->admin_owner_member_id ?? null;
        return ($id === null || $id === '') ? null : (int) $id;
    }
}
