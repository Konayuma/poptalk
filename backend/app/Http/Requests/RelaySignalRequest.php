<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RelaySignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(config('poptalk.signal_types'))],
            'payload' => ['required', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                $encoded = json_encode($value);

                if ($encoded === false || strlen($encoded) > (int) config('poptalk.max_signal_payload_bytes')) {
                    $fail('The payload is too large.');
                }
            }],
            'target_id' => ['nullable', 'uuid', 'exists:users,uuid'],
        ];
    }
}
