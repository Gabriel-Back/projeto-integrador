<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela `logs`, que guarda cada mensagem MQTT recebida.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();

            // Topico MQTT de origem da mensagem (ex.: semaforo/estado).
            $table->string('topico');

            // Payload bruto, exatamente como chegou pelo broker.
            $table->text('payload');

            // Versao normalizada do comando (sem aspas, minuscula, etc.).
            $table->string('comando')->nullable();

            // Momento em que o Laravel recebeu a mensagem.
            $table->timestamp('recebido_em');

            $table->timestamps();

            // Indice para acelerar consultas por periodo de recebimento.
            $table->index('recebido_em');
        });
    }

    /**
     * Remove a tabela `logs`.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
