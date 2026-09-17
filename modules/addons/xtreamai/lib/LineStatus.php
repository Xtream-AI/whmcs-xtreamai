<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

final class LineStatus
{
    public const ACTIVE = 'Active';
    public const EXPIRED = 'Expired';
    public const DISABLED = 'Disabled';
    public const BLOCKED = 'Blocked by panel';

    public static function fromPanel(array $line, ?int $now = null): string
    {
        if (array_key_exists('admin_enabled', $line) && !self::flag($line['admin_enabled'])) {
            return self::BLOCKED;
        }

        if (array_key_exists('enabled', $line) && !self::flag($line['enabled'])) {
            return self::DISABLED;
        }

        $expiry = self::expiry($line);
        if ($expiry !== null && $expiry <= ($now === null ? time() : $now)) {
            return self::EXPIRED;
        }

        return self::ACTIVE;
    }

    public static function badgeClass(string $status): string
    {
        switch ($status) {
            case self::ACTIVE:
                return 'xtai-badge--success';
            case self::EXPIRED:
                return 'xtai-badge--warning';
            case self::BLOCKED:
                return 'xtai-badge--danger';
        }

        return 'xtai-badge--neutral';
    }

    private static function expiry(array $line): ?int
    {
        if (array_key_exists('exp_date', $line) && $line['exp_date'] !== null && $line['exp_date'] !== '') {
            $timestamp = (int) $line['exp_date'];

            return $timestamp > 0 ? $timestamp : null;
        }

        $raw = isset($line['expires_at']) ? trim((string) $line['expires_at']) : '';
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $matches) === 1) {
            $timestamp = strtotime($matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' 00:00:00 UTC');

            return $timestamp === false ? null : (int) $timestamp;
        }

        $timestamp = strtotime($raw);

        return $timestamp === false ? null : (int) $timestamp;
    }

    private static function flag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return !($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'no' || $normalized === 'off');
        }

        return (bool) $value;
    }
}
