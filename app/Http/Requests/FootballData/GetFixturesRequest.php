<?php

namespace App\Http\Requests\FootballData;

use Illuminate\Foundation\Http\FormRequest;

class GetFixturesRequest extends FormRequest
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
            'cache_fixtures_id' => ['nullable', 'string'],
        ];
    }

    public function validatedCacheFixturesId(): ?string
    {
        $value = $this->input('cache_fixtures_id');

        if (!is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('YmdHisv', $candidate);

        if ($date !== false && $date->format('YmdHisv') === $candidate) {
            return $candidate;
        }

        return null;
    }
}
