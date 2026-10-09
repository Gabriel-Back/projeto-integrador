<?php

namespace App\Http\Resources;

use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um registro da tabela `logs` para a API.
 *
 * @mixin Log
 */
class LogResource extends JsonResource
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
            'topico' => $this->topico,
            'payload' => $this->payload,
            'comando' => $this->comando,
            'dispositivo' => $this->dispositivo,
            'cor' => $this->cor,
            'porta' => $this->porta,
            'estado' => $this->estado,
            'ciclo' => $this->ciclo,
            'recebido_em' => $this->recebido_em?->toIso8601String(),
        ];
    }
}
