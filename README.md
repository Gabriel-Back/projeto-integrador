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

As variáveis de MQTT ficam no `.env`:

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_CLIENT_ID=laravel-semaforo
MQTT_TOPIC_ESTADO=semaforo/estado
```

> `MQTT_PORT` é a porta **TCP** do broker (não confunda com a porta 8000 de
> WebSocket, usada pelo Paho no navegador). Se você usa o broker do repositório
> `projeto-semaforo/docker`, o listener TCP está em `1880`, então ajuste
> `MQTT_PORT=1880`.

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

### Testando com o MQTTX

1. Crie uma conexão no [MQTTX](https://mqttx.app/) apontando para
   `mqtt://127.0.0.1:1883` (ou a porta TCP do seu broker).
2. Publique no tópico `semaforo/estado`, por exemplo:
   - `iniciar`
   - `parar`
   - `ligarintermitente`
   - `{"msg":"tempovermelho=5"}`
   - `"tempoverde=4"`
3. Cada publicação deve aparecer no terminal do `mqtt:escutar` e na API.

## API

Todos os endpoints retornam JSON com o formato:

```json
{
  "id": 12,
  "topico": "semaforo/estado",
  "payload": "tempovermelho=5",
  "comando": "tempovermelho=5",
  "recebido_em": "2026-10-08T14:32:10-03:00"
}
```

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/logs` | Lista paginada, mais recentes primeiro. Filtros: `?comando=`, `?de=`, `?ate=`, `?per_page=` |
| GET | `/api/logs/ultimo` | Último registro |
| GET | `/api/logs/novos?depois_do_id=123` | Logs com `id > 123`, em ordem crescente (ideal para polling de ~500 ms) |
| GET | `/api/logs/stream` | Server-Sent Events; envia cada novo log em tempo real (suporta `Last-Event-ID`) |

Exemplos com `curl`:

```bash
# Lista paginada
curl "http://localhost:8000/api/logs"

# Filtros
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
- Endpoints `/api/logs`, `/api/logs/ultimo` e `/api/logs/novos`.

## Estrutura relevante

```
app/
├── Console/Commands/EscutarMqtt.php   # comando mqtt:escutar
├── Http/Controllers/LogController.php # endpoints da API
├── Http/Resources/LogResource.php     # formato JSON
├── Models/Log.php                     # model da tabela logs
└── Services/NormalizadorDeComando.php # normalizacao do comando
config/
├── cors.php                           # CORS da API
└── mqtt-client.php                    # config do broker MQTT
routes/api.php                         # rotas da API
```
