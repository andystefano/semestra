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
        Schema::create('asignatura_clases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero')->nullable();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_termino')->nullable();
            $table->decimal('horas_cronologicas', 8, 2)->nullable();
            $table->decimal('horas_pedagogicas', 8, 2)->nullable();
            $table->boolean('sin_clase')->default(false);
            $table->text('comentario')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignatura_clases');
    }
};
