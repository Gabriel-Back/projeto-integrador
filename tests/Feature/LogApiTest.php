<?php

namespace Tests\Feature;

use App\Models\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testa os endpoints JSON que expoem os logs MQTT.
 */
class LogApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria tres logs de texto com horarios crescentes (mais antigo primeiro).
     */
    private function criarLogs(): array
    {
        $primeiro = Log::create([
            'topico' => 'semaforo/estado',
            'payload' => 'iniciar',
            'comando' => 'iniciar',
            'recebido_em' => now()->subMinutes(10),
        ]);

        $segundo = Log::create([
            'topico' => 'semaforo/estado',
            'payload' => 'tempovermelho=5',
            'comando' => 'tempovermelho=5',
            'recebido_em' => now()->subMinutes(5),
        ]);

        $terceiro = Log::create([
            'topico' => 'semaforo/estado',
            'payload' => 'parar',
            'comando' => 'parar',
            'recebido_em' => now(),
        ]);

        return [$primeiro, $segundo, $terceiro];
    }

    /**
     * Cria um log no formato JSON do ESP32.
     */
    private function criarLogEsp32(array $atributos = []): Log
    {
        $dados = array_merge([
            'dispositivo' => 'semaforo',
            'cor' => 'vermelho',
            'porta' => 26,
            'estado' => true,
            'ciclo' => false,
        ], $atributos);

        return Log::create(array_merge($dados, [
            'topico' => 'semaforo/estado',
            'payload' => json_encode($dados),
            'recebido_em' => now(),
        ]));
    }

    public function test_lista_logs_em_ordem_decrescente(): void
    {
        [$primeiro, $segundo, $terceiro] = $this->criarLogs();

        $resposta = $this->getJson('/api/logs');

        $resposta->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'topico', 'payload', 'comando',
                        'dispositivo', 'cor', 'porta', 'estado', 'ciclo',
                        'recebido_em',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.id', $terceiro->id)
            ->assertJsonPath('data.1.id', $segundo->id)
            ->assertJsonPath('data.2.id', $primeiro->id);
    }

    public function test_lista_logs_filtra_por_comando(): void
    {
        $this->criarLogs();

        $resposta = $this->getJson('/api/logs?comando=parar');

        $resposta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comando', 'parar');
    }

    public function test_recurso_retorna_campos_do_esp32_tipados(): void
    {
        $this->criarLogEsp32();

        $resposta = $this->getJson('/api/logs');

        $resposta->assertOk()
            ->assertJsonPath('data.0.dispositivo', 'semaforo')
            ->assertJsonPath('data.0.cor', 'vermelho')
            ->assertJsonPath('data.0.porta', 26)
            ->assertJsonPath('data.0.estado', true)
            ->assertJsonPath('data.0.ciclo', false);

        $item = $resposta->json('data.0');

        $this->assertIsInt($item['porta']);
        $this->assertIsBool($item['estado']);
        $this->assertIsBool($item['ciclo']);
    }

    public function test_lista_logs_filtra_por_dispositivo(): void
    {
        $this->criarLogEsp32(['dispositivo' => 'semaforo']);
        $this->criarLogEsp32(['dispositivo' => 'outro']);

        $resposta = $this->getJson('/api/logs?dispositivo=semaforo');

        $resposta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.dispositivo', 'semaforo');
    }

    public function test_lista_logs_filtra_por_cor_ignorando_maiusculas(): void
    {
        $this->criarLogEsp32(['cor' => 'vermelho']);
        $this->criarLogEsp32(['cor' => 'verde']);

        $resposta = $this->getJson('/api/logs?cor=VERMELHO');

        $resposta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cor', 'vermelho');
    }

    public function test_lista_logs_filtra_por_estado(): void
    {
        $ligado = $this->criarLogEsp32(['estado' => true]);
        $this->criarLogEsp32(['estado' => false]);

        $resposta = $this->getJson('/api/logs?estado=true');

        $resposta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ligado->id)
            ->assertJsonPath('data.0.estado', true);
    }

    public function test_lista_logs_filtra_por_ciclo_false(): void
    {
        $this->criarLogEsp32(['ciclo' => true]);
        $comCicloFalso = $this->criarLogEsp32(['ciclo' => false]);

        $resposta = $this->getJson('/api/logs?ciclo=false');

        $resposta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $comCicloFalso->id)
            ->assertJsonPath('data.0.ciclo', false);
    }

    public function test_filtro_booleano_invalido_retorna_422(): void
    {
        $this->getJson('/api/logs?estado=sim')
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    public function test_retorna_o_ultimo_log(): void
    {
        [, , $terceiro] = $this->criarLogs();

        $resposta = $this->getJson('/api/logs/ultimo');

        $resposta->assertOk()
            ->assertJsonPath('data.id', $terceiro->id)
            ->assertJsonPath('data.payload', 'parar');
    }

    public function test_ultimo_retorna_null_quando_vazio(): void
    {
        $resposta = $this->getJson('/api/logs/ultimo');

        $resposta->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_novos_retorna_apenas_ids_maiores_em_ordem_crescente(): void
    {
        [$primeiro, $segundo, $terceiro] = $this->criarLogs();

        $resposta = $this->getJson('/api/logs/novos?depois_do_id='.$primeiro->id);

        $resposta->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $segundo->id)
            ->assertJsonPath('data.1.id', $terceiro->id);
    }

    public function test_novos_valida_depois_do_id(): void
    {
        $this->getJson('/api/logs/novos?depois_do_id=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('depois_do_id');
    }
}
