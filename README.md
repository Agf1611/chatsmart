# WHASTAPP Hosting Package

Paket ini adalah source aplikasi Laravel + Node.js yang sudah dibersihkan untuk deployment mandiri.

## Kebutuhan

- PHP 8.x
- MySQL atau MariaDB
- Node.js 18+ di server/hosting
- Hosting yang mendukung aplikasi Node.js jika fitur gateway WhatsApp ingin berjalan

## Upload Ke Hosting

1. Upload file zip rilis hosting ke server lalu extract.
2. Copy `.env.example` menjadi `.env`.
3. Isi nilai penting di `.env`:
   - `APP_URL`
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `WA_URL_SERVER`
   - `AUTH`
   - `CORS_ALLOWED_ORIGINS`
4. Pastikan `APP_INSTALLED=false` sebelum menjalankan installer pertama kali.

## Setup Web

- Jika hosting mendukung custom document root, arahkan ke folder `public/`.
- Jika tidak, paket hosting sudah menyertakan root `.htaccess` yang akan meneruskan request ke `public/`.

## Setup Node.js

- Paket zip tidak menyertakan `node_modules`, jadi install di hosting:
```bash
npm ci --omit=dev
```
- Jalankan Node app dengan startup file `server.js`.
- Aplikasi sudah mendukung environment `PORT` dari hosting.

## Setup Laravel

- Jika composer tersedia, jalankan:
```bash
composer install --no-dev --optimize-autoloader
```
- Pastikan folder ini writable:
  - `storage/`
  - `bootstrap/cache/`
- Buat ulang symlink storage:
```bash
php artisan storage:link
```

## Instalasi Aplikasi

1. Buka `/install`
2. Isi konfigurasi database
3. Buat akun admin
4. Login ke dashboard
5. Segera ganti password admin

## Catatan Penting

- Jika hosting tidak mendukung Node.js, fitur WhatsApp gateway tidak akan berjalan normal.
- File `.env` aktif tidak disertakan dalam paket rilis.
- Zip rilis hosting ada di folder `dist/`.

Panduan lebih rinci tersedia di [DEPLOY-HOSTING.md](DEPLOY-HOSTING.md).
