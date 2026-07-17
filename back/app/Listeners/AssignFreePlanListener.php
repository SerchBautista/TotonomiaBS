<?php

namespace App\Listeners;

use App\Contracts\AssignUserPlanActionInterface;
use App\Events\UserRegistered;

class AssignFreePlanListener
{
    public function __construct(
        private readonly AssignUserPlanActionInterface $assignUserPlanAction,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->assignUserPlanAction->execute($event->user, 'free');
    }
}
