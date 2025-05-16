<?php

namespace App\Http\Requests;

use App\Models\WeddingHall;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReservationRequest extends FormRequest
{
    public function authorize()
    {
        $user = Auth::user();

        if ($user && $user->role === 'admin') {
            return false;
        }

        if ($user && $user->role === 'owner') {
            $weddingHallId = $this->input('wedding_hall_id');
            if ($weddingHallId) {
                $weddingHall = WeddingHall::find($weddingHallId);
                if ($weddingHall && $weddingHall->owner_id === $user->id) {
                    return false;
                }
            }
        }

        return true;
    }

    public function rules()
    {
        return [
            'wedding_hall_id' => 'required|exists:wedding_halls,id',
            'reservation_date' => 'required|date|after_or_equal:' . Carbon::today()->toDateString(),
            'number_of_guests' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_surname' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
        ];
    }

    protected function prepareForValidation()
    {
        if (auth()->check()) {
            $user = auth()->user();
            $this->merge([
                'customer_name' => $this->customer_name ?? $user->name,
                'customer_surname' => $this->customer_surname ?? $user->surname,
                'customer_phone' => $this->customer_phone ?? $user->phone,
            ]);
        }
    }

    public function forbiddenResponse()
    {
        return response()->json(['message' => 'Admins or owners of this hall cannot make reservations here.'], 403);
    }
}
