<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida o CEP em `GET /api/cep/{cep}` e `POST /api/cep`.
 *
 * O CEP pode vir com ou sem hifen/pontos/espacos (ex.: 01001-000, 01001000,
 * 01.001-000). Tudo que nao for digito e removido antes da validacao, que
 * exige exatamente 8 numeros.
 */
class BuscarCepRequest extends FormRequest
{
    /**
     * Rota publica (sem autenticacao).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o CEP antes de validar, aceitando tanto o parametro da rota
     * (GET) quanto o corpo JSON (POST).
     */
    protected function prepareForValidation(): void
    {
        $cep = $this->route('cep') ?? $this->input('cep');

        $this->merge([
            'cep' => preg_replace('/\D/', '', (string) $cep),
        ]);
    }

    /**
     * Regras de validacao do CEP.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cep' => ['required', 'string', 'digits:8'],
        ];
    }

    /**
     * Mensagens amigaveis (em portugues).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cep.required' => 'Informe o CEP.',
            'cep.digits' => 'CEP inválido. Informe 8 números.',
        ];
    }

    /**
     * Garante 422 em JSON mesmo quando o cliente nao envia o header
     * `Accept: application/json` (caso contrario o Laravel redirecionaria).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
