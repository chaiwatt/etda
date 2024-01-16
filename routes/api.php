<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DialogflowIntentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'dialogflow'], function () {
    Route::group(['prefix' => 'intent'], function () {
        Route::post('', [DialogflowIntentController::class, 'index'])->name('dialogflow.intent');
        Route::get('create', [DialogflowIntentController::class, 'create'])->name('dialogflow.intent.create');
        // Route::post('detect-intent-text', [DialogflowIntentController::class, 'detectIntentText'])->name('dialogflow.intent.detect-intent-text');
        Route::post('detect-intent-text', [DialogflowIntentController::class, 'anotherDetect'])->name('dialogflow.intent.detect-intent-text');
        Route::post('delete', [DialogflowIntentController::class, 'delete'])->name('dialogflow.intent.delete');
        Route::get('audio-detect', [DialogflowIntentController::class, 'audioDetect'])->name('dialogflow.intent.audio-detect');
        Route::get('list', [DialogflowIntentController::class, 'list'])->name('dialogflow.intent.list');
        Route::get('new-intent-with-entity', [DialogflowIntentController::class, 'newIntentWithEntity'])->name('new-intent-with-entity');
    });
});

