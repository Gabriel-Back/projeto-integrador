# projeto-integrador

Aplicação Laravel que faz a ponte entre o semáforo (MQTT) e o frontend web.

O Laravel assina o tópico MQTT `semaforo/estado`, grava **cada mensagem
exatamente como chegou** (payload bruto) na tabela `logs` e disponibiliza esses
dados por uma API JSON. O frontend (JS) deixa de falar MQTT direto e passa a
consumir apenas a API.

No sentido inverso, a API também **envia comandos** para o ESP32: um `POST` em
`/api/comandos` valida o pedido, publica em `semaforo/comando` e guarda o
histórico em `comandos_enviados`.

## Requisitos

- PHP 8.2+
- Composer
- MySQL (ou SQLite)
- Broker MQTT (Mosquitto) acessível por TCP

## Configuração

As variáveis de MQTT ficam no `.env` (o broker público da HiveMQ já vem
configurado):

```env
MQTT_HOST=broker.hivemq.com
MQTT_PORT=1883
MQTT_CLIENT_ID=laravel-semaforo
MQTT_TOPIC_ESTADO=semaforo/estado

# Fluxo reverso (Laravel -> ESP32)
MQTT_TOPIC_COMANDO=semaforo/comando
MQTT_PUBLISH_CLIENT_ID=laravel-semaforo-publisher-4827
```

> O `MQTT_PUBLISH_CLIENT_ID` **precisa** ser diferente do `MQTT_CLIENT_ID`: são
> duas conexões distintas no broker (o listener de `semaforo/estado` e o
> publicador de `semaforo/comando`). Se forem iguais, o broker derruba uma delas.

> `MQTT_PORT` é a porta **TCP** do broker (não confunda com a porta 8000 de
> WebSocket, usada pelo Paho no navegador).

O CORS da API é configurável por `CORS_ALLOWED_ORIGINS` (lista separada por
vírgula; use `*` em desenvolvimento). O timezone da aplicação é
`America/Sao_Paulo`.

## Como rodar

```bash
# 1. Dependências
composer install

# 2. Banco de dados
php artisan migrate

# 3. Servidor HTTP (API + frontend)
php artisan serve

# 4. Escutar o MQTT (processo contínuo, deixe em outro terminal)
php artisan mqtt:escutar
```

O comando `mqtt:escutar` reconecta automaticamente a cada 3 segundos caso o
broker ainda não tenha subido ou caia, e encerra com segurança em
`Ctrl+C`/SIGTERM.

### Formato da mensagem (ESP32)

O ESP32 publica um objeto JSON no tópico `semaforo/estado`:

```json
{
  "dispositivo": "semaforo",
  "cor": "vermelho",
  "porta": 26,
  "estado": true,
  "ciclo": false
}
```

O `payload` bruto é sempre gravado como chegou. Quando o JSON é válido, o
Laravel também preenche `dispositivo`, `cor` (em minúsculas), `porta`, `estado`
e `ciclo`. Campos ausentes ou com tipo errado ficam `null`. Mensagens de texto
(ex.: `iniciar`) continuam sendo gravadas apenas com `payload` e `comando`.

### Testando a publicação

Com o MQTTX, crie uma conexão apontando para `broker.hivemq.com:1883` e publique
no tópico `semaforo/estado`. Ou use o `mosquitto_pub`:

```bash
mosquitto_pub -h broker.hivemq.com -t "semaforo/estado" -m '{"dispositivo":"semaforo","cor":"vermelho","porta":26,"estado":true,"ciclo":false}'
```

Cada publicação deve aparecer no terminal do `mqtt:escutar` e na API.

### Enviando comandos (fluxo reverso)

Além de receber estados, a API também **envia comandos** para o ESP32. Um `POST`
em `/api/comandos` valida o pedido, publica um JSON no tópico `semaforo/comando`
(QoS 1) e grava o histórico na tabela `comandos_enviados`.

```json
{
  "dispositivo": "semaforo",
  "comando": "iniciar",
  "tempos": { "vermelho": 3, "amarelo": 1, "verde": 3 }
}
```

- `dispositivo`: obrigatório, até 50 caracteres.
- `comando`: obrigatório, um de `iniciar`, `parar` ou `intermitente`.
- `tempos`: opcional; se informado, os três campos são obrigatórios e devem ser
  inteiros entre 1 e 60 segundos.

