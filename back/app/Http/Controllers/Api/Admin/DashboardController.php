<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetDashboardStatsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserListResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardStatsAction $getDashboardStatsAction,
    ) {}

    #[OA\Get(
        path: '/admin/dashboard',
        tags: ['Admin'],
        summary: 'Admin dashboard endpoint',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard loaded'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        $result = $this->getDashboardStatsAction->execute();

        return response()->json([
            'message' => __('api.dashboard.loaded'),
            'data' => [
                'kpis' => $result['kpis'],
                'recent_users' => AdminUserListResource::collection($result['recent_users'])->resolve(),
            ],
        ]);
    }
}
