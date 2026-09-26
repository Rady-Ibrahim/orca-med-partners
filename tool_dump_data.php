<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = DB::connection();
$database = (string) $connection->getDatabaseName();
$pdo = $connection->getPdo();

$binaryTypes = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit'];

$tables = array_map(
    static fn ($row) => (string) $row->name,
    DB::select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")
);

$sql = "-- orca-med-partners full backup (schema + data)\n";
$sql .= '-- generated: '.date('c')."\n";
$sql .= "-- database: {$database}\n";
$sql .= "-- tables: ".count($tables)."\n\n";
$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
$sql .= "SET AUTOCOMMIT=0;\n";
$sql .= "START TRANSACTION;\n\n";

foreach ($tables as $table) {
    $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");

    if ($create !== null) {
        $ddl = (array) $create;
        $definition = (string) array_values($ddl)[1];
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $definition.";\n\n";
    }
}

$statementCount = 0;
$rowCount = 0;

foreach ($tables as $table) {
    $rows = DB::table($table)->get();
    $rowCount += $rows->count();

    if ($rows->isEmpty()) {
        $sql .= "-- table `{$table}` is empty\n\n";

        continue;
    }

    $binaryColumns = array_map(
        static fn ($row) => (string) $row->COLUMN_NAME,
        DB::select(
            'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND data_type IN ("binary","varbinary","tinyblob","blob","mediumblob","longblob","bit")',
            [$table]
        )
    );

    $sql .= "-- ----------------------------\n";
    $sql .= "-- table `{$table}` (".$rows->count()." rows)\n";
    $sql .= "-- ----------------------------\n";

    foreach ($rows->chunk(200) as $chunk) {
        $values = [];

        foreach ($chunk as $row) {
            $cells = [];

            foreach ((array) $row as $column => $value) {
                if ($value === null) {
                    $cells[] = 'NULL';

                    continue;
                }

                if (in_array((string) $column, $binaryColumns, true)) {
                    $cells[] = '0x'.bin2hex((string) $value);

                    continue;
                }

                if (is_bool($value)) {
                    $cells[] = $value ? '1' : '0';

                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $cells[] = (string) $value;

                    continue;
                }

                $cells[] = $pdo->quote((string) $value);
            }

            $values[] = '('.implode(',', $cells).')';
        }

        $columns = implode(',', array_map(
            static fn ($column) => '`'.$column.'`',
            array_keys((array) $chunk->first())
        ));

        $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $values).";\n";
        $statementCount++;
    }

    $sql .= "\n";
}

$sql .= "COMMIT;\n";
$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

$target = __DIR__.'/database/backup_data_'.date('Ymd_His').'.sql';

if (file_put_contents($target, $sql) === false) {
    fwrite(STDERR, "FAILED to write {$target}\n");
    exit(1);
}

printf(
    "dumped %d tables, %d rows, %d INSERT statements -> %s (%.1f KB)\n",
    count($tables),
    $rowCount,
    $statementCount,
    basename($target),
    filesize($target) / 1024
);
