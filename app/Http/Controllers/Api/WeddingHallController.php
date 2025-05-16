<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHallRequest;
use App\Models\District;
use App\Models\Reservation;
use App\Models\WeddingHall;
use App\Services\WeddingHallService;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WeddingHallController extends Controller
{
    use ApiResponser;

    protected WeddingHallService $weddingHallService;

    public function __construct(WeddingHallService $weddingHallService)
    {
        $this->weddingHallService = $weddingHallService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = WeddingHall::with(['district', 'primaryImage', 'owner']);

        if (auth()->user()->role === 'admin') {
        } elseif (auth()->user()->role === 'owner') {
            $query->where('owner_id', auth()->id());
        } else {
            $query->approved();
        }

        if ($request->has('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->has('status') && auth()->user()->role === 'admin') {
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

    public function store(WeddingHallRequest $request)
    {
        $validatedData = $request->validated();

        $images = $request->file('images');
        $primaryImageIndexInput = $request->input('primary_image');

        $hallCreationData = collect($validatedData)->except(['images', 'primary_image'])->toArray();

        try {
            $weddingHall = $this->weddingHallService->createWeddingHall(
                $hallCreationData,
                auth()->id(),
                $images,
                $primaryImageIndexInput !== null ? (int)$primaryImageIndexInput : null
            );

            $weddingHall->load(['district', 'primaryImage', 'owner']);

            return $this->success($weddingHall, 'Wedding hall created successfully and is pending approval.');

        } catch (Exception $e) {
            Log::error('WeddingHallController@store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->error('Failed to create wedding hall due to a server error.', 500);
        }
    }

    public function show($id): JsonResponse
    {
        $reservation = Reservation::with(['weddingHall.district', 'user'])->findOrFail($id);

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

    public function getDistricts()
    {
        $districts = District::all();
        return $this->success($districts, 'Districts retrieved successfully');
    }

    public function cancel($id)
    {
        $reservation = Reservation::findOrFail($id);

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

    public function userReservations(): JsonResponse
    {
        $reservations = Reservation::with(['weddingHall.district'])
            ->where('user_id', auth()->id())
            ->paginate(10);

        return $this->success($reservations, 'User reservations retrieved successfully');
    }
}
