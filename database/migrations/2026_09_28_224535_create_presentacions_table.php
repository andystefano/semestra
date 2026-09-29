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
        Schema::create('presentaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('orden');
            $table->json('contenidos');
            $table->json('diapositivas');
            $table->string('archivo_path');
            $table->string('archivo_nombre');
            $table->timestamps();

            $table->unique(['asignatura_id', 'fecha', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentaciones');
    }
};
