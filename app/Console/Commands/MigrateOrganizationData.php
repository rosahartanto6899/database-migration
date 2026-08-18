<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOrganizationData extends Command
{
    protected $signature = 'migrate:organization-data
                            {--table= : Migrasi hanya satu tabel (mis. MsCostCenter)}
                            {--chunk=1000 : Jumlah baris per batch insert}
                            {--truncate : Kosongkan tabel tujuan sebelum migrasi}
                            {--dry-run : Hanya hitung baris di sumber, tidak menulis data}';

    protected $description = 'Migrasi data dari SQL Server (omni-db-organization-dev) ke PostgreSQL';

    protected string $sourceConnection = 'sqlsrv_source';

    protected string $targetConnection = 'pgsql';

    protected string $schema = 'dbo';

    /**
     * Urutan migrasi: tabel master (tanpa dependensi) lebih dulu,
     * baru tabel yang mereferensikannya. Tidak ada FK constraint
     * di schema ini, tapi urutan ini tetap dijaga demi konsistensi.
     */
    protected array $tables = [
        'MsOrganizationStructure' => [
            'columns' => ['Id', 'OrgStructureName'],
            'order_by' => 'Id',
            'identity' => false,
        ],
        'MsCostCenter' => [
            'columns' => ['Id', 'CostCenterName', 'CustomerId', 'BusinessUnitId'],
            'order_by' => 'Id',
            'identity' => false,
        ],
        'ConfigOrganization' => [
            'columns' => [
                'Id', 'OrgStructureId', 'OrgName', 'ParentId',
                'IsParent', 'CostCenterId', 'CustomerId', 'BusinessUnitId', 'PoolId',
            ],
            'order_by' => 'Id',
            'identity' => false,
            'casts' => ['IsParent' => 'bool'],
        ],
        'ConfigOrganizationUserApproval' => [
            'columns' => ['Id', 'ConfigOrganizationId', 'UserId', 'Email'],
            'order_by' => 'Id',
            'identity' => true,
        ],
        'ConfigDirectUserApproval' => [
            'columns' => ['Id', 'CostCenterId', 'UserId', 'Sequence', 'Email'],
            'order_by' => 'Id',
            'identity' => true,
        ],
    ];

    public function handle(): int
    {
        $only = $this->option('table');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $truncate = (bool) $this->option('truncate');
        $dryRun = (bool) $this->option('dry-run');

        $tables = $only ? array_intersect_key($this->tables, [$only => true]) : $this->tables;

        if ($only && empty($tables)) {
            $this->components->error(
                "Tabel '{$only}' tidak dikenal. Pilihan: ".implode(', ', array_keys($this->tables))
            );

            return self::FAILURE;
        }

        try {
            DB::connection($this->sourceConnection)->getPdo();
        } catch (\Throwable $e) {
            $this->components->error("Gagal konek ke SQL Server ({$this->sourceConnection}): {$e->getMessage()}");

            return self::FAILURE;
        }

        try {
            DB::connection($this->targetConnection)->getPdo();
        } catch (\Throwable $e) {
            $this->components->error("Gagal konek ke PostgreSQL ({$this->targetConnection}): {$e->getMessage()}");

            return self::FAILURE;
        }

        foreach ($tables as $table => $config) {
            $this->migrateTable($table, $config, $chunkSize, $truncate, $dryRun);
        }

        $this->newLine();
        $this->components->info('Migrasi selesai.');

        return self::SUCCESS;
    }

    protected function migrateTable(string $table, array $config, int $chunkSize, bool $truncate, bool $dryRun): void
    {
        $source = DB::connection($this->sourceConnection)->table("{$this->schema}.{$table}");
        $targetTable = "{$this->schema}.{$table}";

        $total = (clone $source)->count();

        $this->newLine();
        $this->line("<fg=cyan>[{$table}]</> {$total} baris ditemukan di sumber");

        if ($total === 0 || $dryRun) {
            return;
        }

        if ($truncate) {
            $identitySuffix = $config['identity'] ? ' RESTART IDENTITY' : '';
            DB::connection($this->targetConnection)->statement(
                "TRUNCATE TABLE \"{$this->schema}\".\"{$table}\"{$identitySuffix} CASCADE"
            );
        }
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $columns = $config['columns'];
        $casts = $config['casts'] ?? [];

        $source->orderBy($config['order_by'])
            ->chunk($chunkSize, function ($rows) use ($columns, $casts, $targetTable, $bar) {
                $payload = [];

                foreach ($rows as $row) {
                    $row = (array) $row;
                    $data = [];

                    foreach ($columns as $column) {
                        $value = $row[$column] ?? null;

                        if ($value !== null && isset($casts[$column])) {
                            $data[$column] = match ($casts[$column]) {
                                'bool' => (bool) $value,
                                default => $value,
                            };
                        } else {
                            $data[$column] = $value;
                        }
                    }

                    $payload[] = $data;
                }

                dd($payload);

                DB::connection($this->targetConnection)->table($targetTable)->insert($payload);

                $bar->advance(count($payload));
            });

        $bar->finish();
        $this->newLine();

        if ($config['identity']) {
            $this->resetIdentitySequence($table, $config['columns'][0]);
        }
    }

    /**
     * Insert manual dengan Id eksplisit ke kolom GENERATED BY DEFAULT AS IDENTITY
     * tidak otomatis memajukan sequence Postgres, jadi harus disamakan ke MAX(Id)
     * supaya insert berikutnya (lewat aplikasi) tidak bentrok primary key.
     */
    protected function resetIdentitySequence(string $table, string $idColumn): void
    {
        DB::connection($this->targetConnection)->statement(<<<SQL
            SELECT setval(
                pg_get_serial_sequence('"{$this->schema}"."{$table}"', '{$idColumn}'),
                COALESCE((SELECT MAX("{$idColumn}") FROM "{$this->schema}"."{$table}"), 1),
                true
            )
        SQL);
    }
}
