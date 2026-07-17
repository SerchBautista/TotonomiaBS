<?php

namespace App\Actions;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Removes redundant `user` role from accounts that already have the
 * more privileged `admin` role. Idempotent: users without the conflict
 * are left untouched, and admins that lack the `user` role are no-op.
 */
class DedupeUserRolesAction
{
    /**
     * @return array<int, array{user_id: string, email: string, removed: bool, already_clean: bool}>
     */
    public function execute(?string $userId = null, bool $apply = false): array
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->whereHas('roles', fn ($q) => $q->where('name', 'user'));

        if ($userId !== null) {
            $query->where('id', $userId);
        }

        $adminRole = Role::query()->where('name', 'admin')->where('guard_name', 'api')->first();
        $userRole = Role::query()->where('name', 'user')->where('guard_name', 'api')->first();

        $report = [];

        foreach ($query->get() as $user) {
            $entry = [
                'user_id' => $user->id,
                'email' => $user->email,
                'removed' => false,
                'already_clean' => false,
            ];

            if (! $apply) {
                $report[] = $entry;

                continue;
            }

            // removeRole() only acts when the relation exists; it is a no-op otherwise.
            $user->removeRole($userRole ?? 'user');
            $user->load('roles');
            $entry['removed'] = $user->hasRole($adminRole ?? 'admin') && ! $user->hasRole('user');
            $report[] = $entry;
        }

        return $report;
    }
}
