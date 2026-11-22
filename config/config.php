<?php
// Secrets are read from environment variables only — never hardcode them here.
declare(strict_types=1);

return [
    'db' => ['path' => dirname(__DIR__) . '/storage/app.sqlite'],
    'smtp' => [
        'host' => getenv('TH_SMTP_HOST') ?: '',
        'port' => (int)(getenv('TH_SMTP_PORT') ?: 587),
        'user' => getenv('TH_SMTP_USER') ?: '',
        'pass' => getenv('TH_SMTP_PASS') ?: '',
        'from' => getenv('TH_SMTP_FROM') ?: 'noreply@tendari.org',
    ],
    'telegram' => [
        'bot_token' => getenv('TH_TG_TOKEN') ?: '',
        'bot_username' => getenv('TH_TG_BOT_USER') ?: '',
        'webhook_secret' => getenv('TH_TG_WEBHOOK_SECRET') ?: 'dev-secret',
    ],
    'ted' => [
        'api_key' => getenv('TED_API_KEY') ?: '',
    ],
    'app' => [
        'base_url' => getenv('TH_BASE_URL') ?: 'http://localhost:8080',
        'fetch_concurrency' => 1,
        'notify_batch' => 200,
        // Comparable awards needed before a price range is shown on a tender.
        'award_min_sample' => (int)(getenv('TH_AWARD_MIN_SAMPLE') ?: 5),
    ],
    'billing' => [
        'stripe_secret_key' => getenv('TH_STRIPE_SECRET_KEY') ?: '',
        'stripe_webhook_secret' => getenv('TH_STRIPE_WEBHOOK_SECRET') ?: '',
        'currency' => 'usd',
        'sales_email' => getenv('TH_SALES_EMAIL') ?: 'hello@tendari.org',
        // Each paid plan needs recurring Stripe Price IDs (env: TH_STRIPE_PRICE_*).
        'plans' => [
            'free' => [
                'name' => 'Free',
                'price_monthly' => 0,
                'price_yearly' => null,
                'intelligence_monthly' => 0,
                'tagline' => 'See what Tendari finds.',
                'features' => [
                    'Browse all listings',
                    '1 saved search',
                    'Weekly email digest',
                    '48-hour delayed alerts',
                ],
            ],
            'starter' => [
                'name' => 'Starter',
                'price_monthly' => 15,
                'price_yearly' => 149,
                'intelligence_monthly' => 5,
                'tagline' => 'For solo bidders getting started.',
                'stripe_price_monthly' => getenv('TH_STRIPE_PRICE_STARTER_MONTHLY') ?: '',
                'stripe_price_yearly' => getenv('TH_STRIPE_PRICE_STARTER_YEARLY') ?: '',
                'features' => [
                    '5 saved searches',
                    'Daily email and Telegram alerts',
                    '1 region',
                    'Basic filters',
                    '5 tender intelligence views / month',
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'price_monthly' => 39,
                'price_yearly' => 390,
                'intelligence_monthly' => 15,
                'tagline' => 'For teams that bid regularly.',
                'featured' => true,
                'stripe_price_monthly' => getenv('TH_STRIPE_PRICE_PRO_MONTHLY') ?: (getenv('TH_STRIPE_PRICE_PRO') ?: ''),
                'stripe_price_yearly' => getenv('TH_STRIPE_PRICE_PRO_YEARLY') ?: '',
                'features' => [
                    'Unlimited saved searches',
                    'Real-time alerts',
                    'All regions',
                    'Tenders and funding in one feed',
                    'CSV export',
                    'Deadline reminders',
                    '15 tender intelligence views / month',
                    '3 seats',
                ],
            ],
            'business' => [
                'name' => 'Business',
                'price_monthly' => 99,
                'price_yearly' => null,
                'intelligence_monthly' => null,
                'tagline' => 'For organisations with bid teams.',
                'price_note' => 'From $99/mo',
                'stripe_price_monthly' => getenv('TH_STRIPE_PRICE_BUSINESS_MONTHLY') ?: '',
                'stripe_price_yearly' => '',
                'features' => [
                    '10 seats',
                    'Unlimited tender intelligence',
                    'Award and competitor data',
                    'Team workflows',
                    'API or webhooks',
                    'Translation',
                    'Priority support',
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'custom' => true,
                'intelligence_monthly' => null,
                'tagline' => 'For large or specialised teams.',
                'features' => [
                    'Custom sources',
                    'Unlimited tender intelligence',
                    'SLAs',
                    'Integrations',
                    'Invoicing',
                    'Dedicated support',
                ],
            ],
        ],
    ],
];
