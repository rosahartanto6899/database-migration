<?php

namespace App\Http\Controllers;

use App\Services\DynamicDatabaseMigrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class MigrationToolController extends Controller
{
    public function index()
    {
        return view('migration.index', [
            'defaults' => [
                'source' => [
                    'host' => env('SQLSRV_HOST', ''),
                    'port' => (int) env('SQLSRV_PORT', 1433),
                    'database' => env('SQLSRV_DATABASE', ''),
                    'username' => env('SQLSRV_USERNAME', ''),
                    'password' => env('SQLSRV_PASSWORD', ''),
                    'schema' => 'dbo',
                ],
                'target' => [
                    'host' => env('DB_HOST', ''),
                    'port' => (int) env('DB_PORT', 5432),
                    'database' => env('DB_DATABASE', ''),
                    'username' => env('DB_USERNAME', ''),
                    'password' => env('DB_PASSWORD', ''),
                    'schema' => 'dbo',
                ],
            ],
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $data = $this->validateCredentials($request);

        $migrator = new DynamicDatabaseMigrator($data['source'], $data['target']);

        $result = [
            'source' => ['ok' => false, 'message' => null],
            'target' => ['ok' => false, 'message' => null],
            'tables' => [],
        ];

        try {
            $migrator->testSource();
            $result['source']['ok'] = true;
        } catch (Throwable $e) {
            $result['source']['message'] = $this->cleanErrorMessage($e);
        }

        try {
            $migrator->testTarget();
            $result['target']['ok'] = true;
        } catch (Throwable $e) {
            $result['target']['message'] = $this->cleanErrorMessage($e);
        }

        if ($result['source']['ok']) {
            try {
                $result['tables'] = $migrator->listSourceTables($data['source']['schema']);
            } catch (Throwable $e) {
                $result['source']['message'] = $this->cleanErrorMessage($e);
                $result['source']['ok'] = false;
            }
        }

        return response()->json($result);
    }

    public function createDatabase(Request $request): JsonResponse
    {
        $data = $this->validateCredentials($request);

        $migrator = new DynamicDatabaseMigrator($data['source'], $data['target']);

        try {
            $result = $migrator->createDatabaseIfMissing();

            return response()->json(['ok' => true] + $result);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $this->cleanErrorMessage($e)], 422);
        }
    }

    public function createSchema(Request $request): JsonResponse
    {
        $data = $this->validateCredentials($request);

        $validated = $request->validate([
            'tables' => 'required|array|min:1',
            'tables.*' => 'required|string',
        ]);

        $migrator = new DynamicDatabaseMigrator($data['source'], $data['target']);

        try {
            $results = $migrator->createSchema($data['source']['schema'], $validated['tables'], $data['target']['schema']);
            $procedures = $migrator->listStoredProcedures($data['source']['schema']);

            return response()->json(['results' => $results, 'stored_procedures' => $procedures]);
        } catch (Throwable $e) {
            return response()->json(['message' => $this->cleanErrorMessage($e)], 422);
        }
    }

    public function targetTables(Request $request): JsonResponse
    {
        $data = $this->validateCredentials($request);

        $migrator = new DynamicDatabaseMigrator($data['source'], $data['target']);

        try {
            return response()->json(['tables' => $migrator->listTargetTables($data['target']['schema'])]);
        } catch (Throwable $e) {
            return response()->json(['message' => $this->cleanErrorMessage($e)], 422);
        }
    }

    public function truncate(Request $request): JsonResponse
    {
        $data = $this->validateCredentials($request);

        $validated = $request->validate([
            'tables' => 'required|array|min:1',
            'tables.*' => 'required|string',
        ]);

        $migrator = new DynamicDatabaseMigrator($data['source'], $data['target']);

        try {
            $results = $migrator->truncateTables($data['target']['schema'], $validated['tables']);

            return response()->json(['results' => $results]);
        } catch (Throwable $e) {
            return response()->json(['message' => $this->cleanErrorMessage($e)], 422);
        }
    }

    public function migrate(Request $request): JsonResponse
    {
        $data = $this->validateCredentials($request);

        $validated = $request->validate([
            'tables' => 'required|array|min:1',
            'tables.*' => 'required|string',
            'truncate' => 'boolean',
        ]);

        set_time_limit(300);

        $migrator = new DynamicDatabaseMigrator($data['source'], $data['target']);

        $results = [];

        foreach ($validated['tables'] as $table) {
            try {
                $migrated = $migrator->migrateTable(
                    $data['source']['schema'],
                    $table,
                    $data['target']['schema'],
                    (bool) ($validated['truncate'] ?? false)
                );

                $results[] = [
                    'table' => $table,
                    'status' => 'success',
                    'rows_migrated' => $migrated['rows_migrated'],
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'table' => $table,
                    'status' => 'error',
                    'message' => $this->cleanErrorMessage($e),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    protected function validateCredentials(Request $request): array
    {
        try {
            return $request->validate([
                'source.host' => 'required|string|max:255',
                'source.port' => 'required|integer',
                'source.database' => 'required|string|max:255',
                'source.username' => 'required|string|max:255',
                'source.password' => 'nullable|string|max:255',
                'source.schema' => 'required|string|max:128',
                'target.host' => 'required|string|max:255',
                'target.port' => 'required|integer',
                'target.database' => 'required|string|max:255',
                'target.username' => 'required|string|max:255',
                'target.password' => 'nullable|string|max:255',
                'target.schema' => 'required|string|max:128',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    protected function cleanErrorMessage(Throwable $e): string
    {
        // Ambil baris pertama saja supaya pesan driver PDO yang panjang tetap ringkas di UI.
        return trim(strtok($e->getMessage(), "\n")) ?: $e->getMessage();
    }
}
