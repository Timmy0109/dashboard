<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Fire-and-forget broadcast wrapper.
 *
 * Reverb daemon failures or queue connection errors must never block controllers.
 * Use this helper instead of `broadcast(...)` everywhere we want
 * "the WS message tried to go out, but if it failed, log it and move on".
 *
 * Usage:
 *   SafeBroadcast::dispatch(new TaskCommentCreated($comment));
 *
 * Increments App\Support\Metrics::increment('broadcast_failed_total') on failure.
 */
class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning('broadcast failed', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
            Metrics::increment('broadcast_failed_total');
        }
    }
}
