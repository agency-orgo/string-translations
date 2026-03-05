<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('string-translations.database.table');

        Schema::table($tableName, function (Blueprint $table) {
            $table->boolean('auto_translated')->default(false)->after('value');
        });
    }

    public function down(): void
    {
        $tableName = config('string-translations.database.table');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('auto_translated');
        });
    }
};
