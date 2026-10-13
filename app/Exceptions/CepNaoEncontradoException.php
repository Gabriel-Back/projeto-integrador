<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lancada quando o CEP tem formato valido, mas nao existe na base do ViaCEP.
 * O controller traduz isso em HTTP 404.
 */
class CepNaoEncontradoException extends RuntimeException
{
    public function __construct(string $message = 'CEP não encontrado.')
    {
        parent::__construct($message, 404);
    }
}
