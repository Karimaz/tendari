<?php

namespace Tendari\Services;

use Stripe\StripeClient;
use Stripe\Webhook;
use Tendari\Core\Db;
use Tendari\Models\User;

final class Billing
{
    public static function enabled(): bool
    {
        return (string)config('billing.stripe_secret_key') !== '';
    }

    /** @return array<string, array> plans defined in config, with free plan first */
    public static function plans(): array
    {
        return (array)config('billing.plans', []);
    }

    public static function plan(string $key): ?array
    {
        return self::plans()[$key] ?? null;
    }

    public static function subscriptionFor(int $userId): ?array
    {
        return Db::one(
            "SELECT * FROM subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [$userId]
        );
    }

    public static function hasActiveSubscription(int $userId): bool
    {
        $sub = self::subscriptionFor($userId);
        return $sub !== null && in_array($sub['status'], ['active', 'trialing'], true);
    }

    /** Stripe Price ID for a plan + interval ('month'|'year'), or null when not configured. */
    public static function priceId(string $planKey, string $interval = 'month'): ?string
    {
        $plan = self::plan($planKey);
        if ($plan === null) {
            return null;
        }
        $key = $interval === 'year' ? 'stripe_price_yearly' : 'stripe_price_monthly';
        $id = (string)($plan[$key] ?? '');
        return $id !== '' ? $id : null;
    }

    public static function checkoutAvailable(string $planKey, string $interval = 'month'): bool
    {
        return self::enabled() && self::priceId($planKey, $interval) !== null;
    }

    /** Create a Stripe Checkout session and return its redirect URL, or null when misconfigured. */
    public static function createCheckout(int $userId, string $planKey, string $interval = 'month'): ?string
    {
        $interval = $interval === 'year' ? 'year' : 'month';
        $plan = self::plan($planKey);
        $priceId = self::priceId($planKey, $interval);
        if ($plan === null || $priceId === null || !self::enabled()) {
            return null;
        }
        $user = User::find($userId);
        if ($user === null) {
            return null;
        }
        $base = rtrim((string)config('app.base_url'), '/');
        $stripe = new StripeClient((string)config('billing.stripe_secret_key'));
        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $user['email'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $base . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base . '/pricing?canceled=1',
            'metadata' => [
                'user_id' => (string)$userId,
                'plan' => $planKey,
                'interval' => $interval,
            ],
        ]);
        Db::q(
            "INSERT INTO subscriptions (user_id, plan, stripe_session_id, status) VALUES (?,?,?, 'incomplete')",
            [$userId, $planKey, $session->id]
        );
        return $session->url;
    }

    /** Verify a completed Checkout session after redirect and activate the local record. */
    public static function confirmCheckout(string $sessionId): bool
    {
        if (!self::enabled() || !preg_match('/^cs_[A-Za-z0-9_-]+$/', $sessionId)) {
            return false;
        }
        $stripe = new StripeClient((string)config('billing.stripe_secret_key'));
        try {
            $session = $stripe->checkout->sessions->retrieve($sessionId, []);
        } catch (\Throwable $e) {
            logger('error', 'Billing: session retrieve failed: ' . $e->getMessage());
            return false;
        }
        if (($session->payment_status ?? '') !== 'paid' || $session->metadata?->user_id === null) {
            return false;
        }
        self::activateSession($session);
        return true;
    }

    /** Handle a verified webhook event. Returns true when the event was applied. */
    public static function handleWebhookEvent(object $event): bool
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                self::activateSession($event->data->object);
                return true;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                self::syncSubscription($event->data->object);
                return true;
        }
        return false;
    }

    private static function activateSession(object $session): void
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE stripe_session_id = ?', [$session->id]);
        if ($row === null) {
            Db::q(
                'INSERT INTO subscriptions (user_id, plan, stripe_session_id) VALUES (?,?,?)',
                [(int)($session->metadata->user_id ?? 0), (string)($session->metadata->plan ?? ''), $session->id]
            );
            $row = ['id' => Db::lastId()];
        }
        Db::q(
            "UPDATE subscriptions SET stripe_customer_id = ?, stripe_subscription_id = ?, status = 'active', updated_at = datetime('now') WHERE id = ?",
            [
                (string)($session->customer ?? ''),
                (string)($session->subscription ?? ''),
                (int)$row['id'],
            ]
        );
    }

    private static function syncSubscription(object $sub): void
    {
        $status = $sub->status ?? '';
        $periodEnd = isset($sub->current_period_end)
            ? gmdate('Y-m-d H:i:s', (int)$sub->current_period_end)
            : null;
        Db::q(
            "UPDATE subscriptions SET status = ?, current_period_end = ?, updated_at = datetime('now') WHERE stripe_subscription_id = ?",
            [(string)$status, $periodEnd, (string)($sub->id ?? '')]
        );
    }

    /** Verify the Stripe-Signature header against the raw request body. */
    public static function verifyWebhook(string $payload, string $sigHeader): ?object
    {
        $secret = (string)config('billing.stripe_webhook_secret');
        if ($secret === '') {
            return null;
        }
        try {
            return Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            logger('error', 'Billing: webhook signature check failed: ' . $e->getMessage());
            return null;
        }
    }
}
