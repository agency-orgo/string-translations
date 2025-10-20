<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('string-translations.database.table');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('lang');
            $table->text('value');
            $table->timestamps();
            $table->unique(['key', 'lang']);
        });
    }

    public function down(): void
    {
        $tableName = config('string-translations.database.table');
        Schema::dropIfExists($tableName);
    }
};