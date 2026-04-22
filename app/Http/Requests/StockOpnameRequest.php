<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOpnameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opname_date' => 'required|date',
            'status' => 'required|string|in:pending,completed,canceled',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            // 'products.*.product_id' => 'required|integer',
            'products.*.physical_quantity' => 'required|integer|min:0',
        ];

        // $isTesting = app()->environment('local');

        // return [
        //     'opname_date' => 'required|date',
        //     'status' => 'required|string|in:pending,completed,canceled',
        //     'products' => 'required|array|min:1',

        //     'products.*.product_id' => $isTesting
        //         ? 'required|integer'
        //         : 'required|exists:products,id',

        //     'products.*.physical_quantity' => 'required|integer|min:0',
        // ];
    }
}
