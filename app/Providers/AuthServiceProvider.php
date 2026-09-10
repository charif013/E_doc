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
use App\Models\V2\Document as V2Document;
use App\Models\V2\LeaveRequest as V2LeaveRequest;
use App\Models\V2\RoomBooking as V2RoomBooking;
use App\Policies\V2\DocumentPolicy as V2DocumentPolicy;
use App\Policies\V2\LeaveRequestPolicy as V2LeaveRequestPolicy;
use App\Policies\V2\RoomBookingPolicy as V2RoomBookingPolicy;
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
        V2Document::class => V2DocumentPolicy::class,
        V2LeaveRequest::class => V2LeaveRequestPolicy::class,
        V2RoomBooking::class => V2RoomBookingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
