# Tendari 🎯

Aggregates tender notices & funding opportunities from public procurement sources across
Africa and the Middle East into one searchable feed, with personalized alerts via **email**
(PHPMailer/SMTP) or **Telegram**.

## Stack

- **PHP 8.2+** (plain PHP, no framework), PDO + **SQLite** (WAL, FTS5)
- **Bulma 1.0 + HTMX + vanilla JS**; assets vendored under `public/assets/vendor/`
- **Stripe** (`stripe/stripe-php`) for subscriptions; **PHPMailer** for email
- **Cron jobs** for fetch / notify / digest / dedupe / health / documents
- **i18n**: `src/Core/I18n.php`, string maps in `lang/en.php` + `lang/ar.php`, RTL layout
  (`dir="rtl"`), locale stored in the `td_lang` cookie (`/lang/en` · `/lang/ar`)


## Setup

```bash
php cron/migrate.php            # create storage/app.sqlite + schema
php -S localhost:8080 -t public # dev server
```

Composer is optional: `bootstrap.php` loads PHPMailer from `lib/phpmailer/` when
`vendor/autoload.php` is absent. Run `composer install` only if you prefer to manage
PHPMailer updates that way.

### Deploying on nginx

Production uses **nginx + php-fpm** (no Apache `.htaccess`). The repo holds a server-block
**template** in [`deploy/nginx.conf`](deploy/nginx.conf); it is not applied automatically.
Run the one-time provisioning script **on the VPS** to install it (domain, app path and PHP
socket are substituted), create the deploy user, add cron jobs and optionally issue TLS:

```bash
# from a checkout of this repo on the server, as root:
sudo ./deploy/setup-vps.sh --domain tendari.org --app-dir /var/www/tendari \
     --user deploy --php 8.3 --email you@example.com
```

Re-run any time; it is idempotent for the nginx block and env block. Then push to `main` (or
run the workflow) so the workflow's `VPS_DEPLOY_PATH` matches `--app-dir`. If you prefer to
wire it manually, the Nginx server block is:

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/tendari.conf
sudo ln -s /etc/nginx/sites-available/tendari.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Key points: document root **must** be `public/` (so `storage/`, `config/`, `src/` are not
web-accessible), `fastcgi_pass` must point at your PHP-FPM socket/port, and the config sets
`application/manifest+json` for `.webmanifest`, cached static assets, and denies dotfiles.

### GitHub Actions deploy → VPS

`.github/workflows/deploy.yml` runs the test suite and, on a push to `main` (or manual
dispatch), rsyncs the repo to the VPS and runs migrations. Add these repository secrets
(Settings → Secrets and variables → Actions):

| Secret | Example | Purpose |
| --- | --- | --- |
| `VPS_HOST` | `203.0.113.10` | Server IP or hostname |
| `VPS_USER` | `deploy` | SSH user (member of the PHP-FPM group) |
| `VPS_PORT` | `22` | SSH port (optional, defaults to 22) |
| `VPS_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----…` | Private key for `VPS_USER` |
| `VPS_DEPLOY_PATH` | `/var/www/tendari` | Directory containing `public/` |
| `SITE_URL` | `https://tendari.org` | Optional post-deploy health check |
| `VPS_KNOWN_HOSTS` | output of `ssh-keyscan -H your.server` | Optional pinned host key (recommended; otherwise the workflow runs `ssh-keyscan` on first deploy) |

The workflow:
1. runs `composer install` + `php tests/run.php` (fails the deploy if tests fail),
2. syncs files with rsync, **preserving** `storage/app.sqlite*`, `storage/cache`, `storage/logs`
   and `storage/tenders` (plus any server-side `.env`),
3. runs `php cron/migrate.php` and fixes `storage/` permissions on the server.

HTTPS must be enabled for the domain in `SITE_URL` (a certificate for a different
hostname makes the health check warn). With certbot:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tendari.org -d www.tendari.org
```

The health-check step is **non-fatal** (`continue-on-error`): a TLS/DNS problem is reported
as a workflow warning with certificate diagnostics but does not roll back a successful
deploy. Set `SITE_URL` to `http://…` (or leave it unset) until HTTPS is configured.

Configure PHP-FPM environment variables from [`deploy/php-fpm.env.example`](deploy/php-fpm.env.example)
(the app reads config via `getenv()`, not a `.env` file). Ensure the PHP-FPM user can write to
`storage/` — either deploy as that user or set the `storage/` group writable and add the deploy
user to it. If your PHP version/socket differs, adjust `fastcgi_pass` in `deploy/nginx.conf`
and the optional FPM reload line in the workflow.

### Environment variables (secrets — never commit)

