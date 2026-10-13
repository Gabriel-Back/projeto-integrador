<?php

namespace App\Enums;

/**
 * Comandos aceitos pela API reversa (`POST /api/comandos`).
 *
 * Para adicionar um novo comando, basta incluir um caso aqui: a validacao
 * usa esta lista automaticamente (via {@see \Illuminate\Validation\Rule::enum}).
 */
enum ComandoSemaforo: string
{
    case Iniciar = 'iniciar';
    case Parar = 'parar';
    case Intermitente = 'intermitente';

    /**
     * Lista dos valores aceitos (strings), util para validacoes e documentacao.
     *
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(static fn (self $comando): string => $comando->value, self::cases());
    }
}
