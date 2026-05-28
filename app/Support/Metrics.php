<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight in-process metrics façade.
 *
 * Future-proof for Prometheus / DataDog integration: counters live in cache
 * and can be scraped via a /metrics endpoint or pushed to a stats collector.
 *
 * Counters (this PR):
 *   - broadcast_failed_total            : SafeBroadcast::dispatch failures
 *   - notifications_dispatched_total    : NotificationCreated successful broadcasts
 *
 * Gauges (this PR):
 *   - reverb_connected_clients          : current WS client count (read from Reverb)
 */
class Metrics
{
    public static function increment(string $key, int $by = 1): void
    {
        Cache::increment("metrics.$key", $by);
    }

    public static function read(string $key): int
    {
        return (int) Cache::get("metrics.$key", 0);
    }

    /** Reset (test convenience). */
    public static function reset(string $key): void
    {
        Cache::forget("metrics.$key");
    }
}