| Var | Purpose |
| --- | --- |
| `TH_SMTP_HOST` / `TH_SMTP_PORT` / `TH_SMTP_USER` / `TH_SMTP_PASS` / `TH_SMTP_FROM` | SMTP for outgoing mail |
| `TH_TG_TOKEN` / `TH_TG_BOT_USER` | Telegram bot token & username (Telegram UI hidden when unset) |
| `TH_TG_WEBHOOK_SECRET` | Secret path segment for `POST /telegram/webhook/{secret}` |
| `TH_BASE_URL` | e.g. `https://tendari.org` (used in emails/deep links) |
| `TH_RW_APPNAME` | ReliefWeb API appname (must be approved; request at apidoc.reliefweb.int) |
| `TED_API_KEY` | TED API key for the `ted` connector |
| `TH_STRIPE_SECRET_KEY` | Stripe secret key (`sk_...`) — enables Stripe Checkout on `/pricing` |
| `TH_STRIPE_PRICE_STARTER_MONTHLY` / `TH_STRIPE_PRICE_STARTER_YEARLY` | Stripe Price IDs (`price_...`) for Starter monthly / yearly |
| `TH_STRIPE_PRICE_PRO_MONTHLY` / `TH_STRIPE_PRICE_PRO_YEARLY` | Stripe Price IDs for Pro monthly / yearly (`TH_STRIPE_PRICE_PRO` still works as the monthly alias) |
| `TH_STRIPE_PRICE_BUSINESS_MONTHLY` | Optional Stripe Price ID for Business (otherwise plans show "Contact sales") |
| `TH_SALES_EMAIL` | Where Business / Enterprise enquiries go (default `hello@tendari.org`) |
| `TH_STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret for `POST /stripe/webhook` |
| `TH_AWARD_MIN_SAMPLE` | Comparable award amounts required before a price range is shown (default `5`) |

`POST /stripe/webhook` receives Stripe subscription events (signature-verified, CSRF-exempt).

## Landing / billing / waiting list

- `/` — marketing landing page (hero, features, pricing teaser, waiting list form).
- `POST /waitlist` — stores emails in the `waiting_list` table (deduplicated).
- `/pricing` — plan cards; when Stripe env vars are set, Subscribe buttons create
  Stripe Checkout sessions (`POST /checkout`), confirmed on `/billing/success`.
- Webhook: add an endpoint in the Stripe dashboard pointing at
  `https://tendari.org/stripe/webhook` with events
  `checkout.session.completed`, `customer.subscription.updated`,
  `customer.subscription.deleted`.

## How tenders get here (and what costs money)

Tendari is a **pull-based aggregator**, not a reseller of portal access:

- **Connectors** run from cron (`cron/fetch.php`) every 30 minutes: public JSON APIs
  (World Bank, Kenya PPIP, ReliefWeb, OCHA FTS, TED), public HTML scrapers (AfDB, EBRD,
  SA eTenders) and registration-gated stubs for portals that require a supplier login.
- Each record is **normalized** (dates, countries, funding type) then **deduplicated**
  across sources by URL and by a title+deadline+organisation signature, and indexed in
  SQLite full-text search.
- `cron/notify.php` matches new records against saved searches and sends email/Telegram;
  `cron/documents.php` mirrors PDFs and caches source pages; `cron/digest.php` sends daily
  digests. The feed is therefore **live and refreshed automatically**, not manually curated.
- **Registration:** browsing is free on every supported portal. A supplier account is needed
  only to *bid* on most portals (and some require a digital certificate or tax documents).
- **Subscription:** a few portals charge for participation — Etimad (paid supplier
  subscription), Tanzania (foreign-supplier annual fee), and some UAE entities
  (prequalification fees). Tendari itself has free and paid plans; see `/pricing`.
- Full per-portal detail: [`docs/tender-portals.md`](docs/tender-portals.md).

## Cron

```cron
*/5 * * * *  php /path/to/cron/notify.php     # match + dispatch notifications
*/30 * * * * php /path/to/cron/fetch.php      # run due connectors
17 6 * * *   php /path/to/cron/deduper.php    # re-signature last 7 days; close expired
0 7 * * *    php /path/to/cron/digest.php     # daily digest emails (07:00 per user tz)
*/30 * * * * php /path/to/cron/health.php     # probe all source endpoints (exit 1 on failure)
*/15 * * * * php /path/to/cron/documents.php  # mirror tender documents + cache source pages
php /path/to/cron/migrate.php                 # apply new migrations
```

`cron/health.php` checks every source's endpoint is live and unchanged (HTTP status,
JSON shape) and records the results shown on `/admin/health`. It exits non-zero when a
problem is found, so external monitors can alert on it. The admin area
(`/admin/sources`, `/admin/health`, `/admin/logs`, `/admin/users`) also offers on-demand
probes, a go-live checklist (SMTP/Ted/Telegram/base-url presence) and a daily log viewer.

