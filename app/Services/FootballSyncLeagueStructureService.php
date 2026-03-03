<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\League;
use App\Models\LeagueSeason;
use App\Models\LeagueStanding;
use App\Models\Team;
use Illuminate\Support\Collection;

class FootballSyncLeagueStructureService
{
    /**
     * @var array<int, string>
     */
    private const FINISHED_STATUS_SHORTS = ['FT', 'AET', 'PEN'];

    /**
     * Synchronize league season structure with DB standings/fixtures data.
     * The method only updates values in existing keys; it does not add or remove JSON fields.
     *
     * @param League|int $league
     */
    public function syncLeagueStructure(League|int $league, int $season): bool
    {
        $leagueModel = $league instanceof League
            ? $league
            : League::query()->find($league);

        if ($leagueModel === null) {
            return false;
        }

        $leagueSeason = LeagueSeason::query()
            ->where('league_id', $leagueModel->id)
            ->where('year', $season)
            ->first();

        if ($leagueSeason === null || !is_array($leagueSeason->structure)) {
            return false;
        }

        $structure = $leagueSeason->structure;
        $seasonStructure = $structure['season_structure'] ?? null;
        $phases = $seasonStructure['phases'] ?? null;

        if (!is_array($seasonStructure) || !is_array($phases) || $phases === []) {
            return false;
        }

        $standings = LeagueStanding::query()
            ->with(['rows.team'])
            ->where('league_id', $leagueModel->id)
            ->where('season', $season)
            ->get();

        $fixtures = Fixture::query()
            ->where('league_id', $leagueModel->id)
            ->where('season', $season)
            ->get()
            ->keyBy('id');

        $phaseOrder = $this->sortedPhaseKeys($phases);
        $slotAssignments = [];

        foreach ($phaseOrder as $phaseKey) {
            $phase = $phases[$phaseKey] ?? null;
            if (!is_array($phase)) {
                continue;
            }

            $phaseType = (string) ($phase['phase_type'] ?? '');
            $groupMode = (string) ($phase['group_mode'] ?? '');

            if ($phaseType === 'group') {
                $phase = $this->syncGroupPhaseStandings($phase, $standings);
                $slotAssignments = array_merge(
                    $slotAssignments,
                    $this->buildClassificationSlotsFromPhase($phase)
                );
            }

            if ($phaseType === 'playoff') {
            $phase = $this->syncPlayoffPhaseStandings(
                $phase,
                $fixtures,
                $slotAssignments
            );
            }

            if (isset($phase['classified_team_slots']) && is_array($phase['classified_team_slots'])) {
                $phase['classified_team_slots'] = $this->applySlotAssignmentsToSlots(
                    $phase['classified_team_slots'],
                    $slotAssignments
                );
            }

            foreach (($phase['ties'] ?? []) as $tie) {
                if (!is_array($tie)) {
                    continue;
                }

                $winnerSlot = $tie['winner_slot'] ?? null;
                $winnerTeam = $this->resolveTieWinnerTeam(
                    $tie,
                    $fixtures,
                    $slotAssignments
                );
                if (
                    is_string($winnerSlot)
                    && $winnerSlot !== ''
                    && is_int($winnerTeam)
                ) {
                    $slotAssignments[$winnerSlot] = $winnerTeam;
                }
            }

            $phases[$phaseKey] = $phase;
        }

        $seasonStructure['phases'] = $phases;
        if (isset($seasonStructure['classified_team_slots']) && is_array($seasonStructure['classified_team_slots'])) {
            $seasonStructure['classified_team_slots'] = $this->applySlotAssignmentsToSlots(
                $seasonStructure['classified_team_slots'],
                $slotAssignments
            );
        }
        $seasonStructure = $this->syncCurrentPhase($seasonStructure, $slotAssignments, $fixtures);
        $structure['season_structure'] = $seasonStructure;

        if ($structure === $leagueSeason->structure) {
            return false;
        }

        $leagueSeason->structure = $structure;
        $leagueSeason->save();

        return true;
    }

