<?php

namespace App\Http\Resources;

use App\Models\ComandoEnviado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um registro da tabela `comandos_enviados` para a API.
 *
 * @mixin ComandoEnviado
 */
class ComandoResource extends JsonResource
{
    /**
     * Transforma o recurso em um array pronto para o JSON da resposta.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispositivo' => $this->dispositivo,
            'comando' => $this->comando,
            'tempos' => $this->tempos,
            'topico' => $this->topico,
            'payload' => $this->payload,
            'publicado' => $this->publicado,
            'erro' => $this->erro,
            'enviado_em' => $this->enviado_em?->toIso8601String(),
        ];
    }
}
