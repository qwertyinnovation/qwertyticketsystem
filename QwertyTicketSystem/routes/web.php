<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\ServiceTicketPublicController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::get('/login', [LoginController::class, 'create']);
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::get('/error-preview/{code}', function (Request $request, int $code) {
    abort_unless(app()->environment(['local', 'testing']), 404);

    if (! in_array($code, [403, 404, 419, 500, 503], true)) {
        abort(404);
    }

    return response()->view("errors.{$code}", [
        'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException($code),
    ], $code);
})->whereNumber('code')->name('errors.preview');

Route::get('/submit-ticket/{publicLink:token}', [ServiceTicketPublicController::class, 'create'])->name('service-tickets.public.create');
Route::post('/submit-ticket/{publicLink:token}', [ServiceTicketPublicController::class, 'store'])->name('service-tickets.public.store');
Route::get('/track-ticket/{token}', [ServiceTicketPublicController::class, 'show'])->name('service-tickets.public.track');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:'.User::PERMISSION_VIEW_DASHBOARD)
        ->name('dashboard');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('live-chat')
        ->middleware('permission:'.User::PERMISSION_VIEW_DASHBOARD)
        ->group(function (): void {
            Route::get('/', [ProjectChatController::class, 'index'])->name('project-chat.index');
            Route::get('/projects/{project}', [ProjectChatController::class, 'show'])->name('project-chat.show');
            Route::post('/projects/{project}/messages', [ProjectChatController::class, 'store'])->name('project-chat.store');
            Route::put('/projects/{project}/messages/{projectMessage}', [ProjectChatController::class, 'update'])->name('project-chat.update');
            Route::delete('/projects/{project}/messages/{projectMessage}', [ProjectChatController::class, 'destroy'])->name('project-chat.destroy');
        });

    Route::prefix('users')
        ->middleware('permission:'.User::PERMISSION_MANAGE_USERS)
        ->group(function (): void {
            Route::get('/', [UserManagementController::class, 'index'])->name('users.index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('users.create');
            Route::post('/', [UserManagementController::class, 'store'])->name('users.store');
            Route::get('/{user}', [UserManagementController::class, 'show'])->name('users.show');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('users.update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        });

    Route::prefix('projects')
        ->middleware('permission:'.User::PERMISSION_MANAGE_PROJECTS)
        ->group(function (): void {
            Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('/create', [ProjectController::class, 'create'])->name('projects.create');
            Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
            Route::get('/{project}', [ProjectController::class, 'show'])->name('projects.show');
            Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
            Route::put('/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        });

    Route::prefix('service-tickets')
        ->middleware('permission:'.User::PERMISSION_MANAGE_TICKETS)
        ->group(function (): void {
            Route::get('/', [ServiceTicketController::class, 'index'])->name('service-tickets.index');
            Route::get('/create', [ServiceTicketController::class, 'create'])->name('service-tickets.create');
            Route::get('/public-links', [ServiceTicketController::class, 'publicLinks'])
                ->middleware('permission:'.User::PERMISSION_GENERATE_LINKS)
                ->name('service-tickets.public-links.index');
            Route::post('/', [ServiceTicketController::class, 'store'])->name('service-tickets.store');
            Route::post('/public-links', [ServiceTicketController::class, 'storePublicLink'])
                ->middleware('permission:'.User::PERMISSION_GENERATE_LINKS)
                ->name('service-tickets.public-links.store');
            Route::get('/{serviceTicket}', [ServiceTicketController::class, 'show'])->name('service-tickets.show');
            Route::put('/{serviceTicket}/response', [ServiceTicketController::class, 'updateResponse'])->name('service-tickets.response.update');
            Route::delete('/{serviceTicket}', [ServiceTicketController::class, 'destroy'])->name('service-tickets.destroy');
        });

    Route::prefix('settings')
        ->middleware('permission:'.User::PERMISSION_MANAGE_SETTINGS)
        ->group(function (): void {
            Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('/', [SettingsController::class, 'update'])->name('settings.update');
        });
});
