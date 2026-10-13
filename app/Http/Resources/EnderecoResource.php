<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata o endereco retornado pelo ViaCEP no contrato da API de CEP.
 *
 * O Resource recebe o array cru do ViaCEP e faz o mapeamento (ex.: `localidade`
 * -> `cidade`) e a montagem do `endereco_completo`.
 */
class EnderecoResource extends JsonResource
{
    /**
     * Nomes por extenso das unidades federativas (o ViaCEP so devolve a sigla).
     *
     * @var array<string, string>
     */
    private const ESTADOS_POR_UF = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    /**
     * Transforma o endereco do ViaCEP no JSON da API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dados = is_array($this->resource) ? $this->resource : [];

        $logradouro = $this->texto($dados, 'logradouro');
        $complemento = $this->texto($dados, 'complemento');
        $bairro = $this->texto($dados, 'bairro');
        $cidade = $this->texto($dados, 'localidade');
        $uf = $this->texto($dados, 'uf');
        $cep = $this->texto($dados, 'cep');

        return [
            'cep' => $cep,
            'logradouro' => $logradouro,
            'complemento' => $complemento,
            'bairro' => $bairro,
            'cidade' => $cidade,
            'uf' => $uf,
            'estado' => self::ESTADOS_POR_UF[$uf] ?? $uf,
            'ibge' => $this->texto($dados, 'ibge'),
            'ddd' => $this->texto($dados, 'ddd'),
            'endereco_completo' => $this->montarEnderecoCompleto($logradouro, $complemento, $bairro, $cidade, $uf, $cep),
        ];
    }

    /**
     * Monta algo como "Praça da Sé, lado ímpar - Sé, São Paulo/SP, CEP 01001-000",
     * omitindo partes vazias sem deixar virgulas ou hifens sobrando.
     */
    private function montarEnderecoCompleto(
        string $logradouro,
        string $complemento,
        string $bairro,
        string $cidade,
        string $uf,
        string $cep
    ): string {
        // Logradouro e complemento na mesma "linha", separados por virgula.
        $logradouroComComplemento = $logradouro;
        if ($complemento !== '') {
            $logradouroComComplemento = $logradouro === ''
                ? $complemento
                : $logradouro.', '.$complemento;
        }

        // Entre o endereco e o bairro usamos " - ".
        $inicio = implode(' - ', array_filter(
            [$logradouroComComplemento, $bairro],
            static fn (string $parte): bool => $parte !== ''
        ));

        // "Cidade/UF" so aparece se houver pelo menos um dos dois.
        $local = trim($cidade.'/'.$uf, '/');

        $partes = array_filter([
            $inicio,
            $local,
            $cep !== '' ? 'CEP '.$cep : '',
        ], static fn (string $parte): bool => $parte !== '');

        return implode(', ', $partes);
    }

    /**
     * Le um campo do ViaCEP como string, aparando espacos de sobra.
     *
     * @param  array<string, mixed>  $dados
     */
    private function texto(array $dados, string $chave): string
    {
        return trim((string) ($dados[$chave] ?? ''));
    }
}
