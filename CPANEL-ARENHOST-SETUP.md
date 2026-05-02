# Panduan cPanel Aren Host

Panduan ini khusus untuk instalasi `ChatSmart / MPWA` di hosting yang memakai `cPanel + Node.js App`, seperti Aren Host.

Kasus error seperti:

```text
Cannot GET /whastapp/public/
```

berarti request web utama masuk ke **Node app**, padahal halaman dashboard harus masuk ke **Laravel `public/index.php`**.

Arsitektur yang benar harus dipisah:

- `Web Laravel` untuk dashboard/admin
- `Node.js Gateway` untuk WhatsApp runtime

## 1. Konsep URL Yang Benar

Contoh pemisahan yang aman:

- Web admin Laravel:
  - `https://wasickas.my.id`
  - atau `https://wasickas.my.id/whastapp/public`
- Node gateway:
  - `https://node-wasickas.my.id`

Jangan pakai satu URL yang sama untuk dua fungsi itu.

Kalau domain utama diarahkan ke Node app, hasilnya biasanya:

```text
Cannot GET /
```

atau:

```text
Cannot GET /whastapp/public/
```

## 2. Struktur Folder Yang Disarankan

Misalnya setelah upload source:

```text
/home/USER/whastapp
```

Isi project ada di folder itu, termasuk:

- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `server/`
- `server.js`
- `artisan`

## 3. Source Yang Dipakai

Untuk instalasi baru dan update GitHub, gunakan source dari folder repo:

`C:\xampp\htdocs\whastapp\chatsmart`

## 4. Upload Ke Hosting

1. Compress source `chatsmart` menjadi zip.
2. Upload zip ke hosting lewat File Manager.
3. Extract ke folder target, misalnya:
   - `/home/USER/whastapp`
4. Pastikan file seperti `server.js`, `artisan`, `public/index.php`, dan `.env.example` ada.

## 5. Setup Domain Web Laravel

Bagian ini untuk dashboard/admin aplikasi.

### Opsi A: Hosting mendukung document root ke `public/`

Gunakan ini jika tersedia.

Set domain/subdomain web ke:

- document root: `/home/USER/whastapp/public`

Lalu akses web lewat:

- `https://domain-kamu/`

Tanpa menulis `/public` lagi.

### Opsi B: Hosting tidak mendukung document root custom

Kalau document root tidak bisa diarahkan ke folder `public/`, gunakan app root biasa:

- `/home/USER/whastapp`

Lalu akses web lewat:

- `https://domain-kamu/public`

atau:

- `https://domain-kamu/whastapp/public`

sesuai lokasi folder project kamu.

Catatan:

- root `.htaccess` project sudah membantu untuk banyak kasus
- tetapi cara paling aman tetap document root langsung ke `public/`

## 6. Setup Node.js App Di cPanel

Bagian ini untuk gateway WhatsApp.

Di menu `Setup Node.js App` atau menu sejenis:

1. Create Application
2. Isi:
   - `Node.js version`: `18+`
   - `Application mode`: `Production`
   - `Application root`: `/home/USER/whastapp`
   - `Application URL`: pakai subdomain khusus Node, misalnya:
     - `node-wasickas.my.id`
   - `Application startup file`: `server.js`
3. Save/Create

Setelah dibuat:

4. Jalankan:
   - `npm install`
   - atau `npm ci --omit=dev`
5. Restart Node app

## 7. Isi File `.env`

Copy:

```bash
.env.example -> .env
```

Lalu isi minimal seperti ini:

```env
APP_NAME=ChatSmart
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wasickas.my.id

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

TYPE_SERVER=hosting
PORT_NODE=3100

WA_URL_SERVER=https://node-wasickas.my.id
CORS_ALLOWED_ORIGINS=https://wasickas.my.id

AUTH=ganti_dengan_token_rahasia
APP_INSTALLED=false
```

Kalau web kamu masih di subfolder, contoh:

