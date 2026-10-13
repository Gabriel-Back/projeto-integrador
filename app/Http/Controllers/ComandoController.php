<?php

namespace App\Http\Controllers;

use App\Enums\ComandoSemaforo;
use App\Exceptions\FalhaAoPublicarComando;
use App\Http\Requests\EnviarComandoRequest;
use App\Http\Resources\ComandoResource;
use App\Models\ComandoEnviado;
use App\Services\PublicadorDeComando;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Fluxo reverso: recebe um comando do frontend e publica no topico MQTT
 * do ESP32 (via {@see PublicadorDeComando}).
 */
class ComandoController extends Controller
{
    public function __construct(
        private readonly PublicadorDeComando $publicador
    ) {}

    /**
     * Lista paginada do historico de comandos, mais recentes primeiro.
     *
     * Filtros opcionais via query string: `dispositivo` e `comando`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $consulta = ComandoEnviado::query();

        if ($request->filled('dispositivo')) {
            $consulta->where('dispositivo', trim((string) $request->query('dispositivo')));
        }

        if ($request->filled('comando')) {
            $consulta->where('comando', trim((string) $request->query('comando')));
        }

        $porPagina = (int) $request->query('per_page', 50);
        $porPagina = max(1, min($porPagina, 200));

        $comandos = $consulta
            ->orderByDesc('enviado_em')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();

        return ComandoResource::collection($comandos);
    }

    /**
     * Valida o comando, publica no broker e grava o historico.
     *
     * Respostas:
     *   - 201 quando o comando e publicado;
     *   - 422 quando a validacao falha (padrao do Laravel);
     *   - 503 quando o broker MQTT esta indisponivel.
     */
    public function store(EnviarComandoRequest $request): JsonResponse
    {
        $dados = $request->validated();

        try {
            $registro = $this->publicador->publicar(
                $dados['dispositivo'],
                ComandoSemaforo::from($dados['comando']),
                $dados['tempos'] ?? null,
            );
        } catch (FalhaAoPublicarComando $e) {
            return response()->json([
                'message' => 'Broker MQTT indisponivel. O comando foi registrado, mas nao foi publicado.',
                'erro' => $e->getMessage(),
                'command' => new ComandoResource($e->comando),
            ], 503);
        }

        return (new ComandoResource($registro))
            ->response()
            ->setStatusCode(201);
    }
}
