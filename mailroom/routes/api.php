<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->middleware([AuthenticateApiKey::class, 'role', 'throttle:120,1'])->group(function (): void {
    Route::get('ping', [ApiController::class, 'ping'])->name('ping');
    Route::get('audiences', [ApiController::class, 'audiences'])->name('audiences');
    Route::get('audiences/{audience}/contacts', [ApiController::class, 'contacts'])->name('contacts');
    Route::put('audiences/{audience}/contacts', [ApiController::class, 'upsertContact'])->name('contacts.upsert');
    Route::get('audiences/{audience}/contacts/{email}', [ApiController::class, 'showContact'])->name('contacts.show');
    Route::delete('audiences/{audience}/contacts/{email}', [ApiController::class, 'deleteContact'])->name('contacts.delete');
    Route::post('audiences/{audience}/contacts/{email}/tags', [ApiController::class, 'tags'])->name('contacts.tags');
    Route::get('campaigns', [ApiController::class, 'campaigns'])->name('campaigns');
    Route::get('campaigns/{campaign}/report', [ApiController::class, 'report'])->name('campaigns.report');
    Route::post('messages/send', [ApiController::class, 'send'])->name('messages.send');
});
