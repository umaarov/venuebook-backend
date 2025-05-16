<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRequest;
use App\Models\Reservation;
use App\Models\WeddingHall;
use App\Services\ReservationService;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    use ApiResponser;

    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function index(Request $request)
    {
        $query = Reservation::with(['weddingHall.district', 'user']);

        $user = Auth::user();
        if ($user->role === 'admin') {
        } elseif ($user->role === 'owner') {
            $query->whereHas('weddingHall', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->has('wedding_hall_id')) {
            $query->where('wedding_hall_id', $request->wedding_hall_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sortBy = $request->input('sort_by', 'reservation_date');
        $sortDirection = $request->input('sort_direction', 'asc');
        $validSortColumns = ['reservation_date', 'created_at', 'number_of_guests', 'status'];

        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('reservation_date', 'asc');
        }

        $reservations = $query->paginate($request->input('per_page', 10));

        return $this->success($reservations, 'Reservations retrieved successfully.');
    }

    public function store(ReservationRequest $request)
    {

        $weddingHall = WeddingHall::findOrFail($request->input('wedding_hall_id'));

        if ($weddingHall->status !== 'approved') {
            return $this->error('Selected wedding hall is not currently available for booking.', 400);
        }

        $existingReservation = Reservation::where('wedding_hall_id', $request->input('wedding_hall_id'))
            ->where('reservation_date', $request->input('reservation_date'))
            ->where('status', 'booked')
            ->first();

        if ($existingReservation) {
            return $this->error('This date (and potentially time slot) is already booked for the selected hall.', 400);
        }

        if ($request->input('number_of_guests') > $weddingHall->capacity) {
            return $this->error('Number of guests exceeds the wedding hall capacity.', 400);
        }

        try {
            $reservation = $this->reservationService->createReservation($request, $weddingHall);

            $reservation->load(['weddingHall.district', 'user']);

            return $this->success($reservation, 'Reservation created successfully.');

        } catch (Exception $e) {
            Log::error('ReservationController@store Error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create reservation due to a server error. Please try again later.', 500);
        }
    }

    public function show($id)
    {
        $reservation = Reservation::with(['weddingHall.district', 'user'])->findOrFail($id);

        $user = Auth::user();
        if ($user->role === 'owner' && $reservation->weddingHall->owner_id !== $user->id) {
            return $this->error('You do not have permission to view this reservation.', 403);
        } elseif ($user->role === 'user' && $reservation->user_id !== $user->id) {
            return $this->error('You do not have permission to view this reservation.', 403);
        }

        return $this->success($reservation, 'Reservation retrieved successfully.');
    }

    public function cancel($id)
    {
        $reservation = Reservation::findOrFail($id);

        $user = Auth::user();
        if ($user->role === 'owner' && $reservation->weddingHall->owner_id !== $user->id) {
            return $this->error('You do not have permission to cancel this reservation.', 403);
        } elseif ($user->role === 'user' && $reservation->user_id !== $user->id) {
            return $this->error('You do not have permission to cancel this reservation.', 403);
        }

        // $this->reservationService->cancelReservation($reservation);
        $reservation->status = 'cancelled';
        $reservation->save();

        return $this->success($reservation, 'Reservation cancelled successfully.');
    }

    public function userReservations(Request $request)
    {
        $reservations = Reservation::with(['weddingHall.district'])
            ->where('user_id', Auth::id())
            ->orderBy('reservation_date', 'asc')
            ->paginate($request->input('per_page', 10));

        return $this->success($reservations, 'User reservations retrieved successfully.');
    }
}
