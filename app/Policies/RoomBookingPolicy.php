<?php

namespace App\Policies;

use App\Models\RoomBooking;
use App\Models\User;

class RoomBookingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') && $ability !== 'respond' ? true : null;
    }

    public function view(User $user, RoomBooking $booking): bool
    {
        return $booking->created_by === $user->id
            || $booking->invitees()->where('users.id', $user->id)->exists();
    }

    public function respond(User $user, RoomBooking $booking): bool
    {
        return $booking->booking_type === 'meeting'
            && $booking->status !== 'CANCELED'
            && $booking->invitees()->where('users.id', $user->id)->exists();
    }

    public function delete(User $user, RoomBooking $booking): bool
    {
        return $booking->created_by === $user->id;
    }
}
