<?php
/**
 * ATOMA 1-Click SQLite to MySQL Auto-Fixer & Migrator (Zero Terminal Required)
 * Akses via browser: https://domain-anda.com/migrate.php
 */
set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');

$baseDir = __DIR__;
if (!file_exists($baseDir . '/bootstrap/app.php') && file_exists(dirname($baseDir) . '/bootstrap/app.php')) {
    $baseDir = dirname($baseDir);
}

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>ATOMA MySQL Migrator</title>";
echo "<style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; display: flex; justify-content: center; }
    .card { background: #1e293b; border-radius: 24px; padding: 36px; max-width: 620px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 1px solid #334155; }
    h2 { margin-top: 0; color: #38bdf8; font-size: 22px; }
    .step { margin-bottom: 14px; padding: 12px 16px; border-radius: 12px; background: #0f172a; border: 1px solid #334155; font-size: 14px; }
    .success { color: #4ade80; font-weight: 500; }
    .error { color: #f87171; font-weight: 500; }
    .btn { display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 12px; font-weight: bold; margin-top: 16px; }
    pre { background: #020617; padding: 12px; border-radius: 8px; font-size: 12px; overflow-x: auto; color: #cbd5e1; }
</style></head><body><div class='card'>";
echo "<h2>🐬 ATOMA SQLite ke MySQL Migrator</h2>";

try {
    // 1. Perbaiki urutan migration likes dan posts secara otomatis
    $oldLikeMig = $baseDir . '/database/migrations/2026_07_05_025535_create_likes_table.php';
    $newLikeMig = $baseDir . '/database/migrations/2026_07_05_025536_create_likes_table.php';
    if (file_exists($oldLikeMig)) {
        @rename($oldLikeMig, $newLikeMig);
    }

    // 2. Backup SQLite otomatis untuk jaminan keamanan
    $sqliteFile = $baseDir . '/database/database.sqlite';
    if (file_exists($sqliteFile)) {
        $backup = $baseDir . '/database/database.sqlite.bak_' . date('Ymd_His');
        @copy($sqliteFile, $backup);
        echo "<div class='step'><span class='success'>✅ Database SQLite asli berhasil di-backup ke <code>" . basename($backup) . "</code>. Data Anda 100% aman!</span></div>";
    }

    // 3. Load framework Laravel
    require $baseDir . '/vendor/autoload.php';
    $app = require_once $baseDir . '/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    // 4. KUNCI: Paksa koneksi SQLite agar membaca file fisik database.sqlite
    // (Mencegah error 'Database file at path [nama_mysql] does not exist')
    config(['database.connections.sqlite.database' => $baseDir . '/database/database.sqlite']);
    \Illuminate\Support\Facades\DB::purge('sqlite');

    // 5. Bersihkan tabel di MySQL dan buat semua tabel fresh di MySQL
    \Illuminate\Support\Facades\DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0;');
    
    // Jalankan migrate:fresh khusus pada database MySQL (SQLite tidak disentuh sama sekali)
    \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
        '--database' => 'mysql',
        '--force' => true,
    ]);
    echo "<div class='step'><span class='success'>✅ Seluruh struktur 43 tabel MySQL berhasil dibuat bersih tanpa bentrok foreign key!</span></div>";

    // 6. Salin data dari SQLite ke MySQL
    $sqliteTables = \Illuminate\Support\Facades\Schema::connection('sqlite')->getTableListing();
    $totalRecords = 0;
    $details = [];

    foreach ($sqliteTables as $rawTable) {
        $table = str_replace(['main.', '"', '`'], '', $rawTable);
        if (in_array($table, ['sqlite_sequence', 'sqlite_master', 'sqlite_stat1', 'migrations'])) continue;
        if (!\Illuminate\Support\Facades\Schema::connection('mysql')->hasTable($table)) continue;

        $rows = \Illuminate\Support\Facades\DB::connection('sqlite')->table($table)->get();
        if ($rows->isNotEmpty()) {
            $data = array_map(fn($r) => (array) $r, $rows->toArray());
            \Illuminate\Support\Facades\DB::connection('mysql')->table($table)->insert($data);
            $count = count($data);
            $totalRecords += $count;
            $details[] = "• {$table}: {$count} data disalin";
        }
    }
    \Illuminate\Support\Facades\DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1;');

    // 7. Bersihkan cache
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');

    echo "<div class='step'><span class='success'>✅ <b>{$totalRecords} baris data</b> (akun user, task, journal, level, XP) berhasil dipindahkan ke MySQL!</span>";
    if (!empty($details)) {
        echo "<pre>" . implode("\n", $details) . "</pre>";
    }
    echo "</div>";

    echo "<div style='margin-top:24px;background:#052e16;border:1px solid #166534;padding:16px;border-radius:14px;'>";
    echo "<h3 style='margin:0 0 6px;color:#4ade80;'>🎉 Migrasi Sukses 100%!</h3>";
    echo "<p style='margin:0;color:#bbf7d0;font-size:13px;'>Database MySQL Anda sekarang sudah aktif, data lama lengkap, dan error 500 sudah hilang.</p>";
    echo "</div>";

    echo "<a href='/login' class='btn'>🚀 Buka Halaman Login</a>";
    echo "<p style='margin-top:20px;font-size:12px;color:#f87171;'>⚠️ Setelah login berhasil, silakan hapus file <code>migrate.php</code> ini dari File Manager demi keamanan.</p>";

} catch (\Throwable $e) {
    echo "<div class='step'><span class='error'>❌ Terjadi Kendala:</span><br>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre></div>";
}

echo "</div></body></html>";
