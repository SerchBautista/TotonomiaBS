<?php

namespace App\Actions\Admin;

use App\Models\User;

class GetDashboardStatsAction
{
    /**
     * @return array{kpis: array<string, mixed>, recent_users: \Illuminate\Support\Collection}
     */
    public function execute(): array
    {
        $now = now();

        $usersTotal = User::count();
        $usersRegisteredToday = User::whereDate('created_at', $now->toDateString())->count();
        $usersRegisteredWeek = User::where('created_at', '>=', $now->copy()->startOfWeek())->count();
        $emailPendingVerification = User::whereNull('email_verified_at')->count();

        $premiumActiveTotal = User::query()
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>', $now)
            ->count();

        $recentUsers = User::query()
            ->with('roles')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'kpis' => [
                'users_total' => $usersTotal,
                'users_registered_today' => $usersRegisteredToday,
                'users_registered_week' => $usersRegisteredWeek,
                'email_pending_verification' => $emailPendingVerification,
                'premium_active_total' => $premiumActiveTotal,
            ],
            'recent_users' => $recentUsers,
        ];
    }
}
