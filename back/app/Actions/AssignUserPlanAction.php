<?php

namespace App\Actions;

use App\Contracts\AssignUserPlanActionInterface;
use App\Events\UserPlanChanged;
use App\Models\User;
use Carbon\Carbon;

class AssignUserPlanAction implements AssignUserPlanActionInterface
{
    public function execute(User $user, string $plan): void
    {
        if ($plan === 'premium') {
            $user->assignRole('premium');
            $this->setOrExtendSubscription($user);
        } else {
            $user->removeRole('premium');
            $user->update(['subscription_ends_at' => null]);
        }

        UserPlanChanged::dispatch($user, $plan);
    }

    private function setOrExtendSubscription(User $user): void
    {
        if ($user->hasActiveSubscription()) {
            $user->update([
                'subscription_ends_at' => $user->subscription_ends_at->copy()->addMonth(),
            ]);
        } else {
            $user->update([
                'subscription_ends_at' => Carbon::now()->addMonth(),
            ]);
        }
    }
}
