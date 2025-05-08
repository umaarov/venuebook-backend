<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class ReservationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'wedding_hall_id' => 'required|exists:wedding_halls,id',
            'reservation_date' => 'required|date|after_or_equal:today',
            'number_of_guests' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_surname' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'customer_name' => $this->customer_name ?? auth()->user()->name,
            'customer_surname' => $this->customer_surname ?? auth()->user()->surname,
            'customer_phone' => $this->customer_phone ?? auth()->user()->phone,
        ]);
    }
}
