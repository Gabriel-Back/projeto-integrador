<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Representa uma mensagem MQTT recebida do topico do semaforo.
 */
class Log extends Model
{
    use HasFactory;

    /**
     * Nome explicito da tabela (o plural de "Log" ja seria "logs",
     * mas deixamos claro para evitar ambiguidades).
     *
     * @var string
     */
    protected $table = 'logs';

    /**
     * Campos preenchiveis em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'topico',
        'payload',
        'comando',
        'dispositivo',
        'cor',
        'porta',
        'estado',
        'ciclo',
        'recebido_em',
    ];

    /**
     * Casts dos atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'porta' => 'integer',
            'estado' => 'boolean',
            'ciclo' => 'boolean',
            'recebido_em' => 'datetime',
        ];
    }
}
