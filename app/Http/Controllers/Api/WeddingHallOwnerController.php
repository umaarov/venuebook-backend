<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\WeddingHall;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;

class WeddingHallOwnerController extends Controller
{
    use ApiResponser;

    public function myWeddingHalls()
    {
        $weddingHalls = WeddingHall::with(['district', 'primaryImage'])
            ->where('owner_id', auth()->id())
            ->get();

        return $this->success($weddingHalls, 'Wedding halls retrieved successfully');
    }

    public function myReservations(Request $request)
    {
        $query = Reservation::with(['weddingHall.district', 'user'])
            ->whereHas('weddingHall', function($query) {
                $query->where('owner_id', auth()->id());
            });

        if ($request->has('wedding_hall_id')) {
            $query->where('wedding_hall_id', $request->wedding_hall_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sortBy = $request->sort_by ?? 'reservation_date';
        $sortDirection = $request->sort_direction ?? 'asc';

        if (in_array($sortBy, ['reservation_date', 'created_at', 'number_of_guests'])) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $reservations = $query->paginate($request->per_page ?? 10);

        return $this->success($reservations, 'Reservations retrieved successfully');
    }

    public function cancelReservation($id)
    {
        $reservation = Reservation::with('weddingHall')->findOrFail($id);

        if ($reservation->weddingHall->owner_id !== auth()->id()) {
            return $this->error('You do not have permission to cancel this reservation', 403);
        }

        $reservation->status = 'cancelled';
        $reservation->save();

        return $this->success($reservation, 'Reservation cancelled successfully');
    }
}
