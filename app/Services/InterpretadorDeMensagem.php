<?php

namespace App\Services;

/**
 * Interpreta o payload bruto recebido por MQTT.
 *
 * O ESP32 publica um objeto JSON com os campos do semaforo:
 *
 *   {"dispositivo":"semaforo","cor":"vermelho","porta":26,"estado":true,"ciclo":false}
 *
 * Mensagens que nao sejam um objeto JSON (ex.: comandos de texto como
 * `iniciar`) continuam sendo tratadas pelo {@see NormalizadorDeComando},
 * gravando apenas `payload` + `comando`.
 *
 * Regras de tipo: qualquer campo ausente ou com tipo errado vira null,
 * sem interromper o processo de escuta.
 */
class InterpretadorDeMensagem
{
    public function __construct(
        private readonly NormalizadorDeComando $normalizador
    ) {}

    /**
     * Retorna os atributos prontos para gravar na tabela `logs`.
     *
     * @return array{
     *     comando: string|null,
     *     dispositivo: string|null,
     *     cor: string|null,
     *     porta: int|null,
     *     estado: bool|null,
     *     ciclo: bool|null
     * }
     */
    public function interpretar(?string $payload): array
    {
        $dados = $this->decodificarJson($payload);

        if ($dados !== null) {
            // JSON de objeto: extrai os campos do ESP32 com validacao de tipo.
            // Se houver uma chave "msg", reaproveita o normalizador para o
            // campo `comando` (compatibilidade com o formato antigo).
            $comando = array_key_exists('msg', $dados)
                ? $this->normalizador->normalizar($payload)
                : null;

            return [
                'comando' => $comando,
                'dispositivo' => $this->texto($dados['dispositivo'] ?? null),
                'cor' => $this->cor($dados['cor'] ?? null),
                'porta' => $this->inteiro($dados['porta'] ?? null),
                'estado' => $this->booleano($dados['estado'] ?? null),
                'ciclo' => $this->booleano($dados['ciclo'] ?? null),
            ];
        }

        // Nao e um objeto JSON valido: trata como comando de texto.
        return [
            'comando' => $this->normalizador->normalizar($payload),
            'dispositivo' => null,
            'cor' => null,
            'porta' => null,
            'estado' => null,
            'ciclo' => null,
        ];
    }

    /**
     * Decodifica o payload quando ele for um objeto JSON (`{...}`).
     *
     * @return array<string, mixed>|null
     */
    private function decodificarJson(?string $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $texto = trim($payload);

        if ($texto === '' || ! str_starts_with($texto, '{')) {
            return null;
        }

        $json = json_decode($texto, true);

        return is_array($json) ? $json : null;
    }

    /**
     * Campo de texto (string) com trim. Vazio/invalido vira null.
     */
    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * Cor em minusculas e sem espacos nas pontas.
     */
    private function cor(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        return $texto === null ? null : mb_strtolower($texto);
    }

    /**
     * Porta inteira e nao negativa.
     */
    private function inteiro(mixed $valor): ?int
    {
        if (! is_int($valor) || $valor < 0) {
            return null;
        }

        return $valor;
    }

    /**
     * Booleano de verdade (nao aceita "true"/"false" como string).
     */
    private function booleano(mixed $valor): ?bool
    {
        return is_bool($valor) ? $valor : null;
    }
}
