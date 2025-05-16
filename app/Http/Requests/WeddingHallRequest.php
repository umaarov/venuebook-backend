<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class WeddingHallRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'district_id' => $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|required|exists:districts,id' : 'required|exists:districts,id',
            'address' => $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|required|string' : 'required|string',
            'capacity' => $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|required|integer|min:1' : 'required|integer|min:1',
            'price_per_seat' => $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|required|numeric|min:0' : 'required|numeric|min:0',
            'phone' => $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|required|string|max:20' : 'required|string|max:20',
        ];

        if (Auth::check() && Auth::user()->role === 'admin') {
            $rules['owner_id'] = 'nullable|exists:users,id';
            $rules['status'] = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes|string|in:pending,approved,rejected' : 'nullable|string|in:pending,approved,rejected';
        }

        if ($this->isMethod('post') && !$this->input('_method')) {
            $rules['images'] = 'nullable|array';
            $rules['images.*'] = 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
            $rules['primary_image'] = 'nullable|integer|min:0';
        }

        if (($this->isMethod('put') || $this->isMethod('patch')) || ($this->isMethod('post') && $this->input('_method'))) {
            $rules['new_images'] = 'nullable|array';
            $rules['new_images.*'] = 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
            $rules['primary_image'] = 'nullable|integer|min:0';
        }

        return $rules;
    }
}
