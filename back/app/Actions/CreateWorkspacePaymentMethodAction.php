<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workspace;
use App\Support\PaymentMethod\PaymentMethodSummaryMapper;
use App\ValueObjects\PaymentMethodSummary;
use Illuminate\Support\Facades\DB;

class CreateWorkspacePaymentMethodAction
{
    public function __construct(
        private readonly PaymentMethodSummaryMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $owner, Workspace $workspace, array $data): PaymentMethodSummary
    {
        return DB::transaction(function () use ($owner, $workspace, $data): PaymentMethodSummary {
            if ($data['type'] === 'card') {
                $card = $owner->cards()->create([
                    'workspace_id' => $workspace->id,
                    'name' => $data['name'],
                    'card_type' => $data['card_type'],
                    'brand' => $data['brand'] ?? null,
                    'last_4_digits' => $data['last_4_digits'] ?? null,
                ]);

                $card->workspaces()->syncWithoutDetaching([
                    $workspace->id => ['is_shared' => true, 'is_active' => true],
                ]);

                return $this->mapper->fromCardLinked($card);
            }

            $method = $owner->otherPaymentMethods()->create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $method->workspaces()->syncWithoutDetaching([
                $workspace->id => ['is_shared' => true, 'is_active' => true],
            ]);

            return $this->mapper->fromOtherLinked($method);
        });
    }
}
