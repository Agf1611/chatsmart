# ChatSmart Source Package

Paket ini adalah source aplikasi Laravel + Node.js yang sudah dibersihkan untuk deployment mandiri.

## Struktur Kerja

- Folder kerja lokal aktif saat ini: `C:\xampp\htdocs\whastapp`
- Folder source utama yang terhubung ke GitHub: `C:\xampp\htdocs\whastapp\chatsmart`

Jika ada perubahan baru di folder kerja utama, sinkronkan ke folder GitHub dengan:

```powershell
powershell -ExecutionPolicy Bypass -File tools\sync-to-chatsmart.ps1
```

Atau:

```bat
tools\sync-to-chatsmart.bat
```

Script sinkronisasi akan menyalin source aplikasi ke `chatsmart` tanpa membawa file sensitif dan runtime lokal seperti `.env`, `credentials`, `node_modules`, dan log/cache sementara.

Jika ingin sinkronisasi otomatis saat ada file berubah:

```powershell
powershell -ExecutionPolicy Bypass -File tools\start-chatsmart-sync-watcher.ps1
```

Untuk menghentikannya:

```powershell
powershell -ExecutionPolicy Bypass -File tools\stop-chatsmart-sync-watcher.ps1
```

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
5. Jika `APP_KEY` masih kosong, aplikasi sekarang akan membuatnya otomatis saat pertama kali dibuka atau saat installer dijalankan.

## Setup Web

- Jika hosting mendukung custom document root, arahkan ke folder `public/`.
- Jika tidak, paket hosting sudah menyertakan root `.htaccess` generik yang akan meneruskan request ke `public/` tanpa hardcode nama folder project seperti `/whastapp`.

## Setup Node.js

- Paket zip tidak menyertakan `node_modules`, jadi install di hosting:
```bash
npm ci --omit=dev
```

## Cloudflare Tunnel

- Cloudflare Tunnel cocok untuk aplikasi ini jika web Laravel dan runtime Node dipublikasikan sebagai hostname terpisah.
- Contoh:
  - `https://app.domainkamu.com` -> `http://127.0.0.1:80` atau service web lokal
  - `https://node.domainkamu.com` -> `http://127.0.0.1:3100`
- Untuk `WA_URL_SERVER`, gunakan URL publik tunnel Node seperti `https://node.domainkamu.com` tanpa menambahkan port lokal `:3100`.
- Untuk setup yang lebih stabil, pisahkan:
  - `WA_URL_SERVER_PUBLIC=https://node.domainkamu.com`
  - `WA_URL_SERVER_INTERNAL=http://127.0.0.1:3100`
- Session WhatsApp sekarang default disimpan di `storage/app/wa-sessions`, jadi lebih aman untuk Linux hosting daripada folder root project.
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
- Folder session WhatsApp ikut memakai `storage/app/wa-sessions`, jadi permission `storage/` yang benar biasanya sudah cukup.
- Buat ulang symlink storage:
```bash
php artisan storage:link
```

## Instalasi Aplikasi

1. Buka `/install`
2. Pilih `Mode Sederhana`
3. Isi konfigurasi database yang sudah dibuat di panel hosting
4. Buat akun admin
5. Installer akan otomatis menjalankan migrasi database dan membuat install lock
6. Login ke dashboard
7. Segera ganti password admin

## Catatan Penting

- Jika hosting tidak mendukung Node.js, fitur WhatsApp gateway tidak akan berjalan normal.
- File `.env` aktif tidak disertakan dalam paket rilis.
- Zip rilis hosting ada di folder `dist/`.

Panduan lebih rinci tersedia di [DEPLOY-HOSTING.md](DEPLOY-HOSTING.md).

Jika deploy memakai cPanel Aren Host atau hosting serupa yang memisahkan web Laravel dan Node app, ikuti panduan khusus:

- [CPANEL-ARENHOST-SETUP.md](CPANEL-ARENHOST-SETUP.md)
