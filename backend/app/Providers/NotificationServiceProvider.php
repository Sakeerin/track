<?php

namespace App\Providers;

use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\LineNotificationChannel;
use App\Services\Notification\NotificationService;
use App\Services\Notification\SmsNotificationChannel;
use App\Services\Notification\TemplateManager;
use App\Services\Notification\WebhookNotificationChannel;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register notification channels as singletons
        $this->app->singleton(EmailNotificationChannel::class);
        $this->app->singleton(SmsNotificationChannel::class);
        $this->app->singleton(LineNotificationChannel::class);
        $this->app->singleton(WebhookNotificationChannel::class);
        
        // Register template manager
        $this->app->singleton(TemplateManager::class);
        
        // Register main notification service
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(EmailNotificationChannel::class),
                $app->make(SmsNotificationChannel::class),
                $app->make(LineNotificationChannel::class),
                $app->make(WebhookNotificationChannel::class),
                $app->make(TemplateManager::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
