<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;

/**
 * Plan-based access to bid intelligence (similar awards + price ranges).
 *
 * Free = locked. Starter = 5 tenders/month. Pro = 15 tenders/month.
 * Business/Enterprise = unlimited. Re-opening the same tender in the same
 * month does not consume another view.
 */
final class IntelligenceAccess
{
    /** Current quota period (UTC calendar month, e.g. "2026-11"). */
    public static function period(): string
    {
        return gmdate('Y-m');
    }

    /** Plan key from an active subscription, or "free". */
    public static function planForUser(int $userId): string
    {
        $sub = Billing::subscriptionFor($userId);
        if ($sub !== null && in_array($sub['status'], ['active', 'trialing'], true)) {
            $plan = (string)$sub['plan'];
            if (Billing::plan($plan) !== null) {
                return $plan;
            }
        }
        return 'free';
    }

    /** Monthly tender-intelligence allowance; null means unlimited. */
    public static function quotaForPlan(string $plan): ?int
    {
        $config = Billing::plan($plan);
        if ($config === null) {
            return 0;
        }
        if (!array_key_exists('intelligence_monthly', $config)) {
            return 0;
        }
        return $config['intelligence_monthly'] === null
            ? null
            : max(0, (int)$config['intelligence_monthly']);
    }

    /**
     * @return array{plan:string,limit:?int,used:int,remaining:?int,unlimited:bool,period:string}
     */
    public static function status(?int $userId, ?string $period = null, bool $admin = false): array
    {
        $period = $period ?? self::period();
        if ($userId === null) {
            return ['plan' => 'guest', 'limit' => 0, 'used' => 0, 'remaining' => 0,
                    'unlimited' => false, 'period' => $period];
        }
        if ($admin) {
            return ['plan' => 'admin', 'limit' => null, 'used' => 0, 'remaining' => null,
                    'unlimited' => true, 'period' => $period];
        }
        $plan = self::planForUser($userId);
        $limit = self::quotaForPlan($plan);
        $used = (int)Db::scalar(
            'SELECT COUNT(*) FROM intelligence_usage WHERE user_id = ? AND period = ?',
            [$userId, $period]
        );
        $remaining = $limit === null ? null : max(0, $limit - $used);
        return [
            'plan' => $plan,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'unlimited' => $limit === null,
            'period' => $period,
        ];
    }

    /**
     * Check access without consuming a view.
     *
     * @return array{allowed:bool,reason:string,plan:string,limit:?int,used:int,remaining:?int,unlimited:bool,period:string}
     */
    public static function canView(?int $userId, int $tenderId, ?string $period = null, bool $admin = false): array
    {
        $status = self::status($userId, $period, $admin);
        if ($userId === null) {
            return ['allowed' => false, 'reason' => 'guest'] + $status;
        }
        if ($admin || $status['unlimited']) {
            return ['allowed' => true, 'reason' => $admin ? 'admin' : 'unlimited'] + $status;
        }
        if (($status['limit'] ?? 0) <= 0) {
            return ['allowed' => false, 'reason' => 'plan'] + $status;
        }
        $seen = Db::scalar(
            'SELECT 1 FROM intelligence_usage WHERE user_id = ? AND tender_id = ? AND period = ?',
            [$userId, $tenderId, $status['period']]
        );
        if ($seen) {
            return ['allowed' => true, 'reason' => 'already'] + $status;
        }
        if (($status['remaining'] ?? 0) > 0) {
            return ['allowed' => true, 'reason' => 'quota'] + $status;
        }
        return ['allowed' => false, 'reason' => 'exhausted'] + $status;
    }

    /**
     * Consume one view when needed. Returns the resulting access state.
     *
     * @return array{allowed:bool,reason:string,plan:string,limit:?int,used:int,remaining:?int,unlimited:bool,period:string}
     */
    public static function unlock(?int $userId, int $tenderId, ?string $period = null, bool $admin = false): array
    {
        $access = self::canView($userId, $tenderId, $period, $admin);
        if ($userId === null || !$access['allowed'] || $access['reason'] !== 'quota') {
            return $access;
        }
        $inserted = Db::q(
            'INSERT OR IGNORE INTO intelligence_usage (user_id, tender_id, period) VALUES (?,?,?)',
            [$userId, $tenderId, $access['period']]
        );
        $access = self::canView($userId, $tenderId, $access['period'], $admin);
        if ($inserted->rowCount() > 0
            && !$access['unlimited']
            && $access['limit'] !== null
            && $access['used'] > $access['limit']) {
            // Two concurrent first-views can both pass canView(); keep the quota hard.
            Db::q(
                'DELETE FROM intelligence_usage WHERE user_id = ? AND tender_id = ? AND period = ?',
                [$userId, $tenderId, $access['period']]
            );
            $access = self::canView($userId, $tenderId, $access['period'], $admin);
            $access['reason'] = 'exhausted';
            return $access;
        }
        $access['reason'] = 'quota';
        return $access;
    }
}
