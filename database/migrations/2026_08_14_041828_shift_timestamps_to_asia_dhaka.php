<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private const OFFSET = "6 hours";

    /**
     * The app previously stored all timestamps as naive UTC wall-clock values
     * while the host runs Asia/Dhaka (UTC+6). Shift every existing timestamp
     * column in the public schema by +6 hours so stored values match what the
     * app now writes in Asia/Dhaka.
     */
    public function up(): void
    {
        foreach ($this->timestampColumns() as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(sprintf(
                    'UPDATE %s SET %s = %s + INTERVAL \'%s\'',
                    $this->quote($table),
                    $this->quote($column),
                    $this->quote($column),
                    self::OFFSET,
                ));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->timestampColumns() as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(sprintf(
                    'UPDATE %s SET %s = %s - INTERVAL \'%s\'',
                    $this->quote($table),
                    $this->quote($column),
                    $this->quote($column),
                    self::OFFSET,
                ));
            }
        }
    }

    private function timestampColumns(): array
    {
        $rows = DB::select(
            'SELECT table_name, column_name
             FROM information_schema.columns
             WHERE table_schema = \'public\'
               AND data_type = \'timestamp without time zone\'
             ORDER BY table_name, column_name'
        );

        $result = [];

        foreach ($rows as $row) {
            $result[$row->table_name][] = $row->column_name;
        }

        return $result;
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};
