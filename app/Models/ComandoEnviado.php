<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Representa um comando publicado pela API reversa (Laravel -> ESP32).
 *
 * Diferente do model {@see Log}, que guarda o que CHEGA do ESP32, este model
 * guarda o que SAI para o topico `semaforo/comando`.
 */
class ComandoEnviado extends Model
{
    use HasFactory;

    /**
     * Nome explicito da tabela.
     *
     * @var string
     */
    protected $table = 'comandos_enviados';

    /**
     * Campos preenchiveis em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dispositivo',
        'comando',
        'tempos',
        'topico',
        'payload',
        'publicado',
        'erro',
        'enviado_em',
    ];

    /**
     * Casts dos atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tempos' => 'array',
            'payload' => 'array',
            'publicado' => 'boolean',
            'enviado_em' => 'datetime',
        ];
    }
}
