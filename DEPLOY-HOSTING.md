# Deploy Hosting

## Paket Yang Dipakai

Gunakan file zip rilis bersih dari folder `dist/`.

## Langkah Cepat

1. Upload zip ke hosting.
2. Extract ke folder aplikasi.
3. Copy `.env.example` menjadi `.env`.
4. Isi:
   - `APP_URL=https://domain-kamu`
   - `WA_URL_SERVER=https://subdomain-node-kamu`
   - `DB_HOST`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `AUTH`
   - `CORS_ALLOWED_ORIGINS`
5. Jalankan:
```bash
composer install --no-dev --optimize-autoloader
npm ci --omit=dev
php artisan storage:link
```
6. Pastikan permission `storage/` dan `bootstrap/cache/` bisa ditulis.
7. Start aplikasi Node dengan startup file `server.js`.
8. Buka `/install` lalu selesaikan wizard.

## Rekomendasi cPanel / Shared Hosting

- App root: folder project hasil extract
- Startup file Node: `server.js`
- Node version: `18+`
- Document root web: `public/` jika hosting mendukung

## Sesudah Install

- Set `APP_INSTALLED=true` akan diisi otomatis oleh installer
- cek login admin
- ganti password admin
- rotasi `AUTH` bila sebelumnya memakai nilai contoh

## Isi Paket Rilis

- source Laravel
- source Node gateway
- `vendor/`
- `.env.example`
- panduan deploy

Yang tidak ikut:

- `.env` aktif
- `node_modules`
- cache, session, log runtime
- file upload/contoh lama
