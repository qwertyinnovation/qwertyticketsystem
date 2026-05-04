<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeneralChatController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\ServiceTicketPublicController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        'exception' => new HttpException($code),
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
            Route::get('/general', [GeneralChatController::class, 'show'])->name('general-chat.show');
            Route::post('/general/join-request', [GeneralChatController::class, 'requestJoin'])->name('general-chat.request');
            Route::post('/general/read', [GeneralChatController::class, 'markRead'])->name('general-chat.read');
            Route::post('/general/messages', [GeneralChatController::class, 'store'])->name('general-chat.store');
            Route::put('/general/messages/{generalChatMessage}', [GeneralChatController::class, 'update'])->name('general-chat.update');
            Route::delete('/general/messages/{generalChatMessage}', [GeneralChatController::class, 'destroy'])->name('general-chat.destroy');
            Route::post('/general/members/invite', [GeneralChatController::class, 'invite'])->name('general-chat.members.invite');
            Route::put('/general/members/{generalChatMember}/approve', [GeneralChatController::class, 'approve'])->name('general-chat.members.approve');
            Route::put('/general/members/{generalChatMember}/reject', [GeneralChatController::class, 'reject'])->name('general-chat.members.reject');
            Route::put('/general/members/{generalChatMember}/kick', [GeneralChatController::class, 'kick'])->name('general-chat.members.kick');
            Route::get('/projects/{project}', [ProjectChatController::class, 'show'])->name('project-chat.show');
            Route::post('/projects/{project}/read', [ProjectChatController::class, 'markRead'])->name('project-chat.read');
            Route::post('/projects/{project}/messages', [ProjectChatController::class, 'store'])->name('project-chat.store');
            Route::put('/projects/{project}/messages/{projectMessage}', [ProjectChatController::class, 'update'])->name('project-chat.update');
            Route::delete('/projects/{project}/messages/{projectMessage}', [ProjectChatController::class, 'destroy'])->name('project-chat.destroy');
        });

    Route::prefix('users')
        ->middleware('permission:'.User::PERMISSION_MANAGE_USERS)
        ->group(function (): void {
            $missingUser = function () {
                return redirect()
                    ->route('users.index')
                    ->withErrors(['user' => 'The selected user could not be found. It may have been deleted.']);
            };

            Route::get('/', [UserManagementController::class, 'index'])->name('users.index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('users.create');
            Route::post('/', [UserManagementController::class, 'store'])->name('users.store');
            Route::delete('/bulk', [UserManagementController::class, 'bulkDestroy'])->name('users.bulk-destroy');
            Route::get('/{user}', [UserManagementController::class, 'show'])->missing($missingUser)->name('users.show');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->missing($missingUser)->name('users.edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->missing($missingUser)->name('users.update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->missing($missingUser)->name('users.destroy');
        });

    Route::prefix('projects')
        ->middleware('permission:'.User::PERMISSION_MANAGE_PROJECTS)
        ->group(function (): void {
            Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('/create', [ProjectController::class, 'create'])->name('projects.create');
            Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
            Route::delete('/bulk', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
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
            Route::delete('/public-links/bulk', [ServiceTicketController::class, 'bulkDestroyPublicLinks'])
                ->middleware('permission:'.User::PERMISSION_GENERATE_LINKS)
                ->name('service-tickets.public-links.bulk-destroy');
            Route::delete('/bulk', [ServiceTicketController::class, 'bulkDestroy'])->name('service-tickets.bulk-destroy');
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
