<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Membangun koneksi SQL Server (source) & PostgreSQL (target) secara dinamis
 * dari kredensial yang dikirim lewat UI (bukan dari .env), lalu menyediakan
 * introspeksi tabel dan proses copy data generik (tidak hardcode nama tabel).
 */
class DynamicDatabaseMigrator
{
    protected const SOURCE_CONNECTION = 'ui_sqlsrv_dynamic';

    protected const TARGET_CONNECTION = 'ui_pgsql_dynamic';

    protected const MAINTENANCE_CONNECTION = 'ui_pgsql_maintenance';

    public function __construct(protected array $source, protected array $target)
    {
        config([
            'database.connections.'.self::SOURCE_CONNECTION => [
                'driver' => 'sqlsrv',
                'host' => $source['host'],
                'port' => $source['port'],
                'database' => $source['database'],
                'username' => $source['username'],
                'password' => $source['password'] ?? '',
                'charset' => 'utf8',
                'trust_server_certificate' => 'true',
                'encrypt' => 'yes',
            ],
            'database.connections.'.self::TARGET_CONNECTION => [
                'driver' => 'pgsql',
                'host' => $target['host'],
                'port' => $target['port'],
                'database' => $target['database'],
                'username' => $target['username'],
                'password' => $target['password'] ?? '',
                'charset' => 'utf8',
                'search_path' => ($target['schema'] ?? 'public').',public',
                'sslmode' => 'prefer',
            ],
        ]);

        DB::purge(self::SOURCE_CONNECTION);
        DB::purge(self::TARGET_CONNECTION);
    }

    public function testSource(): void
    {
        DB::connection(self::SOURCE_CONNECTION)->getPdo();
    }

    public function testTarget(): void
    {
        DB::connection(self::TARGET_CONNECTION)->getPdo();
    }

    /**
     * Daftar tabel di schema source beserta estimasi jumlah baris (cepat,
     * pakai sys.partitions supaya tidak full-scan tabel besar).
     */
    public function listSourceTables(string $schema): array
    {
        $rows = DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
            SELECT s.name AS schema_name, t.name AS table_name, SUM(p.rows) AS row_count
            FROM sys.tables t
            JOIN sys.schemas s ON t.schema_id = s.schema_id
            JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
            WHERE s.name = ?
            GROUP BY s.name, t.name
            ORDER BY t.name
        SQL, [$schema]);

