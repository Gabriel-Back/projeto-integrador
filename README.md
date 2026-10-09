# projeto-integrador

Aplicação Laravel que faz a ponte entre o semáforo (MQTT) e o frontend web.

O Laravel assina o tópico MQTT `semaforo/estado`, grava **cada mensagem
exatamente como chegou** (payload bruto) na tabela `logs` e disponibiliza esses
dados por uma API JSON. O frontend (JS) deixa de falar MQTT direto e passa a
consumir apenas a API.

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
```

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

> **SSE no `php artisan serve`:** o servidor embutido do PHP é single-thread por
> padrão, então o `/api/logs/stream` pode bloquear outras requisições. Para
> desenvolvimento, defina `PHP_CLI_SERVER_WORKERS=4` no `.env` antes de subir o
> `php artisan serve`.

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

## Estrutura relevante

```
app/
├── Console/Commands/EscutarMqtt.php   # comando mqtt:escutar
├── Http/Controllers/LogController.php # endpoints da API
├── Http/Resources/LogResource.php     # formato JSON
├── Models/Log.php                     # model da tabela logs
└── Services/
    ├── InterpretadorDeMensagem.php    # interpreta o JSON do ESP32
    └── NormalizadorDeComando.php      # normalizacao do comando de texto
config/
├── cors.php                           # CORS da API
└── mqtt-client.php                    # config do broker MQTT
routes/api.php                         # rotas da API
```
