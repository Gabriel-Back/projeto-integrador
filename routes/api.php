<?php

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
