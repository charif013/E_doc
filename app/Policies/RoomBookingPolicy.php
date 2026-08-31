<?php

namespace App\Policies;

use App\Models\RoomBooking;
use App\Models\User;

class RoomBookingPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function view(User $user, RoomBooking $booking): bool
    {
        return true;
    }

    public function delete(User $user, RoomBooking $booking): bool
    {
        return $booking->created_by === $user->id;
    }
}
