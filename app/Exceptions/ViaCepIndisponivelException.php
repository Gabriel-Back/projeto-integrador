<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Lancada quando o ViaCEP esta inacessivel (falha de rede, timeout ou 5xx).
 * O controller traduz isso em HTTP 502.
 */
class ViaCepIndisponivelException extends RuntimeException
{
    public function __construct(string $message = 'ViaCEP indisponível.', ?Throwable $previous = null)
    {
        parent::__construct($message, 502, $previous);
    }
}
