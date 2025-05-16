<?php

namespace App\Services;

use App\Models\WeddingHall;
use App\Models\WeddingHallImage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeddingHallService
{
    public function createWeddingHall(array $data, int $ownerId, ?array $imageFiles = null, ?int $primaryImageIndex = null): WeddingHall
    {
        DB::beginTransaction();

        try {
            // Use the $data array directly
            $weddingHallData = $data; // $data already contains name, district_id, address, etc.

            $weddingHallData['owner_id'] = $ownerId;
            $weddingHallData['status'] = (Auth::user() && Auth::user()->role === 'admin' && isset($data['status'])) ? $data['status'] : 'pending';
            // If an admin is creating AND explicitly setting a status for another owner. Otherwise, default to pending.
            // If an admin can also create for themselves, owner_id might come from $data['owner_id'] if validated & present, or default to Auth::id()

            $weddingHall = WeddingHall::create($weddingHallData);

            // Handle image uploads if any (using $imageFiles and $primaryImageIndex)
            if ($imageFiles) {
                foreach ($imageFiles as $index => $imageFile) {
                    // Ensure $imageFile is an UploadedFile instance
                    if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                        $path = $imageFile->store('wedding-halls', 'public'); // Store the file

                        WeddingHallImage::create([
                            'wedding_hall_id' => $weddingHall->id,
                            'image_path' => $path,
                            'is_primary' => $primaryImageIndex !== null && $index === $primaryImageIndex,
                        ]);
                    }
                }
            }

            DB::commit();

            return $weddingHall;
        } catch (Exception $e) {
            DB::rollBack();
            // Log the error before re-throwing or handle it
            Log::error('Error in WeddingHallService@createWeddingHall: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e; // Re-throw to be caught by controller or Laravel's handler
        }
    }

    public function updateWeddingHall(WeddingHall $weddingHall, Request $request)
    {
        $weddingHallData = [];

        if ($request->has('name')) {
            $weddingHallData['name'] = $request->name;
        }

        if ($request->has('district_id')) {
            $weddingHallData['district_id'] = $request->district_id;
        }

        if ($request->has('address')) {
            $weddingHallData['address'] = $request->address;
        }

        if ($request->has('capacity')) {
            $weddingHallData['capacity'] = $request->capacity;
        }

        if ($request->has('price_per_seat')) {
            $weddingHallData['price_per_seat'] = $request->price_per_seat;
        }

        if ($request->has('phone')) {
            $weddingHallData['phone'] = $request->phone;
        }

        // Only admin can update owner and status
        if (auth()->user()->role === 'admin') {
            if ($request->has('owner_id')) {
                $weddingHallData['owner_id'] = $request->owner_id;
            }

            if ($request->has('status')) {
                $weddingHallData['status'] = $request->status;
            }
        }

        $weddingHall->update($weddingHallData);

        return $weddingHall;
    }

    public function storeImages(WeddingHall $weddingHall, array $imageFiles, ?int $primaryImageIndex = null): void
    {
        foreach ($imageFiles as $index => $imageFile) {
            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                $path = $imageFile->store('wedding-halls', 'public');
                WeddingHallImage::create([
                    'wedding_hall_id' => $weddingHall->id,
                    'image_path' => $path,
                    'is_primary' => $primaryImageIndex !== null && $index === $primaryImageIndex,
                ]);
            }
        }
    }
}

