<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'ECM API is running',
    ]);
});

Route::get('/test-db-schema', function () {
    $searchPath = config('database.connections.pgsql.search_path', 'ecm_app');
    $schema = trim(explode(',', (string) $searchPath)[0]);

    try {
        DB::connection()->getPdo();
        $databaseName = DB::connection()->getDatabaseName();

        $rows = DB::select(
            'select table_name from information_schema.tables
             where table_schema = ? and table_type = ?
             order by table_name',
            [$schema, 'BASE TABLE']
        );

        $tables = array_map(fn ($r) => $r->table_name, $rows);

        return response()->view('db_schema_test', [
            'ok' => true,
            'schema' => $schema,
            'database' => $databaseName,
            'tables' => $tables,
            'error' => null,
        ]);
    } catch (\Throwable $e) {
        return response()->view('db_schema_test', [
            'ok' => false,
            'schema' => $schema,
            'database' => null,
            'tables' => [],
            'error' => $e->getMessage(),
        ], 500);
    }
});
