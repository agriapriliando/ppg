<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LocalLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $login = $this->input('login');
        if (is_string($login)) {
            $this->merge(['login' => mb_strtolower(trim($login))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['login.required' => 'Masukkan ID login atau email.', 'password.required' => 'Masukkan kata sandi.'];
    }
}
