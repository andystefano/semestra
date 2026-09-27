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
        Schema::create('asignatura_fecha_excluidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->timestamps();

            $table->unique(['asignatura_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignatura_fecha_excluidas');
    }
};
