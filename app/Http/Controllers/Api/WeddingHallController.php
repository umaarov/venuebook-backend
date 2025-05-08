<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHallRequest;
use App\Models\District;
use App\Models\Reservation;
use App\Models\WeddingHall;
use App\Models\WeddingHallImage;
use App\Services\WeddingHallService;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WeddingHallController extends Controller
{
    use ApiResponser;

    protected $weddingHallService;

    public function __construct(WeddingHallService $weddingHallService)
    {
        $this->weddingHallService = $weddingHallService;
    }

    public function index(Request $request)
    {
        $query = WeddingHall::with(['district', 'primaryImage', 'owner']);

        // Apply role-based filters
        if (auth()->user()->role === 'admin') {
            // Admin can see all wedding halls
        } elseif (auth()->user()->role === 'owner') {
            // Owners can see only their own wedding halls
            $query->where('owner_id', auth()->id());
        } else {
            // Users can see only approved wedding halls
            $query->approved();
        }

        // Apply filters
        if ($request->has('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->has('status') && auth()->user()->role === 'admin') {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortBy = $request->sort_by ?? 'reservation_date';
        $sortDirection = $request->sort_direction ?? 'asc';

        if (in_array($sortBy, ['reservation_date', 'created_at', 'number_of_guests'])) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $reservations = $query->paginate($request->per_page ?? 10);

        return $this->success($reservations, 'Reservations retrieved successfully');
    }

    public function store(ReservationRequest $request)
    {
        $weddingHall = WeddingHall::findOrFail($request->wedding_hall_id);

        // Check if wedding hall is approved
        if ($weddingHall->status !== 'approved') {
            return $this->error('Wedding hall is not available for booking', 400);
        }

        // Check if date is already booked
        $existingReservation = Reservation::where('wedding_hall_id', $request->wedding_hall_id)
            ->where('reservation_date', $request->reservation_date)
            ->where('status', 'booked')
            ->first();

        if ($existingReservation) {
            return $this->error('This date is already booked', 400);
        }

        // Check capacity
        if ($request->number_of_guests > $weddingHall->capacity) {
            return $this->error('Number of guests exceeds the wedding hall capacity', 400);
        }

        $reservation = $this->reservationService->createReservation($request, $weddingHall);

        return $this->success($reservation, 'Reservation created successfully');
    }

    public function show($id)
    {
        $reservation = Reservation::with(['weddingHall.district', 'user'])->findOrFail($id);

        // Check if user has permission to view
        if (auth()->user()->role === 'owner') {
            if ($reservation->weddingHall->owner_id !== auth()->id()) {
                return $this->error('You do not have permission to view this reservation', 403);
            }
        } elseif (auth()->user()->role === 'user') {
            if ($reservation->user_id !== auth()->id()) {
                return $this->error('You do not have permission to view this reservation', 403);
            }
        }

        return $this->success($reservation, 'Reservation retrieved successfully');
    }

    public function cancel($id)
    {
        $reservation = Reservation::findOrFail($id);

        // Check if user has permission to cancel
        if (auth()->user()->role === 'owner') {
            if ($reservation->weddingHall->owner_id !== auth()->id()) {
                return $this->error('You do not have permission to cancel this reservation', 403);
            }
        } elseif (auth()->user()->role === 'user') {
            if ($reservation->user_id !== auth()->id()) {
                return $this->error('You do not have permission to cancel this reservation', 403);
            }
        }

        $reservation->status = 'cancelled';
        $reservation->save();

        return $this->success($reservation, 'Reservation cancelled successfully');
    }

    public function userReservations()
    {
        $reservations = Reservation::with(['weddingHall.district'])
            ->where('user_id', auth()->id())
            ->paginate(10);

        return $this->success($reservations, 'User reservations retrieved successfully');
    }
}
