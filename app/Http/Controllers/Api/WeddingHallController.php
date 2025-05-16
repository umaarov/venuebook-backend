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

    protected WeddingHallService $weddingHallService;

    public function __construct(WeddingHallService $weddingHallService)
    {
        $this->weddingHallService = $weddingHallService;
    }

    public function index(Request $request)
    {
        $query = WeddingHall::with(['district', 'primaryImage', 'owner']);
        $user = Auth::user();

        if ($user) {
            if ($user->role === 'admin') {
                if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
                    $query->where('status', $request->status);
                }
            } elseif ($user->role === 'owner') {
                $query->where('owner_id', $user->id);
                if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
                    $query->where('status', $request->status);
                }
            } else {
                $query->where('status', 'approved');
            }
        } else {
            $query->where('status', 'approved');
        }

        if ($request->has('district_id') && !empty($request->district_id)) {
            $query->where('district_id', $request->district_id);
        }

        $validSortColumns = ['name', 'capacity', 'price_per_seat', 'created_at', 'status'];
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
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
        $hallCreationData = collect($validatedData)->except(['images', 'primary_image', 'owner_id'])->toArray();

        $ownerIdToSet = Auth::id();
        if (Auth::user()->role === 'admin' && isset($validatedData['owner_id'])) {
            $ownerIdToSet = $validatedData['owner_id'];
        }

        try {
            $weddingHall = $this->weddingHallService->createWeddingHall(
                $hallCreationData,
                $ownerIdToSet,
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

        try {
            $updatedWeddingHall = $this->weddingHallService->updateWeddingHall($weddingHall, $request);

            $updatedWeddingHall->load(['district', 'images', 'owner', 'primaryImage']);

            return $this->success($updatedWeddingHall, 'Wedding hall updated successfully.');

        } catch (Exception $e) {
            Log::error('WeddingHallController@update Error: ' . $e->getMessage(), [
                'wedding_hall_id' => $id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update wedding hall due to a server error: ' . $e->getMessage(), 500);
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
