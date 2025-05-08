<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeddingHallOwnerRequest;
use App\Models\User;
use App\Models\WeddingHall;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    use ApiResponser;

    public function addOwner(WeddingHallOwnerRequest $request)
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

    public function associateOwner(Request $request)
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

    public function approveWeddingHall($id)
    {
        $weddingHall = WeddingHall::findOrFail($id);
        $weddingHall->status = 'approved';
        $weddingHall->save();

        return $this->success($weddingHall, 'Wedding hall approved successfully');
    }

    public function rejectWeddingHall($id)
    {
        $weddingHall = WeddingHall::findOrFail($id);
        $weddingHall->status = 'rejected';
        $weddingHall->save();

        return $this->success($weddingHall, 'Wedding hall rejected successfully');
    }

    public function listOwners()
    {
        $owners = User::where('role', 'owner')->get();
        return $this->success($owners, 'Wedding hall owners retrieved successfully');
    }
}
