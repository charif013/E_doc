<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\LeaveRequest;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Observers\AuditObserver;
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
        Document::observe(AuditObserver::class);
        DocumentAccessRequest::observe(AuditObserver::class);
        LeaveRequest::observe(AuditObserver::class);
        RoomBooking::observe(AuditObserver::class);
        Room::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
    }
}
