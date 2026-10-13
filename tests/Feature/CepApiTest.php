<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Testa a API avulsa de consulta de CEP (`/api/cep`).
 *
 * Todas as chamadas externas sao interceptadas com `Http::fake`, entao os
 * testes nao acessam a internet de verdade.
 */
class CepApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Evita que um teste "polua" o cache dos outros.
        Cache::flush();
    }

    /**
     * Resposta tipica do ViaCEP para o CEP 01001-000.
     *
     * @return array<string, string>
     */
    private function payloadPadrao(): array
    {
        return [
            'cep' => '01001-000',
            'logradouro' => 'Praça da Sé',
            'complemento' => 'lado ímpar',
            'unidade' => '',
            'bairro' => 'Sé',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'ibge' => '3550308',
            'gia' => '1004',
            'ddd' => '11',
            'siafi' => '7107',
        ];
    }

    public function test_consulta_cep_valido_com_hifen(): void
    {
        Http::fake(['*' => Http::response($this->payloadPadrao(), 200)]);

        $this->getJson('/api/cep/01001-000')
            ->assertOk()
            ->assertJsonPath('cep', '01001-000')
            ->assertJsonPath('logradouro', 'Praça da Sé')
            ->assertJsonPath('complemento', 'lado ímpar')
            ->assertJsonPath('bairro', 'Sé')
            ->assertJsonPath('cidade', 'São Paulo')
            ->assertJsonPath('uf', 'SP')
            ->assertJsonPath('estado', 'São Paulo')
            ->assertJsonPath('ibge', '3550308')
            ->assertJsonPath('ddd', '11')
            ->assertJsonPath('endereco_completo', 'Praça da Sé, lado ímpar - Sé, São Paulo/SP, CEP 01001-000');

        // A URL chamada deve conter apenas os 8 digitos (sem hifen).
        Http::assertSent(
            fn (Request $request): bool => $request->url() === 'https://viacep.com.br/ws/01001000/json'
        );
    }

    public function test_consulta_cep_valido_sem_hifen_e_via_post(): void
    {
        Http::fake(['*' => Http::response($this->payloadPadrao(), 200)]);

        // GET sem hifen.
        $this->getJson('/api/cep/01001000')
            ->assertOk()
            ->assertJsonPath('cidade', 'São Paulo');

        // POST com pontos e hifen.
        $this->postJson('/api/cep', ['cep' => '01.001-000'])
            ->assertOk()
            ->assertJsonPath('cep', '01001-000');

        Http::assertSent(
            fn (Request $request): bool => $request->url() === 'https://viacep.com.br/ws/01001000/json'
        );
    }

    public function test_rejeita_cep_invalido(): void
    {
        Http::fake();

        // Letras (some tudo na limpeza).
        $this->getJson('/api/cep/abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('cep');

        // 7 digitos.
        $this->getJson('/api/cep/0100100')
            ->assertStatus(422)
            ->assertJsonValidationErrors('cep')
            ->assertJsonPath('message', 'CEP inválido. Informe 8 números.');

        // Vazio no corpo.
        $this->postJson('/api/cep', ['cep' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cep');

        // Sem o campo.
        $this->postJson('/api/cep', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cep');

        Http::assertNothingSent();
    }

    public function test_cep_inexistente_retorna_404(): void
    {
        // ViaCEP devolve 200 com "erro": true quando o CEP nao existe.
        Http::fake(['*' => Http::response(['erro' => true], 200)]);

        $this->getJson('/api/cep/00000000')
            ->assertStatus(404)
            ->assertJsonPath('mensagem', 'CEP não encontrado.');

        // Variacao com a flag vinda como string.
        Http::fake(['*' => Http::response(['erro' => 'true'], 200)]);

        $this->getJson('/api/cep/99999999')
            ->assertStatus(404)
            ->assertJsonPath('mensagem', 'CEP não encontrado.');
    }

    public function test_viacep_erro_500_retorna_502(): void
    {
        Http::fake(['*' => Http::response(['erro' => true], 500)]);

        $this->getJson('/api/cep/01001000')
            ->assertStatus(502)
            ->assertJsonPath('mensagem', 'Serviço de CEP indisponível. Tente novamente em instantes.');
    }

    public function test_viacep_timeout_retorna_502(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out');
        });

        $this->getJson('/api/cep/01001000')
            ->assertStatus(502)
            ->assertJsonPath('mensagem', 'Serviço de CEP indisponível. Tente novamente em instantes.');
    }

    public function test_segunda_chamada_usa_cache(): void
    {
        $chamadas = 0;

        Http::fake(function () use (&$chamadas) {
            $chamadas++;

            return Http::response($this->payloadPadrao(), 200);
        });

        $this->getJson('/api/cep/01001000')->assertOk();
        $this->getJson('/api/cep/01001000')->assertOk();

        // A segunda chamada veio do cache: o HTTP foi feito uma unica vez.
        $this->assertSame(1, $chamadas);
        Http::assertSentCount(1);
    }

    public function test_endereco_completo_sem_lixo_quando_campos_vazios(): void
    {
        Http::fake(['*' => Http::response([
            'cep' => '69900-000',
            'logradouro' => '',
            'complemento' => '',
            'bairro' => '',
            'localidade' => 'Rio Branco',
            'uf' => 'AC',
            'ibge' => '1200401',
            'ddd' => '68',
        ], 200)]);

        $resposta = $this->getJson('/api/cep/69900000')->assertOk();

        $this->assertSame('Rio Branco/AC, CEP 69900-000', $resposta->json('endereco_completo'));
    }
}
