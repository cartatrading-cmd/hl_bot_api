<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BotConfigController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\BotLogController;
use App\Http\Controllers\Api\BotStateController;
use Illuminate\Support\Facades\Route;

// ── Bot ingestion (called by Python bots, Bearer auth) ─────────────────────
Route::post('bot/logs',  [BotLogController::class,   'ingest']);
Route::post('bot/state', [BotStateController::class, 'ingest']);

// ── Bot configs CRUD + lifecycle ────────────────────────────────────────────
Route::apiResource('bot-configs', BotConfigController::class);
Route::post('bot-configs/{bot_config}/start',          [BotConfigController::class, 'start']);
Route::post('bot-configs/{bot_config}/stop',           [BotConfigController::class, 'stop']);
Route::post('bot-configs/{bot_config}/emergency-stop', [BotConfigController::class, 'emergencyStop']);
Route::get('bot-configs/{bot_config}/command',         [BotConfigController::class, 'command']);

// ── Per-bot state & history ─────────────────────────────────────────────────
Route::get('bot-configs/{bot_config}/snapshot',       [BotController::class,      'snapshot']);
Route::get('bot-configs/{bot_config}/wallet',         [BotController::class,      'wallet']);
Route::get('bot-configs/{bot_config}/history',        [BotController::class,      'history']);
Route::get('bot-configs/{bot_config}/state',          [BotStateController::class, 'show']);
Route::get('bot-configs/{bot_config}/state/history',  [BotStateController::class, 'history']);

// ── Per-bot logs ─────────────────────────────────────────────────────────────
Route::get('bot-configs/{bot_config}/logs',    [BotLogController::class, 'index']);
Route::delete('bot-configs/{bot_config}/logs', [BotLogController::class, 'destroy']);

// ── Trader / leaderboard ─────────────────────────────────────────────────────
Route::get('leaderboard',                  [\App\Http\Controllers\Api\TraderController::class, 'leaderboard']);
Route::get('trader/{address}',             [\App\Http\Controllers\Api\TraderController::class, 'show']);
Route::get('trader/{address}/portfolio',   [\App\Http\Controllers\Api\TraderController::class, 'portfolioHistory']);