O comando `mqtt:escutar` **não** assina `semaforo/comando` — esse tópico é só de
saída. Como o `payload` bruto do ESP32 continua indo para `logs`, os comandos
enviados ficam separados em `comandos_enviados`.

## API

Todos os endpoints retornam JSON com o formato:

```json
{
  "id": 12,
  "topico": "semaforo/estado",
  "payload": "{\"dispositivo\":\"semaforo\",\"cor\":\"vermelho\",\"porta\":26,\"estado\":true,\"ciclo\":false}",
  "comando": null,
  "dispositivo": "semaforo",
  "cor": "vermelho",
  "porta": 26,
  "estado": true,
  "ciclo": false,
  "recebido_em": "2026-10-08T14:32:10-03:00"
}
```

> `porta` é devolvido como número e `estado`/`ciclo` como booleanos de verdade
> (não strings), prontos para o semáforo digital em JS espelhar o ESP32.

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/logs` | Lista paginada, mais recentes primeiro. Filtros: `?dispositivo=`, `?cor=`, `?estado=`, `?ciclo=`, `?comando=`, `?de=`, `?ate=`, `?per_page=` |
| GET | `/api/logs/ultimo` | Último registro |
| GET | `/api/logs/novos?depois_do_id=123` | Logs com `id > 123`, em ordem crescente (ideal para polling de ~500 ms) |
| GET | `/api/logs/stream` | Server-Sent Events; envia cada novo log em tempo real (suporta `Last-Event-ID`) |
| GET | `/api/comandos` | Histórico paginado dos comandos enviados, mais recentes primeiro. Filtros: `?dispositivo=`, `?comando=`, `?per_page=` |
| POST | `/api/comandos` | Valida e publica um comando em `semaforo/comando` (QoS 1) e grava o histórico |
| GET | `/api/cep/{cep}` | Consulta um CEP no ViaCEP (aceita com ou sem hífen/pontos/espaços) |
| POST | `/api/cep` | Igual ao anterior, mas o CEP vai no corpo JSON: `{ "cep": "01001-000" }` |

Exemplos com `curl`:

```bash
# Lista paginada
curl "http://localhost:8000/api/logs"

# Filtros por dispositivo/cor/estado/ciclo
curl "http://localhost:8000/api/logs?dispositivo=semaforo&cor=vermelho&estado=true&ciclo=false"

# Filtros por período
curl "http://localhost:8000/api/logs?comando=parar&de=2026-10-01&ate=2026-10-08"

# Último registro
curl "http://localhost:8000/api/logs/ultimo"

# Novos desde o id 123
curl "http://localhost:8000/api/logs/novos?depois_do_id=123"

# Stream SSE (fica aberto recebendo eventos)
curl -N "http://localhost:8000/api/logs/stream"
```

Exemplos de comandos (`curl`):

```bash
# Enviar comando simples
curl -X POST "http://localhost:8000/api/comandos" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"dispositivo":"semaforo","comando":"iniciar"}'

# Enviar comando com tempos personalizados
curl -X POST "http://localhost:8000/api/comandos" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"dispositivo":"semaforo","comando":"iniciar","tempos":{"vermelho":3,"amarelo":1,"verde":3}}'

# Listar histórico
curl "http://localhost:8000/api/comandos?comando=iniciar"
```

Respostas possíveis:

- **201** – comando validado, publicado no broker e registrado.
- **422** – payload inválido (validação padrão do Laravel).
- **503** – broker MQTT indisponível; o registro é salvo com `publicado: false`
  e o campo `erro` preenchido.

Para inspecionar o comando chegando no broker, assine `semaforo/comando` no
MQTTX (ou `mosquitto_sub -h broker.hivemq.com -t "semaforo/comando" -v`).

> **SSE no `php artisan serve`:** o servidor embutido do PHP é single-thread por
> padrão, então o `/api/logs/stream` pode bloquear outras requisições. Para
> desenvolvimento, defina `PHP_CLI_SERVER_WORKERS=4` no `.env` antes de subir o
> `php artisan serve`.

## Consulta de CEP (API avulsa)

API independente do semáforo/MQTT: não usa a tabela `logs` nem o broker e não
cria tabelas. Consulta o ViaCEP e devolve o endereço. A URL base e o tempo de
cache (24h) ficam em `config/services.php` (`VIACEP_BASE_URI` e
`VIACEP_CACHE_TTL` no `.env`).

O CEP é aceito com ou sem hífen/pontos/espaços (`01001-000`, `01001000`,
`01.001-000`); tudo que não for dígito é removido antes de exigir 8 números.

```bash
# CEP pela URL
curl "http://localhost:8000/api/cep/01001000"

