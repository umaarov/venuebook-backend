<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHallOwnerRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WeddingHall;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    use ApiResponser;

    public function addOwner(WeddingHallOwnerRequest $request): JsonResponse
    {
        $owner = User::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'owner',
        ]);

        return $this->success($owner, 'Wedding hall owner created successfully');
    }

    public function associateOwner(Request $request): JsonResponse
    {
        $request->validate([
            'wedding_hall_id' => 'required|exists:wedding_halls,id',
            'owner_id' => 'required|exists:users,id',
        ]);

        $owner = User::findOrFail($request->owner_id);

        if ($owner->role !== 'owner') {
            return $this->error('User is not a wedding hall owner', 400);
        }

        $weddingHall = WeddingHall::findOrFail($request->wedding_hall_id);
        $weddingHall->owner_id = $request->owner_id;
        $weddingHall->save();

        return $this->success($weddingHall, 'Owner associated with wedding hall successfully');
    }

    public function approveWeddingHall($id): JsonResponse
    {
        $weddingHall = WeddingHall::findOrFail($id);
        $weddingHall->status = 'approved';
        $weddingHall->save();

        return $this->success($weddingHall, 'Wedding hall approved successfully');
    }

    public function rejectWeddingHall($id): JsonResponse
    {
        $weddingHall = WeddingHall::findOrFail($id);
        $weddingHall->status = 'rejected';
        $weddingHall->save();

        return $this->success($weddingHall, 'Wedding hall rejected successfully');
    }

    public function listOwners(): JsonResponse
    {
        $owners = User::where('role', 'owner')->get();
        return $this->success($owners, 'Wedding hall owners retrieved successfully');
    }

    public function listAllReservations(Request $request)
    {
        $query = Reservation::with([
            'weddingHall:id,name,district_id',
            'weddingHall.district:id,name',
            'user:id,name,surname,phone,username'
        ]);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }
        if ($request->has('wedding_hall_id') && !empty($request->wedding_hall_id)) {
            $query->where('wedding_hall_id', $request->wedding_hall_id);
        }
        if ($request->has('district_id') && !empty($request->district_id)) {
            $query->whereHas('weddingHall', function ($q) use ($request) {
                $q->where('district_id', $request->district_id);
            });
        }
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('reservation_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('reservation_date', '<=', $request->date_to);
        }


        $sortBy = $request->input('sort_by', 'reservation_date');
        $sortDirection = $request->input('sort_direction', 'desc');

        $validSortColumns = ['id', 'reservation_date', 'number_of_guests', 'status', 'created_at'];

        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } elseif ($sortBy === 'venue_name') {
            // $query->orderBy(WeddingHall::select('name')->whereColumn('reservations.wedding_hall_id', 'wedding_halls.id'), $sortDirection);
            $query->join('wedding_halls', 'reservations.wedding_hall_id', '=', 'wedding_halls.id')
                ->orderBy('wedding_halls.name', $sortDirection)
                ->select('reservations.*');
        } elseif ($sortBy === 'district_name') {
            $query->join('wedding_halls', 'reservations.wedding_hall_id', '=', 'wedding_halls.id')
                ->join('districts', 'wedding_halls.district_id', '=', 'districts.id')
                ->orderBy('districts.name', $sortDirection)
                ->select('reservations.*');
        } else {
            $query->orderBy('reservation_date', 'desc');
        }


        $reservations = $query->paginate($request->input('per_page', 15));

        return $this->success($reservations, 'All reservations retrieved successfully.');
    }

    public function cancelReservation(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        if (in_array($reservation->status, ['cancelled', 'completed'])) {
            return $this->error('This reservation is already ' . $reservation->status . ' and cannot be cancelled again.', 400);
        }

        $reservation->status = 'cancelled';
        // $reservation->cancellation_reason = $request->input('reason', 'Cancelled by Admin');
        $reservation->save();

        return $this->success($reservation, 'Reservation cancelled successfully by admin.');
    }

}
