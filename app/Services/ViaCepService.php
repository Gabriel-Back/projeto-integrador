<?php

namespace App\Services;

use App\Exceptions\CepNaoEncontradoException;
use App\Exceptions\ViaCepIndisponivelException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consulta CEPs no ViaCEP (https://viacep.com.br).
 *
 * - Usa o cliente HTTP nativo do Laravel (timeout de 5s e 2 tentativas).
 * - Cacheia o endereco por 24h (config `services.viacep.cache_ttl`).
 * - NUNCA cacheia erros nem CEPs inexistentes.
 * - Nao deixa falha de rede/timeout/5xx estourar para o usuario: converte em
 *   {@see ViaCepIndisponivelException} (o controller responde 502).
 */
class ViaCepService
{
    /**
     * Busca o endereco de um CEP ja normalizado (somente digitos, 8 numeros).
     *
     * @return array<string, mixed> Dados crus do ViaCEP.
     *
     * @throws CepNaoEncontradoException quando o CEP tem formato valido mas nao existe.
     * @throws ViaCepIndisponivelException quando o ViaCEP esta inacessivel.
     */
    public function consultar(string $cep): array
    {
        $chave = 'viacep:'.$cep;

        $endereco = Cache::remember(
            $chave,
            (int) config('services.viacep.cache_ttl', 86400),
            fn (): ?array => $this->buscarNoViaCep($cep)
        );

        // `null` significa "nao encontrado". Como o Cache::remember nao guarda
        // null de forma persistente, CEPs inexistentes nunca ficam em cache.
        if ($endereco === null) {
            throw new CepNaoEncontradoException;
        }

        return $endereco;
    }

    /**
     * Faz a chamada ao ViaCEP. Retorna `null` quando o CEP nao existe.
     *
     * @return array<string, mixed>|null
     *
     * @throws ViaCepIndisponivelException
     */
    private function buscarNoViaCep(string $cep): ?array
    {
        $base = rtrim((string) config('services.viacep.base_uri', 'https://viacep.com.br/ws'), '/');
        $url = $base.'/'.$cep.'/json';

        try {
            $resposta = Http::timeout(5)
                ->retry(2, 200)
                ->acceptJson()
                ->get($url);
        } catch (Throwable $e) {
            // Falha de conexao, DNS ou timeout apos as tentativas.
            throw new ViaCepIndisponivelException('Falha ao consultar o ViaCEP.', $e);
        }

        // 5xx indica instabilidade do servico: nao cacheia e responde 502.
        if ($resposta->serverError()) {
            throw new ViaCepIndisponivelException('ViaCEP respondeu com erro '.$resposta->status().'.');
        }

        if ($resposta->ok()) {
            $dados = $resposta->json();

            // O ViaCEP responde 200 com {"erro": true} (ou {"erro": "true"})
            // quando o formato e valido mas o CEP nao existe.
            if (! is_array($dados) || $this->temIndicacaoDeErro($dados)) {
                return null;
            }

            return $dados;
        }

        // Qualquer outro status inesperado (ex.: 4xx) e tratado como inexistente.
        return null;
    }

    /**
     * Detecta a flag de erro do ViaCEP aceitando booleanos e strings
     * ("true", "1", etc.) em qualquer tipo.
     *
     * @param  array<string, mixed>  $dados
     */
    private function temIndicacaoDeErro(array $dados): bool
    {
        if (! array_key_exists('erro', $dados)) {
            return false;
        }

        return filter_var($dados['erro'], FILTER_VALIDATE_BOOLEAN);
    }
}