```env
APP_URL=https://wasickas.my.id/whastapp/public
WA_URL_SERVER=https://node-wasickas.my.id
CORS_ALLOWED_ORIGINS=https://wasickas.my.id
```

Catatan penting:

- `APP_URL` = alamat dashboard Laravel
- `WA_URL_SERVER` = alamat Node gateway
- `CORS_ALLOWED_ORIGINS` = domain web yang diizinkan mengakses Node
- `AUTH` harus random dan jangan pakai contoh bawaan

## 8. Install Dependency

Masuk ke terminal hosting atau SSH, lalu jalankan dari folder project:

```bash
composer install --no-dev --optimize-autoloader
npm ci --omit=dev
php artisan key:generate
php artisan storage:link
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

Kalau `npm ci` gagal karena lockfile atau batas hosting:

```bash
npm install --omit=dev
```

## 9. Permission Folder

Pastikan folder berikut writable:

- `storage/`
- `bootstrap/cache/`

Jika hosting menyediakan menu permission, biasanya:

- folder: `755` atau `775`
- file: `644`

## 10. Jalankan Installer

Sesudah web Laravel siap:

1. buka:
   - `https://wasickas.my.id/install`
   - atau path install sesuai folder project
2. pilih `Mode Sederhana`
3. isi database yang sudah dibuat dari panel hosting
4. buat akun admin
5. submit installer, migrasi database akan berjalan otomatis dan install lock dibuat
6. login

## 11. Cara Cek Apakah Web dan Node Sudah Benar

### Cek web Laravel

Kalau benar, membuka URL web akan menampilkan:

- halaman install
- atau halaman login

Bukan:

```text
Cannot GET /
```

### Cek Node gateway

Buka:

```text
https://node-wasickas.my.id/backend-getgroups
```

kalau di browser biasa bisa tampil error method/token, itu normal. Yang penting subdomain Node hidup.

## 12. Arti Error Umum

### Error: `Cannot GET /whastapp/public/`

Penyebab:

- URL web masuk ke Node app

Perbaikan:

- pisahkan domain web dan domain node
- arahkan domain web ke Laravel `public/`
- pakai subdomain khusus untuk Node

### Error: halaman putih / 500

Penyebab umum:

- `.env` belum benar
- database belum cocok
- permission `storage/` salah
- `composer install` belum selesai

### Error: QR / device tidak connect

Penyebab umum:

- Node app belum running
- `WA_URL_SERVER` salah
- `CORS_ALLOWED_ORIGINS` tidak cocok
- port/environment Node belum aktif

## 13. Checklist Instalasi Aren Host

Checklist singkat:

1. Upload source project
2. Extract project
3. Buat `.env`
4. Isi `APP_URL`
5. Isi `WA_URL_SERVER`
6. Isi `CORS_ALLOWED_ORIGINS`
7. Isi database
8. Jalankan `composer install`
9. Jalankan `npm ci --omit=dev`
10. Jalankan `php artisan key:generate`
11. Jalankan `php artisan storage:link`
12. Buat Node.js App
13. Set startup file `server.js`
14. Restart Node app
15. Buka `/install`
16. Login dashboard

## 14. Rekomendasi Struktur Final

Paling aman:

- `Web Laravel`:
  - `https://wasickas.my.id`
- `Node Gateway`:
  - `https://node-wasickas.my.id`

Dengan ini:

- dashboard tidak bentrok dengan Node
- API internal WhatsApp lebih jelas
- troubleshooting lebih mudah

## 15. Setelah Berhasil Install

Segera lakukan ini:

1. ganti password admin
2. ganti token `AUTH`
3. isi API key bila memakai AI bot
4. test login
5. test scan / connect device
6. test kirim pesan
7. test auto reply

## 16. File Panduan Terkait

- [README.md](README.md)
- [DEPLOY-HOSTING.md](DEPLOY-HOSTING.md)
