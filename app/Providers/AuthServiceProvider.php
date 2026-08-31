<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\LeaveRequest;
use App\Models\RoomBooking;
use App\Policies\DocumentAccessRequestPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\RoomBookingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Document::class => DocumentPolicy::class,
        DocumentAccessRequest::class => DocumentAccessRequestPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        RoomBooking::class => RoomBookingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
