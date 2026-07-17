<?php

namespace App\Http\Middleware;

use App\Support\Http\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestId::resolve($request);

        Log::shareContext([
            RequestId::ATTRIBUTE => $requestId,
        ]);

        return $next($request);
    }
}
