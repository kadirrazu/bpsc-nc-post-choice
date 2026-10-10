# BPSC Choice Taking System

An English-interface application for Bangladesh Public Service Commission (BPSC) to manage choice events, import candidates and collect ordered preferences, with receipts and administrative exports. Built with Laravel, MySQL and Tabler; times use Asia/Dhaka (UTC+06:00).

## Install

Requirements: PHP 8.3+, Composer, MySQL and Node.js 20.19+ / 22.12+. Enable Composer-required PHP extensions, including pdo_mysql; tests require pdo_sqlite.

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
```

Create a MySQL database and set APP_URL and DB_* credentials in `.env`, then run:

```bash
php artisan migrate
php artisan db:seed --class=DesignationSeeder
php artisan db:seed --class=DefaultAdminUserSeeder
npm install
npm run build
php artisan serve
```

Open http://127.0.0.1:8000. Initial administrator: `testuser@gmail.com` / `12345678`; change the password after setup. Run the administrator seeder only once: rerunning it resets this account's password. PDFs require Times New Roman (times.ttf and timesbd.ttf in storage/app/receipt-fonts, or Windows Fonts); Bengali choice titles use the bundled Nikosh font.

## Development

Run in separate terminals:

```bash
php artisan serve
npm run dev
```

Verification and maintenance:

```bash
php artisan test
node --test tests/ui/choice-selection-state.test.mjs
npm run build
php artisan optimize:clear
```

After changes, run migrations only when new migrations are included; rebuild assets when frontend source changes. Keep the existing `.env` and APP_KEY.
