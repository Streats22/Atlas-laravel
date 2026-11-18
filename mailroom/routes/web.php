<?php

declare(strict_types=1);

use App\Http\Controllers\AbTestController;
use App\Http\Controllers\AudienceController;
use App\Http\Controllers\AudienceFieldController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BuilderController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public: hosted signup form, confirmation, unsubscribe, tracking.
Route::get('/f/{publicId}', [PublicSubscriptionController::class, 'form'])->name('public.form');
Route::post('/f/{publicId}', [PublicSubscriptionController::class, 'subscribe'])
    ->middleware('throttle:10,1')->name('public.subscribe');
Route::get('/confirm/{token}', [PublicSubscriptionController::class, 'confirm'])->name('public.confirm');
Route::get('/u/{token}', [PublicSubscriptionController::class, 'unsubscribePage'])->name('public.unsubscribe');
Route::post('/u/{token}', [PublicSubscriptionController::class, 'unsubscribe'])->name('public.unsubscribe.do');
Route::post('/webhooks/{provider}/{secret}', [WebhookController::class, 'handle'])->middleware('throttle:600,1')->name('webhooks');
Route::get('/p/{slug}', [LandingPageController::class, 'show'])->name('landing.show');
Route::get('/t/o/{token}.gif', [TrackingController::class, 'open'])->name('track.open');
Route::get('/t/c/{token}/{hash}', [TrackingController::class, 'click'])->name('track.click');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', 'role'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('audiences', AudienceController::class);
    Route::get('audiences/{audience}/form', [AudienceController::class, 'form'])->name('audiences.form');

    Route::scopeBindings()->prefix('audiences/{audience}')->name('audiences.')->group(function (): void {
        Route::resource('contacts', ContactController::class)->except(['index', 'show']);
        Route::post('contacts/bulk', [ContactController::class, 'bulk'])->name('contacts.bulk');
        Route::get('contacts/{contact}/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::get('fields', [AudienceFieldController::class, 'index'])->name('fields.index');
        Route::post('fields', [AudienceFieldController::class, 'store'])->name('fields.store');
        Route::put('fields/{field}', [AudienceFieldController::class, 'update'])->name('fields.update');
        Route::delete('fields/{field}', [AudienceFieldController::class, 'destroy'])->name('fields.destroy');
        Route::get('import', [ContactImportController::class, 'create'])->name('import');
        Route::post('import', [ContactImportController::class, 'store'])->name('import.store');
        Route::get('export', [ContactImportController::class, 'export'])->name('export');
        Route::resource('segments', SegmentController::class)->except(['show']);
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/invite', [UserController::class, 'resendInvite'])->name('users.invite');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::post('builder/preview', [BuilderController::class, 'preview'])->name('builder.preview');
    Route::get('media', [BuilderController::class, 'media'])->name('media.index');
    Route::post('media', [BuilderController::class, 'upload'])->name('media.store');
    Route::resource('templates', TemplateController::class)->only(['index', 'show', 'store', 'destroy']);

    Route::resource('automations', AutomationController::class)->except(['edit']);
    Route::scopeBindings()->prefix('automations/{automation}')->name('automations.')->group(function (): void {
        Route::post('status', [AutomationController::class, 'status'])->name('status');
        Route::post('enroll', [AutomationController::class, 'enroll'])->name('enroll');
        Route::post('steps', [AutomationController::class, 'addStep'])->name('steps.store');
        Route::put('steps/{step}', [AutomationController::class, 'updateStep'])->name('steps.update');
        Route::post('steps/{step}/move', [AutomationController::class, 'moveStep'])->name('steps.move');
        Route::delete('steps/{step}', [AutomationController::class, 'deleteStep'])->name('steps.destroy');
    });

    Route::resource('landing-pages', LandingPageController::class)->except(['show']);
    Route::get('landing-pages/{landing_page}/preview', [LandingPageController::class, 'preview'])->name('landing-pages.preview');

    Route::resource('campaigns', CampaignController::class);
    Route::prefix('campaigns/{campaign}')->name('campaigns.')->group(function (): void {
        Route::get('preview', [CampaignController::class, 'preview'])->name('preview');
        Route::get('review', [CampaignController::class, 'review'])->name('review');
        Route::post('test', [CampaignController::class, 'test'])->name('test');
        Route::post('send', [CampaignController::class, 'send'])->name('send');
        Route::post('schedule', [CampaignController::class, 'schedule'])->name('schedule');
        Route::post('unschedule', [CampaignController::class, 'unschedule'])->name('unschedule');
        Route::post('duplicate', [CampaignController::class, 'duplicate'])->name('duplicate');
        Route::put('ab-test', [AbTestController::class, 'update'])->name('ab.update');
        Route::get('variants/{variant}', [AbTestController::class, 'editVariant'])->name('variants.edit')->scopeBindings();
        Route::put('variants/{variant}', [AbTestController::class, 'updateVariant'])->name('variants.update')->scopeBindings();
        Route::post('variants/{variant}/winner', [AbTestController::class, 'pickWinner'])->name('variants.winner')->scopeBindings();
    });
});
