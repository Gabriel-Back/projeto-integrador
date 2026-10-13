<?php

namespace App\Exceptions;

use App\Models\ComandoEnviado;
use RuntimeException;
use Throwable;

/**
 * Lancada quando o comando foi validado e registrado, mas nao pode ser
 * publicado no broker MQTT. O controller traduz isso em HTTP 503.
 */
class FalhaAoPublicarComando extends RuntimeException
{
    public function __construct(
        public readonly ComandoEnviado $comando,
        string $message = 'Nao foi possivel publicar o comando no broker MQTT.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
