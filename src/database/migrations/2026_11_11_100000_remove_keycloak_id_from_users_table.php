<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keycloak se quitó del proyecto: el login, el registro y la recuperación de contraseña los
     * hace Laravel con la tabla users. La contraseña sigue pudiendo ser null: las cuentas creadas
     * con Keycloak no tienen una local y la crean con "¿Olvidaste tu contraseña?".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['keycloak_id']);
            $table->dropColumn('keycloak_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_id')->nullable()->unique()->after('email');
        });
    }
};
