<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\LeagueStandingRow;
use App\Models\PlayerLeagueStat;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FootballFixturesCacheService
{
    public const CACHE_FIXTURES = 'cache-fixtures';
    public const CACHE_FIXTURES_CHANGES = 'cache-fixtures-changes';
    public const CACHE_FIXTURES_ID = 'cache-fixtures-id';
    public const CACHE_FIXTURES_CHANGES_ID = 'cache-fixtures-changes-id';
    private const CACHE_FIXTURES_CHANGES_SNAPSHOT = 'cache-fixtures-changes-snapshot';

    /**
     * @var array<int, string>
     */
    private const LIVE_STATUS_SHORTS = ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE', 'INT'];

    /**
     * @var array<int, string>
     */
    private const UPCOMING_STATUS_SHORTS = ['TBD', 'NS'];

    /**
     * @return array<string, mixed>
     */
    public function cacheFixtures(): array
    {
        $fixtures = $this->fullFixturesQuery()->get();
        $payload = $this->buildPayload($fixtures);
        $cacheVersionId = $this->generateCacheVersionId();

        Cache::forever(self::CACHE_FIXTURES, $payload);
        Cache::forever(self::CACHE_FIXTURES_ID, $cacheVersionId);
        Cache::forever(self::CACHE_FIXTURES_CHANGES_ID, $cacheVersionId);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function cacheFixtureChanges(): array
    {
        $fixtures = $this->changesFixturesQuery()->get();
        $currentSnapshot = $this->buildPayload($fixtures);
        $fullCachePayload = Cache::get(self::CACHE_FIXTURES, [
            'matches' => [],
            'indexes' => [],
            'leagues' => [],
            'teams' => [],
        ]);
        $previousSnapshot = Cache::get(self::CACHE_FIXTURES_CHANGES_SNAPSHOT, [
            'matches' => [],
            'teams' => [],
            'players' => [],
            'leagues' => [],
        ]);

        $changedMatchIds = [];
        $removedMatchIds = [];

        $baselineMatches = is_array($fullCachePayload['matches'] ?? null) ? $fullCachePayload['matches'] : [];
        $previousMatches = is_array($previousSnapshot['matches'] ?? null) ? $previousSnapshot['matches'] : [];
        $currentMatches = is_array($currentSnapshot['matches'] ?? null) ? $currentSnapshot['matches'] : [];

        foreach ($currentMatches as $matchId => $matchPayload) {
            $baselineMatchPayload = $baselineMatches[$matchId] ?? null;
            if ($baselineMatchPayload !== $matchPayload) {
                $changedMatchIds[] = (string) $matchId;
            }
        }

        foreach ($previousMatches as $matchId => $_) {
            if (!isset($currentMatches[$matchId])) {
                $removedMatchIds[] = (string) $matchId;
            }
        }

        $hasChanges = !empty($changedMatchIds) || !empty($removedMatchIds);
        $changedMatchContexts = [];

        $changes = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'source' => 'api_football',
                'version' => 1,
                'full_cache_id' => Cache::get(self::CACHE_FIXTURES_ID),
                'removed_match_ids' => $removedMatchIds,
            ],
            'indexes' => [
                'by_status' => [
                    'live' => [],
                    'upcoming' => [],
                    'finished' => [],
                ],
                'by_league' => [],
                'team_matches' => [],
            ],
            'leagues' => [],
            'teams' => [],
            'matches' => [],
            'players' => [],
        ];

        if (!empty($changedMatchIds)) {
            foreach ($changedMatchIds as $matchId) {
                $match = $currentMatches[$matchId] ?? null;
                if (!is_array($match)) {
                    continue;
                }

                $changes['matches'][$matchId] = $match;

                $status = (string) ($match['status'] ?? 'finished');
                $leagueId = (string) ($match['league_id'] ?? '');

                if (!in_array($status, ['live', 'upcoming', 'finished'], true)) {
                    $status = 'finished';
                }

                $teamKeys = [];
                foreach (['home_team_id', 'away_team_id'] as $teamIdField) {
                    $teamId = $match[$teamIdField] ?? null;
                    if ($teamId !== null) {
                        $teamKeys[] = (string) $teamId;
                    }
                }

                $changedMatchContexts[$matchId] = [
                    'status' => $status,
                    'league_id' => $leagueId,
                    'team_ids' => array_values(array_unique($teamKeys)),
                ];

                $changes['indexes']['by_status'][$status][] = $matchId;

                if (!isset($changes['indexes']['by_league'][$leagueId])) {
                    $changes['indexes']['by_league'][$leagueId] = [
                        'live' => [],
                        'upcoming' => [],
                        'finished' => [],
                    ];
                }
                $changes['indexes']['by_league'][$leagueId][$status][] = $matchId;

                $leaguePayload = $currentSnapshot['leagues'][$leagueId] ?? null;
                if (is_array($leaguePayload)) {
                    if (!isset($changes['leagues'][$leagueId])) {
                        $changes['leagues'][$leagueId] = [
                            'id' => $leaguePayload['id'] ?? null,
                            'name' => $leaguePayload['name'] ?? null,
                            'country' => $leaguePayload['country'] ?? null,
                            'season' => $leaguePayload['season'] ?? null,
                            'round' => $leaguePayload['round'] ?? null,
                            'logo' => $leaguePayload['logo'] ?? null,
                            'flag' => $leaguePayload['flag'] ?? null,
                            'teams' => [],
                            'matches' => [
                                'live' => [],
                                'upcoming' => [],
                                'finished' => [],
                            ],
                        ];
                    }

                    $changes['leagues'][$leagueId]['matches'][$status][] = $matchId;
                }

                foreach (['home_team_id', 'away_team_id'] as $teamIdField) {
                    $teamId = $match[$teamIdField] ?? null;
                    if ($teamId === null) {
                        continue;
                    }

                    $teamKey = (string) $teamId;

                    $changes['indexes']['team_matches'][$teamKey] = $changes['indexes']['team_matches'][$teamKey] ?? [
                        'live' => [],
                        'upcoming' => [],
                        'finished' => [],
                    ];
                    $changes['indexes']['team_matches'][$teamKey][$status][] = $matchId;

                    if (isset($changes['leagues'][$leagueId])) {
                        $changes['leagues'][$leagueId]['teams'][] = $teamKey;
                    }

                    if (isset($currentSnapshot['teams'][$teamKey]) && !isset($changes['teams'][$teamKey])) {
                        $changes['teams'][$teamKey] = $currentSnapshot['teams'][$teamKey];
                    }
                }
            }
        }

        if ($hasChanges && is_array($fullCachePayload['indexes'] ?? null)) {
            $changes['indexes'] = $fullCachePayload['indexes'];
        } elseif ($hasChanges && is_array($currentSnapshot['indexes'] ?? null)) {
            $changes['indexes'] = $currentSnapshot['indexes'];
        }

        if ($hasChanges && is_array($fullCachePayload['leagues'] ?? null)) {
            foreach ($changes['leagues'] as $leagueId => &$leaguePayload) {
                if (isset($fullCachePayload['leagues'][$leagueId]['matches']) && is_array($fullCachePayload['leagues'][$leagueId]['matches'])) {
                    $leaguePayload['matches'] = $fullCachePayload['leagues'][$leagueId]['matches'];
                }
            }
            unset($leaguePayload);
        }

        if ($hasChanges && is_array($fullCachePayload['teams'] ?? null)) {
            foreach ($changes['teams'] as $teamId => &$teamPayload) {
                if (isset($fullCachePayload['teams'][$teamId]['matches']) && is_array($fullCachePayload['teams'][$teamId]['matches'])) {
                    $teamPayload['matches'] = $fullCachePayload['teams'][$teamId]['matches'];
                }
            }
            unset($teamPayload);
        }

        if ($hasChanges) {
            foreach ($changedMatchContexts as $matchId => $context) {
                $targetStatus = $context['status'] ?? 'finished';
                $targetLeagueId = (string) ($context['league_id'] ?? '');
                $targetTeamIds = is_array($context['team_ids'] ?? null) ? $context['team_ids'] : [];

                foreach (['live', 'upcoming', 'finished'] as $status) {
                    $changes['indexes']['by_status'][$status] = array_values(array_filter(
                        $changes['indexes']['by_status'][$status] ?? [],
                        fn ($id) => (string) $id !== (string) $matchId
                    ));
                }
                $changes['indexes']['by_status'][$targetStatus][] = $matchId;

                if (!isset($changes['indexes']['by_league'][$targetLeagueId])) {
                    $changes['indexes']['by_league'][$targetLeagueId] = [
                        'live' => [],
                        'upcoming' => [],
                        'finished' => [],
                    ];
                }
                foreach (['live', 'upcoming', 'finished'] as $status) {
                    $changes['indexes']['by_league'][$targetLeagueId][$status] = array_values(array_filter(
                        $changes['indexes']['by_league'][$targetLeagueId][$status] ?? [],
                        fn ($id) => (string) $id !== (string) $matchId
                    ));
                }
                $changes['indexes']['by_league'][$targetLeagueId][$targetStatus][] = $matchId;

                foreach ($targetTeamIds as $teamId) {
                    if (!isset($changes['indexes']['team_matches'][$teamId])) {
                        $changes['indexes']['team_matches'][$teamId] = [
                            'live' => [],
                            'upcoming' => [],
                            'finished' => [],
                        ];
                    }
                    foreach (['live', 'upcoming', 'finished'] as $status) {
                        $changes['indexes']['team_matches'][$teamId][$status] = array_values(array_filter(
                            $changes['indexes']['team_matches'][$teamId][$status] ?? [],
                            fn ($id) => (string) $id !== (string) $matchId
                        ));
                    }
                    $changes['indexes']['team_matches'][$teamId][$targetStatus][] = $matchId;
                }

                if (isset($changes['leagues'][$targetLeagueId]['matches'])) {
                    foreach (['live', 'upcoming', 'finished'] as $status) {
                        $changes['leagues'][$targetLeagueId]['matches'][$status] = array_values(array_filter(
                            $changes['leagues'][$targetLeagueId]['matches'][$status] ?? [],
                            fn ($id) => (string) $id !== (string) $matchId
                        ));
                    }
                    $changes['leagues'][$targetLeagueId]['matches'][$targetStatus][] = $matchId;
                }

                foreach ($targetTeamIds as $teamId) {
                    if (isset($changes['teams'][$teamId]['matches'])) {
                        foreach (['live', 'upcoming', 'finished'] as $status) {
                            $changes['teams'][$teamId]['matches'][$status] = array_values(array_filter(
                                $changes['teams'][$teamId]['matches'][$status] ?? [],
                                fn ($id) => (string) $id !== (string) $matchId
                            ));
                        }
                        $changes['teams'][$teamId]['matches'][$targetStatus][] = $matchId;
                    }
                }
            }

            if (!empty($removedMatchIds)) {
                foreach (['live', 'upcoming', 'finished'] as $status) {
                    $changes['indexes']['by_status'][$status] = array_values(array_filter(
                        $changes['indexes']['by_status'][$status] ?? [],
                        fn ($id) => !in_array((string) $id, $removedMatchIds, true)
                    ));
                }

                foreach ($changes['indexes']['by_league'] as $leagueId => $byStatus) {
                    foreach (['live', 'upcoming', 'finished'] as $status) {
                        $changes['indexes']['by_league'][$leagueId][$status] = array_values(array_filter(
                            $byStatus[$status] ?? [],
                            fn ($id) => !in_array((string) $id, $removedMatchIds, true)
                        ));
                    }
                }

                foreach ($changes['indexes']['team_matches'] as $teamId => $byStatus) {
                    foreach (['live', 'upcoming', 'finished'] as $status) {
                        $changes['indexes']['team_matches'][$teamId][$status] = array_values(array_filter(
                            $byStatus[$status] ?? [],
                            fn ($id) => !in_array((string) $id, $removedMatchIds, true)
                        ));
                    }
                }

                foreach ($changes['leagues'] as $leagueId => $leaguePayload) {
                    foreach (['live', 'upcoming', 'finished'] as $status) {
                        $changes['leagues'][$leagueId]['matches'][$status] = array_values(array_filter(
                            $leaguePayload['matches'][$status] ?? [],
                            fn ($id) => !in_array((string) $id, $removedMatchIds, true)
                        ));
                    }
                }

                foreach ($changes['teams'] as $teamId => $teamPayload) {
                    foreach (['live', 'upcoming', 'finished'] as $status) {
                        $changes['teams'][$teamId]['matches'][$status] = array_values(array_filter(
                            $teamPayload['matches'][$status] ?? [],
                            fn ($id) => !in_array((string) $id, $removedMatchIds, true)
                        ));
                    }
                }
            }
        }

        $teamIds = array_keys($changes['teams']);
        foreach ($teamIds as $teamId) {
            $teamPayload = $changes['teams'][$teamId];
            $playerIds = $teamPayload['players'] ?? [];
            if (!is_array($playerIds)) {
                continue;
            }
            foreach ($playerIds as $playerId) {
                if (isset($currentSnapshot['players'][(string) $playerId])) {
                    $changes['players'][(string) $playerId] = $currentSnapshot['players'][(string) $playerId];
                }
            }
        }

        $this->deduplicateIndexes($changes);
        Cache::forever(self::CACHE_FIXTURES_CHANGES, $changes);
        Cache::forever(self::CACHE_FIXTURES_CHANGES_ID, $this->generateCacheVersionId());
        Cache::forever(self::CACHE_FIXTURES_CHANGES_SNAPSHOT, $currentSnapshot);

        return $changes;
    }

    public function hasRelevantFixturesForChanges(?int $providerLeagueId = null): bool
    {
        return $this->changesFixturesQuery(withRelations: false, providerLeagueId: $providerLeagueId)->exists();
    }

    private function fullFixturesQuery()
    {
        return Fixture::query()
            ->with(['league', 'homeTeam', 'awayTeam', 'teamStats.team'])
            ->whereHas('league', fn ($query) => $query->where('current', true))
            ->orderBy('fixture_date');
    }

    private function changesFixturesQuery(bool $withRelations = true, ?int $providerLeagueId = null)
    {
        $now = now();
        $inFiveMinutes = $now->copy()->addMinutes(5);
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        $query = Fixture::query()
            ->whereHas('league', function ($query) use ($providerLeagueId) {
                $query->where('current', true);

                if ($providerLeagueId !== null) {
                    $query->where('provider_league_id', $providerLeagueId);
                }
            })
            ->where(function ($query) use ($now, $inFiveMinutes, $fiveMinutesAgo) {
                $query->whereIn('status_short', self::LIVE_STATUS_SHORTS)
                    ->orWhere(function ($upcomingQuery) use ($fiveMinutesAgo, $inFiveMinutes) {
                        $upcomingQuery->whereIn('status_short', self::UPCOMING_STATUS_SHORTS)
                            ->whereNotNull('fixture_date')
                            ->whereBetween('fixture_date', [$fiveMinutesAgo, $inFiveMinutes]);
                    })
                    ->orWhere(function ($finishedQuery) use ($fiveMinutesAgo) {
                        $finishedQuery
                            ->whereNotIn('status_short', array_merge(self::LIVE_STATUS_SHORTS, self::UPCOMING_STATUS_SHORTS))
                            ->whereNotNull('finished_at')
                            ->where('finished_at', '>=', $fiveMinutesAgo);
                    });
            });

        if ($withRelations) {
            $query->with(['league', 'homeTeam', 'awayTeam', 'teamStats.team']);
        }

        return $query->orderBy('fixture_date');
    }

    /**
     * @param Collection<int, Fixture> $fixtures
     * @return array<string, mixed>
     */
    private function buildPayload(Collection $fixtures): array
    {
        $payload = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'source' => 'api_football',
                'version' => 1,
            ],
            'indexes' => [
                'by_status' => [
                    'live' => [],
                    'upcoming' => [],
                    'finished' => [],
                ],
                'by_league' => [],
                'team_matches' => [],
            ],
            'leagues' => [],
            'teams' => [],
            'matches' => [],
            'players' => [],
        ];

        $teamLocalIds = [];
        $leagueRoundStatus = [];

        foreach ($fixtures as $fixture) {
            if ($fixture->league === null) {
                continue;
            }

            $fixtureKey = (string) $fixture->provider_fixture_id;
            $leagueProviderId = (string) $fixture->league->provider_league_id;
            $status = $this->mapFixtureStatus($fixture->status_short);

            if (!isset($payload['leagues'][$leagueProviderId])) {
                $payload['leagues'][$leagueProviderId] = [
                    'id' => (int) $fixture->league->provider_league_id,
                    'name' => $fixture->league->name,
                    'country' => $fixture->league->country_name,
                    'season' => (int) $fixture->season,
                    'round' => null,
                    'logo' => $fixture->league->logo,
                    'flag' => $fixture->league->flag,
                    'teams' => [],
                    'matches' => [
                        'live' => [],
                        'upcoming' => [],
                        'finished' => [],
                    ],
                ];
            }

            if (!isset($payload['indexes']['by_league'][$leagueProviderId])) {
                $payload['indexes']['by_league'][$leagueProviderId] = [
                    'live' => [],
                    'upcoming' => [],
                    'finished' => [],
                ];
            }

            $homeProviderTeamId = $fixture->homeTeam?->provider_team_id;
            $awayProviderTeamId = $fixture->awayTeam?->provider_team_id;
            $homeProviderTeamKey = $homeProviderTeamId !== null ? (string) $homeProviderTeamId : null;
            $awayProviderTeamKey = $awayProviderTeamId !== null ? (string) $awayProviderTeamId : null;

            $teamStats = [];
            $playersByTeam = [];

            foreach ($fixture->teamStats as $stat) {
                $providerTeamId = $stat->team?->provider_team_id;
                if ($providerTeamId === null) {
                    continue;
                }

                $providerTeamKey = (string) $providerTeamId;
                $teamStats[$providerTeamKey] = [
                    'goals' => $stat->goals,
                    'winner' => $stat->winner,
                    'statistics' => $stat->raw_statistics,
                ];
                $playersByTeam[$providerTeamKey] = [
                    'starters' => [],
                    'bench' => [],
                ];
            }

            $payload['matches'][$fixtureKey] = [
                'id' => (int) $fixture->provider_fixture_id,
                'league_id' => (int) $fixture->league->provider_league_id,
                'season' => (int) $fixture->season,
                'round' => $fixture->round,
                'status' => $status,
                'status_short' => $fixture->status_short,
                'minute' => $fixture->status_elapsed,
                'date' => $fixture->fixture_date?->toIso8601String(),
                'home_team_id' => $homeProviderTeamId,
                'away_team_id' => $awayProviderTeamId,
                'score' => [
                    'home' => $fixture->home_goals,
                    'away' => $fixture->away_goals,
                ],
                'team_stats' => $teamStats,
                'players' => $playersByTeam,
            ];

            $payload['indexes']['by_status'][$status][] = $fixtureKey;
            $payload['indexes']['by_league'][$leagueProviderId][$status][] = $fixtureKey;
            $payload['leagues'][$leagueProviderId]['matches'][$status][] = $fixtureKey;

            $currentRound = $payload['leagues'][$leagueProviderId]['round'] ?? null;
            $fixtureRound = $fixture->round;
            $currentRoundStatus = $leagueRoundStatus[$leagueProviderId] ?? null;
            if ($this->shouldPromoteLeagueRound($currentRound, $fixtureRound, $status, $currentRoundStatus)) {
                $payload['leagues'][$leagueProviderId]['round'] = $fixtureRound;
                $leagueRoundStatus[$leagueProviderId] = $status;
            }

            if ($homeProviderTeamKey !== null && $fixture->homeTeam !== null) {
                $payload['leagues'][$leagueProviderId]['teams'][] = $homeProviderTeamKey;
                $payload['indexes']['team_matches'][$homeProviderTeamKey] = $payload['indexes']['team_matches'][$homeProviderTeamKey] ?? [
                    'live' => [],
                    'upcoming' => [],
                    'finished' => [],
                ];
                $payload['indexes']['team_matches'][$homeProviderTeamKey][$status][] = $fixtureKey;
                $teamLocalIds[] = $fixture->homeTeam->id;
            }

            if ($awayProviderTeamKey !== null && $fixture->awayTeam !== null) {
                $payload['leagues'][$leagueProviderId]['teams'][] = $awayProviderTeamKey;
                $payload['indexes']['team_matches'][$awayProviderTeamKey] = $payload['indexes']['team_matches'][$awayProviderTeamKey] ?? [
                    'live' => [],
                    'upcoming' => [],
                    'finished' => [],
                ];
                $payload['indexes']['team_matches'][$awayProviderTeamKey][$status][] = $fixtureKey;
                $teamLocalIds[] = $fixture->awayTeam->id;
            }
        }

        $teamLocalIds = array_values(array_unique($teamLocalIds));
        $this->hydrateTeamsAndPlayers($payload, $teamLocalIds);
        $this->deduplicateIndexes($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int> $teamLocalIds
     */
    private function hydrateTeamsAndPlayers(array &$payload, array $teamLocalIds): void
    {
        if (empty($teamLocalIds)) {
            return;
        }

        $teams = Team::query()
            ->whereIn('id', $teamLocalIds)
            ->get()
            ->keyBy('id');

        $standingRows = LeagueStandingRow::query()
            ->with(['standing.league', 'team'])
            ->whereIn('team_id', $teamLocalIds)
            ->get();

        $teamLeagueStats = [];
        foreach ($standingRows as $row) {
            if ($row->standing === null || $row->standing->league === null) {
                continue;
            }

            $teamProviderId = $row->team?->provider_team_id;
            if ($teamProviderId === null) {
                continue;
            }

            $teamProviderKey = (string) $teamProviderId;
            $leagueProviderId = (int) $row->standing->league->provider_league_id;
            $season = (int) $row->standing->season;
            $leagueSeasonKey = $leagueProviderId . '_' . $season;

            $teamLeagueStats[$teamProviderKey][$leagueSeasonKey] = [
                'rank' => $row->rank_position,
                'points' => $row->points,
                'played' => $row->matches_played,
                'win' => $row->matches_win,
                'draw' => $row->matches_draw,
                'lose' => $row->matches_lose,
                'goals_for' => $row->goals_for,
                'goals_against' => $row->goals_against,
                'goals_diff' => $row->goals_diff,
            ];
        }

        $playerStats = PlayerLeagueStat::query()
            ->with(['player', 'league', 'team'])
            ->whereIn('team_id', $teamLocalIds)
            ->get();

        $teamPlayers = [];

        foreach ($teams as $team) {
            $providerTeamKey = (string) $team->provider_team_id;

            $leagueIds = [];
            foreach ($playerStats->where('team_id', $team->id) as $stat) {
                if ($stat->league !== null) {
                    $leagueIds[] = (int) $stat->league->provider_league_id;
                }
            }
            $leagueIds = array_values(array_unique($leagueIds));

            $payload['teams'][$providerTeamKey] = [
                'id' => (int) $team->provider_team_id,
                'league_ids' => $leagueIds,
                'name' => $team->name,
                'code' => $team->code,
                'country' => $team->country_name,
                'logo' => $team->logo,
                'venue' => [
                    'id' => $team->venue_provider_id,
                    'name' => $team->venue_name,
                    'city' => $team->venue_city,
                ],
                'current_form' => null,
                'league_stats' => $teamLeagueStats[$providerTeamKey] ?? [],
                'matches' => $payload['indexes']['team_matches'][$providerTeamKey] ?? [
                    'live' => [],
                    'upcoming' => [],
                    'finished' => [],
                ],
                'players' => [],
            ];
        }

        foreach ($playerStats as $stat) {
            if ($stat->player === null || $stat->league === null || $stat->team === null) {
                continue;
            }

            $playerKey = (string) $stat->player->provider_player_id;
            $teamProviderId = (int) $stat->team->provider_team_id;
            $teamProviderKey = (string) $teamProviderId;
            $leagueProviderId = (int) $stat->league->provider_league_id;
            $leagueSeasonKey = $leagueProviderId . '_' . (int) $stat->season;

            if (!isset($payload['players'][$playerKey])) {
                $payload['players'][$playerKey] = [
                    'id' => (int) $stat->player->provider_player_id,
                    'name' => $stat->player->full_name ?? trim((string) $stat->player->firstname . ' ' . (string) $stat->player->lastname),
                    'team_id' => $teamProviderId,
                    'league_ids' => [],
                    'match_stats' => [],
                    'league_stats' => [],
                ];
            }

            $payload['players'][$playerKey]['league_ids'][] = $leagueProviderId;
            $payload['players'][$playerKey]['league_stats'][$leagueSeasonKey] = [
                'appearances' => $stat->games_appearences,
                'goals' => $stat->goals_total,
                'assists' => $stat->goals_assists,
                'yellow_cards' => $stat->cards_yellow,
                'red_cards' => $stat->cards_red,
            ];

            $teamPlayers[$teamProviderKey][] = $playerKey;
        }

        foreach ($payload['teams'] as $teamKey => &$teamPayload) {
            $teamPayload['players'] = array_values(array_unique($teamPlayers[$teamKey] ?? []));
            if (!empty($teamPayload['league_stats'])) {
                $firstLeagueStat = collect($teamPayload['league_stats'])->first();
                $teamPayload['current_form'] = is_array($firstLeagueStat) ? ($firstLeagueStat['form'] ?? null) : null;
            }
        }
        unset($teamPayload);

        foreach ($payload['players'] as &$playerPayload) {
            $playerPayload['league_ids'] = array_values(array_unique($playerPayload['league_ids']));
        }
        unset($playerPayload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deduplicateIndexes(array &$payload): void
    {
        foreach (['live', 'upcoming', 'finished'] as $status) {
            $payload['indexes']['by_status'][$status] = array_values(array_unique($payload['indexes']['by_status'][$status]));
        }

        foreach ($payload['indexes']['by_league'] as $leagueId => $byStatus) {
            foreach (['live', 'upcoming', 'finished'] as $status) {
                $payload['indexes']['by_league'][$leagueId][$status] = array_values(array_unique($byStatus[$status] ?? []));
            }
        }

        foreach ($payload['indexes']['team_matches'] as $teamId => $byStatus) {
            foreach (['live', 'upcoming', 'finished'] as $status) {
                $payload['indexes']['team_matches'][$teamId][$status] = array_values(array_unique($byStatus[$status] ?? []));
            }
        }

        foreach ($payload['leagues'] as $leagueId => $leaguePayload) {
            $payload['leagues'][$leagueId]['teams'] = array_values(array_unique($leaguePayload['teams']));
            foreach (['live', 'upcoming', 'finished'] as $status) {
                $payload['leagues'][$leagueId]['matches'][$status] = array_values(array_unique($leaguePayload['matches'][$status]));
            }
        }
    }

    private function mapFixtureStatus(?string $statusShort): string
    {
        $status = strtoupper((string) $statusShort);

        if (in_array($status, self::LIVE_STATUS_SHORTS, true)) {
            return 'live';
        }

        if (in_array($status, self::UPCOMING_STATUS_SHORTS, true)) {
            return 'upcoming';
        }

        return 'finished';
    }

    private function generateCacheVersionId(): string
    {
        return now()->format('YmdHisv');
    }

    private function shouldPromoteLeagueRound(
        ?string $currentRound,
        ?string $candidateRound,
        string $candidateStatus,
        ?string $currentStatus
    ): bool
    {
        if ($candidateRound === null || trim($candidateRound) === '') {
            return false;
        }

        if ($currentRound === null || trim($currentRound) === '') {
            return true;
        }

        $priority = [
            'finished' => 1,
            'upcoming' => 2,
            'live' => 3,
        ];

        $candidatePriority = $priority[$candidateStatus] ?? 0;
        $currentPriority = $priority[$currentStatus ?? ''] ?? 0;

        if ($candidatePriority > $currentPriority) {
            return true;
        }

        return $candidatePriority === $currentPriority;
    }
}
