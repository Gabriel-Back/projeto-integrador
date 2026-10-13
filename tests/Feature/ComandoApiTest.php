<?php

namespace Tests\Feature;

use App\Models\ComandoEnviado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Testa a API reversa de comandos (`/api/comandos`).
 *
 * A facade MQTT e substituida por um mock, entao os testes nao dependem de
 * um broker MQTT real.
 */
class ComandoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valida_campos_obrigatorios(): void
    {
        $this->postJson('/api/comandos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dispositivo', 'comando']);
    }

    public function test_rejeita_comando_fora_da_lista(): void
    {
        $this->postJson('/api/comandos', [
            'dispositivo' => 'semaforo',
            'comando' => 'explodir',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comando');
    }

    public function test_rejeita_tempos_fora_do_intervalo_e_incompletos(): void
    {
        $this->postJson('/api/comandos', [
            'dispositivo' => 'semaforo',
            'comando' => 'iniciar',
            'tempos' => [
                'vermelho' => 0,
                'amarelo' => 61,
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tempos.vermelho', 'tempos.amarelo', 'tempos.verde']);
    }

    public function test_publica_comando_no_topico_configurado(): void
    {
        $cliente = Mockery::mock(MqttClientContract::class);

        MQTT::shouldReceive('connection')->once()->with('publicador')->andReturn($cliente);
        MQTT::shouldReceive('disconnect')->once()->with('publicador');

        $topico = (string) config('mqtt-client.topic_comando');

        $cliente->shouldReceive('publish')
            ->once()
            ->with(
                $topico,
                Mockery::on(function (string $payload): bool {
                    $dados = json_decode($payload, true);

                    return $dados['dispositivo'] === 'semaforo'
                        && $dados['comando'] === 'iniciar'
                        && $dados['tempos'] === ['vermelho' => 3, 'amarelo' => 1, 'verde' => 3];
                }),
                MqttClient::QOS_AT_LEAST_ONCE,
                false
            );

        $resposta = $this->postJson('/api/comandos', [
            'dispositivo' => 'semaforo',
            'comando' => 'iniciar',
            'tempos' => ['vermelho' => 3, 'amarelo' => 1, 'verde' => 3],
        ]);

        $resposta->assertStatus(201)
            ->assertJsonPath('data.dispositivo', 'semaforo')
            ->assertJsonPath('data.comando', 'iniciar')
            ->assertJsonPath('data.publicado', true)
            ->assertJsonPath('data.topico', $topico);

        $this->assertDatabaseHas('comandos_enviados', [
            'dispositivo' => 'semaforo',
            'comando' => 'iniciar',
            'publicado' => true,
        ]);

        // O historico fica em `comandos_enviados`, nao em `logs`.
        $this->assertDatabaseCount('logs', 0);
    }

    public function test_publica_comando_sem_tempos(): void
    {
        $cliente = Mockery::mock(MqttClientContract::class);

        MQTT::shouldReceive('connection')->once()->with('publicador')->andReturn($cliente);
        MQTT::shouldReceive('disconnect')->once()->with('publicador');

        $cliente->shouldReceive('publish')
            ->once()
            ->with(
                (string) config('mqtt-client.topic_comando'),
                Mockery::on(function (string $payload): bool {
                    $dados = json_decode($payload, true);

                    return $dados === ['dispositivo' => 'semaforo', 'comando' => 'parar'];
                }),
                MqttClient::QOS_AT_LEAST_ONCE,
                false
            );

        $this->postJson('/api/comandos', [
            'dispositivo' => 'semaforo',
            'comando' => 'parar',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.tempos', null)
            ->assertJsonPath('data.publicado', true);
    }

    public function test_falha_do_broker_retorna_503_e_grava_a_falha(): void
    {
        MQTT::shouldReceive('connection')
            ->once()
            ->with('publicador')
            ->andThrow(new \RuntimeException('broker fora do ar'));
        MQTT::shouldReceive('disconnect')->once()->with('publicador');

        $resposta = $this->postJson('/api/comandos', [
            'dispositivo' => 'semaforo',
            'comando' => 'parar',
        ]);

        $resposta->assertStatus(503)
            ->assertJsonPath('command.publicado', false);

        $this->assertDatabaseHas('comandos_enviados', [
            'dispositivo' => 'semaforo',
            'comando' => 'parar',
            'publicado' => false,
        ]);

        $registro = ComandoEnviado::first();
        $this->assertNotNull($registro);
        $this->assertNotNull($registro->erro);
    }

    public function test_lista_comandos_com_filtros(): void
    {
        ComandoEnviado::create([
            'dispositivo' => 'semaforo',
            'comando' => 'iniciar',
            'topico' => 'semaforo/comando',
            'payload' => ['dispositivo' => 'semaforo', 'comando' => 'iniciar'],
            'publicado' => true,
            'enviado_em' => now()->subMinute(),
        ]);

        ComandoEnviado::create([
            'dispositivo' => 'outro',
            'comando' => 'parar',
            'topico' => 'semaforo/comando',
            'payload' => ['dispositivo' => 'outro', 'comando' => 'parar'],
            'publicado' => true,
            'enviado_em' => now(),
        ]);

        $this->getJson('/api/comandos')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/comandos?dispositivo=semaforo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.dispositivo', 'semaforo');

        $this->getJson('/api/comandos?comando=parar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comando', 'parar');
    }
}
