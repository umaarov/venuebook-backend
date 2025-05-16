<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHallRequest;
use App\Models\District;
use App\Models\WeddingHall;
use App\Models\WeddingHallImage;
use App\Services\WeddingHallService;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'owner') {
                $query->where('owner_id', $user->id);
            } elseif ($user->role !== 'admin') {
                $query->approved();
            }
        } else {
            $query->approved();
        }

        if ($request->has('district_id')) {
            $query->where('district_id', $request->district_id);
        }
        if (Auth::check() && Auth::user()->role === 'admin' && $request->has('status')) {
            $query->where('status', $request->status);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $validSortColumns = ['name', 'capacity', 'price_per_seat', 'address', 'created_at'];

        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $weddingHalls = $query->paginate($request->input('per_page', 10));

        return $this->success($weddingHalls, 'Wedding halls retrieved successfully');
    }

    public function store(WeddingHallRequest $request)
    {
        $validatedData = $request->validated();
        $images = $request->file('images');
        $primaryImageIndexInput = $request->input('primary_image');

        try {
            $weddingHall = $this->weddingHallService->createWeddingHall(
                collect($validatedData)->except(['images', 'primary_image'])->toArray(),
                Auth::id(),
                $images,
                $primaryImageIndexInput !== null ? (int)$primaryImageIndexInput : null
            );
            $weddingHall->load(['district', 'primaryImage', 'owner']);
            return $this->success($weddingHall, 'Wedding hall created successfully. Pending approval.');
        } catch (Exception $e) {
            Log::error('WeddingHallController@store Error: ' . $e->getMessage());
            return $this->error('Failed to create wedding hall.', 500);
        }
    }

    public function show($id)
    {
        $weddingHall = WeddingHall::with(['district', 'images', 'owner'])->findOrFail($id);

        if (!Auth::check() || (Auth::user()->role !== 'admin' && Auth::id() !== $weddingHall->owner_id)) {
            if ($weddingHall->status !== 'approved') {
                return $this->error('This wedding hall is not currently available.', 403);
            }
        }
        return $this->success($weddingHall, 'Wedding hall retrieved successfully');
    }

    public function update(WeddingHallRequest $request, $id)
    {
        $weddingHall = WeddingHall::findOrFail($id);

        if (Auth::user()->role !== 'admin' && $weddingHall->owner_id !== Auth::id()) {
            return $this->error('You do not have permission to update this wedding hall.', 403);
        }

        $validatedData = $request->validated();
        $newImages = $request->file('new_images');
        $primaryImageIndexInput = $request->input('primary_image');

        try {
            $updatedWeddingHall = $this->weddingHallService->updateWeddingHall(
                $weddingHall,
                collect($validatedData)->except(['new_images', 'primary_image'])->toArray(),
                $newImages,
                $primaryImageIndexInput !== null ? (int)$primaryImageIndexInput : null
            );
            $updatedWeddingHall->load(['district', 'images', 'owner']);
            return $this->success($updatedWeddingHall, 'Wedding hall updated successfully.');
        } catch (Exception $e) {
            Log::error('WeddingHallController@update Error: ' . $e->getMessage());
            return $this->error('Failed to update wedding hall.', 500);
        }
    }

    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') {
            return $this->error('You do not have permission to delete this wedding hall.', 403);
        }

        $weddingHall = WeddingHall::findOrFail($id);
        foreach ($weddingHall->images as $image) {
            Storage::delete('public/' . $image->image_path);
            $image->delete();
        }
        $weddingHall->delete();
        return $this->success(null, 'Wedding hall deleted successfully.');
    }

    public function uploadImage(Request $request, $id)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'primary_image' => 'nullable|integer|min:0',
        ]);

        $weddingHall = WeddingHall::findOrFail($id);

        if (Auth::user()->role !== 'admin' && $weddingHall->owner_id !== Auth::id()) {
            return $this->error('You do not have permission to upload images.', 403);
        }

        try {
            if ($request->hasFile('images')) {
                $this->weddingHallService->storeImages(
                    $weddingHall,
                    $request->file('images'),
                    $request->input('primary_image') !== null ? (int)$request->input('primary_image') : null
                );
            }
            $weddingHall->load('images', 'primaryImage');
            return $this->success($weddingHall, 'Images uploaded successfully.');
        } catch (Exception $e) {
            Log::error('WeddingHallController@uploadImage Error: ' . $e->getMessage());
            return $this->error('Failed to upload images.', 500);
        }
    }

    public function deleteImage($imageId)
    {
        $image = WeddingHallImage::findOrFail($imageId);
        $weddingHall = $image->weddingHall;

        if (Auth::user()->role !== 'admin' && $weddingHall->owner_id !== Auth::id()) {
            return $this->error('You do not have permission to delete this image.', 403);
        }

        Storage::delete('public/' . $image->image_path);
        $image->delete();
        return $this->success(null, 'Image deleted successfully.');
    }

    public function getDistricts()
    {
        $districts = District::all();
        return $this->success($districts, 'Districts retrieved successfully');
    }
}
