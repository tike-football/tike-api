<?php

namespace App\Http\Requests\Group;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'allows_comments' => ['sometimes', 'boolean'],
            'accepts_join_requests' => ['sometimes', 'boolean'],
            'requires_join_approval' => ['sometimes', 'boolean'],
            'language' => ['sometimes', 'string', Rule::in(['es', 'en'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The group name is required.',
            'name.string' => 'The group name must be a string.',
            'name.max' => 'The group name may not be greater than 150 characters.',
            'description.string' => 'The group description must be a string.',
            'is_active.boolean' => 'The is_active field must be true or false.',
            'allows_comments.boolean' => 'The allows_comments field must be true or false.',
            'accepts_join_requests.boolean' => 'The accepts_join_requests field must be true or false.',
            'requires_join_approval.boolean' => 'The requires_join_approval field must be true or false.',
            'language.string' => 'The language field must be a string.',
            'language.in' => 'The selected language is invalid.',
        ];
    }
}
