<?php

namespace App\Services;

use App\Enums\ComandoSemaforo;
use App\Exceptions\FalhaAoPublicarComando;
use App\Models\ComandoEnviado;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Publica comandos no topico MQTT do ESP32 (fluxo reverso).
 *
 * Usa uma conexao dedicada (`publicador`) com client_id diferente da conexao
 * do comando `mqtt:escutar`, evitando que o broker derrube uma das conexoes.
 * Sempre grava o historico em `comandos_enviados`, mesmo quando falha.
 */
class PublicadorDeComando
{
    /**
     * Publica o comando e retorna o registro salvo.
     *
     * @param  array{vermelho:int|string,amarelo:int|string,verde:int|string}|null  $tempos
     *
     * @throws FalhaAoPublicarComando quando o broker esta indisponivel.
     */
    public function publicar(string $dispositivo, ComandoSemaforo $comando, ?array $tempos = null): ComandoEnviado
    {
        $topico = (string) config('mqtt-client.topic_comando', 'semaforo/comando');
        $conexao = (string) config('mqtt-client.publicador_connection', 'publicador');

        $tempos = $this->normalizarTempos($tempos);

        // Monta a mensagem exatamente como sera publicada.
        $mensagem = [
            'dispositivo' => $dispositivo,
            'comando' => $comando->value,
        ];

        if ($tempos !== null) {
            $mensagem['tempos'] = $tempos;
        }

        $registro = new ComandoEnviado([
            'dispositivo' => $dispositivo,
            'comando' => $comando->value,
            'tempos' => $tempos,
            'topico' => $topico,
            'payload' => $mensagem,
            'enviado_em' => now(),
        ]);

        try {
            $cliente = MQTT::connection($conexao);

            $cliente->publish(
                $topico,
                (string) json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                MqttClient::QOS_AT_LEAST_ONCE,
                false
            );

            $registro->publicado = true;
            $registro->erro = null;
        } catch (Throwable $e) {
            // Grava a falha mesmo sem conseguir publicar.
            $registro->publicado = false;
            $registro->erro = $e->getMessage();
            $registro->save();

            Log::error('Falha ao publicar comando MQTT.', [
                'dispositivo' => $dispositivo,
                'comando' => $comando->value,
                'topico' => $topico,
                'erro' => $e->getMessage(),
            ]);

            throw new FalhaAoPublicarComando(
                $registro,
                'Nao foi possivel publicar o comando no broker MQTT. Verifique se ele esta acessivel e tente novamente.',
                0,
                $e
            );
        } finally {
            // A conexao e curta: publica e desconecta ao final da requisicao.
            $this->desconectar($conexao);
        }

        $registro->save();

        return $registro;
    }

    /**
     * Normaliza os tempos: null quando nao informados, senão inteiros.
     *
     * @param  array<string, mixed>|null  $tempos
     * @return array{vermelho:int,amarelo:int,verde:int}|null
     */
    private function normalizarTempos(?array $tempos): ?array
    {
        if ($tempos === null || $tempos === []) {
            return null;
        }

        return [
            'vermelho' => (int) $tempos['vermelho'],
            'amarelo' => (int) $tempos['amarelo'],
            'verde' => (int) $tempos['verde'],
        ];
    }

    /**
     * Descarta a conexao do publicador sem interromper a requisicao.
     */
    private function desconectar(string $conexao): void
    {
        try {
            MQTT::disconnect($conexao);
        } catch (Throwable $e) {
            // Sem conexao ativa: nada a fazer.
        }
    }
}
