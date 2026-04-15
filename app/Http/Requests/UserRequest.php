<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
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
        /**
         * Kita akan membedakan antara store dan update dengan melihat apakah terdapat user
         * dari parameter route. Jika `$this->route('user')` ada, berarti sedang update.
         * Jika tidak ada, berarti sedang store (create).
         */

        $userId = $this->route('user') ? $this->route('user')->id : null;

        // Aturan umum
        $rules = [
            'name'  => 'required',
            'email' => 'required|email|unique:users,email,' . $userId,
        ];

        if ($this->isMethod('POST')) {
            // Aturan khusus saat store
            $rules['password'] = 'required|confirmed';
        } else {
            // Aturan khusus saat update (PUT/PATCH)
            $rules['password'] = 'nullable|confirmed';
        }

        return $rules;
    }
}
