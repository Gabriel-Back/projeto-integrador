<?php

namespace Tests\Unit;

use App\Services\NormalizadorDeComando;
use PHPUnit\Framework\TestCase;

/**
 * Testa as regras de normalizacao do comando MQTT.
 */
class NormalizadorDeComandoTest extends TestCase
{
    private NormalizadorDeComando $normalizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizador = new NormalizadorDeComando;
    }

    public function test_texto_simples_vira_minusculo(): void
    {
        $this->assertSame('iniciar', $this->normalizador->normalizar('iniciar'));
    }

    public function test_remove_aspas_duplas_das_pontas(): void
    {
        $this->assertSame('iniciar', $this->normalizador->normalizar('"iniciar"'));
    }

    public function test_remove_aspas_simples_das_pontas(): void
    {
        $this->assertSame('iniciar', $this->normalizador->normalizar("'iniciar'"));
    }

    public function test_extrai_msg_do_json_e_deixa_minusculo(): void
    {
        $this->assertSame('iniciar', $this->normalizador->normalizar('{"msg":"INICIAR"}'));
    }

    public function test_mantem_espacos_internos_e_aplica_trim(): void
    {
        $this->assertSame('tempovermelho= 2', $this->normalizador->normalizar(' tempovermelho= 2 '));
    }

    public function test_json_invalido_mantem_texto_original_normalizado(): void
    {
        // JSON invalido: nao da para extrair "msg", entao o texto e mantido
        // (apenas trim + minusculas).
        $this->assertSame('{"msg": iniciar}', $this->normalizador->normalizar('{"msg": iniciar}'));
    }

    public function test_payload_vazio_retorna_null(): void
    {
        $this->assertNull($this->normalizador->normalizar(''));
        $this->assertNull($this->normalizador->normalizar('   '));
        $this->assertNull($this->normalizador->normalizar(null));
    }

    public function test_json_com_msg_numerica_e_convertida_para_string(): void
    {
        $this->assertSame('tempovermelho=5', $this->normalizador->normalizar('{"msg":"tempovermelho=5"}'));
    }
}
