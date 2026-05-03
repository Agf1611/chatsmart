# Deploy Hosting

## Source yang Dipakai

Untuk rilis, instalasi baru, dan update lanjutan, gunakan source dari folder:

`C:\xampp\htdocs\whastapp\chatsmart`

Jika deploy memakai cPanel Aren Host atau hosting yang memisahkan Laravel web dan Node app, baca juga:

- [CPANEL-ARENHOST-SETUP.md](CPANEL-ARENHOST-SETUP.md)

Jika pengembangan masih dilakukan di folder induk `whastapp`, sinkronkan dulu perubahan terbaru ke folder `chatsmart` memakai:

```powershell
powershell -ExecutionPolicy Bypass -File tools\sync-to-chatsmart.ps1
```

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
9. Pilih `Mode Sederhana`, lalu isi database yang sudah dibuat di panel hosting.
10. Saat submit installer, aplikasi akan otomatis menjalankan migrasi database dan membuat install lock.

## Rekomendasi cPanel / Shared Hosting

- App root: folder project hasil extract
- Startup file Node: `server.js`
- Node version: `18+`
- Document root web: `public/` jika hosting mendukung
- Jika document root tidak bisa diubah, root `.htaccess` akan meneruskan request ke `public/` tanpa memaksa subfolder `/whastapp`
- Pakai subdomain terpisah untuk Node gateway

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
