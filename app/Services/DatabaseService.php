<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseService
{
    protected string $backupPath;

    public function __construct()
    {
        $this->backupPath = storage_path('app/backups');

        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * Export the database to a SQL dump file and return the file path.
     */
    public function export(): string
    {
        $database = config('database.connections.' . config('database.default'));
        $databaseName = $database['database'];

        $filename = 'minal-ledger-backup-' . date('Y-m-d_His') . '.sql';
        $filePath = $this->backupPath . '/' . $filename;

        $handle = fopen($filePath, 'w');

        fwrite($handle, "-- Minal Ledger Database Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: {$databaseName}\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = DB::select("SHOW TABLES");
        $dbName = $databaseName;

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$dbName}"} ?? reset($table);

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($createTable)) {
                $createSql = $createTable[0]->{'Create Table'} ?? '';
                fwrite($handle, "-- Table: {$tableName}\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($handle, $createSql . ";\n\n");
            }

            $rows = DB::select("SELECT * FROM `{$tableName}`");
            if (!empty($rows)) {
                $columns = array_keys((array) $rows[0]);
                $columnList = implode('`, `', array_map(fn($c) => "`{$c}`", $columns));

                foreach ($rows as $row) {
                    $values = array_map(function ($value) {
                        if ($value === null) return 'NULL';
                        return "'" . addslashes($value) . "'";
                    }, (array) $row);

                    $valueList = implode(', ', $values);
                    fwrite($handle, "INSERT INTO `{$tableName}` ({$columnList}) VALUES ({$valueList});\n");
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($handle, "-- End of backup\n");

        fclose($handle);

        return $filePath;
    }
}
