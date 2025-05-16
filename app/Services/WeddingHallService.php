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
            $weddingHallData = $data;

            $weddingHallData['owner_id'] = $ownerId;
            $weddingHallData['status'] = (Auth::user() && Auth::user()->role === 'admin' && isset($data['status'])) ? $data['status'] : 'pending';

            $weddingHall = WeddingHall::create($weddingHallData);

            if ($imageFiles) {
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

            DB::commit();

            return $weddingHall;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in WeddingHallService@createWeddingHall: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    public function updateWeddingHall(WeddingHall $weddingHall, Request $request): WeddingHall
    {
        DB::beginTransaction();
        try {
            $updateData = [];
            if ($request->has('name')) $updateData['name'] = $request->input('name');
            if ($request->has('district_id')) $updateData['district_id'] = $request->input('district_id');
            if ($request->has('address')) $updateData['address'] = $request->input('address');
            if ($request->has('capacity')) $updateData['capacity'] = $request->input('capacity');
            if ($request->has('price_per_seat')) $updateData['price_per_seat'] = $request->input('price_per_seat');
            if ($request->has('phone')) $updateData['phone'] = $request->input('phone');

            if (Auth::user()->role === 'admin') {
                if ($request->has('owner_id')) {
                    $updateData['owner_id'] = $request->input('owner_id');
                }
                if ($request->has('status')) {
                    $updateData['status'] = $request->input('status');
                }
            }

            if (!empty($updateData)) {
                $weddingHall->update($updateData);
            }

            if ($request->hasFile('new_images')) {
                $newImageFiles = $request->file('new_images');
                $primaryImageIndexInput = $request->input('primary_image');
                $primaryImageIndex = $primaryImageIndexInput !== null ? (int)$primaryImageIndexInput : null;

                if ($primaryImageIndex !== null && is_array($newImageFiles) && isset($newImageFiles[$primaryImageIndex])) {
                    WeddingHallImage::where('wedding_hall_id', $weddingHall->id)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);
                }

                foreach ($newImageFiles as $index => $imageFile) {
                    if ($imageFile instanceof \Illuminate\Http\UploadedFile && $imageFile->isValid()) {
                        $path = $imageFile->store('wedding-halls', 'public');
                        WeddingHallImage::create([
                            'wedding_hall_id' => $weddingHall->id,
                            'image_path' => $path,
                            'is_primary' => ($primaryImageIndex !== null && $index === $primaryImageIndex),
                        ]);
                    }
                }
            } elseif ($request->filled('primary_image') && $request->input('primary_image') === '' && $request->has('clear_primary_image_flag')) {
                // WeddingHallImage::where('wedding_hall_id', $weddingHall->id)->update(['is_primary' => false]);
            }


            DB::commit();
            $weddingHall->refresh();
            return $weddingHall;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in WeddingHallService@updateWeddingHall: ' . $e->getMessage(), [
                'wedding_hall_id' => $weddingHall->id,
                'request_all' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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

