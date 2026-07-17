<?php

namespace App\Actions;

use App\Exceptions\DomainConflictException;
use App\Exceptions\DomainNotFoundException;
use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PaymentMethod\PaymentMethodSummaryMapper;
use App\ValueObjects\PaymentMethodSummary;
use Illuminate\Support\Facades\DB;

class CreateUserPaymentMethodAction
{
    public function __construct(
        private readonly PaymentMethodSummaryMapper $mapper,
    ) {}

    /**
     * Create a user-owned payment method (card or other) in the given workspace
     * and link it to the requested workspaces. When no explicit workspace list is
     * provided, the method is linked only to the workspace where it was created.
     *
     * Mirrors the shape produced by the workspace-scoped flow so the front can
     * reuse the existing PaymentMethodSummaryResource.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $workspaceIds
     */
    public function execute(User $user, Workspace $workspace, array $data, ?array $workspaceIds = null): PaymentMethodSummary
    {
        return DB::transaction(function () use ($user, $workspace, $data, $workspaceIds): PaymentMethodSummary {
            $result = $this->createPaymentMethod($user, $workspace, $data);
            $paymentMethod = $result['model'];

            $targetIds = $workspaceIds ?? [$workspace->id];

            app(SyncPaymentMethodWorkspacesAction::class)->execute($paymentMethod, $user, $targetIds);

            return $result['summary'];
        });
    }

    /**
     * Resolve the user's default workspace for personal payment method operations.
     *
     * Order of resolution:
     *   1. The user's explicit `default_workspace_id` (set via
     *      PUT /api/v1/user/default-workspace).
     *   2. Any active workspace membership (i.e. a workspace the user is a
     *      member of). This covers both owned and joined workspaces, so we
     *      do not silently fall through to an arbitrary owned workspace the
     *      user might not be an active member of anymore.
     *
     * If the user has neither a default workspace nor any active membership,
     * throws DomainConflictException with code `user_has_no_default_workspace`.
     */
    public function resolveDefaultWorkspace(User $user): Workspace
    {
        $defaultWorkspace = $user->defaultWorkspace;

        if ($defaultWorkspace === null) {
            $defaultWorkspace = $user->workspaces()->first();
        }

        if ($defaultWorkspace === null) {
            throw new DomainConflictException(
                'user_has_no_default_workspace',
                __('api.errors.user_has_no_default_workspace'),
            );
        }

        return $defaultWorkspace;
    }

    /**
     * Delete a user-owned payment method (Card or OtherPaymentMethod) by id.
     * The id must belong to the user; otherwise a DomainNotFoundException is thrown.
     *
     * Throws DomainConflictException with code `payment_method_in_use` when the
     * payment method is referenced by any Expense or FixedExpense, mirroring the
     * in-use guard used by the workspace-scoped flow.
     */
    public function delete(User $user, string $paymentMethodId): void
    {
        $paymentMethod = $this->resolveOwnedPaymentMethod($user, $paymentMethodId);

        $this->guardAgainstInUse($paymentMethod);

        DB::transaction(function () use ($paymentMethod): void {
            $paymentMethod->workspaces()->detach();
            $paymentMethod->delete();
        });
    }

    /**
     * Update a user-owned payment method (Card or OtherPaymentMethod) by id.
     * The id must belong to the user; otherwise a DomainNotFoundException is thrown.
     *
     * When $workspaceIds is provided, the workspace links are synchronized. The
     * workspace where the method was originally created is always preserved as
     * a linked workspace.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $workspaceIds
     */
    public function update(User $user, string $paymentMethodId, array $data, ?array $workspaceIds = null): PaymentMethodSummary
    {
        $paymentMethod = $this->resolveOwnedPaymentMethod($user, $paymentMethodId);

        return DB::transaction(function () use ($paymentMethod, $data, $workspaceIds, $user): PaymentMethodSummary {
            $this->updatePaymentMethod($paymentMethod, $data);

            if ($workspaceIds !== null) {
                app(SyncPaymentMethodWorkspacesAction::class)->execute($paymentMethod, $user, $workspaceIds);
            }

            return $this->summarize($paymentMethod);
        });
    }

    public function summarize(Card|OtherPaymentMethod $paymentMethod): PaymentMethodSummary
    {
        return $this->mapper->fromModel($paymentMethod);
    }

    /**
     * Throw if the given payment method is referenced by any expense or
     * fixed expense, regardless of workspace.
     */
    private function guardAgainstInUse(Card|OtherPaymentMethod $paymentMethod): void
    {
        $usageCount = $paymentMethod->expenses()->count()
            + $paymentMethod->fixedExpenses()->count();

        if ($usageCount > 0) {
            throw new DomainConflictException(
                'payment_method_in_use',
                __('api.errors.payment_method_in_use'),
            );
        }
    }

    public function resolveOwnedPaymentMethod(User $user, string $paymentMethodId): Card|OtherPaymentMethod
    {
        $card = $user->cards()->find($paymentMethodId);
        if ($card instanceof Card) {
            return $card;
        }

        $other = $user->otherPaymentMethods()->find($paymentMethodId);
        if ($other instanceof OtherPaymentMethod) {
            return $other;
        }

        throw new DomainNotFoundException(
            'user_payment_method_not_found',
            __('api.errors.user_payment_method_not_found'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{model: Card|OtherPaymentMethod, summary: PaymentMethodSummary}
     */
    private function createPaymentMethod(User $user, Workspace $workspace, array $data): array
    {
        if ($data['type'] === 'card') {
            $card = $user->cards()->create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'card_type' => $data['card_type'],
                'brand' => $data['brand'] ?? null,
                'last_4_digits' => $data['last_4_digits'] ?? null,
            ]);

            return [
                'model' => $card,
                'summary' => $this->summarize($card),
            ];
        }

        $method = $user->otherPaymentMethods()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return [
            'model' => $method,
            'summary' => $this->summarize($method),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updatePaymentMethod(Card|OtherPaymentMethod $paymentMethod, array $data): void
    {
        if ($paymentMethod instanceof Card) {
            $payload = array_intersect_key($data, array_flip([
                'name',
                'card_type',
                'brand',
                'last_4_digits',
            ]));

            if ($payload !== []) {
                $paymentMethod->update($payload);
            }

            return;
        }

        $payload = array_intersect_key($data, array_flip([
            'name',
            'description',
        ]));

        if ($payload !== []) {
            $paymentMethod->update($payload);
        }
    }
}