    /**
     * @param array<string, mixed> $phase
     * @param Collection<int, LeagueStanding> $standings
     * @return array<string, mixed>
     */
    private function syncGroupPhaseStandings(array $phase, Collection $standings): array
    {
        $groupMode = (string) ($phase['group_mode'] ?? '');

        if ($groupMode === 'single_group' && isset($phase['standings']) && is_array($phase['standings'])) {
            $standing = $this->resolveSingleGroupStanding($standings);
            $phase['standings'] = $this->applyRowsToStandingsArray(
                $phase['standings'],
                $standing?->rows ?? collect()
            );
        }

        if ($groupMode === 'multi_group' && isset($phase['groups']) && is_array($phase['groups'])) {
            foreach ($phase['groups'] as $index => $group) {
                if (!is_array($group) || !isset($group['standings']) || !is_array($group['standings'])) {
                    continue;
                }

                $standing = $this->resolveStandingForGroup($standings, $group, $index);
                $group['standings'] = $this->applyRowsToStandingsArray(
                    $group['standings'],
                    $standing?->rows ?? collect()
                );
                $phase['groups'][$index] = $group;
            }
        }

        return $phase;
    }

    /**
     * @param array<string, mixed> $phase
     * @param Collection<int, Fixture> $fixtures
     * @param array<string, int> $slotAssignments
     * @return array<string, mixed>
     */
    private function syncPlayoffPhaseStandings(
        array $phase,
        Collection $fixtures,
        array $slotAssignments
    ): array {
        if (!isset($phase['ties']) || !is_array($phase['ties'])) {
            return $phase;
        }

        foreach ($phase['ties'] as $index => $tie) {
            if (!is_array($tie)) {
                continue;
            }

            $resolvedTeams = [];
            if (isset($tie['teams']) && is_array($tie['teams'])) {
                foreach ($tie['teams'] as $teamValue) {
                    $resolvedTeam = $this->resolveTeamValue($teamValue, $slotAssignments);
                    $resolvedTeams[] = $resolvedTeam;
                }
            }

            $tie = $this->syncTieLegs(
                $tie,
                $phase,
                $fixtures,
                $resolvedTeams
            );

            if (isset($tie['standings']) && is_array($tie['standings'])) {
                $computedStats = $this->computeTieStandingsStats($tie, $fixtures, $resolvedTeams);
                $tie['standings'] = $this->applyComputedStatsToStandingsArray($tie['standings'], $computedStats);
            }

            $phase['ties'][$index] = $tie;
        }

        return $phase;
    }

    /**
     * @param array<string, mixed> $phase
     * @return array<string, int>
     */
    private function buildClassificationSlotsFromPhase(array $phase): array
    {
        $slots = [];
        $classification = $phase['classification'] ?? null;
        if (!is_array($classification)) {
            return $slots;
        }

        $outputSlots = $classification['output_slots'] ?? null;
        $qualifiedPositions = $classification['qualified_positions'] ?? null;
        $standings = $phase['standings'] ?? null;

        if (!is_array($outputSlots) || !is_array($qualifiedPositions) || !is_array($standings)) {
            return $slots;
        }

        foreach ($qualifiedPositions as $idx => $position) {
            $slot = $outputSlots[$idx] ?? null;
            if (!is_string($slot) || $slot === '') {
                continue;
            }

            $standingRow = $this->findStandingRowByPosition($standings, (int) $position);
            $team = $standingRow['team'] ?? null;
            if (is_int($team)) {
                $slots[$slot] = $team;
            }
        }

        return $slots;
    }