All CLI scripts refuse web execution (`PHP_SAPI === 'cli'` guard).

## Sources

API connectors (no auth): `worldbank` (search.worldbank.org/api/v2/procnotices), `fts`
(OCHA HPC.tools flow API), `ted` (needs API key). `reliefweb` needs an approved appname
(`TH_RW_APPNAME`). HTML scrapers: `afdb`, `ebrd`, `ke_tenders`, `za_etenders` (+ more in
`PendingConnector` until implemented). Sources requiring registration (`isdb`,
`sa_etimad`, `sa_aramco`, `ae_adgpg`, `ae_dubai_esupply`, `ae_federal`, `jo_joneps`,
`tz_taneps`) throw `RegistrationRequiredException` — visible in the admin UI; enable them
later by storing cookies/tokens in `sources.config`.

The researched hub list — 18 portals, licensing, scraping status and supplier registration
steps — lives in [`docs/tender-portals.md`](docs/tender-portals.md). Estimated coverage
shown on the landing page: **~190,000 notices/year across 18 portals in 13 countries**.

Enable sources under `/admin/sources` (admin account required). Raw payloads are cached in
`storage/cache/` per source for parser debugging.

Tender documents are mirrored into `storage/tenders/{tenderId}/` and served from
`/tenders/{id}/files/{fileId}` (PDFs inline, everything else as a download). Connectors can
return a `documents` array; the documents job also scrapes detail pages for PDF/DOC/XLS/ZIP
links and, when a portal exposes no files (or renders with JavaScript), stores a cached copy
of the source page so the “source” link is never blank.

## Bid intelligence (awards & price ranges)

Awards are collected from day one, alongside notices, so the intelligence feature needs only
data and search — no model:

- **Ingestion.** `cron/fetch.php` mirrors every notice whose status is `awarded` into the
  `awards` table. Connectors can return richer records (winner, amount, award date) by
  implementing `AwardsConnectorInterface`; the World Bank connector now also fetches
  `Contract Award` notices. Awards are deduplicated by source reference (or URL + title) and
  indexed in `awards_fts`. `php cron/backfill_awards.php` mirrors any awarded notices already
  in the database (safe to re-run).
- **Similar past awards.** Each tender page ranks comparable awards by shared sectors,
  countries and title tokens, and shows buyer, winner, amount, award date and a source link.
- **Price ranges.** Once at least `TH_AWARD_MIN_SAMPLE` (default 5) comparable amounts exist,
  the page shows per-currency P25–P75 "safe range", median, min/max and where the tender's own
  value sits. Sector scope is tried first, then country scope; below the threshold the page
  says how many awards are still needed instead of guessing.
- **Deliberately simple.** Bands are nearest-rank quantiles over public award notices. They
  are labelled indicative and should always be checked against the tender documents.
- **Plan gating.** Intelligence is a paid feature and covers both similar awards and price
  ranges: Free is locked, Starter unlocks 5 tenders/month, Pro 15, Business/Enterprise
  unlimited. The quota is distinct tenders per UTC calendar month (`intelligence_usage`);
  re-opening the same tender in the same month is free, and tenders with no comparable data
  do not consume a view. Configure the allowances with `intelligence_monthly` on each plan in
  `config/config.php`; admins bypass the limit.

Roadmap: more award sources (TED/TED eForms, national portals), then a sector-level model once
there are a few thousand awards, and BOQ parsing/costing for construction and supply tenders.

## Matching & notifications

Saved searches (keywords AND-semantics, quoted phrases, excludes, countries, sectors,
funding types, deadline window) are matched against new tenders every 5 minutes.
Email is grouped per user (max 20 tenders per mail); Telegram messages are per tender.
Failed sends retry up to 3 attempts, visible in `/settings/notifications`.

Telegram linking: `/settings` → `https://t.me/{bot}?start={token}` → bot webhook handles
`/start <token>` (binds chat), `/stop`, `/status`. Webhook URL:
`https://{host}/telegram/webhook/{TH_TG_WEBHOOK_SECRET}`.

## Tests

```bash
php tests/run.php    # plain asserts; runs against a temp SQLite DB
php -l <file>        # lint any file
```

## Project layout

```
public/   front controller + assets        src/Core/   App, Db, Auth, Config, Validator
src/Connectors/  API + Web connectors      src/Services/    Normalizer, Deduper, MatchEngine, AwardService…
src/Notifiers/   Email (SMTP) + Telegram   templates/  PHP templates (layout, pages, partials)
cron/      migrate, fetch, notify, deduper, digest
storage/   app.sqlite, migrations/, logs/, cache/
tests/     run.php + fixtures/
```
