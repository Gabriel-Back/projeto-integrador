<?php

namespace App\Http\Requests;

use App\Enums\ComandoSemaforo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida o payload de `POST /api/comandos`.
 */
class EnviarComandoRequest extends FormRequest
{
    /**
     * Esta rota e publica (sem autenticacao).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validacao do comando.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dispositivo' => ['required', 'string', 'max:50'],
            'comando' => ['required', 'string', Rule::enum(ComandoSemaforo::class)],

            // `tempos` e opcional; se vier, os tres campos sao obrigatorios
            // e devem ser inteiros entre 1 e 60 segundos.
            'tempos' => ['sometimes', 'array'],
            'tempos.vermelho' => ['required_with:tempos', 'integer', 'between:1,60'],
            'tempos.amarelo' => ['required_with:tempos', 'integer', 'between:1,60'],
            'tempos.verde' => ['required_with:tempos', 'integer', 'between:1,60'],
        ];
    }

    /**
     * Mensagens amigaveis (em portugues) para os erros mais comuns.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dispositivo.required' => 'O campo dispositivo e obrigatorio.',
            'dispositivo.max' => 'O dispositivo deve ter no maximo 50 caracteres.',
            'comando.required' => 'O campo comando e obrigatorio.',
            'comando.enum' => 'O comando deve ser um destes: '.implode(', ', ComandoSemaforo::valores()).'.',
            'tempos.vermelho.required_with' => 'Informe o tempo do vermelho (1 a 60 segundos).',
            'tempos.amarelo.required_with' => 'Informe o tempo do amarelo (1 a 60 segundos).',
            'tempos.verde.required_with' => 'Informe o tempo do verde (1 a 60 segundos).',
            'tempos.*.integer' => 'Os tempos devem ser numeros inteiros.',
            'tempos.*.between' => 'Cada tempo deve estar entre 1 e 60 segundos.',
        ];
    }
}
