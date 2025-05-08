<?php

namespace App\Services;

use App\Models\WeddingHall;
use App\Models\WeddingHallImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WeddingHallService
{
    public function createWeddingHall(Request $request)
    {
        DB::beginTransaction();

        try {
            $weddingHallData = [
                'name' => $request->name,
                'district_id' => $request->district_id,
                'address' => $request->address,
                'capacity' => $request->capacity,
                'price_per_seat' => $request->price_per_seat,
                'phone' => $request->phone,
                'status' => auth()->user()->role === 'admin' ? 'approved' : 'pending',
            ];

            // If user is an owner, set owner_id to the authenticated user
            if (auth()->user()->role === 'owner') {
                $weddingHallData['owner_id'] = auth()->id();
            }
            // If user is an admin and owner_id is provided
            elseif (auth()->user()->role === 'admin' && $request->has('owner_id')) {
                $weddingHallData['owner_id'] = $request->owner_id;
            }

            $weddingHall = WeddingHall::create($weddingHallData);

            // Handle image uploads if any
            if ($request->hasFile('images')) {
                $primaryImageIndex = $request->primary_image ?? 0;

                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('wedding-halls', 'public');

                    WeddingHallImage::create([
                        'wedding_hall_id' => $weddingHall->id,
                        'image_path' => $path,
                        'is_primary' => $index === (int)$primaryImageIndex,
                    ]);
                }
            }

            DB::commit();

            return $weddingHall;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
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
}

