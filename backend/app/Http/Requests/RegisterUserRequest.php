<?php

namespace App\Http\Requests;

use App\Services\WalkieTalkieService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $callsign = $this->input('callsign');

        $this->merge([
            'email' => is_string($email) ? strtolower(trim($email)) : $email,
            'callsign' => is_string($callsign)
                ? app(WalkieTalkieService::class)->normalizeCallsign($callsign)
                : $callsign,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'callsign' => [
                'required',
                'string',
                'min:'.config('poptalk.callsign.min'),
                'max:'.config('poptalk.callsign.max'),
                'regex:'.config('poptalk.callsign.pattern'),
                'unique:users,callsign',
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
