<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponser;

    public function update(UserUpdateRequest $request)
    {
        $user = auth()->user();

        $data = $request->validated();

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return $this->success($user, 'User updated successfully');
    }

    public function destroy()
    {
        $user = auth()->user();

        if ($user->role !== 'user') {
            return $this->error('Admin and owner accounts cannot be deleted through this endpoint', 403);
        }

        $user->delete();

        return $this->success(null, 'User deleted successfully');
    }
}
