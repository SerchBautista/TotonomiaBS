<?php

namespace App\Contracts;

use App\Models\User;

interface AssignUserPlanActionInterface
{
    /**
     * Assign or revoke the premium plan for a user.
     *
     * @param  string  $plan  'free' | 'premium'
     */
    public function execute(User $user, string $plan): void;
}
