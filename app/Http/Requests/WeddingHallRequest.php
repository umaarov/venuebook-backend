<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WeddingHallRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'district_id' => 'required|exists:districts,id',
            'address' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'price_per_seat' => 'required|numeric|min:0',
            'phone' => 'required|string|max:20',
        ];

        // Only admin can set owner directly when creating a hall
        if (auth()->user()->role === 'admin') {
            $rules['owner_id'] = 'nullable|exists:users,id';
        }

        // Only allow image upload during creation
        if ($this->isMethod('post')) {
            $rules['images'] = 'nullable|array';
            $rules['images.*'] = 'image|max:2048';
            $rules['primary_image'] = 'nullable|integer|min:0';
        }

        return $rules;
    }
}
