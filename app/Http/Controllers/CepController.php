<?php

namespace App\Http\Controllers;

use App\Exceptions\CepNaoEncontradoException;
use App\Exceptions\ViaCepIndisponivelException;
use App\Http\Requests\BuscarCepRequest;
use App\Http\Resources\EnderecoResource;
use App\Services\ViaCepService;
use Illuminate\Http\JsonResponse;

/**
 * API avulsa de consulta de CEP.
 */
class CepController extends Controller
{
    public function __construct(
        private readonly ViaCepService $viaCepService
    ) {}

    
    public function consultar(BuscarCepRequest $request): JsonResponse
    {
        try {
            $endereco = $this->viaCepService->consultar($request->validated()['cep']);
        } catch (CepNaoEncontradoException) {
            return response()->json(['mensagem' => 'CEP não encontrado.'], 404);
        } catch (ViaCepIndisponivelException) {
            return response()->json([
                'mensagem' => 'Serviço de CEP indisponível. Tente novamente em instantes.',
            ], 502);
        }

        // O contrato do CEP e na raiz do JSON (sem o envelope "data").
        return response()->json((new EnderecoResource($endereco))->resolve($request));
    }
}
