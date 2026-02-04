# OpenCollab Music — Prasyarat & Cara Menjalankan

Project ini adalah aplikasi web berbasis PHP + MySQL. Berikut daftar tools/teknologi yang perlu disiapkan terlebih dahulu agar aplikasi dapat berjalan dengan baik di macOS.

## Prasyarat
- PHP 8.1+ (disarankan 8.4)
- MySQL 8 (server dan client)
- Ekstensi PHP:
  - mysqli (aktif secara default pada paket PHP Homebrew/MAMP)
  - json (aktif secara default)
- Composer (opsional, hanya untuk memastikan ekstensi terpasang; tidak ada library pihak ketiga)

## Instalasi di macOS (Homebrew)

```bash
# 1) Install PHP
brew install php

# 2) Install MySQL
brew install mysql
brew services start mysql  # Menyalakan MySQL sebagai service

# 3) (Opsional) Install Composer
brew install composer
```

Jika memakai MAMP/XAMPP, pastikan versi PHP ≥ 8.1 dan MySQL berjalan, lalu sesuaikan PATH bila perlu.

## Menyiapkan Database
- Nama database: `opencollab_music`
- Skema tersedia pada file `schema_project.sql` (dihasilkan otomatis dari DB).

Import skema:
```bash
mysql -u root -p < schema_project.sql
```

Catatan:
- Jika password root MySQL belum diset, jalankan:
```bash
mysql_secure_installation
```
- Sesuaikan kredensial MySQL di `apps/web/db.php`:
  - host: `localhost`
  - user: `root`
  - password: `''` (ubah sesuai kebutuhan)
  - database: `opencollab_music`

## Menjalankan Aplikasi (Server Development)
Di root project:
```bash
php -S 127.0.0.1:8000 -t apps/web
```

Lalu buka:
- http://127.0.0.1:8000/

Jika port 8000 sudah dipakai, ganti port:
```bash
php -S 127.0.0.1:8080 -t apps/web
```

## Pengujian Cepat
- Buat akun via halaman Register.
- Login dan unggah karya.
- Coba fitur pencarian dan kirim permintaan kolaborasi.
- Pastikan tombol “Request Sent” tetap persisten setelah refresh.

## Troubleshooting
- Tidak bisa konek DB:
  - Cek service MySQL: `brew services list`
  - Ubah kredensial di `apps/web/db.php`
  - Pastikan database `opencollab_music` sudah ada dan skema sudah diimport.
- Ekstensi mysqli/json tidak aktif:
  - Pastikan memakai PHP dari Homebrew atau MAMP yang mengaktifkan ekstensi default.
- Akses audio/cover:
  - Pastikan folder `uploads/` dapat ditulis (PHP punya hak tulis).

## (Opsional) Tools Dokumentasi
Jika ingin mengonversi dokumen presentasi ke PDF/DOCX:
- Node.js + paket konversi dokumen (misal `markdown-pdf`, `markdown-docx`)
- Ini tidak wajib untuk menjalankan aplikasi web.

