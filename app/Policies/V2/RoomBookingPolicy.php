<?php

namespace App\Policies\V2;

use App\Models\User;
use App\Models\V2\RoomBooking;

class RoomBookingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') && $ability !== 'respond' ? true : null;
    }

    public function view(User $user, RoomBooking $booking): bool
    {
        return $booking->created_by === $user->id
            || $booking->participants()->where('users.id', $user->id)->exists();
    }

    public function delete(User $user, RoomBooking $booking): bool
    {
        return $booking->created_by === $user->id
            && in_array($booking->status, ['PENDING', 'APPROVED'], true);
    }

    public function respond(User $user, RoomBooking $booking): bool
    {
        return strtoupper((string) $booking->booking_type) === 'MEETING'
            && $booking->status !== 'CANCELED'
            && $booking->participants()->where('users.id', $user->id)->exists();
    }
}
