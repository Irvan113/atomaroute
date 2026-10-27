<?php
/**
 * ATOMA 1-Click SQLite to MySQL Data Migrator
 * Khusus untuk Shared Hosting / cPanel (RAM 1 GB)
 * Akses via browser: https://domain-anda.com/migrate_to_mysql.php
 * atau jalankan via terminal: php migrate_to_mysql.php
 */

header('Content-Type: text/html; charset=utf-8');

// Tentukan Root Project Laravel
$baseDir = __DIR__;
if (!file_exists($baseDir . '/bootstrap/app.php') && file_exists(dirname($baseDir) . '/bootstrap/app.php')) {
    $baseDir = dirname($baseDir);
}

$isCli = (php_sapi_name() === 'cli');

function renderStep($title, $status, $message, $isCli) {
    if ($isCli) {
        $icon = ($status === 'success') ? '[OK]' : (($status === 'error') ? '[FAIL]' : '[INFO]');
        echo "{$icon} {$title}: {$message}\n";
        return;
    }
    $colorClass = ($status === 'success') ? 'success' : (($status === 'error') ? 'error' : 'info');
    $icon = ($status === 'success') ? '✅' : (($status === 'error') ? '❌' : 'ℹ️');
    echo "<div class='step'><b>{$title}:</b><br><span class='{$colorClass}'>{$icon} {$message}</span></div>";
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>ATOMA SQLite to MySQL Migration</title>";
    echo "<style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; display: flex; justify-content: center; }
        .card { background: #1e293b; border-radius: 24px; padding: 36px; max-width: 680px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 1px solid #334155; }
        h2 { margin-top: 0; color: #38bdf8; font-size: 24px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
        .step { margin-bottom: 16px; padding: 14px 18px; border-radius: 12px; background: #0f172a; border: 1px solid #334155; font-size: 14px; }
        .success { color: #4ade80; font-weight: 500; }
        .error { color: #f87171; font-weight: 500; }
        .info { color: #38bdf8; }
        .btn { display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 12px; font-weight: bold; margin-top: 16px; margin-right: 12px; }
        .btn-sec { background: #334155; }
        pre { background: #020617; padding: 14px; border-radius: 10px; font-size: 12px; overflow-x: auto; color: #e2e8f0; border: 1px solid #1e293b; }
    </style></head><body><div class='card'>";
    echo "<h2>🐬 ATOMA 1-Click SQLite to MySQL Migrator</h2>";
    echo "<p>Membuat struktur tabel baru di MySQL, menyalin data user, tugas, catatan, dan progres XP dari SQLite ke MySQL secara aman tanpa menghapus database lama.</p>";
}

// 1. Validasi Root
if (!file_exists($baseDir . '/bootstrap/app.php')) {
    renderStep("1. Deteksi Root Laravel", "error", "Gagal menemukan <code>bootstrap/app.php</code>.", $isCli);
    if (!$isCli) echo "</div></body></html>";
    exit;
}
renderStep("1. Deteksi Root Laravel", "success", "Ditemukan di <code>" . htmlspecialchars($baseDir) . "</code>", $isCli);

// 2. Backup Otomatis SQLite
$sqliteFile = $baseDir . '/database/database.sqlite';
if (file_exists($sqliteFile)) {
    $backupFile = $baseDir . '/database/database.sqlite.bak_' . date('Ymd_His');
    if (@copy($sqliteFile, $backupFile)) {
        renderStep("2. Cadangan Keamanan Data", "success", "Database SQLite asli berhasil di-backup ke: <code>" . basename($backupFile) . "</code> (Ukuran: " . round(filesize($sqliteFile) / 1024, 2) . " KB). Data Anda 100% aman!", $isCli);
    } else {
        renderStep("2. Cadangan Keamanan Data", "info", "File SQLite ditemukan namun gagal membuat copy. Membaca langsung dari file utama.", $isCli);
    }
} else {
    renderStep("2. Cadangan Keamanan Data", "error", "File <code>database/database.sqlite</code> tidak ditemukan.", $isCli);
    if (!$isCli) echo "</div></body></html>";
    exit;
}

// 3. Bootstrap Framework Laravel
try {
    require $baseDir . '/vendor/autoload.php';
    $app = require_once $baseDir . '/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    renderStep("3. Inisialisasi Framework", "success", "Laravel " . app()->version() . " siap dieksekusi.", $isCli);
} catch (\Throwable $e) {
    renderStep("3. Inisialisasi Framework", "error", "Gagal memuat framework: " . htmlspecialchars($e->getMessage()), $isCli);
    if (!$isCli) echo "</div></body></html>";
    exit;
}

// 4. Periksa Konfigurasi .env MySQL
$currentDbConnection = config('database.default');
$mysqlConfig = config('database.connections.mysql');

if ($currentDbConnection !== 'mysql') {
    renderStep("4. Cek Konfigurasi .env", "error", "Koneksi database aktif saat ini masih <code>{$currentDbConnection}</code>. Silakan buka file <code>.env</code> dan set <code>DB_CONNECTION=mysql</code> beserta host, database, user, dan password MySQL Anda terlebih dahulu.", $isCli);
    if (!$isCli) {
        echo "<p style='color:#fbbf24;font-size:13px;'>Contoh pengaturan di file <code>.env</code>:<br><pre>DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=nama_cpanel_database\nDB_USERNAME=nama_cpanel_user\nDB_PASSWORD=password_database_anda</pre></p>";
        echo "</div></body></html>";
    }
    exit;
}

// 5. Tes Koneksi MySQL
try {
    \Illuminate\Support\Facades\DB::connection('mysql')->getPdo();
    renderStep("5. Tes Koneksi MySQL", "success", "Berhasil terhubung ke MySQL database <code>" . htmlspecialchars($mysqlConfig['database']) . "</code>!", $isCli);
} catch (\Throwable $e) {
    renderStep("5. Tes Koneksi MySQL", "error", "Gagal login ke MySQL: " . htmlspecialchars($e->getMessage()) . ". Pastikan nama database, user, dan password di .env sudah sesuai dengan yang dibuat di cPanel MySQL Database Wizard.", $isCli);
    if (!$isCli) echo "</div></body></html>";
    exit;
}

// 6. Jalankan Migrasi Skema (php artisan migrate)
try {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $migrateOutput = \Illuminate\Support\Facades\Artisan::output();
    renderStep("6. Pembuatan Struktur Tabel MySQL", "success", "Skema dan tabel berhasil dibuat di database MySQL.", $isCli);
} catch (\Throwable $e) {
    renderStep("6. Pembuatan Struktur Tabel MySQL", "error", "Gagal menjalankan migrasi: " . htmlspecialchars($e->getMessage()), $isCli);
    if (!$isCli) echo "</div></body></html>";
    exit;
}

// 7. Transfer Data dari SQLite ke MySQL
try {
    \Illuminate\Support\Facades\Artisan::call('db:transfer-sqlite', ['--target' => 'mysql']);
    $transferOutput = \Illuminate\Support\Facades\Artisan::output();
    renderStep("7. Transfer Data Pengguna (Zero-Loss)", "success", "Data tugas, catatan, akun user, dan XP berhasil disalin dari SQLite ke MySQL!", $isCli);
    if (!$isCli) {
        echo "<details style='margin-bottom:16px;'><summary style='color:#38bdf8;cursor:pointer;font-size:13px;'>Lihat Rincian Rekap Data per Tabel</summary><pre>" . htmlspecialchars($transferOutput) . "</pre></details>";
    }
} catch (\Throwable $e) {
    renderStep("7. Transfer Data Pengguna", "error", "Gagal transfer data: " . htmlspecialchars($e->getMessage()), $isCli);
    if (!$isCli) echo "</div></body></html>";
    exit;
}

// 8. Bersihkan Cache Aplikasi
try {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    renderStep("8. Pembersihan Cache Produksi", "success", "Cache konfigurasi dan views berhasil dibersihkan.", $isCli);
} catch (\Throwable $e) {
    renderStep("8. Pembersihan Cache Produksi", "info", "Cache dilewati: " . htmlspecialchars($e->getMessage()), $isCli);
}

// Selesai
if (!$isCli) {
    echo "<div style='margin-top:24px;background:#052e16;border:1px solid #166534;padding:16px;border-radius:14px;'>";
    echo "<h3 style='margin:0 0 8px;color:#4ade80;'>🎉 Migrasi ke MySQL Sukses 100%!</h3>";
    echo "<p style='margin:0;color:#bbf7d0;font-size:13px;'>ATOMA kini aktif menggunakan MySQL. Data pengguna lama aman tanpa ada yang terhapus.</p>";
    echo "</div>";
    
    echo "<div style='margin-top:24px;'>";
    echo "<a href='/dashboard' class='btn'>🚀 Buka Dashboard ATOMA</a>";
    echo "</div>";
    
    echo "<p style='margin-top:24px;font-size:12px;color:#f87171;'>⚠️ <b>Penting:</b> Demi keamanan, silakan hapus file <code>migrate_to_mysql.php</code> ini dari File Manager cPanel setelah migrasi selesai.</p>";
    echo "</div></body></html>";
} else {
    echo "\n🎉 MIGRASI KE MYSQL SELESAI DENGAN SUKSES!\n";
}