    /**
     * @param array<int, mixed> $standings
     * @return array<string, mixed>|null
     */
    private function findStandingRowByPosition(array $standings, int $position): ?array
    {
        foreach ($standings as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((int) ($row['position'] ?? 0) === $position) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $standingsTemplate
     * @param Collection<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyRowsToStandingsArray(array $standingsTemplate, Collection $rows): array
    {
        $orderedRows = $rows
            ->sortBy('rank_position')
            ->values();

        $result = [];
        $length = count($standingsTemplate);

        for ($i = 0; $i < $length; $i++) {
            $template = is_array($standingsTemplate[$i] ?? null) ? $standingsTemplate[$i] : [];
            $source = $orderedRows->get($i);
            $result[] = $this->mergeStandingRowFromRanking($template, $source, $i + 1);
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $standingsTemplate
     * @param array<int, array<string, mixed>> $computedStats
     * @return array<int, array<string, mixed>>
     */
    private function applyComputedStatsToStandingsArray(array $standingsTemplate, array $computedStats): array
    {
        $result = [];
        $length = count($standingsTemplate);

        for ($i = 0; $i < $length; $i++) {
            $template = is_array($standingsTemplate[$i] ?? null) ? $standingsTemplate[$i] : [];
            $source = $computedStats[$i] ?? null;
            $result[] = $this->mergeStandingRowFromComputed($template, $source, $i + 1);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $template
     * @param mixed $source
     * @return array<string, mixed>
     */
    private function mergeStandingRowFromRanking(array $template, mixed $source, int $defaultPosition): array
    {
        $row = $template;

        if (array_key_exists('position', $row)) {
            $row['position'] = $source !== null ? (int) ($source->rank_position ?? $defaultPosition) : $defaultPosition;
        }
        if (array_key_exists('team', $row)) {
            $row['team'] = $source?->team?->id !== null ? (int) $source->team->id : ($row['team'] ?? null);
        }
        if (array_key_exists('points', $row)) {
            $row['points'] = $source?->points;
        }
        if (array_key_exists('matches_played', $row)) {
            $row['matches_played'] = $source?->matches_played;
        }
        if (array_key_exists('matches_won', $row)) {
            $row['matches_won'] = $source?->matches_win;
        }
        if (array_key_exists('matches_drawn', $row)) {
            $row['matches_drawn'] = $source?->matches_draw;
        }
        if (array_key_exists('matches_lost', $row)) {
            $row['matches_lost'] = $source?->matches_lose;
        }
        if (array_key_exists('goals_for', $row)) {
            $row['goals_for'] = $source?->goals_for;
        }
        if (array_key_exists('goals_against', $row)) {
            $row['goals_against'] = $source?->goals_against;
        }
        if (array_key_exists('home_goals_for', $row)) {
            $row['home_goals_for'] = $source?->home_goals_for;
        }
        if (array_key_exists('away_goals_for', $row)) {
            $row['away_goals_for'] = $source?->away_goals_for;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed>|null $source
     * @return array<string, mixed>
     */
    private function mergeStandingRowFromComputed(array $template, ?array $source, int $defaultPosition): array
    {
        $row = $template;
        $source = $source ?? [];

        if (array_key_exists('position', $row)) {
            $row['position'] = (int) ($source['position'] ?? $defaultPosition);
        }
        if (array_key_exists('team', $row) && array_key_exists('team', $source)) {
            $row['team'] = $source['team'];
        }
        if (array_key_exists('points', $row)) {
            $row['points'] = $source['points'] ?? null;
        }
        if (array_key_exists('matches_played', $row)) {
            $row['matches_played'] = $source['matches_played'] ?? null;
        }
        if (array_key_exists('matches_won', $row)) {
            $row['matches_won'] = $source['matches_won'] ?? null;
        }
        if (array_key_exists('matches_drawn', $row)) {
            $row['matches_drawn'] = $source['matches_drawn'] ?? null;
        }
        if (array_key_exists('matches_lost', $row)) {
            $row['matches_lost'] = $source['matches_lost'] ?? null;
        }
        if (array_key_exists('goals_for', $row)) {
            $row['goals_for'] = $source['goals_for'] ?? null;
        }
        if (array_key_exists('goals_against', $row)) {
            $row['goals_against'] = $source['goals_against'] ?? null;
        }
        if (array_key_exists('home_goals_for', $row)) {
            $row['home_goals_for'] = $source['home_goals_for'] ?? null;
        }
        if (array_key_exists('away_goals_for', $row)) {
            $row['away_goals_for'] = $source['away_goals_for'] ?? null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $tie
     * @param Collection<int, Fixture> $fixtures
     * @param array<int, int|string|null> $resolvedTeams
     * @return array<int, array<string, mixed>>
     */
    private function computeTieStandingsStats(
        array $tie,
        Collection $fixtures,
        array $resolvedTeams
    ): array {
        $stats = [];

        $legs = $tie['legs'] ?? $tie['matches'] ?? [];
        if (!is_array($legs)) {
            $legs = [];
        }

        foreach ($legs as $leg) {
            if (!is_array($leg)) {
                continue;
            }

            $fixtureId = $leg['fixture_id'] ?? null;
            if (!is_int($fixtureId)) {
                continue;
            }

            /** @var Fixture|null $fixture */
            $fixture = $fixtures->get($fixtureId);
            if ($fixture === null) {
                continue;
            }

            $homeTeamId = $fixture->home_team_id;
            $awayTeamId = $fixture->away_team_id;
            if (!is_int($homeTeamId) || !is_int($awayTeamId)) {
                continue;
            }

            if (!isset($stats[$homeTeamId])) {
                $stats[$homeTeamId] = $this->emptyStatsRow($homeTeamId);
            }
            if (!isset($stats[$awayTeamId])) {
                $stats[$awayTeamId] = $this->emptyStatsRow($awayTeamId);
            }

            $homeGoals = $fixture->home_goals;
            $awayGoals = $fixture->away_goals;

            if ($homeGoals === null || $awayGoals === null) {
                continue;
            }

            $stats[$homeTeamId]['matches_played'] += 1;
            $stats[$awayTeamId]['matches_played'] += 1;

            $stats[$homeTeamId]['goals_for'] += $homeGoals;
            $stats[$homeTeamId]['goals_against'] += $awayGoals;
            $stats[$awayTeamId]['goals_for'] += $awayGoals;
            $stats[$awayTeamId]['goals_against'] += $homeGoals;

            $stats[$homeTeamId]['home_goals_for'] += $homeGoals;
            $stats[$awayTeamId]['away_goals_for'] += $awayGoals;

            if ($homeGoals > $awayGoals) {
                $stats[$homeTeamId]['matches_won'] += 1;
                $stats[$awayTeamId]['matches_lost'] += 1;
                $stats[$homeTeamId]['points'] += 3;
            } elseif ($homeGoals < $awayGoals) {
                $stats[$awayTeamId]['matches_won'] += 1;
                $stats[$homeTeamId]['matches_lost'] += 1;
                $stats[$awayTeamId]['points'] += 3;
            } else {
                $stats[$homeTeamId]['matches_drawn'] += 1;
                $stats[$awayTeamId]['matches_drawn'] += 1;
                $stats[$homeTeamId]['points'] += 1;
                $stats[$awayTeamId]['points'] += 1;
            }
        }

        if ($stats === []) {
            return [];
        }

        $ordered = array_values($stats);
        usort($ordered, function (array $a, array $b): int {
            $pointsCompare = ($b['points'] <=> $a['points']);
            if ($pointsCompare !== 0) {
                return $pointsCompare;
            }

            $goalDiffA = $a['goals_for'] - $a['goals_against'];
            $goalDiffB = $b['goals_for'] - $b['goals_against'];
            $goalDiffCompare = $goalDiffB <=> $goalDiffA;
            if ($goalDiffCompare !== 0) {
                return $goalDiffCompare;
            }

            $goalsForCompare = $b['goals_for'] <=> $a['goals_for'];
            if ($goalsForCompare !== 0) {
                return $goalsForCompare;
            }

            return ((int) $a['team']) <=> ((int) $b['team']);
        });

        foreach ($ordered as $index => &$item) {
            $item['position'] = $index + 1;
        }
        unset($item);

        return $ordered;
    }

    /**
     * @return array<string, int>
     */
    private function emptyStatsRow(int $teamId): array
    {
        return [
            'position' => 0,
            'team' => $teamId,
            'points' => 0,
            'matches_played' => 0,
            'matches_won' => 0,
            'matches_drawn' => 0,
            'matches_lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'home_goals_for' => 0,
            'away_goals_for' => 0,
        ];
    }

    private function resolveTeamValue(mixed $teamValue, array $slotAssignments): mixed
    {
        if (is_int($teamValue)) {
            return $teamValue;
        }

        if (is_string($teamValue) && isset($slotAssignments[$teamValue])) {
            return $slotAssignments[$teamValue];
        }

        return $teamValue;
    }

    /**
     * @param array<string, mixed> $tie
     * @param array<string, mixed> $phase
     * @param Collection<int, Fixture> $fixtures
     * @param array<int, int|string|null> $resolvedTeams
     * @return array<string, mixed>
     */
    private function syncTieLegs(
        array $tie,
        array $phase,
        Collection $fixtures,
        array $resolvedTeams
    ): array {
        $legsKey = isset($tie['legs']) && is_array($tie['legs']) ? 'legs' : (
            (isset($tie['matches']) && is_array($tie['matches'])) ? 'matches' : null
        );

        if ($legsKey === null) {
            return $tie;
        }

        $legs = $tie[$legsKey];
        $resolvedTeamIds = array_values(array_filter($resolvedTeams, fn ($team) => is_int($team)));
        if (count($resolvedTeamIds) < 2) {
            return $tie;
        }

        $localTeamA = $resolvedTeamIds[0];
        $localTeamB = $resolvedTeamIds[1];

        $phaseName = (string) ($phase['phase_name'] ?? '');
        $tieName = (string) ($tie['name'] ?? '');

        $candidateFixtures = $fixtures
            ->filter(function (Fixture $fixture) use ($localTeamA, $localTeamB, $phaseName, $tieName): bool {
                $teamsMatch = (
                    ($fixture->home_team_id === $localTeamA && $fixture->away_team_id === $localTeamB)
                    || ($fixture->home_team_id === $localTeamB && $fixture->away_team_id === $localTeamA)
                );

                if (!$teamsMatch) {
                    return false;
                }

                return $this->roundMatchesPhase((string) ($fixture->round ?? ''), $phaseName, $tieName);
            })
            ->sortBy(fn (Fixture $fixture): array => [
                (string) ($fixture->fixture_date?->format('Y-m-d H:i:s') ?? ''),
                (int) $fixture->id,
            ])
            ->values();

        if ($candidateFixtures->isEmpty()) {
            return $tie;
        }

        foreach ($legs as $index => $leg) {
            if (!is_array($leg)) {
                continue;
            }

            $fixtureId = $leg['fixture_id'] ?? null;
            $fixture = is_int($fixtureId) ? $fixtures->get($fixtureId) : null;

            if ($fixture === null) {
                $fixture = $candidateFixtures->get($index);
            }

            if ($fixture === null) {
                continue;
            }

            if (array_key_exists('fixture_id', $leg)) {
                $leg['fixture_id'] = (int) $fixture->id;
            }
            if (array_key_exists('home_team_id', $leg)) {
                $leg['home_team_id'] = $fixture->home_team_id ?? $leg['home_team_id'];
            }
            if (array_key_exists('away_team_id', $leg)) {
                $leg['away_team_id'] = $fixture->away_team_id ?? $leg['away_team_id'];
            }

            $legs[$index] = $leg;
        }

        $tie[$legsKey] = $legs;

        return $tie;
    }

    private function roundMatchesPhase(string $round, string $phaseName, string $tieName): bool
    {
        $roundNormalized = $this->normalizeFreeText($round);
        if ($roundNormalized === '') {
            return false;
        }

        $phaseNormalized = $this->normalizeFreeText($phaseName);
        $tieNormalized = $this->normalizeFreeText($tieName);

        $keywords = [];
        foreach ([$phaseNormalized, $tieNormalized] as $source) {
            if ($source === '') {
                continue;
            }
            if (str_contains($source, 'quarter') || str_contains($source, 'cuarto')) {
                $keywords[] = 'quarter';
                $keywords[] = 'cuarto';
            }
            if (str_contains($source, 'semi')) {
                $keywords[] = 'semi';
            }
            if (str_contains($source, 'final')) {
                $keywords[] = 'final';
            }
            if (str_contains($source, 'octavo') || str_contains($source, 'round of 16')) {
                $keywords[] = 'octavo';
                $keywords[] = 'round of 16';
            }
        }

        $keywords = array_values(array_unique(array_filter($keywords)));
        if ($keywords === []) {
            return true;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($roundNormalized, $keyword)) {
                return true;
            }
        }

        // Explicit round aliases for competitions with non-matching labels.
        $aliases = [
            'play-off' => ['playoff', 'play-offs', 'play off'],
            'octavo' => ['round of 16', 'round of 32'],
            'round of 16' => ['round of 32'],
            'league stage' => ['league stage'],
        ];

        foreach ($aliases as $key => $aliasList) {
            if (
                ($phaseNormalized !== '' && str_contains($phaseNormalized, $key))
                || ($tieNormalized !== '' && str_contains($tieNormalized, $key))
            ) {
                foreach ($aliasList as $alias) {
                    if (str_contains($roundNormalized, $alias)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function normalizeFreeText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $value
        );
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    /**
     * @param Collection<int, LeagueStanding> $standings
     */
    private function resolveSingleGroupStanding(Collection $standings): ?LeagueStanding
    {
        if ($standings->isEmpty()) {
            return null;
        }

        $nullGroupStanding = $standings->first(
            fn (LeagueStanding $standing): bool => empty((string) $standing->standing_group)
        );
        if ($nullGroupStanding !== null) {
            return $nullGroupStanding;
        }

        return $standings
            ->sortByDesc(fn (LeagueStanding $standing): int => $standing->rows->count())
            ->first();
    }

    /**
     * @param Collection<int, LeagueStanding> $standings
     * @param array<string, mixed> $group
     */
    private function resolveStandingForGroup(Collection $standings, array $group, int $fallbackIndex): ?LeagueStanding
    {
        $groupLabels = [
            $group['group_id'] ?? null,
            $group['name'] ?? null,
        ];

        foreach ($groupLabels as $label) {
            if (!is_string($label) || $label === '') {
                continue;
            }

            $normalizedLabel = $this->normalizeGroupLabel($label);
            $matched = $standings->first(function (LeagueStanding $standing) use ($normalizedLabel): bool {
                $standingGroup = $this->normalizeGroupLabel((string) ($standing->standing_group ?? ''));
                $standingStage = $this->normalizeGroupLabel((string) ($standing->standing_stage ?? ''));

                return $standingGroup === $normalizedLabel
                    || $standingStage === $normalizedLabel
                    || str_contains($standingGroup, $normalizedLabel)
                    || str_contains($standingStage, $normalizedLabel);
            });

            if ($matched !== null) {
                return $matched;
            }
        }

        return $standings
            ->sortBy('id')
            ->values()
            ->get($fallbackIndex);
    }

    private function normalizeGroupLabel(string $value): string
    {
        return $this->normalizeFreeText($value);
    }

    /**
     * @param array<string, mixed> $seasonStructure
     * @param array<string, int> $slotAssignments
     * @param Collection<int, Fixture> $fixtures
     * @return array<string, mixed>
     */
    private function syncCurrentPhase(array $seasonStructure, array $slotAssignments, Collection $fixtures): array
    {
        $phases = $seasonStructure['phases'] ?? null;
        $currentPhase = $seasonStructure['current_phase'] ?? null;

        if (!is_array($phases) || !is_string($currentPhase) || !isset($phases[$currentPhase])) {
            return $seasonStructure;
        }

        $phaseOrder = $this->sortedPhaseKeys($phases);
        $currentIndex = array_search($currentPhase, $phaseOrder, true);
        if (!is_int($currentIndex)) {
            return $seasonStructure;
        }

        while (true) {
            $phaseKey = $phaseOrder[$currentIndex] ?? null;
            $nextPhaseKey = $phaseOrder[$currentIndex + 1] ?? null;
            if (!is_string($phaseKey) || !is_string($nextPhaseKey)) {
                break;
            }

            $phase = $phases[$phaseKey] ?? null;
            $nextPhase = $phases[$nextPhaseKey] ?? null;

            if (!is_array($phase) || !is_array($nextPhase)) {
                break;
            }

            if (!$this->isPhaseFinished($phase, $fixtures)) {
                break;
            }

            if (!$this->phaseHasDefinedTeams($nextPhase, $slotAssignments)) {
                break;
            }

            $seasonStructure['current_phase'] = $nextPhaseKey;
            $currentIndex++;
        }

        return $seasonStructure;
    }

    /**
     * @param array<string, mixed> $tie
     * @param Collection<int, Fixture> $fixtures
     * @param array<string, int> $slotAssignments
     */
    private function resolveTieWinnerTeam(
        array $tie,
        Collection $fixtures,
        array $slotAssignments
    ): ?int {
        $resolvedTeams = [];
        foreach (($tie['teams'] ?? []) as $teamValue) {
            $resolved = $this->resolveTeamValue($teamValue, $slotAssignments);
            if (is_int($resolved)) {
                $resolvedTeams[] = $resolved;
            }
        }

        if (count($resolvedTeams) < 2) {
            return null;
        }

        $legs = $tie['legs'] ?? $tie['matches'] ?? null;
        if (!is_array($legs) || $legs === []) {
            return null;
        }

        $aggregate = [
            $resolvedTeams[0] => 0,
            $resolvedTeams[1] => 0,
        ];

        foreach ($legs as $leg) {
            if (!is_array($leg) || !is_int($leg['fixture_id'] ?? null)) {
                return null;
            }

            /** @var Fixture|null $fixture */
            $fixture = $fixtures->get((int) $leg['fixture_id']);
            if ($fixture === null) {
                return null;
            }

            if (!in_array((string) $fixture->status_short, self::FINISHED_STATUS_SHORTS, true)) {
                return null;
            }

            $homeTeamId = $fixture->home_team_id;
            $awayTeamId = $fixture->away_team_id;
            if (!is_int($homeTeamId) || !is_int($awayTeamId)) {
                return null;
            }

            if ($fixture->home_goals === null || $fixture->away_goals === null) {
                return null;
            }

            if (isset($aggregate[$homeTeamId])) {
                $aggregate[$homeTeamId] += (int) $fixture->home_goals;
            }
            if (isset($aggregate[$awayTeamId])) {
                $aggregate[$awayTeamId] += (int) $fixture->away_goals;
            }
        }

        if ($aggregate[$resolvedTeams[0]] === $aggregate[$resolvedTeams[1]]) {
            return null;
        }

        return $aggregate[$resolvedTeams[0]] > $aggregate[$resolvedTeams[1]]
            ? $resolvedTeams[0]
            : $resolvedTeams[1];
    }

    /**
     * @param array<string, mixed> $phase
     * @param Collection<int, Fixture> $fixtures
     */
    private function isPhaseFinished(array $phase, Collection $fixtures): bool
    {
        $fixtureIds = $this->collectFixtureIdsFromPhase($phase);

        if ($fixtureIds === []) {
            return false;
        }

        foreach ($fixtureIds as $fixtureId) {
            if (!is_int($fixtureId)) {
                return false;
            }

            /** @var Fixture|null $fixture */
            $fixture = $fixtures->get($fixtureId);
            if ($fixture === null) {
                return false;
            }

            if (!in_array((string) $fixture->status_short, self::FINISHED_STATUS_SHORTS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $phase
     * @param array<string, int> $slotAssignments
     */
    private function phaseHasDefinedTeams(array $phase, array $slotAssignments): bool
    {
        $phaseType = (string) ($phase['phase_type'] ?? '');

        if ($phaseType === 'playoff' && isset($phase['ties']) && is_array($phase['ties'])) {
            if ($phase['ties'] === []) {
                return false;
            }

            foreach ($phase['ties'] as $tie) {
                if (!is_array($tie) || !isset($tie['teams']) || !is_array($tie['teams']) || $tie['teams'] === []) {
                    return false;
                }

                foreach ($tie['teams'] as $teamValue) {
                    $resolved = $this->resolveTeamValue($teamValue, $slotAssignments);
                    if (!is_int($resolved)) {
                        return false;
                    }
                }
            }

            return true;
        }

        if ($phaseType === 'group' && isset($phase['standings']) && is_array($phase['standings'])) {
            foreach ($phase['standings'] as $row) {
                if (!is_array($row) || !isset($row['team']) || !is_int($row['team'])) {
                    return false;
                }
            }

            return !empty($phase['standings']);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $phase
     * @return array<int, mixed>
     */
    private function collectFixtureIdsFromPhase(array $phase): array
    {
        $ids = [];

        if (isset($phase['matches']) && is_array($phase['matches'])) {
            foreach ($phase['matches'] as $match) {
                if (is_array($match) && array_key_exists('fixture_id', $match)) {
                    $ids[] = $match['fixture_id'];
                }
            }
        }

        if (isset($phase['groups']) && is_array($phase['groups'])) {
            foreach ($phase['groups'] as $group) {
                if (!is_array($group) || !isset($group['matches']) || !is_array($group['matches'])) {
                    continue;
                }

                foreach ($group['matches'] as $match) {
                    if (is_array($match) && array_key_exists('fixture_id', $match)) {
                        $ids[] = $match['fixture_id'];
                    }
                }
            }
        }

        if (isset($phase['ties']) && is_array($phase['ties'])) {
            foreach ($phase['ties'] as $tie) {
                if (!is_array($tie)) {
                    continue;
                }

                $legs = $tie['legs'] ?? $tie['matches'] ?? null;
                if (!is_array($legs)) {
                    continue;
                }

                foreach ($legs as $leg) {
                    if (is_array($leg) && array_key_exists('fixture_id', $leg)) {
                        $ids[] = $leg['fixture_id'];
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $phases
     * @return array<int, string>
     */
    private function sortedPhaseKeys(array $phases): array
    {
        $items = collect($phases)
            ->map(function ($phase, $key): array {
                $order = is_array($phase) ? (int) ($phase['order'] ?? PHP_INT_MAX) : PHP_INT_MAX;
                return ['key' => (string) $key, 'order' => $order];
            })
            ->sortBy('order')
            ->values();

        return $items->pluck('key')->all();
    }

    /**
     * @param array<string, mixed> $slots
     * @param array<string, int> $slotAssignments
     * @return array<string, mixed>
     */
    private function applySlotAssignmentsToSlots(array $slots, array $slotAssignments): array
    {
        foreach ($slots as $slotKey => $slotValue) {
            if (!array_key_exists($slotKey, $slotAssignments)) {
                continue;
            }

            $assigned = $slotAssignments[$slotKey];

            if (is_array($slotValue)) {
                $slotValue['value'] = $assigned;
                $slots[$slotKey] = $slotValue;
                continue;
            }

            $slots[$slotKey] = $assigned;
        }

        return $slots;
    }
}
