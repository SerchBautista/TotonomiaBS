<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = $request->query('lang')
            ?? $request->input('lang')
            ?? $request->header('Accept-Language')
            ?? config('app.locale');

        $locale = $this->normalizeLocale($requestedLocale);

        App::setLocale((string) $locale);
        if (config('app.debug')) {
            Log::info('API locale resolved', [
                'path' => $request->path(),
                'query_lang' => $request->query('lang'),
                'body_lang' => $request->input('lang'),
                'accept_language' => $request->header('Accept-Language'),
                'resolved_locale' => $locale,
            ]);
        }

        return $next($request);
    }

    private function normalizeLocale(string $locale): string
    {
        $primaryLocale = strtolower(explode(',', $locale)[0]);
        $primaryLocale = str_replace('_', '-', $primaryLocale);
        $primaryLocale = explode('-', $primaryLocale)[0];

        return in_array($primaryLocale, ['en', 'es'], true)
            ? $primaryLocale
            : (string) config('app.locale');
    }
}
