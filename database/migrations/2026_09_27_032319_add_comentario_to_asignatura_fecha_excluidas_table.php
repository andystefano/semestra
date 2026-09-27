<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asignatura_fecha_excluidas', function (Blueprint $table) {
            $table->text('comentario')->nullable()->after('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asignatura_fecha_excluidas', function (Blueprint $table) {
            $table->dropColumn('comentario');
        });
    }
};