# CEP com hífen (mesmo resultado)
curl "http://localhost:8000/api/cep/01001-000"

# CEP pelo corpo JSON
curl -X POST "http://localhost:8000/api/cep" \
  -H "Content-Type: application/json" \
  -d '{"cep":"01.001-000"}'
```

Resposta de sucesso (`200`):

```json
{
  "cep": "01001-000",
  "logradouro": "Praça da Sé",
  "complemento": "lado ímpar",
  "bairro": "Sé",
  "cidade": "São Paulo",
  "uf": "SP",
  "estado": "São Paulo",
  "ibge": "3550308",
  "ddd": "11",
  "endereco_completo": "Praça da Sé, lado ímpar - Sé, São Paulo/SP, CEP 01001-000"
}
```

Erros:

- **422** – CEP inválido: `{ "mensagem": "CEP inválido. Informe 8 números." }`
- **404** – CEP inexistente: `{ "mensagem": "CEP não encontrado." }`
- **502** – ViaCEP fora do ar/timeout:
  `{ "mensagem": "Serviço de CEP indisponível. Tente novamente em instantes." }`

## Testes

```bash
php artisan test
# ou
composer test
```

Os testes cobrem:

- `NormalizadorDeComando` (regras de normalização do comando).
- `InterpretadorDeMensagem` (JSON do ESP32, campos faltando, tipos errados, texto).
- Endpoints `/api/logs` (incluindo os novos filtros), `/api/logs/ultimo` e `/api/logs/novos`.
- Endpoints `/api/comandos` (validação, publicação no tópico configurado com a
  facade MQTT substituída por mock, histórico e falha do broker → 503).
- Endpoints `/api/cep` (validação/formatação do CEP, sucesso, 404, 502 e uso de
  cache), sempre com `Http::fake` — sem acessar a internet.

## Estrutura relevante

```
app/
├── Console/Commands/EscutarMqtt.php       # comando mqtt:escutar (só recebe estados)
├── Enums/ComandoSemaforo.php              # comandos aceitos (iniciar, parar, intermitente)
├── Exceptions/
│   ├── FalhaAoPublicarComando.php         # falha ao publicar no broker (vira 503)
│   ├── CepNaoEncontradoException.php      # CEP inexistente (vira 404)
│   └── ViaCepIndisponivelException.php    # ViaCEP fora do ar/timeout (vira 502)
├── Http/Controllers/
│   ├── LogController.php                  # endpoints de /api/logs
│   ├── ComandoController.php              # endpoints de /api/comandos
│   └── CepController.php                  # endpoints de /api/cep
├── Http/Requests/
│   ├── EnviarComandoRequest.php           # validação do POST /api/comandos
│   └── BuscarCepRequest.php               # formata/valida o CEP
├── Http/Resources/
│   ├── LogResource.php                    # formato JSON dos logs
│   ├── ComandoResource.php                # formato JSON dos comandos
│   └── EnderecoResource.php               # formato JSON do endereço (CEP)
├── Models/
│   ├── Log.php                            # tabela logs (recebido do ESP32)
│   └── ComandoEnviado.php                 # tabela comandos_enviados (enviado ao ESP32)
└── Services/
    ├── InterpretadorDeMensagem.php        # interpreta o JSON do ESP32
    ├── NormalizadorDeComando.php          # normalizacao do comando de texto
    ├── PublicadorDeComando.php            # publica em semaforo/comando (fluxo reverso)
    └── ViaCepService.php                  # consulta de CEP no ViaCEP (API avulsa)
config/
├── cors.php                               # CORS da API
├── mqtt-client.php                        # config do broker MQTT (listener + publicador)
└── services.php                           # config do ViaCEP (URL base + cache)
routes/api.php                             # rotas da API
```
