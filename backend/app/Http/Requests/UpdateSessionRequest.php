<?php

namespace App\Http\Requests;

use App\Services\WalkieTalkieService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('callsign')) {
            $this->merge([
                'callsign' => app(WalkieTalkieService::class)
                    ->normalizeCallsign((string) $this->input('callsign')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'callsign' => [
                'required_without:channel',
                'string',
                'min:'.config('poptalk.callsign.min'),
                'max:'.config('poptalk.callsign.max'),
                'regex:'.config('poptalk.callsign.pattern'),
                Rule::unique('users', 'callsign')->ignore($this->user()),
            ],
            'channel' => [
                'required_without:callsign',
                'integer',
                'between:'.config('poptalk.min_frequency').','.config('poptalk.max_frequency'),
                'exists:frequencies,number',
            ],
        ];
    }
}
