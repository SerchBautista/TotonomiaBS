<?php

namespace App\Http\Controllers\Api;

use App\Actions\RevokePushDeviceAction;
use App\Actions\UpsertPushDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendPushNotificationRequest;
use App\Http\Requests\UpsertPushDeviceRequest;
use App\Http\Resources\PushDeviceResource;
use App\Models\PushDevice;
use App\Notifications\PushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class PushNotificationController extends Controller
{
    /**
     * Register or update a push device for the authenticated user.
     */
    public function upsertDevice(
        UpsertPushDeviceRequest $request,
        UpsertPushDeviceAction $action,
    ): JsonResponse {
        $device = $action->execute($request->user(), $request->validated());

        return (new PushDeviceResource($device))
            ->response()
            ->setStatusCode($device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Revoke a push device (mark as revoked so FCM delivery stops).
     */
    public function revokeDevice(
        string $installationId,
        Request $request,
        RevokePushDeviceAction $action,
    ): JsonResponse {
        $device = PushDevice::where('user_id', $request->user()->id)
            ->where('installation_id', $installationId)
            ->firstOrFail();

        $action->execute($device);

        return response()->json(null, 204);
    }

    /**
     * Send a push notification directly to a device token.
     */
    public function sendToDevice(SendPushNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Notification::route('fcm', $validated['token'])
            ->notify(new PushNotification(
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? [],
            ));

        return response()->json([
            'message' => 'Push notification sent.',
        ]);
    }
}
