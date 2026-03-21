<?php

namespace App\Http\Requests\Pool;

use App\Models\Fixture;
use App\Models\LeagueSeason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePoolRequest extends FormRequest
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
        /** @var array<int, string> $scopes */
        $scopes = config('pools.scopes', []);

        return [
            'league_id' => ['nullable', 'integer', 'exists:leagues,id'],
            'league_season_id' => ['nullable', 'integer', 'exists:league_seasons,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'name' => ['required', 'string', 'min:3'],
            'description' => ['required', 'string', 'min:100'],
            'scope' => ['required', 'string', Rule::in($scopes)],
            'fixture_id' => ['nullable', 'integer', 'exists:fixtures,id'],
            'start_phase' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'string', 'max:100'],
            'accepts_join_requests' => ['nullable', 'boolean'],
            'requires_join_approval' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $leagueId = $this->input('league_id');
            $leagueSeasonId = $this->input('league_season_id');
            $scope = (string) $this->input('scope', '');
            $fixtureId = $this->input('fixture_id');
            $type = $this->input('type');

            if ($leagueId !== null && $leagueSeasonId === null) {
                $validator->errors()->add('league_season_id', 'The league_season_id field is required when league_id is present.');
            }

            if ($leagueId !== null && $leagueSeasonId !== null) {
                $season = LeagueSeason::query()->find($leagueSeasonId);

                if ($season !== null && (int) $season->league_id !== (int) $leagueId) {
                    $validator->errors()->add('league_season_id', 'The selected league_season_id does not belong to the selected league_id.');
                }
            }

            if ($scope === 'match' && $fixtureId === null) {
                $validator->errors()->add('fixture_id', 'The fixture_id field is required when scope is match.');
            }

            if ($scope !== 'match' && $fixtureId !== null) {
                $validator->errors()->add('fixture_id', 'The fixture_id field is only allowed when scope is match.');
            }

            /** @var array<string, array<int, string>> $typesByScope */
            $typesByScope = config('pools.types_by_scope', []);
            $allowedTypes = $typesByScope[$scope] ?? [];

            if ($scope === 'match') {
                if (!in_array((string) $type, $allowedTypes, true)) {
                    $validator->errors()->add('type', 'The selected type is invalid for the selected scope.');
                }

                if ($fixtureId !== null) {
                    $fixture = Fixture::query()->find($fixtureId);
                    $startsAt = $fixture?->fixture_date;
                    $isUpcoming = $fixture !== null && in_array((string) $fixture->status_short, ['NS', 'TBD'], true);
                    $startsAfterOneHour = $startsAt !== null && $startsAt->greaterThan(now()->addHour());

                    if (!$isUpcoming || !$startsAfterOneHour) {
                        $validator->errors()->add(
                            'fixture_id',
                            'The fixture must be an upcoming fixture and must not start within the next hour.'
                        );
                    }
                }
            }
        });
    }
}
