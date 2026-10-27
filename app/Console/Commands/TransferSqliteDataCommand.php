<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TransferSqliteDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:transfer-sqlite
                            {--target= : The target database connection name (mysql, pgsql, mariadb). Defaults to the default DB_CONNECTION}
                            {--sqlite-path= : Optional custom path to SQLite database file}
                            {--truncate : Truncate target tables before inserting data}
                            {--dry-run : Inspect table row counts without inserting data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely transfer all tables and records from an existing SQLite database into MySQL or PostgreSQL with zero data loss';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetConnection = $this->option('target') ?: config('database.default');

        if ($targetConnection === 'sqlite') {
            $this->error("Target connection is currently 'sqlite'. Please specify a target (e.g. --target=pgsql or --target=mysql) or update DB_CONNECTION in .env.");
            return Command::FAILURE;
        }

        $sqlitePath = $this->option('sqlite-path') ?: config('database.connections.sqlite.database');

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite source database file not found at: {$sqlitePath}");
            return Command::FAILURE;
        }

        // Configure temporary or active sqlite connection
        config(['database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $isDryRun = (bool) $this->option('dry-run');
        $shouldTruncate = (bool) $this->option('truncate');

        $this->info("========================================================");
        $this->info("   ATOMA SQLite to " . strtoupper($targetConnection) . " Data Migration Tool");
        $this->info("========================================================");
        $this->line("• Source (SQLite) : <comment>{$sqlitePath}</comment>");
        $this->line("• Target DB       : <comment>{$targetConnection}</comment> (" . config("database.connections.{$targetConnection}.database") . ")");
        $this->line("• Mode            : " . ($isDryRun ? '<comment>DRY-RUN (Simulasi saja)</comment>' : '<info>LIVE TRANSFER</info>'));
        $this->newLine();

        try {
            // Test target connection
            DB::connection($targetConnection)->getPdo();
        } catch (Throwable $e) {
            $this->error("Gagal terhubung ke koneksi target '{$targetConnection}': " . $e->getMessage());
            $this->warn("Pastikan database server sudah aktif dan konfigurasi .env (host, port, username, password) sudah sesuai.");
            return Command::FAILURE;
        }

        // Fetch tables from SQLite
        $rawTables = Schema::connection('sqlite_source')->getTableListing();
        $tables = [];

        foreach ($rawTables as $rawTable) {
            $tableName = str_replace(['main.', '"', '`'], '', $rawTable);

            // Skip internal system tables
            if (in_array($tableName, ['sqlite_sequence', 'sqlite_master', 'sqlite_stat1', 'migrations'])) {
                continue;
            }

            $tables[] = $tableName;
        }

        if (empty($tables)) {
            $this->warn("Tidak ada tabel yang ditemukan di SQLite source database.");
            return Command::SUCCESS;
        }

        $targetDriver = config("database.connections.{$targetConnection}.driver", $targetConnection);

        $summary = [];

        if (!$isDryRun) {
            $this->disableForeignKeys($targetConnection, $targetDriver);
        }

        $this->output->progressStart(count($tables));

        foreach ($tables as $table) {
            $sourceCount = DB::connection('sqlite_source')->table($table)->count();

            if ($sourceCount === 0) {
                $summary[] = [$table, 0, 0, 'Kosong di SQLite'];
                $this->output->progressAdvance();
                continue;
            }

            if ($isDryRun) {
                $summary[] = [$table, $sourceCount, 0, 'Siap ditransfer'];
                $this->output->progressAdvance();
                continue;
            }

            // Check if table exists in target
            if (!Schema::connection($targetConnection)->hasTable($table)) {
                $summary[] = [$table, $sourceCount, 0, '<fg=red>Tabel belum ada di target (Jalankan php artisan migrate)</>'];
                $this->output->progressAdvance();
                continue;
            }

            try {
                if ($shouldTruncate) {
                    DB::connection($targetConnection)->table($table)->truncate();
                }

                $insertedCount = 0;

                // Read in chunks of 250 rows to optimize memory and handle large datasets
                DB::connection('sqlite_source')->table($table)->orderByRaw('1')->chunk(250, function ($rows) use ($targetConnection, $table, &$insertedCount) {
                    $insertData = [];
                    foreach ($rows as $row) {
                        $insertData[] = (array) $row;
                    }

                    if (!empty($insertData)) {
                        DB::connection($targetConnection)->table($table)->insert($insertData);
                        $insertedCount += count($insertData);
                    }
                });

                // Reset PostgreSQL sequences if applicable so next INSERTs won't fail
                if ($targetDriver === 'pgsql') {
                    $this->resetPostgresSequence($targetConnection, $table);
                }

                $summary[] = [$table, $sourceCount, $insertedCount, '<fg=green>Berhasil Ditransfer</>'];
            } catch (Throwable $e) {
                $summary[] = [$table, $sourceCount, 0, '<fg=red>Error: ' . substr($e->getMessage(), 0, 60) . '...</>'];
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        if (!$isDryRun) {
            $this->enableForeignKeys($targetConnection, $targetDriver);
        }

        $this->newLine();
        $this->table(['Nama Tabel', 'Baris SQLite', 'Baris Ditransfer', 'Status'], $summary);

        if ($isDryRun) {
            $this->info("Simulasi selesai. Jalankan tanpa flag --dry-run untuk memulai transfer data sesungguhnya.");
        } else {
            $this->info("Transfer data dari SQLite ke " . strtoupper($targetConnection) . " selesai dengan sukses!");
            $this->comment("Disarankan menjalankan: php artisan optimize:clear untuk menyegarkan cache aplikasi.");
        }

        return Command::SUCCESS;
    }

    /**
     * Disable foreign key checks on the target database.
     */
    protected function disableForeignKeys(string $connection, string $driver): void
    {
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'pgsql') {
            DB::connection($connection)->statement("SET session_replication_role = 'replica';");
        }
    }

    /**
     * Re-enable foreign key checks on the target database.
     */
    protected function enableForeignKeys(string $connection, string $driver): void
    {
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'pgsql') {
            DB::connection($connection)->statement("SET session_replication_role = 'DEFAULT';");
        }
    }

    /**
     * Synchronize and reset PostgreSQL sequences to match the maximum ID in the table.
     */
    protected function resetPostgresSequence(string $connection, string $table): void
    {
        try {
            $columns = DB::connection($connection)->select("
                SELECT column_name, column_default 
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                  AND table_name = ? 
                  AND column_default LIKE 'nextval%'
            ", [$table]);

            foreach ($columns as $column) {
                $colName = $column->column_name;
                DB::connection($connection)->statement("
                    SELECT setval(
                        pg_get_serial_sequence('\"$table\"', '$colName'), 
                        COALESCE((SELECT MAX(\"$colName\") FROM \"$table\"), 1)
                    )
                ");
            }
        } catch (Throwable $e) {
            // Non-critical sequence reset failure logging
        }
    }
}
