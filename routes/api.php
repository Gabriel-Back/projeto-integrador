<?php

use App\Http\Controllers\ComandoController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Endpoints usados pelo semaforo web para ler os comandos que chegaram
| via MQTT. O Laravel escuta o broker (comando `mqtt:escutar`), grava na
| tabela `logs` e o frontend so consome esta API.
|
*/

// Lista paginada (mais recentes primeiro) com filtros opcionais.
Route::get('/logs', [LogController::class, 'index']);

// Ultimo registro gravado.
Route::get('/logs/ultimo', [LogController::class, 'ultimo']);

// Todos os logs com id maior que ?depois_do_id=, em ordem crescente (polling).
Route::get('/logs/novos', [LogController::class, 'novos']);

// Server-Sent Events com cada novo log em tempo real.
Route::get('/logs/stream', [LogController::class, 'stream']);

/*
|--------------------------------------------------------------------------
| Fluxo reverso (Laravel -> ESP32)
|--------------------------------------------------------------------------
|
| A API recebe o comando do frontend, publica em `semaforo/comando` e guarda
| o historico em `comandos_enviados`. O `mqtt:escutar` NAO assina este topico.
|
*/

// Historico paginado dos comandos enviados (filtros opcionais).
Route::get('/comandos', [ComandoController::class, 'index']);

// Recebe e publica um comando para o ESP32.
Route::post('/comandos', [ComandoController::class, 'store']);
