<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('rfid_cards', 'rfid_cards_uid_unique')) {
            Schema::table('rfid_cards', function (Blueprint $table) {
                $table->dropUnique('rfid_cards_uid_unique');
            });
        }

        if (! Schema::hasIndex('rfid_cards', 'rfid_cards_uid_index')) {
            Schema::table('rfid_cards', function (Blueprint $table) {
                $table->index('uid', 'rfid_cards_uid_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('rfid_cards', 'rfid_cards_uid_unique')) {
            Schema::table('rfid_cards', function (Blueprint $table) {
                $table->unique('uid', 'rfid_cards_uid_unique');
            });
        }
    }
};
