<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar_path' => [
                'required',
                'string',
                'regex:/^(system|users)\/[^\/]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || $value === '') {
                        return;
                    }

                    $folderConfig = config('filesystems.folders.user_avatars', []);
                    $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');

                    if ($disk !== 's3') {
                        return;
                    }

                    $root = trim((string) ($folderConfig['root'] ?? 'users/avatars/'), '/');
                    $storagePath = $root . '/' . ltrim($value, '/');

                    if (!Storage::disk('s3')->exists($storagePath)) {
                        $fail('The selected avatar does not exist.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar_path.required' => 'The avatar path is required.',
            'avatar_path.string' => 'The avatar path must be a string.',
            'avatar_path.regex' => 'The avatar path must be in the format system/IMAGEN or users/IMAGEN.',
        ];
    }
}
