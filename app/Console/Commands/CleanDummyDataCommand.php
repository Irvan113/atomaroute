<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\SystemLog;

class CleanDummyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'atoma:clean-dummy 
                            {--keep-ids= : Komma-separated ID user asli yang dipertahankan, misal: 1,2,3,4}
                            {--dry-run : Simulasi saja tanpa menghapus data nyata}
                            {--force : Lewati konfirmasi interaktif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bersihkan data dummy / testing secara aman tanpa mereset 4 user asli dan datanya.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== ATOMA Database Cleanup Tool ===");
        $this->line("Alat ini menghapus data dummy/pengujian dengan aman tanpa merusak user asli.");
        $this->newLine();

        // 1. Tampilkan semua user yang ada
        $allUsers = User::withCount(['tasks', 'journals', 'focusSessions'])->get();

        if ($allUsers->isEmpty()) {
            $this->warn("Tidak ada user ditemukan di dalam database.");
            return Command::SUCCESS;
        }

        $headers = ['ID', 'Nama', 'Email', 'Tasks', 'Catatan', 'Sesi Fokus', 'Terdaftar Pada'];
        $tableData = $allUsers->map(function ($u) {
            return [
                $u->id,
                $u->name,
                $u->email,
                $u->tasks_count,
                $u->journals_count,
                $u->focus_sessions_count,
                $u->created_at ? $u->created_at->format('Y-m-d H:i') : '-',
            ];
        });

        $this->table($headers, $tableData);

        // 2. Tentukan ID yang ingin dipertahankan
        $keepIdsInput = $this->option('keep-ids');

        if (!$keepIdsInput) {
            $keepIdsInput = $this->ask('Masukkan ID user asli yang ingin DIPERTAHANKAN (pisahkan dengan koma, misal: 12, 17, 18, 21)');
        }

        if (empty($keepIdsInput)) {
            $this->error("Operasi dibatalkan: Anda belum menentukan ID user yang ingin dipertahankan.");
            return Command::FAILURE;
        }

        $keepIds = array_filter(array_map('intval', explode(',', $keepIdsInput)));

        if (empty($keepIds)) {
            $this->error("Format ID tidak valid.");
            return Command::FAILURE;
        }

        $keptUsers = User::whereIn('id', $keepIds)->get();
        $dummyUsers = User::whereNotIn('id', $keepIds)->get();

        $this->newLine();
        $this->info("User yang AKAN DIPERTAHANKAN (" . $keptUsers->count() . " user):");
        foreach ($keptUsers as $ku) {
            $this->line(" [✓] ID {$ku->id}: {$ku->name} ({$ku->email})");
        }

        $this->newLine();
        $this->warn("User dummy yang AKAN DIHAPUS (" . $dummyUsers->count() . " user):");
        foreach ($dummyUsers as $du) {
            $this->line(" [✗] ID {$du->id}: {$du->name} ({$du->email})");
        }

        if ($dummyUsers->isEmpty()) {
            $this->info("Tidak ada user dummy untuk dihapus.");
        }

        // Hitung estimasi log dummy
        $dummyLogsCount = SystemLog::where('ip_address', '127.0.0.1')
            ->orWhere('url', 'like', '%localhost%')
            ->orWhere('url', 'like', '%atoma.test%')
            ->count();

        $this->line("Log testing lokal yang akan dibersihkan: {$dummyLogsCount} log.");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn("Mode --dry-run aktif. Tidak ada perubahan yang disimpan ke database.");
            return Command::SUCCESS;
        }

        // Konfirmasi
        if (!$this->option('force')) {
            $this->newLine();
            if (!$this->confirm('Apakah Anda yakin ingin menghapus data dummy di atas sekarang? Tindakan ini tidak dapat dibatalkan.')) {
                $this->info("Pembersihan dibatalkan oleh pengguna.");
                return Command::SUCCESS;
            }
        }

        // 3. Backup otomatis file SQLite jika driver sqlite
        $dbConnection = config('database.default', 'sqlite');
        if ($dbConnection === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath && file_exists($dbPath)) {
                $backupPath = dirname($dbPath) . '/database_backup_clean_' . date('Ymd_His') . '.sqlite';
                copy($dbPath, $backupPath);
                $this->info("Backup otomatis database berhasil dibuat di: " . basename($backupPath));
            }
        }

        // 4. Proses penghapusan data relasi & user dummy
        $this->newLine();
        $this->info("Memulai proses pembersihan...");

        $dummyIds = $dummyUsers->pluck('id')->toArray();

        if (!empty($dummyIds)) {
            // Aktifkan foreign key pragma pada SQLite jika aktif
            if ($dbConnection === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            }

            // Hapus tabel-tabel spesifik secara terstruktur untuk menghindari constraint error
            $tablesWithUserId = [
                'tasks',
                'sub_tasks',
                'task_templates',
                'template_shares',
                'shared_task_invitations',
                'goals',
                'skills',
                'skill_targets',
                'journals',
                'journal_links',
                'flashcards',
                'finance_transactions',
                'focus_sessions',
                'pomodoro_playlists',
                'workout_sessions',
                'workout_exercises',
                'workout_sets',
                'friendships',
                'friend_nudges',
                'messages',
                'posts',
                'comments',
                'likes',
                'notifications',
                'user_achievements',
                'streak_histories',
                'pending_xp_logs',
                'daily_summaries',
                'ai_insights',
                'ai_usage_logs',
                'mcp_tokens',
                'subscription_transactions',
                'user_feedbacks',
            ];

            foreach ($tablesWithUserId as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                        DB::table($table)->whereIn('user_id', $dummyIds)->delete();
                    }
                }
            }

            // Friendships khusus (ada user_id dan friend_id)
            if (\Illuminate\Support\Facades\Schema::hasTable('friendships')) {
                DB::table('friendships')
                    ->whereIn('user_id', $dummyIds)
                    ->orWhereIn('friend_id', $dummyIds)
                    ->delete();
            }

            // Hapus user itu sendiri
            User::whereIn('id', $dummyIds)->delete();
            $this->info("Berhasil menghapus " . count($dummyIds) . " user dummy beserta seluruh relasi data.");
        }

        // 5. Bersihkan log dummy
        if ($dummyLogsCount > 0) {
            SystemLog::where('ip_address', '127.0.0.1')
                ->orWhere('url', 'like', '%localhost%')
                ->orWhere('url', 'like', '%atoma.test%')
                ->delete();
            $this->info("Berhasil membersihkan {$dummyLogsCount} log testing lokal.");
        }

        // 6. Optimasi SQLite (VACUUM)
        if ($dbConnection === 'sqlite') {
            $this->info("Menjalankan VACUUM untuk mengompresi dan merapikan file SQLite...");
            DB::statement('VACUUM;');
            $this->info("VACUUM selesai. Database menjadi ramping dan cepat!");
        }

        $this->newLine();
        $this->info("=== Pembersihan database selesai dengan SUKSES! ===");
        $this->line("User aktif saat ini (" . User::count() . " user):");
        foreach (User::select('id', 'name', 'email')->get() as $u) {
            $this->line(" [✓] ID {$u->id}: {$u->name} ({$u->email})");
        }

        return Command::SUCCESS;
    }
}
