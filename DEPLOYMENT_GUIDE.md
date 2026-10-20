# Panduan Deployment cPanel & Dokumentasi PostgreSQL
**Garam Capkapal (Frontend & Backend)**

Dokumen ini mencatat seluruh arsitektur, konfigurasi server, setup database PostgreSQL, dan rekam jejak penyelesaian kendala (*troubleshooting*) yang dilakukan pada server cPanel/WHM.

---

## 1. Informasi Server & Spesifikasi

| Parameter | Nilai / Konfigurasi | Keterangan |
| :--- | :--- | :--- |
| **Server Host / IP** | `163.61.58.29` | IP Publik Server |
| **Port SSH** | `22` | Akses terminal remote |
| **Port cPanel** | `2083` | Antarmuka web cPanel (SSL) |
| **Port PostgreSQL** | `5432` | Koneksi database internal (`127.0.0.1`) |
| **User cPanel** | `garamcapkapal` | User akun cPanel |
| **Home Directory** | `/home/garamcapkapal` | Direktori dasar user |
| **PHP Runtime** | `PHP 8.5 (ea-php85)` | Diperlukan oleh Laravel 13 & Symfony 8 |
| **Backend Domain** | `https://api-dev.garamcapkapal.com` | Folder: `/home/garamcapkapal/public_html/api-dev.garamcapkapal.com` |
| **Frontend Domain** | `https://dev.garamcapkapal.com` | Folder: `/home/garamcapkapal/public_html/dev.garamcapkapal.com` |

---

## 2. Dokumentasi Setup Database PostgreSQL

### 2.1. Kredensial Database
* **Database Driver**: `pgsql`
* **Host**: `127.0.0.1`
* **Port**: `5432`
* **Database Name**: `garam_capkapal_api`
* **Database User**: `garamcapkapal_user`
* **Password**: `Capkapal2027!Db`

### 2.2. Script Pembuatan User & Database (Root Terminal)
Jalankan perintah ini di terminal server root (`[root@server ~]#`) untuk membuat ulang database dan hak akses:

```bash
su - postgres -c "psql" << 'EOF'
CREATE USER garamcapkapal_user WITH PASSWORD 'Capkapal2027!Db';
CREATE DATABASE garam_capkapal_api OWNER garamcapkapal_user;
GRANT ALL PRIVILEGES ON DATABASE garam_capkapal_api TO garamcapkapal_user;
\c garam_capkapal_api
GRANT ALL ON SCHEMA public TO garamcapkapal_user;
\q
EOF
```

### 2.3. Instalasi Ekstensi Driver PHP PostgreSQL
Untuk menghubungkan PHP 8.5 dengan PostgreSQL pada server EasyApache 4 cPanel:
```bash
dnf install -y ea-php85-php-pgsql
```

### 2.4. Perintah Cek Status Database & User
```bash
# Cek daftar database
su - postgres -c "psql -c '\l'"

# Cek daftar user / role
su - postgres -c "psql -c '\du'"
```

---

## 3. Konfigurasi Environment Backend (`.env`)

File ini terletak di `/home/garamcapkapal/public_html/api-dev.garamcapkapal.com/.env`:

```env
APP_NAME="Garam Capkapal"
APP_ENV=development
APP_KEY=base64:uTkcx6NFN2LJ0Hf7ukM1F6x5NI6xLa9oAhTZVoHULhE=
APP_DEBUG=true
APP_URL=https://api-dev.garamcapkapal.com
APP_TIMEZONE=Asia/Jakarta
FRONTEND_URL=https://dev.garamcapkapal.com

APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# ─── DATABASE (POSTGRESQL) ──────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=garam_capkapal_api
DB_USERNAME=garamcapkapal_user
DB_PASSWORD=Capkapal2027!Db

# ─── SESSION & CACHE ─────────────────────────────────────────────
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.garamcapkapal.com

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=file

# ─── MAIL (RESEND) ──────────────────────────────────────────────
MAIL_MAILER=resend
RESEND_API_KEY=your_resend_api_key_here
MAIL_FROM_ADDRESS="onboarding@resend.dev"
MAIL_FROM_NAME="${APP_NAME}"

# ─── SANCTUM / CORS ─────────────────────────────────────────────
SANCTUM_STATEFUL_DOMAINS=dev.garamcapkapal.com,api-dev.garamcapkapal.com

# ─── PAYMENT & SECURITY ─────────────────────────────────────────
MIDTRANS_SERVER_KEY=your_midtrans_server_key_here
MIDTRANS_CLIENT_KEY=your_midtrans_client_key_here
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_NOTIFICATION_URL=https://api-dev.garamcapkapal.com/api/v1/webhooks/midtrans
TURNSTILE_SECRET_KEY=your_turnstile_secret_key_here
```

---

## 4. Konfigurasi CI/CD GitHub Actions

Deployment otomatis diatur pada `.github/workflows/deploy.yml` untuk Frontend (`capkapal-sm`) dan Backend (`capkapal-sm-be`).

### 4.1. GitHub Secrets yang Digunakan
Diatur di menu **Settings > Secrets and variables > Actions**:

| Secret Name | Deskripsi |
| :--- | :--- |
| `SSH_PRIVATE_KEY` / `CPANEL_SSH_KEY` | Private Key OpenSSH untuk login ke server via port 22 |
| `SSH_PASSPHRASE` / `CPANEL_PASSPHRASE` | Kata sandi pelindung SSH Private Key |
| `CPANEL_HOST` | *(Opsional)* Default fallback: `163.61.58.29` |
| `CPANEL_USER` | *(Opsional)* Default fallback: `garamcapkapal` |
| `CPANEL_PORT` | *(Opsional)* Default fallback: `22` |

### 4.2. Fitur Otomatis Workflow
1. **Auto Git Init**: Jika folder subdomain cPanel baru dibuat dan belum ada `.git`, workflow otomatis mengeksekusi `git init` dan menghubungkan ke repo GitHub.
2. **Auto Authentication**: Menggunakan `GITHUB_TOKEN` bawaan GitHub Actions untuk menarik (*pull*) repositori privat tanpa perlu login manual di server.
3. **Safe Checkout Force**: Menggunakan opsi `--force` untuk menghindari error konflik file bawaan cPanel (seperti `.htaccess`).

---

## 5. Ringkasan Kendala & Solusi (Troubleshooting Log)

### Kendala 1: `this private key is passphrase protected`
* **Gejala**: SSH Action gagal login karena private key diproteksi kata sandi.
* **Solusi**: Menambahkan secret `SSH_PASSPHRASE` pada GitHub Secrets dan menambahkan parameter `passphrase: ${{ secrets.CPANEL_PASSPHRASE || secrets.SSH_PASSPHRASE }}` di workflow.

### Kendala 2: `Directory ... is not a Git repository`
* **Gejala**: Folder subdomain cPanel sudah ada tetapi belum diinisialisasi Git.
* **Solusi**: Memperbarui workflow agar mengecek keberadaan `.git`, melakukan `git init` otomatis, dan menghubungkan remote origin dengan token autentikasi GitHub.

### Kendala 3: `Composer: ext-exif / ext-iconv missing`
* **Gejala**: `composer install` gagal karena CLI PHP di cPanel belum mengaktifkan ekstensi exif/iconv.
* **Solusi**: Menambahkan parameter `--ignore-platform-reqs` pada perintah Composer install.

### Kendala 4: `could not find driver (Connection: pgsql)`
* **Gejala**: PHP belum memiliki driver PDO PostgreSQL.
* **Solusi**: Menjalankan instalasi paket modul PostgreSQL via terminal root: `dnf install -y ea-php85-php-pgsql`.

### Kendala 5: `Method PostgresConnection::lastQueryLog does not exist`
* **Gejala**: Migrasi terhenti di tabel `carts` saat eksekusi query lebih dari 500ms.
* **Solusi**: Memperbaiki method di `app/Providers/AppServiceProvider.php` dan menonaktifkan slow query logger saat aplikasi berjalan di mode konsol/migrasi (`!app()->runningInConsole()`).

### Kendala 6: Tampilan `Index of /` pada Web Browser
* **Gejala**: Domain backend menampilkan daftar direktori file alih-alih aplikasi Laravel.
* **Solusi**: Membuat file `.htaccess` di root folder backend untuk mengarahkan semua permintaan HTTP ke folder `public/`.

### Kendala 7: `Call to undefined method ReflectionProperty::isVirtual()`
* **Gejala**: Error 500 pada Symfony VarDumper saat dijalankan pada PHP 8.2 / 8.3.
* **Solusi**: Mengubah versi PHP subdomain ke **PHP 8.5 (`ea-php85`)** via **cPanel MultiPHP Manager**, karena method `ReflectionProperty::isVirtual()` diperkenalkan pada PHP 8.4+.

### Kendala 8: `Class "Mysql" not found` pada PHP 8.5
* **Gejala**: Saat me-load `config/database.php`, PHP 8.5 mencari kelas `Mysql` di global namespace.
* **Solusi**: Memperbarui sintaks konstanta SSL MySQL menggunakan pengecekan class `\Pdo\Mysql::class`.

### Kendala 9: `MissingAppKeyException: No application encryption key has been specified`
* **Gejala**: Laravel menolak berjalan karena kunci enkripsi aplikasi belum di-generate.
* **Solusi**: Menjalankan `/usr/local/bin/ea-php85 artisan key:generate` dan `artisan config:clear`.

---

## 6. Perintah Rutin Pemeliharaan Server

Jalankan perintah berikut di folder `/home/garamcapkapal/public_html/api-dev.garamcapkapal.com`:

```bash
# 1. Menjalankan migrasi database terbaru
/usr/local/bin/ea-php85 artisan migrate --force

# 2. Menjalankan seeder awal (Admin & Roles)
/usr/local/bin/ea-php85 artisan db:seed --force

# 3. Membersihkan seluruh cache konfigurasi, route, dan view
/usr/local/bin/ea-php85 artisan optimize:clear

# 4. Memperbaiki izin kepemilikan file setelah operasi root
chown -R garamcapkapal:garamcapkapal /home/garamcapkapal/public_html/api-dev.garamcapkapal.com
chmod -R 775 storage bootstrap/cache
```
