<?php

namespace App\Providers;

use App\Models\EtaLane;
use App\Models\EtaRule;
use App\Models\Event;
use App\Models\Facility;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Observers\AuditableModelObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Tracking\TrackingService::class, function ($app) {
            return new \App\Services\Tracking\TrackingService(
                $app->make(\App\Services\Tracking\ShipmentFormatter::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for older MySQL versions
        Schema::defaultStringLength(191);

        Shipment::observe(AuditableModelObserver::class);
        Event::observe(AuditableModelObserver::class);
        Facility::observe(AuditableModelObserver::class);
        Subscription::observe(AuditableModelObserver::class);
        NotificationLog::observe(AuditableModelObserver::class);
        EtaRule::observe(AuditableModelObserver::class);
        EtaLane::observe(AuditableModelObserver::class);
        User::observe(AuditableModelObserver::class);
        SupportTicket::observe(AuditableModelObserver::class);

        config([
            'session.http_only' => true,
            'session.same_site' => env('SESSION_SAME_SITE', 'lax'),
            'session.secure' => env('SESSION_SECURE_COOKIE', false),
        ]);
    }
}
