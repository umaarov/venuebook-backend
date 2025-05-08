<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\WeddingHall;
use Illuminate\Http\Request;

class ReservationService
{
    public function createReservation(Request $request, WeddingHall $weddingHall)
    {
        $totalPrice = $weddingHall->price_per_seat * $request->number_of_guests;

        $reservation = Reservation::create([
            'wedding_hall_id' => $weddingHall->id,
            'user_id' => auth()->id(),
            'reservation_date' => $request->reservation_date,
            'number_of_guests' => $request->number_of_guests,
            'customer_name' => $request->customer_name,
            'customer_surname' => $request->customer_surname,
            'customer_phone' => $request->customer_phone,
            'total_price' => $totalPrice,
            'status' => 'booked',
        ]);

        return $reservation;
    }
}
