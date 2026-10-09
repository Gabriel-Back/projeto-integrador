<?php

namespace App\Console\Commands;

use App\Models\Log as LogModel;
use App\Services\InterpretadorDeMensagem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

/**
 * Assina o topico de estado do semaforo e grava cada mensagem recebida
 * na tabela `logs`. E um processo continuo: se o broker cair (ou ainda
 * nao tiver subido), reconecta a cada 3 segundos sem encerrar o processo.
 */
class EscutarMqtt extends Command
{
    /**
     * Assinatura do comando Artisan.
     *
     * @var string
     */
    protected $signature = 'mqtt:escutar';

    /**
     * Descricao do comando.
     *
     * @var string
     */
    protected $description = 'Escuta o topico MQTT do semaforo e grava cada mensagem na tabela logs';

    /**
     * Controla o loop principal. Vira false quando recebemos SIGINT/SIGTERM.
     */
    protected bool $rodando = true;

    /**
     * Intervalo (em segundos) entre tentativas de reconexao.
     */
    protected int $intervaloReconexao = 3;

    /**
     * Executa o comando.
     */
    public function handle(InterpretadorDeMensagem $interpretador): int
    {
        $topico = (string) config('mqtt-client.topic_estado', 'semaforo/estado');

        $this->info("Escutando o topico [{$topico}] em {$this->descricaoBroker()} ...");

        $this->registrarSinais();

        while ($this->rodando) {
            $mqtt = null;

            try {
                // MQTT::connection() ja cria e conecta o cliente.
                $mqtt = MQTT::connection();

                $mqtt->subscribe($topico, function (string $topicoRecebido, string $payload) use ($interpretador) {
                    $this->persistir($topicoRecebido, $payload, $interpretador);
                }, MqttClient::QOS_AT_LEAST_ONCE);

                $this->line('Conectado ao broker. Aguardando mensagens...');

                // Bloqueia aqui ate a conexao cair ou recebermos um sinal.
                $mqtt->loop(true);
            } catch (\Throwable $e) {
                // Nao derruba o processo: apenas registra e tenta de novo.
                Log::error('Falha na conexao/loop MQTT. Tentando novamente.', [
                    'erro' => $e->getMessage(),
                    'excecao' => $e::class,
                ]);

                $this->error('MQTT indisponivel: '.$e->getMessage());
            } finally {
                // Garante que a conexao sera descartada antes do proximo ciclo.
                try {
                    MQTT::disconnect();
                } catch (\Throwable $e) {
                    // Ignora falhas ao desconectar.
                }
            }

            if ($this->rodando) {
                $this->line("Reconectando em {$this->intervaloReconexao}s...");
                sleep($this->intervaloReconexao);
            }
        }

        $this->info('Encerrado com seguranca.');

        return self::SUCCESS;
    }

    /**
     * Grava a mensagem bruta e os campos interpretados (JSON do ESP32 ou
     * comando de texto) na tabela logs.
     */
    protected function persistir(string $topico, string $payload, InterpretadorDeMensagem $interpretador): void
    {
        try {
            $atributos = $interpretador->interpretar($payload);

            $log = LogModel::create(array_merge([
                'topico' => $topico,
                'payload' => $payload,
                'recebido_em' => now(),
            ], $atributos));

            $this->info("Recebido #{$log->id} [{$topico}]: {$payload}");
        } catch (\Throwable $e) {
            // Um erro ao salvar nao pode interromper a escuta.
            Log::error('Erro ao salvar mensagem MQTT na tabela logs.', [
                'topico' => $topico,
                'payload' => $payload,
                'erro' => $e->getMessage(),
            ]);

            $this->error('Erro ao salvar mensagem: '.$e->getMessage());
        }
    }

    /**
     * Registra handlers para SIGINT/SIGTERM (Linux/macOS) e Ctrl+C (Windows).
     */
    protected function registrarSinais(): void
    {
        // Linux/macOS: pcntl.
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);

            $encerrar = function () {
                $this->rodando = false;
                $this->interromperLoop();
            };

            pcntl_signal(SIGINT, $encerrar);
            pcntl_signal(SIGTERM, $encerrar);
        }

        // Windows: handler de console.
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function (int $event) {
                $this->rodando = false;
                $this->interromperLoop();

                return true;
            });
        }
    }

    /**
     * Pede ao cliente MQTT que saia do loop de eventos.
     */
    protected function interromperLoop(): void
    {
        try {
            MQTT::connection()->interrupt();
        } catch (\Throwable $e) {
            // Sem conexao ativa: nada a interromper.
        }
    }

    /**
     * Descricao amigavel do broker configurado (host:porta).
     */
    protected function descricaoBroker(): string
    {
        $host = config('mqtt-client.connections.default.host', '127.0.0.1');
        $port = config('mqtt-client.connections.default.port', 1883);

        return "{$host}:{$port}";
    }
}
