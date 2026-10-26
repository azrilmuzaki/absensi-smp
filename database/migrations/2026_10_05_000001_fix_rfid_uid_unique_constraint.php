<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL memberi nama constraint: rfid_cards_uid_unique
        // Kita drop dulu pakai raw SQL agar aman lintas DBMS
        DB::statement('ALTER TABLE rfid_cards DROP CONSTRAINT IF EXISTS rfid_cards_uid_unique');
        DB::statement('DROP INDEX IF EXISTS rfid_cards_uid_unique');

        // Tambah index biasa (bukan unique) supaya query tetap cepat
        Schema::table('rfid_cards', function (Blueprint $table) {
            if (! $this->indexExists('rfid_cards', 'rfid_cards_uid_index')) {
                $table->index('uid', 'rfid_cards_uid_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rfid_cards', function (Blueprint $table) {
            $table->dropIndex('rfid_cards_uid_index');
            $table->unique('uid');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select('
            SELECT 1 FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ', [$table, $index]);

        return ! empty($result);
    }
};
