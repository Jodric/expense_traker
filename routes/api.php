<?php

use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\StatisticController;
use Illuminate\Support\Facades\Route;

// Toutes ces routes sont préfixées par /api (déclaré dans bootstrap/app.php)

// stats avant expenses/{...} pour éviter un conflit de matching si on ajoute un show plus tard
Route::get('/expenses/stats', [StatisticController::class, 'index']);
Route::get('/expenses', [ExpenseController::class, 'index']);
Route::post('/expenses', [ExpenseController::class, 'store']);
