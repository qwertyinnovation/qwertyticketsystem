<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketPublicLink;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $canManageAllTickets = $this->canManageAllTickets($user);
        $canGeneratePublicLink = $user->hasPermission(User::PERMISSION_GENERATE_LINKS);
        $accessibleProjectIds = $canManageAllTickets ? [] : $user->assignedProjectIds();

        $ticketsQuery = ServiceTicket::query();

        if (! $canManageAllTickets) {
            if ($accessibleProjectIds === []) {
                $ticketsQuery->whereRaw('1 = 0');
            } else {
                $ticketsQuery->whereIn('project_id', $accessibleProjectIds);
            }
        }

        $totalTickets = (clone $ticketsQuery)->count();

        $openStatuses = array_values(array_filter(ServiceTicket::statuses(), static function (string $status): bool {
            return ! in_array(strtolower($status), ['resolved', 'closed', 'completed'], true);
        }));

        $closedStatuses = array_values(array_filter(ServiceTicket::statuses(), static function (string $status): bool {
            return in_array(strtolower($status), ['resolved', 'closed', 'completed'], true);
        }));

        $openTickets = $openStatuses === [] ? 0 : (clone $ticketsQuery)->whereIn('status', $openStatuses)->count();
        $closedTickets = $closedStatuses === [] ? 0 : (clone $ticketsQuery)->whereIn('status', $closedStatuses)->count();

        $ticketStatusCountsRaw = (clone $ticketsQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $ticketStatusCounts = [];

        foreach (ServiceTicket::statuses() as $status) {
            $ticketStatusCounts[$status] = (int) ($ticketStatusCountsRaw[$status] ?? 0);
        }

        $requesterRoleCountsRaw = (clone $ticketsQuery)
            ->selectRaw('requester_role, COUNT(*) as total')
            ->groupBy('requester_role')
            ->pluck('total', 'requester_role');

        $requesterRoleCounts = [];

        foreach (ServiceTicket::requesterRoles() as $roleKey => $roleLabel) {
            $requesterRoleCounts[$roleKey] = (int) ($requesterRoleCountsRaw[$roleKey] ?? 0);
        }

        $recentTickets = (clone $ticketsQuery)
            ->with(['project:id,name', 'submittedBy:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        $projectStatuses = Project::statuses();
        $activeProjectStatuses = array_values(array_filter($projectStatuses, static function (string $status): bool {
            return ! in_array(strtolower($status), ['closed', 'completed'], true);
        }));

        $projectScopeQuery = Project::query();

        if (! $canManageAllTickets) {
            if ($accessibleProjectIds === []) {
                $projectScopeQuery->whereRaw('1 = 0');
            } else {
                $projectScopeQuery->whereIn('id', $accessibleProjectIds);
            }
        }

        $activePublicLinksCount = 0;

        if ($canGeneratePublicLink) {
            $activePublicLinksCount = ServiceTicketPublicLink::query()
                ->where('created_by_user_id', $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->count();
        }

        return view('dashboard', [
            'user' => $user,
            'permissions' => User::availablePermissions(),
            'totalUsers' => User::count(),
            'adminUsers' => User::where('role', User::ROLE_ADMIN)->count(),
            'pmUsers' => User::where('role', User::ROLE_PM)->count(),
            'clientUsers' => User::where('role', User::ROLE_CLIENT)->count(),
            'internalUsers' => User::where('role', User::ROLE_INTERNAL)->count(),
            'vendorUsers' => User::where('role', User::ROLE_VENDOR)->count(),
            'totalProjects' => (clone $projectScopeQuery)->count(),
            'activeProjects' => $activeProjectStatuses === []
                ? (clone $projectScopeQuery)->count()
                : (clone $projectScopeQuery)->whereIn('status', $activeProjectStatuses)->count(),
            'totalTickets' => $totalTickets,
            'openTickets' => $openTickets,
            'closedTickets' => $closedTickets,
            'ticketStatusCounts' => $ticketStatusCounts,
            'requesterRoleCounts' => $requesterRoleCounts,
            'recentTickets' => $recentTickets,
            'activePublicLinksCount' => $activePublicLinksCount,
            'requesterRoles' => ServiceTicket::requesterRoles(),
            'canManageAllTickets' => $canManageAllTickets,
            'canGeneratePublicLink' => $canGeneratePublicLink,
        ]);
    }

    private function canManageAllTickets(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN
            || $user->role === User::ROLE_PM
            || $user->hasPermission(User::PERMISSION_MANAGE_PROJECTS)
            || $user->hasPermission(User::PERMISSION_MANAGE_SETTINGS);
    }
}
