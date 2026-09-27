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
        Schema::create('asignatura_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('dia');
            $table->time('hora_inicio');
            $table->time('hora_termino');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignatura_horarios');
    }
};
