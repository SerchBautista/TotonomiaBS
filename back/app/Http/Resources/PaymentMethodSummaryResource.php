<?php

namespace App\Http\Resources;

use App\ValueObjects\LinkedWorkspaceSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\ValueObjects\PaymentMethodSummary */
class PaymentMethodSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'display_name' => $this->displayName,
            'masked_details' => $this->maskedDetails,
            'is_linked' => $this->when($this->isLinked !== null, $this->isLinked),
            'is_valid_for_transactions' => $this->when($this->isValidForTransactions !== null, $this->isValidForTransactions),
            'state' => $this->when($this->state !== null, $this->state),
            'linked_workspaces_count' => $this->when($this->linkedWorkspacesCount !== null, $this->linkedWorkspacesCount),
            'linked_workspaces' => $this->when(
                $this->linkedWorkspaces !== null,
                fn (): array => array_map(
                    fn (LinkedWorkspaceSummary $workspace): array => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                    ],
                    $this->linkedWorkspaces,
                ),
            ),
            'is_in_use_in_workspace' => $this->when($this->isInUseInWorkspace !== null, $this->isInUseInWorkspace),
        ];
    }
}
