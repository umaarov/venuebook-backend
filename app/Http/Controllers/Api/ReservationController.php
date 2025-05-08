<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHallRequest;
use App\Models\District;
use App\Models\Reservation;
use App\Models\WeddingHall;
use App\Models\WeddingHallImage;
use App\Services\ReservationService;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReservationController extends Controller
{
    use ApiResponser;

    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['weddingHall.district', 'user']);

        if (auth()->user()->role === 'admin') {
        } elseif (auth()->user()->role === 'owner') {
            $query->whereHas('weddingHall', function ($query) {
                $query->where('owner_id', auth()->id());
            });
        } else {
            $query->where('user_id', auth()->id());
        }

        if ($request->has('wedding_hall_id')) {
            $query->where('wedding_hall_id', $request->wedding_hall_id);
        }

        if ($request->has('district_id')) {
            $query->whereHas('weddingHall', function ($query) use ($request) {
                $query->where('district_id', $request->district_id);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';

        if (in_array($sortBy, ['price_per_seat', 'capacity', 'name', 'created_at'])) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $weddingHalls = $query->paginate($request->per_page ?? 10);

        return $this->success($weddingHalls, 'Wedding halls retrieved successfully');
    }

    public function store(WeddingHallRequest $request)
    {
        $weddingHall = $this->weddingHallService->createWeddingHall($request);
        return $this->success($weddingHall, 'Wedding hall created successfully');
    }

    public function show($id)
    {
        $weddingHall = WeddingHall::with(['district', 'images', 'owner'])->findOrFail($id);

        if (auth()->user()->role === 'user' && $weddingHall->status !== 'approved') {
            return $this->error('You do not have permission to view this wedding hall', 403);
        }

        if (auth()->user()->role === 'owner' && $weddingHall->owner_id !== auth()->id()) {
            return $this->error('You do not have permission to view this wedding hall', 403);
        }

        $reservations = Reservation::where('wedding_hall_id', $id)
            ->where('status', 'booked')
            ->get(['reservation_date', 'number_of_guests', 'id', 'user_id', 'customer_name', 'customer_surname', 'customer_phone']);

        $today = Carbon::today();
        $daysInYear = 365;

        $availableDates = [];
        $bookedDates = [];
        $pastDates = [];

        for ($i = 0; $i < $daysInYear; $i++) {
            $date = $today->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');

            if ($date->lt($today)) {
                $pastDates[] = $dateString;
            } elseif ($reservations->where('reservation_date', $dateString)->count() > 0) {
                $reservation = $reservations->where('reservation_date', $dateString)->first();
                $bookedDates[] = [
                    'date' => $dateString,
                    'reservation_id' => $reservation->id,
                    'number_of_guests' => $reservation->number_of_guests,
                    'customer_name' => $reservation->customer_name,
                    'customer_surname' => $reservation->customer_surname,
                    'customer_phone' => $reservation->customer_phone,
                    'user_id' => $reservation->user_id,
                ];
            } else {
                $availableDates[] = $dateString;
            }
        }

        $data = [
            'wedding_hall' => $weddingHall,
            'calendar' => [
                'available_dates' => $availableDates,
                'booked_dates' => $bookedDates,
                'past_dates' => $pastDates,
            ],
        ];

        return $this->success($data, 'Wedding hall retrieved successfully');
    }

    public function update(WeddingHallRequest $request, $id)
    {
        $weddingHall = WeddingHall::findOrFail($id);

        if (auth()->user()->role === 'owner' && $weddingHall->owner_id !== auth()->id()) {
            return $this->error('You do not have permission to update this wedding hall', 403);
        }

        $updatedWeddingHall = $this->weddingHallService->updateWeddingHall($weddingHall, $request);
        return $this->success($updatedWeddingHall, 'Wedding hall updated successfully');
    }

    public function destroy($id)
    {
        $weddingHall = WeddingHall::findOrFail($id);

        if (auth()->user()->role !== 'admin') {
            return $this->error('You do not have permission to delete this wedding hall', 403);
        }

        foreach ($weddingHall->images as $image) {
            Storage::delete($image->image_path);
        }

        $weddingHall->delete();

        return $this->success(null, 'Wedding hall deleted successfully');
    }

    public function uploadImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
            'is_primary' => 'boolean',
        ]);

        $weddingHall = WeddingHall::findOrFail($id);

        if (auth()->user()->role === 'owner' && $weddingHall->owner_id !== auth()->id()) {
            return $this->error('You do not have permission to upload images for this wedding hall', 403);
        }

        $path = $request->file('image')->store('wedding-halls', 'public');

        $isPrimary = $request->is_primary ?? false;

        if ($isPrimary) {
            WeddingHallImage::where('wedding_hall_id', $id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $image = WeddingHallImage::create([
            'wedding_hall_id' => $id,
            'image_path' => $path,
            'is_primary' => $isPrimary,
        ]);

        return $this->success($image, 'Image uploaded successfully');
    }

    public function deleteImage($imageId)
    {
        $image = WeddingHallImage::findOrFail($imageId);
        $weddingHall = $image->weddingHall;

        if (auth()->user()->role === 'owner' && $weddingHall->owner_id !== auth()->id()) {
            return $this->error('You do not have permission to delete this image', 403);
        }

        Storage::delete($image->image_path);
        $image->delete();

        return $this->success(null, 'Image deleted successfully');
    }

    public function getDistricts()
    {
        $districts = District::all();
        return $this->success($districts, 'Districts retrieved successfully');
    }
}

