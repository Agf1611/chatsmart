Shared hosting deployment notes

Requirements

- PHP 8.x with common Laravel extensions
- MySQL or MariaDB
- Node.js support on hosting, or SSH access to run the Node gateway
- ability to make `storage/` and `bootstrap/cache/` writable

Recommended layout

1. Upload and extract this package in the app directory on hosting.
2. Copy `.env.example` to `.env`.
3. Update `.env` at minimum:
   - `APP_URL=https://your-domain.example`
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `WA_URL_SERVER=https://node.your-domain.example`
   - `AUTH` with a long random string
   - `CORS_ALLOWED_ORIGINS`
4. Keep `APP_INSTALLED=false` until you finish the installer.

Web server

- If your hosting lets you set document root to `public/`, do that.
- If not, this package already includes a root `.htaccess` that forwards requests into `public/`.

Node app

- Preferred for cPanel/Passenger:
  - app root: this package folder
  - startup file: `server.js`
  - Node version: 18+ if available
- Install dependencies on hosting:
  - `npm ci --omit=dev`
- Start or restart the Node app from the hosting panel.
- This app now supports hosting-provided `PORT` automatically.

Laravel

- If composer is available, run:
  - `composer install --no-dev --optimize-autoloader`
- Ensure writable permissions:
  - `storage/`
  - `bootstrap/cache/`
- Recreate storage link on hosting:
  - `php artisan storage:link`

Finish install

1. Open `/install`
2. Fill database and admin account
3. After install, confirm login works
4. Rotate the temporary or initial admin password immediately

Important

- If your hosting does not support Node.js apps, WhatsApp gateway features will not run correctly.
- `node_modules` is intentionally excluded so packages are built natively on Linux.
- `.env` active file is intentionally excluded from this zip.
