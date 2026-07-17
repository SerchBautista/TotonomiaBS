<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SyncPaymentMethodWorkspacesAction
{
    public function __construct(
        private readonly CountPaymentMethodUsageAction $countPaymentMethodUsage,
    ) {}

    /**
     * Synchronize the workspaces linked to a card or other payment method.
     *
     * Workspaces being removed where the method is still in use are kept as
     * read-only (is_shared=false, is_active=false) to preserve historical data.
     * If the method already has pivots to workspaces the acting user does not
     * own, the sync is rejected and no pivots are mutated.
     *
     * @param  array<int, string>  $workspaceIds
     */
    public function execute(Card|OtherPaymentMethod $paymentMethod, User $user, array $workspaceIds): void
    {
        $workspaceIds = array_values(array_unique($workspaceIds));

        $this->ensureOwnership($workspaceIds, $user);

        DB::transaction(function () use ($paymentMethod, $user, $workspaceIds): void {
            $currentIds = $paymentMethod->workspaces()->pluck('workspaces.id')->all();
            $currentIds = array_map('strval', $currentIds);

            $this->ensureOwnership($currentIds, $user);

            $toRemove = array_diff($currentIds, $workspaceIds);

            if ($workspaceIds !== []) {
                $paymentMethod->workspaces()->syncWithoutDetaching(
                    array_fill_keys($workspaceIds, ['is_shared' => true, 'is_active' => true])
                );
            }

            foreach ($toRemove as $workspaceId) {
                $workspace = Workspace::find($workspaceId);

                if ($workspace === null) {
                    continue;
                }

                $usageCount = $this->countPaymentMethodUsage->execute($paymentMethod, $workspace);

                if ($usageCount > 0) {
                    $paymentMethod->workspaces()->syncWithoutDetaching([
                        $workspaceId => ['is_shared' => false, 'is_active' => false],
                    ]);
                } else {
                    $paymentMethod->workspaces()->detach($workspaceId);
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $workspaceIds
     */
    private function ensureOwnership(array $workspaceIds, User $user): void
    {
        $ownedCount = Workspace::query()
            ->whereIn('id', $workspaceIds)
            ->where('owner_id', $user->id)
            ->count();

        if ($ownedCount !== count($workspaceIds)) {
            throw new AuthorizationException;
        }
    }
}
