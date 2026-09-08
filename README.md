# UHLMS

## University Homestay Management System

UHLMS is a Laravel-based management platform for operating a university homestay facility. It brings room inventory, guest reservations, check-in workflows, payments, operational reporting, and virtual tours into one system.

## What It Provides

- Room and room-type inventory management
- Guest reservation and reservation-status workflows
- Room assignments with automatic occupancy and availability updates
- Room holds for temporary or advance inventory protection
- Check-in records and guest identity details
- Charges, payment records, and online-payment integration points
- Staff administration through a Filament panel
- Reservation activity logs for operational accountability
- 360-degree virtual tours with waypoints and hotspots
- Guest-facing room browsing, reservation, tracking, and tour pages
- Role-based permissions and optional multi-factor authentication
- Automated notifications, background jobs, and security protections

## Technology

- PHP 8.2+
- Laravel 12
- Filament 3
- Livewire
- MySQL or SQLite
- Tailwind CSS and Vite
- Photo Sphere Viewer and Three.js for virtual tours
- PHPUnit and Playwright for automated testing

## Project Structure

```text
app/
  Filament/       Admin resources, pages, widgets, and relation managers
  Http/           Web controllers
  Livewire/       Public Livewire components
  Models/         Eloquent models and domain behavior
  Observers/      Auditing and state-synchronization side effects
  Policies/       Authorization rules
  Services/       Reservation, room-hold, check-in, and payment workflows
config/           Application configuration
 database/
  migrations/     Database schema history
  seeders/        Optional initial and development data
resources/
  css/            Tailwind and application styles
  js/             Vite entry points and virtual-tour code
  views/          Blade templates
routes/            Web and console routes
public/            Web entry point and built frontend assets
storage/           Runtime files, uploaded media, logs, and caches
tests/             PHPUnit and Playwright tests
docs/              Architecture, deployment, testing, and tour documentation
```

## Getting Started

### Requirements

Install the following before setup:

- PHP 8.2 or newer with the extensions required by Laravel
- Composer
- Node.js and npm
- MySQL or SQLite
- A web server or PHP's local development server

### Installation

1. Clone the repository and enter the project directory.
2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Install frontend dependencies:

   ```bash
   npm ci
   ```

4. Create a local environment file:

   ```bash
   cp .env.example .env
   ```

   On Windows PowerShell, use:

   ```powershell
   Copy-Item .env.example .env
   ```

5. Generate an application key:

   ```bash
   php artisan key:generate
   ```

6. Configure the local database and other services in `.env`.

7. Run the database migrations:

   ```bash
   php artisan migrate
   ```

8. Create the public storage link:

   ```bash
   php artisan storage:link
   ```

9. Build frontend assets:

   ```bash
   npm run build
   ```

For local development, `composer run dev` starts the application server, queue listener, log viewer, and Vite development server together.

## Testing and Quality Checks

Run the PHP test suite with:

```bash
./vendor/bin/phpunit
```

Run browser security tests with:

```bash
npm run test:browser-security
```

Check frontend dependency advisories with:

```bash
npm run audit:security
```

Format PHP files with Laravel Pint:

```bash
./vendor/bin/pint
```

## Security and Privacy

This repository documentation intentionally contains no credentials or private operational data.

- Never commit `.env`, API keys, payment secrets, webhook secrets, encryption keys, database passwords, or authentication tokens.
- Use `.env.example` only as a configuration template and replace every placeholder through a secure deployment process.
- Keep uploaded media, private files, backups, logs, and database contents outside public source control.
- Review deployment and tunnel scripts before using them in a new environment.
- Use test credentials for local payment development and production credentials only in a protected production environment.
- Apply least-privilege database and hosting permissions.
- Run dependency audits and keep Composer and npm lock files synchronized with dependency changes.

## Deployment Notes

Production deployments should install dependencies, run non-destructive migrations, cache the application configuration and routes, create the storage link, and build frontend assets. The included deployment scripts are convenience wrappers and should be reviewed against the target hosting environment before use.

Do not use `php artisan migrate:fresh` in a shared or production environment because it deletes existing database data.

## License

This project is maintained for its owning organization. Add the project's approved license and contribution policy here before publishing the repository publicly.
