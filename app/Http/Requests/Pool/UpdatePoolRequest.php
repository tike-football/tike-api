<?php

namespace App\Http\Requests\Pool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pool = $this->input('pool', []);

        if (is_array($pool)) {
            if (!array_key_exists('user_ids', $pool) && array_key_exists('user_is', $pool)) {
                $pool['user_ids'] = $pool['user_is'];
            }

            $this->merge($pool);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<int, string> $scopes */
        $scopes = config('pools.scopes', []);
        /** @var array<int, string> $statuses */
        $statuses = config('pools.statuses', []);
        /** @var array<string, array<int, int>> $possibleScores */
        $possibleScores = config('pools.possible_scores', []);

        return [
            'name' => ['required', 'string', 'min:3'],
            'description' => ['required', 'string', 'min:100'],
            'scope' => ['required', 'string', Rule::in($scopes)],
            'type' => ['required', 'string', 'max:100'],
            'score_repeat_limit' => ['required', 'integer', 'min:0'],
            'accepts_join_requests' => ['required', 'boolean'],
            'requires_join_approval' => ['required', 'boolean'],
            'requires_join_code' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'possible_score_ids' => ['nullable', 'array'],
            'possible_score_ids.*' => ['string', Rule::in(array_keys($possibleScores))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $scope = (string) $this->input('scope', '');
            $type = (string) $this->input('type', '');
            $scoreRepeatLimit = (int) $this->input('score_repeat_limit', 0);
            $possibleScoreIds = $this->input('possible_score_ids');

            /** @var array<string, array<int, string>> $typesByScope */
            $typesByScope = config('pools.types_by_scope', []);
            $allowedTypes = $typesByScope[$scope] ?? [];

            if ($scope === 'match') {
                if (!in_array($type, $allowedTypes, true)) {
                    $validator->errors()->add('type', 'The selected type is invalid for the selected scope.');
                }

                if (!is_array($possibleScoreIds) || $possibleScoreIds === []) {
                    $validator->errors()->add('possible_score_ids', 'The possible_score_ids field is required when scope is match.');
                }
            }

            if ($type === 'selected_score' && $scoreRepeatLimit < 1) {
                $validator->errors()->add('score_repeat_limit', 'The score_repeat_limit field must be at least 1 when type is selected_score.');
            }
        });
    }
}
