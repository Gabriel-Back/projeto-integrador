<?php

namespace App\Http\Controllers;

use App\Http\Resources\LogResource;
use App\Models\Log;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Disponibiliza os logs MQTT para o frontend via API JSON.
 */
class LogController extends Controller
{
    /**
     * Lista paginada dos logs, mais recentes primeiro.
     *
     * Filtros opcionais via query string:
     *   - comando   (igualdade)
     *   - de        (recebido_em >= de)
     *   - ate       (recebido_em <= ate)
     *   - per_page  (itens por pagina, padrao 50)
     */
    public function index(Request $request)
    {
        $consulta = Log::query();

        if ($request->filled('comando')) {
            $consulta->where('comando', $request->query('comando'));
        }

        if ($request->filled('de')) {
            $consulta->where('recebido_em', '>=', $this->interpretarData((string) $request->query('de'), false));
        }

        if ($request->filled('ate')) {
            $consulta->where('recebido_em', '<=', $this->interpretarData((string) $request->query('ate'), true));
        }

        $porPagina = (int) $request->query('per_page', 50);
        $porPagina = max(1, min($porPagina, 200));

        $logs = $consulta
            ->orderByDesc('recebido_em')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();

        return LogResource::collection($logs);
    }

    /**
     * Retorna o ultimo registro gravado (ou `null` se a tabela estiver vazia).
     */
    public function ultimo()
    {
        $log = Log::orderByDesc('recebido_em')
            ->orderByDesc('id')
            ->first();

        if (! $log) {
            return response()->json(['data' => null]);
        }

        return new LogResource($log);
    }

    /**
     * Retorna todos os logs com id maior que `depois_do_id`, em ordem crescente.
     *
     * E o endpoint usado pelo JS no polling (~500 ms) para receber os
     * comandos na ordem correta.
     */
    public function novos(Request $request)
    {
        $request->validate([
            'depois_do_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $depoisDoId = (int) $request->query('depois_do_id', 0);

        $logs = Log::where('id', '>', $depoisDoId)
            ->orderBy('id')
            ->get();

        return LogResource::collection($logs);
    }

    /**
     * Server-Sent Events: envia cada novo log assim que ele aparece no banco.
     *
     * O cliente pode informar o ultimo id recebido pelo header `Last-Event-ID`
     * (padrao do SSE) ou pelo query string `depois_do_id`.
     */
    public function stream(Request $request): StreamedResponse
    {
        $ultimoId = (int) $request->header('Last-Event-ID', $request->query('depois_do_id', 0));

        $cabecalhos = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($ultimoId, $request) {
            // Garante que o PHP nao segure nada em buffer.
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            @set_time_limit(0);

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            // Abre o stream imediatamente para o navegador.
            echo ": conectado\n\n";
            flush();

            $ultimoHeartbeat = time();
            $idAtual = $ultimoId;

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                try {
                    $logs = Log::where('id', '>', $idAtual)
                        ->orderBy('id')
                        ->get();

                    foreach ($logs as $log) {
                        $idAtual = $log->id;
                        $dados = (new LogResource($log))->toArray($request);

                        echo "id: {$log->id}\n";
                        echo "event: log\n";
                        echo 'data: '.json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                    }

                    if ($logs->isNotEmpty()) {
                        flush();
                    }
                } catch (\Throwable $e) {
                    LogFacade::error('Erro no stream SSE de logs.', ['erro' => $e->getMessage()]);
                    break;
                }

                // Comentario periodico para manter a conexao viva.
                if (time() - $ultimoHeartbeat >= 15) {
                    echo ": ping\n\n";
                    flush();
                    $ultimoHeartbeat = time();
                }

                // Verifica novos registros a cada ~300 ms.
                usleep(300000);
            }
        }, 200, $cabecalhos);
    }

    /**
     * Interpreta uma data vinda da query string.
     *
     * Quando a string nao tem horario informado (ex.: "2026-10-08") e
     * $fimDoDia = true, considera 23:59:59 do dia.
     */
    protected function interpretarData(string $valor, bool $fimDoDia): Carbon
    {
        $data = Carbon::parse($valor);

        if ($fimDoDia && ! str_contains($valor, ':')) {
            $data->endOfDay();
        }

        return $data;
    }
}
