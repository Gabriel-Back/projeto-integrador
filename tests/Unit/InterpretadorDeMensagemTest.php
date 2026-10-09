<?php

namespace Tests\Unit;

use App\Services\InterpretadorDeMensagem;
use App\Services\NormalizadorDeComando;
use PHPUnit\Framework\TestCase;

/**
 * Testa a interpretacao das mensagens MQTT (JSON do ESP32 e texto simples).
 */
class InterpretadorDeMensagemTest extends TestCase
{
    private InterpretadorDeMensagem $interpretador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interpretador = new InterpretadorDeMensagem(new NormalizadorDeComando);
    }

    public function test_json_completo_do_esp32(): void
    {
        $payload = '{"dispositivo":"semaforo","cor":"vermelho","porta":26,"estado":true,"ciclo":false}';

        $resultado = $this->interpretador->interpretar($payload);

        $this->assertSame('semaforo', $resultado['dispositivo']);
        $this->assertSame('vermelho', $resultado['cor']);
        $this->assertSame(26, $resultado['porta']);
        $this->assertTrue($resultado['estado']);
        $this->assertFalse($resultado['ciclo']);
        $this->assertNull($resultado['comando']);
    }

    public function test_cor_e_normalizada_para_minusculas_e_sem_espacos(): void
    {
        $payload = '{"cor":"  VERMELHO  "}';

        $resultado = $this->interpretador->interpretar($payload);

        $this->assertSame('vermelho', $resultado['cor']);
    }

    public function test_json_com_campos_faltando_preenche_apenas_os_presentes(): void
    {
        $payload = '{"dispositivo":"semaforo","cor":"verde"}';

        $resultado = $this->interpretador->interpretar($payload);

        $this->assertSame('semaforo', $resultado['dispositivo']);
        $this->assertSame('verde', $resultado['cor']);
        $this->assertNull($resultado['porta']);
        $this->assertNull($resultado['estado']);
        $this->assertNull($resultado['ciclo']);
    }

    public function test_tipos_errados_viram_null(): void
    {
        $payload = '{"dispositivo":123,"cor":true,"porta":"26","estado":"true","ciclo":1}';

        $resultado = $this->interpretador->interpretar($payload);

        $this->assertNull($resultado['dispositivo']);
        $this->assertNull($resultado['cor']);
        $this->assertNull($resultado['porta']);
        $this->assertNull($resultado['estado']);
        $this->assertNull($resultado['ciclo']);
    }

    public function test_porta_negativa_vira_null(): void
    {
        $resultado = $this->interpretador->interpretar('{"porta":-3}');

        $this->assertNull($resultado['porta']);
    }

    public function test_texto_simples_usa_normalizador_e_deixa_campos_nulos(): void
    {
        $resultado = $this->interpretador->interpretar('iniciar');

        $this->assertSame('iniciar', $resultado['comando']);
        $this->assertNull($resultado['dispositivo']);
        $this->assertNull($resultado['cor']);
        $this->assertNull($resultado['porta']);
        $this->assertNull($resultado['estado']);
        $this->assertNull($resultado['ciclo']);
    }

    public function test_json_invalido_cai_no_normalizador(): void
    {
        // Trailing comma deixa o JSON invalido.
        $resultado = $this->interpretador->interpretar('{"dispositivo": "semaforo",}');

        $this->assertSame('{"dispositivo": "semaforo",}', $resultado['comando']);
        $this->assertNull($resultado['dispositivo']);
        $this->assertNull($resultado['estado']);
    }

    public function test_json_antigo_com_msg_reaproveita_normalizador(): void
    {
        $resultado = $this->interpretador->interpretar('{"msg":"INICIAR"}');

        $this->assertSame('iniciar', $resultado['comando']);
        $this->assertNull($resultado['dispositivo']);
    }

    public function test_json_string_nao_e_objeto_e_tratado_como_texto(): void
    {
        $resultado = $this->interpretador->interpretar('"ligarintermitente"');

        $this->assertSame('ligarintermitente', $resultado['comando']);
        $this->assertNull($resultado['estado']);
    }

    public function test_payload_vazio_retorna_tudo_null(): void
    {
        $resultado = $this->interpretador->interpretar('   ');

        $this->assertNull($resultado['comando']);
        $this->assertNull($resultado['dispositivo']);
    }
}
