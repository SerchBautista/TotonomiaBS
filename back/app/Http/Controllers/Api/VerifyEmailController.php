<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiAuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): JsonResponse
    {
        /** @var User|null $user */
        $user = User::find($id);

        if ($user === null || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw new ApiAuthorizationException(
                'email_verification_invalid',
                __('api.auth.email_verification_invalid'),
            );
        }

        // Validate the signature against the canonical APP_URL-based URL so that
        // differences between APP_URL and the actual request host don't matter.
        $expires = (string) $request->query('expires', '');
        $signature = (string) $request->query('signature', '');

        $canonicalUrl = URL::route('verification.verify', ['id' => $id, 'hash' => $hash])
            .'?'.http_build_query(['expires' => $expires, 'signature' => $signature]);

        $canonicalRequest = Request::create($canonicalUrl, 'GET');

        if (! URL::hasValidSignature($canonicalRequest)) {
            throw new ApiAuthorizationException(
                'email_verification_invalid',
                __('api.auth.email_verification_invalid'),
            );
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('api.auth.email_already_verified'),
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json([
            'message' => __('api.auth.email_verified'),
        ]);
    }
}
