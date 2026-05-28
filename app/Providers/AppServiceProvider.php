<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Observers\TaskActivityObserver;
use App\Observers\TaskCommentObserver;
use App\Observers\TaskObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        TaskActivity::observe(TaskActivityObserver::class);
        TaskComment::observe(TaskCommentObserver::class);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return rtrim(config('app.frontend_url', config('app.url')), '/') .
                '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });
    }
}
