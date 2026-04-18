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
        $canViewTicketInsights = $user->hasPermission(User::PERMISSION_MANAGE_TICKETS);
        $canViewProjectInsights = $user->hasPermission(User::PERMISSION_MANAGE_PROJECTS);
        $canViewUserInsights = $user->hasPermission(User::PERMISSION_MANAGE_USERS);
        $canViewSettingsInsights = $user->hasPermission(User::PERMISSION_MANAGE_SETTINGS);
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

        $roleLabel = User::roles()[$user->role] ?? ucfirst($user->role);

        $dashboardSummary = $canManageAllTickets
            ? 'Live overview across all projects, users, and service ticket workflows.'
            : 'Live overview limited to your assigned projects and permitted operations.';

        $todayScopeSummary = $canManageAllTickets
            ? 'Viewing all ticket activity'
            : 'Viewing assigned project ticket activity';

        $metricCards = [];

        if ($canViewTicketInsights) {
            $metricCards[] = [
                'label' => 'Total Tickets',
                'value' => $totalTickets,
                'description' => 'All tickets in your current view scope.',
            ];
            $metricCards[] = [
                'label' => 'Open Tickets',
                'value' => $openTickets,
                'description' => 'New, in progress, waiting, or active states.',
            ];
            $metricCards[] = [
                'label' => 'Closed Tickets',
                'value' => $closedTickets,
                'description' => 'Resolved, completed, or closed outcomes.',
            ];
        }

        if ($canViewProjectInsights || $canViewSettingsInsights) {
            $metricCards[] = [
                'label' => 'Active Projects',
                'value' => $activeProjectStatuses === []
                    ? (clone $projectScopeQuery)->count()
                    : (clone $projectScopeQuery)->whereIn('status', $activeProjectStatuses)->count(),
                'description' => 'Open projects currently being delivered.',
            ];
            $metricCards[] = [
                'label' => 'Total Projects',
                'value' => (clone $projectScopeQuery)->count(),
                'description' => $canManageAllTickets
                    ? 'Projects tracked in the workspace.'
                    : 'Projects you can currently access.',
            ];
        } elseif (! $canManageAllTickets) {
            $metricCards[] = [
                'label' => 'Assigned Projects',
                'value' => count($accessibleProjectIds),
                'description' => 'Projects currently available in your dashboard scope.',
            ];
        }

        if ($canViewUserInsights) {
            $metricCards[] = [
                'label' => 'Total Users',
                'value' => User::count(),
                'description' => 'System users across all requester roles.',
            ];
        } elseif ($canGeneratePublicLink) {
            $metricCards[] = [
                'label' => 'Active Public Links',
                'value' => $activePublicLinksCount,
                'description' => 'One-time links not used and not expired.',
            ];
        }

        $quickActions = [];

        if ($canViewTicketInsights) {
            $quickActions[] = [
                'label' => 'Submit New Ticket',
                'href' => route('service-tickets.create'),
                'variant' => 'primary',
            ];
            $quickActions[] = [
                'label' => 'Open Ticket List',
                'href' => route('service-tickets.index'),
                'variant' => 'secondary',
            ];
        }

        if ($canViewProjectInsights) {
            $quickActions[] = [
                'label' => 'Create Project',
                'href' => route('projects.create'),
                'variant' => 'secondary',
            ];
        }

        if ($canViewUserInsights) {
            $quickActions[] = [
                'label' => 'Create User',
                'href' => route('users.create'),
                'variant' => 'secondary',
            ];
        }

        if ($canGeneratePublicLink) {
            $quickActions[] = [
                'label' => 'One-Time Public Form',
                'href' => route('service-tickets.public-links.index'),
                'variant' => 'accent',
            ];
        }

        if ($canViewSettingsInsights) {
            $quickActions[] = [
                'label' => 'Settings',
                'href' => route('settings.index'),
                'variant' => 'secondary',
            ];
        }

        return view('dashboard', [
            'user' => $user,
            'permissions' => User::availablePermissions(),
            'roleLabel' => $roleLabel,
            'dashboardSummary' => $dashboardSummary,
            'todayScopeSummary' => $todayScopeSummary,
            'metricCards' => $metricCards,
            'quickActions' => $quickActions,
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
            'canViewTicketInsights' => $canViewTicketInsights,
            'canViewProjectInsights' => $canViewProjectInsights,
            'canViewUserInsights' => $canViewUserInsights,
            'canViewSettingsInsights' => $canViewSettingsInsights,
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
