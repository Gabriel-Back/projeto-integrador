<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona os campos enviados pelo ESP32 em formato JSON.
     *
     * A migration antiga (`create_logs_table`) nao e alterada: `payload`
     * (mensagem bruta) e `recebido_em` continuam sendo mantidos.
     */
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->string('dispositivo')->nullable();

            $table->string('cor')->nullable();

            $table->unsignedSmallInteger('porta')->nullable();

            $table->boolean('estado')->nullable();

            $table->boolean('ciclo')->nullable();

            // Indices para acelerar os filtros por dispositivo e cor.
            $table->index('dispositivo');
            $table->index('cor');
        });
    }

    /**
     * Remove os campos adicionados.
     */
    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropIndex(['dispositivo']);
            $table->dropIndex(['cor']);

            $table->dropColumn([
                'dispositivo',
                'cor',
                'porta',
                'estado',
                'ciclo',
            ]);
        });
    }
};
