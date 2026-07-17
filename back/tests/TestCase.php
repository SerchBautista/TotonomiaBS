<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cache key prefixes used by the named rate limiters the API registers.
     * The throttle middleware stores its counter at
     *   `RateLimiter:<limiter-name>:<rate-limit-key>` (e.g. `RateLimiter:api-writes:writes:<userId>`).
     * Flushing the `RateLimiter:*` namespace between tests is the most
     * reliable way to keep the test suite isolated from
     * `throttle:api-writes` (and any other named limiter) without
     * disturbing the rest of the application cache.
     */
    private const RATE_LIMITER_KEY_PREFIXES = [
        'RateLimiter:api-writes:',
        'RateLimiter:auth-login:',
        'RateLimiter:auth-2fa-verify:',
        'RateLimiter:auth-2fa-resend:',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->flushRateLimiterKeys();
    }

    /**
     * Forget every rate-limiter cache entry created by the API's named
     * limiters so a single test class (where the same user is exercised
     * many times) does not bump into the 60-req/min `throttle:api-writes`
     * ceiling or any other limiter ceiling.
     */
    private function flushRateLimiterKeys(): void
    {
        $store = Cache::getStore();

        if (! property_exists($store, 'storage')) {
            return;
        }

        $reflection = new ReflectionProperty($store, 'storage');
        $reflection->setAccessible(true);
        $storage = $reflection->getValue($store);

        if (! is_array($storage)) {
            return;
        }

        foreach (self::RATE_LIMITER_KEY_PREFIXES as $prefix) {
            foreach (array_keys($storage) as $key) {
                if (is_string($key) && str_starts_with($key, $prefix)) {
                    unset($storage[$key]);
                }
            }
        }

        $reflection->setValue($store, $storage);
    }
}