        return array_map(fn ($row) => [
            'name' => $row->table_name,
            'row_count' => (int) $row->row_count,
        ], $rows);
    }

    /**
     * Copy seluruh baris satu tabel dari source ke target. Kolom ditentukan
     * dari INFORMATION_SCHEMA source, jadi tidak perlu tahu struktur tabel
     * di muka. Kolom bertipe `bit` dicast ke boolean untuk Postgres.
     */
    /**
     * Ambil daftar nama kolom & kolom bertipe `bit` (buat dicast ke boolean)
     * dari source. Dipakai bareng oleh migrateTable() dan migrateChunk().
     */
    protected function loadColumnMeta(string $schema, string $table): array
    {
        $columns = DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
            SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        SQL, [$schema, $table]);

        if (empty($columns)) {
            throw new RuntimeException("Tabel {$schema}.{$table} tidak ditemukan di source.");
        }

        $columnNames = array_map(fn ($c) => $c->COLUMN_NAME, $columns);
        $boolColumns = array_map(
            fn ($c) => $c->COLUMN_NAME,
            array_filter($columns, fn ($c) => $c->DATA_TYPE === 'bit')
        );

        return [$columnNames, $boolColumns];
    }

    protected function castRow(array $row, array $columnNames, array $boolColumns): array
    {
        $data = [];

        foreach ($columnNames as $column) {
            $value = $row[$column] ?? null;

            if ($value !== null && in_array($column, $boolColumns, true)) {
                $value = (bool) $value;
            }

            $data[$column] = $value;
        }

        return $data;
    }

    public function migrateTable(
        string $sourceSchema,
        string $table,
        string $targetSchema,
        bool $truncate,
        int $chunkSize = 1000
    ): array {
        [$columnNames, $boolColumns] = $this->loadColumnMeta($sourceSchema, $table);
        $orderColumn = $columnNames[0];

        $targetTable = "{$targetSchema}.{$table}";

        if ($truncate) {
            DB::connection(self::TARGET_CONNECTION)->statement(
                "TRUNCATE TABLE \"{$targetSchema}\".\"{$table}\" CASCADE"
            );
        }

        $migrated = 0;

        DB::connection(self::SOURCE_CONNECTION)
            ->table("{$sourceSchema}.{$table}")
            ->orderBy($orderColumn)
            ->chunk($chunkSize, function ($rows) use (&$migrated, $columnNames, $boolColumns, $targetTable) {
                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = $this->castRow((array) $row, $columnNames, $boolColumns);
                }

                $this->insertInSafeBatches($targetTable, $payload, count($columnNames));

                $migrated += count($payload);
            });

        $this->resetIdentityIfNeeded($targetSchema, $table);

        return ['table' => $table, 'rows_migrated' => $migrated];
    }

    /**
     * PostgreSQL membatasi 1 statement maksimal 65.535 parameter terikat.
     * Untuk tabel yang sangat lebar (banyak kolom), insert satu batch besar
     * bisa saja melewati batas itu — jadi dipecah lagi jadi sub-batch yang
     * aman berdasar jumlah kolomnya.
     */
    protected function insertInSafeBatches(string $targetTable, array $payload, int $columnCount): void
    {
        $safeBatchSize = max(1, intdiv(60000, max(1, $columnCount)));

        foreach (array_chunk($payload, $safeBatchSize) as $batch) {
            DB::connection(self::TARGET_CONNECTION)->table($targetTable)->insert($batch);
        }
    }

    /**
     * Migrasi SATU potongan (chunk) tabel, dipanggil berulang oleh client
     * (offset naik tiap panggilan) supaya progres per baris kelihatan di UI
     * dan setiap request tetap singkat/aman dari timeout, alih-alih satu
     * request raksasa untuk seluruh tabel seperti migrateTable().
     */
    public function migrateChunk(
        string $sourceSchema,
        string $table,
        string $targetSchema,
        int $offset,
        int $chunkSize,
        bool $truncateFirst
    ): array {
        [$columnNames, $boolColumns] = $this->loadColumnMeta($sourceSchema, $table);
        $orderColumn = $columnNames[0];
        $targetTable = "{$targetSchema}.{$table}";

        if ($truncateFirst) {
            DB::connection(self::TARGET_CONNECTION)->statement(
                "TRUNCATE TABLE \"{$targetSchema}\".\"{$table}\" CASCADE"
            );
        }

        $rows = DB::connection(self::SOURCE_CONNECTION)
            ->table("{$sourceSchema}.{$table}")
            ->orderBy($orderColumn)
            ->skip($offset)
            ->take($chunkSize)
            ->get();

        $migrated = 0;

        if ($rows->isNotEmpty()) {
            $payload = [];

            foreach ($rows as $row) {
                $payload[] = $this->castRow((array) $row, $columnNames, $boolColumns);
            }

            $this->insertInSafeBatches($targetTable, $payload, count($columnNames));
            $migrated = count($payload);
        }

        // Kurang dari chunk size = sudah baris terakhir, tidak perlu request lagi.
        $done = $migrated < $chunkSize;

        if ($done) {
            $this->resetIdentityIfNeeded($targetSchema, $table);
        }

        return [
            'migrated' => $migrated,
            'next_offset' => $offset + $migrated,
            'done' => $done,
        ];
    }

    /**
     * Insert manual dengan Id eksplisit ke kolom GENERATED BY DEFAULT AS IDENTITY
     * tidak memajukan sequence Postgres, jadi disamakan ke MAX(Id) setelahnya.
     */
    protected function resetIdentityIfNeeded(string $schema, string $table): void
    {
        try {
            $identityColumn = DB::connection(self::TARGET_CONNECTION)->selectOne(<<<'SQL'
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND is_identity = 'YES'
                LIMIT 1
            SQL, [$schema, $table]);
        } catch (Throwable) {
            return;
        }

        if (! $identityColumn) {
            return;
        }

        $column = $identityColumn->column_name;

        DB::connection(self::TARGET_CONNECTION)->statement(<<<SQL
            SELECT setval(
                pg_get_serial_sequence('"{$schema}"."{$table}"', '{$column}'),
                COALESCE((SELECT MAX("{$column}") FROM "{$schema}"."{$table}"), 1),
                true
            )
        SQL);
    }

    /**
     * Buat database target kalau belum ada. Karena database-nya sendiri belum
     * tentu ada, koneksi ini terpisah dari TARGET_CONNECTION — nyambung ke
     * database "postgres" bawaan (maintenance DB) memakai host/kredensial target.
     */
    public function createDatabaseIfMissing(): array
    {
        config([
            'database.connections.'.self::MAINTENANCE_CONNECTION => [
                'driver' => 'pgsql',
                'host' => $this->target['host'],
                'port' => $this->target['port'],
                'database' => 'postgres',
                'username' => $this->target['username'],
                'password' => $this->target['password'] ?? '',
                'charset' => 'utf8',
                'sslmode' => 'prefer',
            ],
        ]);
        DB::purge(self::MAINTENANCE_CONNECTION);

        $exists = DB::connection(self::MAINTENANCE_CONNECTION)->selectOne(
            'SELECT 1 AS found FROM pg_database WHERE datname = ?',
            [$this->target['database']]
        );

        if ($exists) {
            return ['created' => false, 'message' => "Database \"{$this->target['database']}\" sudah ada."];
        }

        $quoted = '"'.str_replace('"', '""', $this->target['database']).'"';
        DB::connection(self::MAINTENANCE_CONNECTION)->statement("CREATE DATABASE {$quoted}");

        return ['created' => true, 'message' => "Database \"{$this->target['database']}\" berhasil dibuat."];
    }

    /**
     * Introspeksi tabel-tabel source lalu jalankan CREATE SCHEMA/CREATE TABLE
     * yang setara di target. Idempotent (IF NOT EXISTS) — tidak akan menimpa
     * atau menghapus tabel yang sudah ada beserta isinya.
     */
    public function createSchema(string $sourceSchema, array $tables, string $targetSchema): array
    {
        DB::connection(self::TARGET_CONNECTION)->statement(
            "CREATE SCHEMA IF NOT EXISTS \"{$targetSchema}\""
        );

        $results = [];

        foreach ($tables as $table) {
            $sql = null;

            try {
                $columns = DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
                    SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH,
                           NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_DEFAULT
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY ORDINAL_POSITION
                SQL, [$sourceSchema, $table]);

                if (empty($columns)) {
                    throw new RuntimeException('Tabel tidak ditemukan di source.');
                }

                $identityColumns = array_map(
                    fn ($r) => $r->COLUMN_NAME,
                    DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
                        SELECT c.name AS COLUMN_NAME
                        FROM sys.columns c
                        JOIN sys.tables t ON c.object_id = t.object_id
                        JOIN sys.schemas s ON t.schema_id = s.schema_id
                        WHERE s.name = ? AND t.name = ? AND c.is_identity = 1
                    SQL, [$sourceSchema, $table])
                );

                $pkColumns = array_map(
                    fn ($r) => $r->COLUMN_NAME,
                    DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
                        SELECT kcu.COLUMN_NAME
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                        JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                            ON kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                            AND kcu.TABLE_SCHEMA = tc.TABLE_SCHEMA
                        WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                            AND tc.TABLE_SCHEMA = ? AND tc.TABLE_NAME = ?
                        ORDER BY kcu.ORDINAL_POSITION
                    SQL, [$sourceSchema, $table])
                );

                $sql = $this->buildCreateTableSql($targetSchema, $table, $columns, $identityColumns, $pkColumns);

                if (str_contains($sql, 'gen_random_uuid()')) {
                    $this->ensurePgcryptoExtension();
                }

                DB::connection(self::TARGET_CONNECTION)->statement($sql);

                $results[] = ['table' => $table, 'status' => 'success', 'sql' => $sql];
            } catch (Throwable $e) {
                $results[] = [
                    'table' => $table,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'sql' => $sql,
                ];
            }
        }

        return $results;
    }

    /**
     * Buat FOREIGN KEY dari source ke tabel-tabel yang diminta. Dipanggil
     * TERPISAH dari createSchema() (bukan per-tabel) supaya tidak masalah
     * kalau tabel yang direferensikan kebetulan diproses belakangan dalam
     * batch yang sama. Idempotent (dilewati kalau constraint dengan nama
     * sama sudah ada) dan aman kalau tabel referensi belum ada di target
     * (dilaporkan sebagai "skipped", bukan error yang menghentikan proses).
     */
    public function createForeignKeys(string $sourceSchema, array $tables, string $targetSchema): array
    {
        $results = [];

        foreach ($tables as $table) {
            $rows = DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
                SELECT
                    fk.name AS fk_name,
                    pc.name AS parent_column,
                    tr.name AS ref_table,
                    rc.name AS ref_column,
                    fk.delete_referential_action_desc AS on_delete,
                    fk.update_referential_action_desc AS on_update,
                    fkc.constraint_column_id AS col_order
                FROM sys.foreign_keys fk
                JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                JOIN sys.tables tp ON fk.parent_object_id = tp.object_id
                JOIN sys.schemas sch_parent ON tp.schema_id = sch_parent.schema_id
                JOIN sys.columns pc ON pc.object_id = fkc.parent_object_id AND pc.column_id = fkc.parent_column_id
                JOIN sys.tables tr ON fk.referenced_object_id = tr.object_id
                JOIN sys.columns rc ON rc.object_id = fkc.referenced_object_id AND rc.column_id = fkc.referenced_column_id
                WHERE sch_parent.name = ? AND tp.name = ?
                ORDER BY fk.name, fkc.constraint_column_id
            SQL, [$sourceSchema, $table]);

            if (empty($rows)) {
                continue;
            }

            foreach ($this->groupForeignKeyRows($rows) as $fkName => $fk) {
                $results[] = $this->applyForeignKey($table, $fkName, $fk, $targetSchema);
            }
        }

        return $results;
    }

    protected function groupForeignKeyRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->fk_name]['parent_columns'][] = $row->parent_column;
            $grouped[$row->fk_name]['ref_columns'][] = $row->ref_column;
            $grouped[$row->fk_name]['ref_table'] = $row->ref_table;
            $grouped[$row->fk_name]['on_delete'] = $row->on_delete;
            $grouped[$row->fk_name]['on_update'] = $row->on_update;
        }

        return $grouped;
    }

    protected function applyForeignKey(string $table, string $fkName, array $fk, string $targetSchema): array
    {
        $sql = null;

        try {
            $refTableExists = DB::connection(self::TARGET_CONNECTION)->selectOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$targetSchema, $fk['ref_table']]
            );

            if (! $refTableExists) {
                return [
                    'table' => $table,
                    'fk' => $fkName,
                    'status' => 'skipped',
                    'message' => "Tabel referensi \"{$targetSchema}\".\"{$fk['ref_table']}\" belum ada di target — buat tabel itu juga lalu ulangi.",
                ];
            }

            $alreadyExists = DB::connection(self::TARGET_CONNECTION)->selectOne(
                'SELECT 1 FROM pg_constraint WHERE conname = ?',
                [$fkName]
            );

            if ($alreadyExists) {
                return ['table' => $table, 'fk' => $fkName, 'status' => 'exists'];
            }

            $parentCols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $fk['parent_columns']));
            $refCols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $fk['ref_columns']));
            $onDelete = str_replace('_', ' ', $fk['on_delete']);
            $onUpdate = str_replace('_', ' ', $fk['on_update']);

            $sql = "ALTER TABLE \"{$targetSchema}\".\"{$table}\" "
                ."ADD CONSTRAINT \"{$fkName}\" FOREIGN KEY ({$parentCols}) "
                ."REFERENCES \"{$targetSchema}\".\"{$fk['ref_table']}\" ({$refCols}) "
                ."ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

            DB::connection(self::TARGET_CONNECTION)->statement($sql);

            return ['table' => $table, 'fk' => $fkName, 'status' => 'success', 'sql' => $sql];
        } catch (Throwable $e) {
            return ['table' => $table, 'fk' => $fkName, 'status' => 'error', 'message' => $e->getMessage(), 'sql' => $sql];
        }
    }

    /**
     * Stored procedure T-SQL tidak bisa diterjemahkan otomatis ke PL/pgSQL
     * (sintaks & semantik jauh berbeda), jadi cuma dideteksi & dilaporkan
     * supaya user tahu perlu migrasi manual — tidak dieksekusi apapun.
     */
    public function listStoredProcedures(string $schema): array
    {
        try {
            $rows = DB::connection(self::SOURCE_CONNECTION)->select(<<<'SQL'
                SELECT ROUTINE_NAME
                FROM INFORMATION_SCHEMA.ROUTINES
                WHERE ROUTINE_TYPE = 'PROCEDURE' AND ROUTINE_SCHEMA = ?
                ORDER BY ROUTINE_NAME
            SQL, [$schema]);
        } catch (Throwable) {
            return [];
        }

        return array_map(fn ($r) => $r->ROUTINE_NAME, $rows);
    }

    /**
     * gen_random_uuid() (padanan NEWID()) butuh extension pgcrypto. Dicoba
     * aktifkan otomatis; kalau role target tidak punya izin, dibiarkan lanjut
     * — CREATE TABLE-nya nanti akan gagal dengan pesan jelas soal fungsi
     * tidak ditemukan, bukan gagal diam-diam.
     */
    protected function ensurePgcryptoExtension(): void
    {
        try {
            DB::connection(self::TARGET_CONNECTION)->statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        } catch (Throwable) {
            // sengaja dibiarkan lanjut, lihat docblock di atas
        }
    }

    protected function buildCreateTableSql(
        string $schema,
        string $table,
        array $columns,
        array $identityColumns,
        array $pkColumns
    ): string {
        $lines = [];

        foreach ($columns as $column) {
            $pgType = $this->mapSqlServerType(
                $column->DATA_TYPE,
                $column->CHARACTER_MAXIMUM_LENGTH,
                $column->NUMERIC_PRECISION,
                $column->NUMERIC_SCALE
            );

            $isIdentity = in_array($column->COLUMN_NAME, $identityColumns, true);
            $default = $this->translateDefault($column->COLUMN_DEFAULT, $pgType);

            $line = "    \"{$column->COLUMN_NAME}\" {$pgType}";

            if ($isIdentity) {
                $line .= ' GENERATED BY DEFAULT AS IDENTITY';
            }

            if ($column->IS_NULLABLE === 'NO') {
                $line .= ' NOT NULL';
            }

            if (! $isIdentity && $default !== null) {
                $line .= " DEFAULT {$default}";
            }

            $lines[] = $line;
        }

        if (! empty($pkColumns)) {
            $quoted = implode(', ', array_map(fn ($c) => "\"{$c}\"", $pkColumns));
            $lines[] = "    CONSTRAINT \"PK_{$table}\" PRIMARY KEY ({$quoted})";
        }

        $body = implode(",\n", $lines);

        return "CREATE TABLE IF NOT EXISTS \"{$schema}\".\"{$table}\" (\n{$body}\n);";
    }

    protected function mapSqlServerType(string $dataType, ?int $maxLength, ?int $precision, ?int $scale): string
    {
        return match ($dataType) {
            'bit' => 'boolean',
            'tinyint', 'smallint' => 'smallint',
            'int' => 'integer',
            'bigint' => 'bigint',
            'decimal', 'numeric' => "numeric({$precision},{$scale})",
            'money' => 'numeric(19,4)',
            'smallmoney' => 'numeric(10,4)',
            'float' => 'double precision',
            'real' => 'real',
            'char', 'nchar' => $maxLength && $maxLength > 0 ? "char({$maxLength})" : 'char(1)',
            'varchar', 'nvarchar' => $maxLength === -1 || $maxLength === null ? 'text' : "varchar({$maxLength})",
            'text', 'ntext' => 'text',
            'date' => 'date',
            'datetime', 'datetime2', 'smalldatetime' => 'timestamp',
            'datetimeoffset' => 'timestamptz',
            'time' => 'time',
            'uniqueidentifier' => 'uuid',
            'varbinary', 'binary', 'image' => 'bytea',
            'xml' => 'xml',
            default => 'text',
        };
    }

    /**
     * Fungsi T-SQL umum yang punya padanan AMAN & jelas di Postgres. Di luar
     * daftar ini (ekspresi kompleks, fungsi custom) sengaja tetap dilewati —
     * lebih aman tidak ada default daripada default yang salah semantik.
     */
    protected const FUNCTION_DEFAULT_MAP = [
        'getdate()' => 'CURRENT_TIMESTAMP',
        'getutcdate()' => "(now() at time zone 'utc')",
        'sysdatetime()' => 'CURRENT_TIMESTAMP',
        'sysutcdatetime()' => "(now() at time zone 'utc')",
        'newid()' => 'gen_random_uuid()',
        'suser_sname()' => 'current_user',
    ];

    /**
     * Menerjemahkan default literal (angka/string/0-1 bit) dan sejumlah
     * fungsi T-SQL umum (lihat FUNCTION_DEFAULT_MAP). Default berupa
     * ekspresi/fungsi lain di luar daftar itu tetap dilewati.
     */
    protected function translateDefault(?string $sqlServerDefault, string $pgType): ?string
    {
        if ($sqlServerDefault === null) {
            return null;
        }

        $value = trim($sqlServerDefault);

        while (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $value = trim(substr($value, 1, -1));
        }

        if (isset(self::FUNCTION_DEFAULT_MAP[strtolower($value)])) {
            return self::FUNCTION_DEFAULT_MAP[strtolower($value)];
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            if ($pgType === 'boolean') {
                return $value === '0' ? 'false' : 'true';
            }

            return $value;
        }

        if (preg_match("/^N?'(.*)'$/s", $value, $matches)) {
            return "'".str_replace("'", "''", $matches[1])."'";
        }

        return null;
    }

    /**
     * Daftar tabel di schema target beserta jumlah baris — dipakai untuk
     * panel Truncate Table.
     */
    public function listTargetTables(string $schema): array
    {
        $rows = DB::connection(self::TARGET_CONNECTION)->select(<<<'SQL'
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
            ORDER BY table_name
        SQL, [$schema]);

        return array_map(function ($row) use ($schema) {
            try {
                $count = DB::connection(self::TARGET_CONNECTION)
                    ->table("{$schema}.{$row->table_name}")
                    ->count();
            } catch (Throwable) {
                $count = null;
            }

            return ['name' => $row->table_name, 'row_count' => $count];
        }, $rows);
    }

    public function truncateTables(string $schema, array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            try {
                DB::connection(self::TARGET_CONNECTION)->statement(
                    "TRUNCATE TABLE \"{$schema}\".\"{$table}\" CASCADE"
                );
                $results[] = ['table' => $table, 'status' => 'success'];
            } catch (Throwable $e) {
                $results[] = ['table' => $table, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }
}
