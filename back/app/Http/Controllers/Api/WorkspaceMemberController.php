<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveWorkspaceMemberAction;
use App\Exceptions\DomainNotFoundException;
use App\Exceptions\DomainValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceMemberRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAddedNotification;
use App\Notifications\WorkspaceMemberRemovedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class WorkspaceMemberController extends Controller
{
    #[OA\Get(
        path: '/workspaces/{workspace}/members',
        tags: ['Workspace Members'],
        summary: 'List workspace members',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Workspace members loaded'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('view', $workspace);

        $members = $workspace->members()->get();

        return WorkspaceMemberResource::collection($members);
    }

    #[OA\Post(
        path: '/workspaces/{workspace}/members',
        tags: ['Workspace Members'],
        summary: 'Add a member to a workspace',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'member@example.com'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Workspace member added'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 404,
                            'code' => 'user_not_found',
                            'message' => 'User not found.',
                            'request_id' => 'req-workspace-member-store-404',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation or business rule error',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/ApiError'),
                                new OA\Schema(example: [
                                    'status' => 422,
                                    'code' => 'validation_error',
                                    'message' => 'The given data was invalid.',
                                    'request_id' => 'req-workspace-member-store-validation-422',
                                    'fieldErrors' => [
                                        'email' => ['The email field must be a valid email address.'],
                                    ],
                                ]),
                            ]
                        ),
                        new OA\Schema(
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/ApiError'),
                                new OA\Schema(example: [
                                    'status' => 422,
                                    'code' => 'workspace_member_already_exists',
                                    'message' => 'User is already a member of this workspace.',
                                    'request_id' => 'req-workspace-member-store-conflict-422',
                                ]),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreWorkspaceMemberRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw new DomainNotFoundException('user_not_found', __('api.workspace_members.user_not_found'));
        }

        if ($workspace->hasMember($user->id)) {
            throw new DomainValidationException(
                'workspace_member_already_exists',
                __('api.workspace_members.already_exists'),
            );
        }

        $workspace->members()->attach($user->id, ['role' => 'guest']);

        $user->notify(new WorkspaceMemberAddedNotification($workspace, auth()->user()));

        $member = $workspace->members()->where('users.id', $user->id)->first();

        return (new WorkspaceMemberResource($member))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Put(
        path: '/workspaces/{workspace}/members/{user}',
        tags: ['Workspace Members'],
        summary: 'Update workspace member permissions',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'role', type: 'string', enum: ['owner', 'guest'], nullable: true),
                    new OA\Property(property: 'can_add_fixed_expenses', type: 'boolean', nullable: true),
                    new OA\Property(property: 'can_add_categories', type: 'boolean', nullable: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Workspace member updated'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(
                response: 404,
                description: 'Workspace member not found',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 404,
                            'code' => 'workspace_member_not_found',
                            'message' => 'Workspace member not found.',
                            'request_id' => 'req-workspace-member-update-404',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation or business rule error',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/ApiError'),
                                new OA\Schema(example: [
                                    'status' => 422,
                                    'code' => 'validation_error',
                                    'message' => 'The given data was invalid.',
                                    'request_id' => 'req-workspace-member-update-422',
                                    'fieldErrors' => [
                                        'role' => ['The selected role is invalid.'],
                                    ],
                                ]),
                            ]
                        ),
                        new OA\Schema(
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/ApiError'),
                                new OA\Schema(example: [
                                    'status' => 422,
                                    'code' => 'cannot_change_owner_role',
                                    'message' => 'The workspace owner role cannot be changed.',
                                    'request_id' => 'req-workspace-member-update-cannot-change-owner-422',
                                ]),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function update(
        UpdateWorkspaceMemberRequest $request,
        Workspace $workspace,
        string $user,
        ResolveWorkspaceMemberAction $resolveWorkspaceMember,
    ): WorkspaceMemberResource {
        $this->authorize('manageMembers', $workspace);

        $member = $resolveWorkspaceMember->execute($workspace, $user);

        // H-012 fix: the workspace owner must always keep the `owner` role
        // in the pivot. Demoting them to `guest` would break the
        // `WorkspacePolicy::manageMembers` invariant because that policy
        // checks `owner_id` for authorization, but other consumers (e.g.
        // member listings) read the role from the pivot.
        if ($workspace->owner_id === $member->id && $request->has('role') && $request->role !== 'owner') {
            throw new DomainValidationException(
                'cannot_change_owner_role',
                __('api.workspace_members.cannot_change_owner_role'),
            );
        }

        $pivotData = array_filter([
            'role' => $request->role,
            'can_add_fixed_expenses' => $request->has('can_add_fixed_expenses')
                                            ? (bool) $request->can_add_fixed_expenses
                                            : null,
            'can_add_categories' => $request->has('can_add_categories')
                                            ? (bool) $request->can_add_categories
                                            : null,
        ], fn ($v) => ! is_null($v));

        $workspace->members()->updateExistingPivot($member->id, $pivotData);

        $member = $workspace->members()->where('users.id', $member->id)->first();

        if (! $member instanceof User) {
            throw new DomainNotFoundException(
                'workspace_member_not_found',
                __('api.workspace_members.member_not_found'),
            );
        }

        return new WorkspaceMemberResource($member);
    }

    #[OA\Delete(
        path: '/workspaces/{workspace}/members/{user}',
        tags: ['Workspace Members'],
        summary: 'Remove a member from a workspace',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Workspace member removed'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(
                response: 404,
                description: 'Workspace member not found',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 404,
                            'code' => 'workspace_member_not_found',
                            'message' => 'Workspace member not found.',
                            'request_id' => 'req-workspace-member-delete-404',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Business rule error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'workspace_owner_cannot_be_removed',
                            'message' => 'Cannot remove the workspace owner.',
                            'request_id' => 'req-workspace-member-delete-422',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function destroy(
        Workspace $workspace,
        string $user,
        ResolveWorkspaceMemberAction $resolveWorkspaceMember,
    ): JsonResponse {
        $this->authorize('manageMembers', $workspace);

        $member = $resolveWorkspaceMember->execute($workspace, $user);

        if ($workspace->owner_id === $member->id) {
            throw new DomainValidationException(
                'workspace_owner_cannot_be_removed',
                __('api.workspace_members.owner_cannot_be_removed'),
            );
        }

        $member->notify(new WorkspaceMemberRemovedNotification($workspace));

        $workspace->members()->detach($member->id);

        if ($member->default_workspace_id === $workspace->id) {
            $member->update(['default_workspace_id' => null]);
        }

        return response()->json(null, 204);
    }
}
