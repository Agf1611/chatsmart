<?php


use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\DeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/internal/ai/respond', [\App\Http\Controllers\Api\InternalAiController::class, 'respond'])->middleware('throttle:internal-ai');

Route::middleware(['checkApiKey', 'throttle:message-api'])->group(function () {
    Route::post('/send-message', [ApiController::class, 'messageText']);
    Route::post('/send-media', [ApiController::class, 'messageMedia']);
    Route::post('/send-button', [ApiController::class, 'messageButton']);
    Route::post('/send-template', [ApiController::class, 'messageTemplate']);
    Route::post('/send-list', [ApiController::class, 'messageList']);
    Route::post('/send-poll', [ApiController::class, 'messagePoll']);
    Route::post('check-number', [ApiController::class, 'checkNumber']);
    Route::post('/logout-device', [DeviceController::class, 'logoutDevice']);
    Route::post('/delete-device', [DeviceController::class, 'deleteDevice']);
});
Route::post('/generate-qr', [ApiController::class, 'generateQr'])->middleware('throttle:message-api');
