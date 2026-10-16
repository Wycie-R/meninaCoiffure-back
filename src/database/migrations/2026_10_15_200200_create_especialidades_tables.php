<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-07: Especialidades del personal (relación muchos a muchos).
     */
    public function up(): void
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_especialidad', function (Blueprint $table) {
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades')->cascadeOnDelete();
            $table->primary(['personal_id', 'especialidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_especialidad');
        Schema::dropIfExists('especialidades');
    }
};
