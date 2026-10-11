# Bills

A small Laravel app that keeps your utility bills (electricity, water, gas, landline, MCI, Irancell, Rightel),
fetches them from a bill API provider on a schedule, and shows the **Bill ID** and **Payment ID** so you can pay.
It also has an API gateway at `/api` (manual: [reference-docs/api-usage.md](reference-docs/api-usage.md)).

Live site: https://bills.kimiasoft.com (home page is "under construction" on purpose; login is `/mainadmin`).

## Design rules (this app should live 7+ years)

- Laravel + Blade + **plain CSS and a few lines of vanilla JS**. No npm, no build step, no UI library.
- No extra backend packages. Only `laravel/framework` and `laravel/tinker`.
- Icons are inline SVG (`resources/views/partials/icon.blade.php`).
- English only.

## Pages

| URL | Who | What |
|---|---|---|
| `/` | everyone | Under construction page |
| `/mainadmin` | everyone | Login |
| `/dashboard`, `/bills/...` | users | Own bills only (users are isolated from each other) |
| `/account` | users | Password, API token (create / rotate / remove). No token by default |
| `/admin/providers` | admin | Which provider serves which bill type + fallback order, provider settings (API token) |
| `/admin/users` | admin | Add users, reset passwords, remove API tokens |
| `/api/v1/...` | token | API gateway and saved bills |

## Bill providers (provider agnostic)

All provider code is in `app/BillProviders/`. The app never talks to a provider directly. It uses `App\Services\BillGateway`.

**To add a new provider (for example Ghabzino), add one PHP file. Nothing else changes.**

1. Copy `app/BillProviders/ZohalProvider.php` to `app/BillProviders/GhabzinoProvider.php`.
2. Change `key()`, `name()`, `supportedTypes()`, `settingsFields()` and `inquire()`.
   `inquire()` returns a list of `App\Support\BillData` (empty list = no payable bill) or throws
   `ProviderException::unavailable()` (try the next provider) / `ProviderException::invalidInput()` (stop).
3. Open **Admin > Providers**, enter the provider settings, tick the bill types it serves, set the order.

The file is found automatically (any file named `*Provider.php`). Mapping and order are stored in the database
(`provider_bill_types`). Settings such as API tokens are stored encrypted (`provider_settings`).

If a caller does not choose a provider, the gateway uses the admin order and **falls back** to the next provider when one is unavailable.

To add a new bill type: add an entry in `app/Support/BillTypes.php` and an icon in `partials/icon.blade.php`.

## Automatic fetching

`php artisan bills:fetch-due` runs every 10 minutes (Laravel scheduler) and fetches up to 5 bills whose
`next_fetch_at` has come. Each bill has its own period (manual, daily, 3 days, weekly, 15 days, monthly, 2 months).
After an error it retries in 6 hours. Cron entry needed on the server:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan bills:user mainadmin --admin          # asks for the password
php artisan bills:provider-set zohal token         # asks for the token
php artisan serve
php artisan test
```

## Commands

| Command | What |
|---|---|
| `bills:user {username} [--admin] [--password=]` | Create a user or change a password |
| `bills:provider-set {provider} {name} [value]` | Set a provider setting (for example the Zohal `token`) |
| `bills:fetch-due [--limit=5]` | Fetch due bills now |

## Deploy (ger1, `bills.kimiasoft.com`)

- App dir: `/mnt/ger_hd1/www/bills/app`, web root `app/public`, PHP 8.4 (PHP-FPM), MySQL database `bills` / user `bills`.
- Secrets (`.env`, DB password, Zohal token) exist **only on the server**. They are never in git.
- **Do not upload `reference-docs/` or any `*.md` file** to the server.
- Zohal answers only requests from IPs registered in the Zohal panel. Register the server IP `162.55.167.140`.

```bash
# from the project root
tar czf /tmp/bills.tgz --exclude=.git --exclude=vendor --exclude=node_modules --exclude=.env \
  --exclude='storage/logs/*' --exclude='bootstrap/cache/*.php' --exclude=database/database.sqlite \
  --exclude=reference-docs --exclude='*.md' .
# copy to the server, extract into app/, then:
composer install --no-dev --optimize-autoloader
php artisan migrate --force --seed && php artisan config:cache && php artisan route:cache && php artisan view:cache
```
