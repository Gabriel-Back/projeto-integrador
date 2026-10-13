<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela `comandos_enviados`, que guarda o historico dos comandos
     * publicados pela API reversa (fluxo Laravel -> ESP32).
     *
     * Esta tabela e separada da `logs`: a `logs` continua guardando apenas os
     * estados que chegam do ESP32.
     */
    public function up(): void
    {
        Schema::create('comandos_enviados', function (Blueprint $table) {
            $table->id();

            // Dispositivo de destino do comando (ex.: semaforo).
            $table->string('dispositivo', 50);

            // Comando enviado (iniciar, parar, intermitente).
            $table->string('comando', 30);

            // Tempos opcionais enviados junto do comando (JSON).
            $table->json('tempos')->nullable();

            // Topico MQTT em que o comando foi (ou seria) publicado.
            $table->string('topico');

            // JSON exatamente como foi publicado no broker.
            $table->json('payload')->nullable();

            // Se a publicacao no broker deu certo.
            $table->boolean('publicado')->default(false);

            // Mensagem de erro quando a publicacao falha.
            $table->text('erro')->nullable();

            // Momento em que a tentativa de envio aconteceu.
            $table->timestamp('enviado_em');

            $table->timestamps();

            $table->index('enviado_em');
            $table->index('dispositivo');
            $table->index('comando');
        });
    }

    /**
     * Remove a tabela `comandos_enviados`.
     */
    public function down(): void
    {
        Schema::dropIfExists('comandos_enviados');
    }
};
