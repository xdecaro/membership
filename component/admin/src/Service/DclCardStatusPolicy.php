<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

final class DclCardStatusPolicy
{
    /**
     * Normalize the persisted lifecycle state without overriding workflow
     * states such as pending/in_review/revoked. An active card whose end date
     * is already in the past is stored as expired.
     */
    public static function normalizeLifecycleStatus(array|object $card, ?string $today = null): string
    {
        $data = is_object($card) ? get_object_vars($card) : $card;
        $status = strtolower(trim((string) ($data['status'] ?? 'pending')));
        $expiresAt = self::date((string) ($data['expires_at'] ?? ''));
        $today = self::date((string) ($today ?? gmdate('Y-m-d'))) ?? gmdate('Y-m-d');

        if ($status === 'active' && $expiresAt !== null && $expiresAt < $today) {
            return 'expired';
        }

        return $status === '' ? 'pending' : $status;
    }

    /**
     * Evaluate whether a DCL card covers an entire competition period.
     *
     * Historical cards with lifecycle status "expired" remain valid for a past
     * competition when their validity dates covered the requested period.
     */
    public static function evaluate(array|object $card, ?string $requiredFrom = null, ?string $requiredUntil = null): string
    {
        $data = is_object($card) ? get_object_vars($card) : $card;
        $status = strtolower(trim((string) ($data['status'] ?? '')));
        $validFrom = self::date((string) ($data['valid_from'] ?? ''));
        $expiresAt = self::date((string) ($data['expires_at'] ?? ''));
        $requiredFrom = self::date((string) ($requiredFrom ?? ''));
        $requiredUntil = self::date((string) ($requiredUntil ?? ''));

        if ($status === 'pending') {
            return 'pending';
        }
        if ($status === 'in_review') {
            return 'in_review';
        }
        if ($status === 'revoked') {
            return 'revoked';
        }
        if ($status === 'suspended') {
            return 'suspended';
        }
        if (in_array($status, ['lost', 'replaced'], true)) {
            return 'invalid';
        }
        if (!in_array($status, ['active', 'expired'], true)) {
            return 'invalid';
        }
        if ($validFrom === null || $expiresAt === null) {
            return 'invalid_period';
        }
        if ($validFrom > $expiresAt) {
            return 'invalid_period';
        }

        if ($requiredFrom !== null && $validFrom > $requiredFrom) {
            return 'invalid_period';
        }
        if ($requiredUntil !== null && $expiresAt < $requiredUntil) {
            return 'invalid_period';
        }

        // With no competition period, evaluate against today.
        if ($requiredFrom === null && $requiredUntil === null) {
            $today = gmdate('Y-m-d');
            if ($validFrom > $today) {
                return 'not_yet_valid';
            }
            if ($expiresAt < $today) {
                return 'expired';
            }
        }

        return 'valid';
    }

    private static function date(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
