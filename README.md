# BPSC Choice Taking System

A simple application for Bangladesh Public Service Commission (BPSC) to manage choice events, import eligible candidates, and collect post preferences.

Built with **Laravel 13, MySQL and Tabler**. The interface is in English; all dates and times use **Asia/Dhaka (UTC+06:00)**.

## Current features

- Event, post and choice management with Draft, Published, Archived and Cancelled statuses.
- Candidate CSV import with validation, preview and confirmation.
- Leading-zero registration preservation and birth-date normalization.
- Archive browsing and administrator-controlled deletion.

Candidate submission, receipts, multiple-post matching, XLS/XLSX import and PDF/XLSX/DBF exports are planned for the next phases.

## Requirements

PHP **8.3+**, Composer, MySQL, and Node.js **20.19+ or 22.12+** compatible with Vite. Enable the PHP extensions required by Composer, including `pdo_mysql`; tests also require `pdo_sqlite`.

## Installation

Extract the project, apply the supplied patches in order, and open a terminal in the project root.

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
```

Create a MySQL database, then update `.env`:

```dotenv
APP_NAME="BPSC Choice Taking System"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bpsc_nc_post_choice
DB_USERNAME=root
DB_PASSWORD=
CHOICE_BIRTH_YEAR_PIVOT=30
```

Use your own database credentials. Complete setup:

```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=DesignationSeeder
php artisan db:seed --class=DefaultAdminUserSeeder
npm install
npm run build
```

Initial administrator: **testuser@gmail.com / 12345678**. Set a new password before real use. Run the administrator seeder only for initial setup; rerunning it resets this account's password.

## Run locally

```bash
php artisan serve
```

Open **http://127.0.0.1:8000**. Use **Administrator Sign In** to manage events.

For frontend development, run `npm run dev` in a second terminal. To verify the application, run `php artisan test`.

## Basic workflow

**Create a Draft event → Add choices → Import / View Candidates → Preview → Confirm → Publish.**

Single-post CSV import is available for current Draft and Published events before submissions exist. Download the import template from the Candidates page. Expired events appear in Archive.

## Apply future patches

Back up the project and database, replace the supplied files, then follow that patch's instructions. Run `php artisan optimize:clear`; run migrations and rebuild assets when required. Keep the existing `.env` and application key.
