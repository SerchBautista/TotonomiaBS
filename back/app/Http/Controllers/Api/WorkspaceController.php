<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CreateWorkspaceActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaces = $request->user()
            ->workspaces()
            ->with('owner')
            ->withCount('members')
            ->paginate(15);

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspaceActionInterface $action): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        $workspace = $action->execute($request->user(), $request->validated());

        return (new WorkspaceResource($workspace))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace): WorkspaceResource
    {
        $this->authorize('view', $workspace);

        $workspace->load('owner', 'members');

        return new WorkspaceResource($workspace);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('update', $workspace);

        $workspace->update($request->validated());

        return new WorkspaceResource($workspace->fresh(['owner']));
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);

        $workspace->delete();

        return response()->json(null, 204);
    }
}
