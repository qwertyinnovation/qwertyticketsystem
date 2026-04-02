<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('dashboard', [
            'user' => $user,
            'permissions' => User::availablePermissions(),
            'totalUsers' => User::count(),
            'adminUsers' => User::where('role', User::ROLE_ADMIN)->count(),
            'pmUsers' => User::where('role', User::ROLE_PM)->count(),
            'clientUsers' => User::where('role', User::ROLE_CLIENT)->count(),
        ]);
    }
}
