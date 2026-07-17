<?php

namespace App\Listeners;

use App\Contracts\CreateDefaultWorkspaceActionInterface;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class CreateDefaultWorkspaceListener
{
    public function __construct(
        private readonly CreateDefaultWorkspaceActionInterface $action,
    ) {}

    public function handle(Verified $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if ($user->workspaces()->exists()) {
            return;
        }

        $this->action->execute($user);
    }
}
