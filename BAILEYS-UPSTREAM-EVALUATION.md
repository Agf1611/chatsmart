# Baileys Upstream Evaluation

Tanggal evaluasi: 2026-05-07

## Kondisi awal

- Dependency project sebelumnya di `package.json`:
  - `@whiskeysockets/baileys: github:laxeder/Baileys#fix-list-type`
- Hasil cek runtime awal:
  - package terpasang melaporkan versi `6.5.0`
  - `package-lock.json` mengunci ke commit fork:
    - `laxeder/Baileys#0793ebb471fff17099b9d7ccca63b44f15092265`
- Artinya, project awalnya tidak memakai upstream resmi langsung, dan juga tidak berada di versi upstream yang baru.

## Temuan teknis

- Masalah logout yang paling nyata di project ini lebih banyak berasal dari lifecycle session dan operasi file credential, bukan semata-mata versi library.
- Sebelum refactor, source gateway:
  - membaca dua root session sekaligus
  - bisa fallback balik ke path legacy
  - menganggap folder kosong sebagai session aktif
  - terlalu agresif menghapus credential pada beberapa disconnect non-final
- Refactor yang sudah diterapkan sekarang:
  - satu root aktif untuk session
  - migrasi legacy hanya satu arah ke root aktif
  - deteksi session aktif berbasis `creds.json`, bukan sekadar ada folder
  - `badSession` dan `multideviceMismatch` tidak lagi langsung menghapus auth state

## Sinyal dari upstream resmi

- Repository resmi `WhiskeySockets/Baileys` menandai adanya breaking changes besar sejak `7.0.0`.
- Halaman release resmi yang saya cek menunjukkan lini terbaru di upstream saat ini adalah `v7.0.0-rc.9`.
- Release notes upstream juga menyebut perbaikan penting yang relevan untuk kasus kita:
  - reliability socket
  - LID support dan migrasi session
  - message retry
  - mutex-based signal key store safety

## Rekomendasi migrasi

Jangan lompat langsung dari fork `6.5.0` ke upstream `7.0.0-rc.x` di produksi.

Urutan yang saya rekomendasikan:

1. Stabilkan dulu lifecycle session pada source saat ini.
2. Pindah dari fork `laxeder` ke upstream resmi sambil tetap menahan perubahan API seminimal mungkin.
3. Uji staging dengan account WhatsApp terpisah.
4. Baru evaluasi loncatan ke `7.0.0-rc.x` jika:
   - QR/pairing sudah stabil
   - LID routing tetap benar
   - auto reply, send media, list, template, poll, dan restore session lolos uji

## Jalur migrasi terkontrol

### Tahap A: keluar dari fork, belum pindah mayor

- Ganti dependency git fork ke package resmi upstream.
- Targetkan dulu seri upstream 6.x yang lebih baru daripada `6.5.0`, bukan langsung 7.x.
- Fokus tahap ini:
  - install bersih
  - pastikan `useMultiFileAuthState` masih kompatibel
  - cek `DisconnectReason`, `makeCacheableSignalKeyStore`, `fetchLatestBaileysVersion`, `jidNormalizedUser`

### Tahap B: staging compatibility test

- Siapkan 1 device staging khusus.
- Jalankan skenario:
  - generate QR
  - scan dan pairing sampai `creds.json` terbentuk
  - restart Node
  - kirim text API
  - auto reply
  - kirim ke nomor yang resolve ke `@lid`
  - kirim media
  - device HP mati sementara
  - Node restart berulang

### Tahap C: evaluasi naik ke 7.x

- Baru dilakukan setelah Tahap A dan B lulus.
- Karena upstream resmi menandai breaking changes sejak `7.0.0`, tahap ini harus dianggap mini-upgrade project, bukan sekadar bump dependency.
- Risiko utama:
  - perubahan event behavior
  - perubahan sync/history behavior
  - regresi pada message types tertentu
  - regresi memori atau event yang masih muncul di issue tracker upstream

## Hasil Tahap A

- Dependency lokal dan produksi sudah dipindahkan ke upstream resmi:
  - `@whiskeysockets/baileys: 6.7.21`
- Dependency `jimp` lama yang tidak dipakai code aktif dihapus agar resolusi upstream bersih.
- Smoke test lokal berhasil:
  - export yang dipakai project tetap tersedia
  - `server/whatsapp.js` bisa di-load normal
- Deploy produksi ke `192.168.1.8` berhasil:
  - `node_modules/@whiskeysockets/baileys/package.json` terbaca `6.7.21`
  - `chatsmart-node.service` berhasil restart
  - restore session tetap berjalan
  - `check-number` dan `send-text` tetap lolos

## Kondisi sesudah Tahap A

- Auto reconnect sesudah restart service: lolos
- Auto reconnect sesudah reboot penuh server: lolos
- Kirim API ke `6285724568236`: tetap lolos
- Noise `init queries` sudah ditekan dengan `fireInitQueries: false`
- Noise `newsletter` / `Unknown message type` sudah dihentikan di level aplikasi dengan ignore guard

## Keputusan yang disarankan sekarang

- Lanjutkan memakai upstream resmi `6.7.21` di produksi.
- Pertahankan hardening session lifecycle yang sudah diterapkan.
- Tahap berikutnya bila ingin lanjut:
  - monitor beberapa hari untuk memastikan tidak ada regresi media/poll/template
  - baru evaluasi loncatan ke `7.0.0-rc.x` bila memang dibutuhkan

## Sumber resmi

- GitHub repo resmi:
  - https://github.com/WhiskeySockets/Baileys
- GitHub releases resmi:
  - https://github.com/WhiskeySockets/Baileys/releases
- NPM package resmi:
  - https://www.npmjs.com/package/@whiskeysockets/baileys
