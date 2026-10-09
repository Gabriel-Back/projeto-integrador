<?php

namespace App\Services;

/**
 * Normaliza o payload bruto recebido por MQTT para uma versao padronizada
 * do comando. As mesmas regras sao usadas pelo JS do semaforo:
 *
 *   1. trim                        -> remove espacos das pontas
 *   2. se comeca com "{"           -> tenta json_decode e extrai o campo "msg"
 *   3. remove aspas simples/duplas -> somente das pontas
 *   4. minusculas                  -> comando sempre em lowercase
 *
 * O payload ORIGINAL nunca e alterado; esta classe gera apenas a coluna
 * `comando`, que serve para filtros e depuracao.
 */
class NormalizadorDeComando
{
    /**
     * Retorna o comando normalizado ou null quando o payload for vazio.
     */
    public function normalizar(?string $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        // 1. Remove espacos das pontas.
        $texto = trim($payload);

        if ($texto === '') {
            return null;
        }

        // 2. Se for um JSON, tenta extrair o campo "msg".
        //    Se o JSON for invalido, mantem o texto original.
        if (str_starts_with($texto, '{')) {
            $json = json_decode($texto, true);

            if (is_array($json) && array_key_exists('msg', $json)) {
                $texto = (string) $json['msg'];
            }
        }

        // 3. Remove aspas simples/duplas apenas das pontas (ex.: "iniciar").
        $texto = trim($texto, "\"'");
        $texto = trim($texto);

        if ($texto === '') {
            return null;
        }

        // 4. Comando sempre em minusculas.
        return mb_strtolower($texto);
    }
}
