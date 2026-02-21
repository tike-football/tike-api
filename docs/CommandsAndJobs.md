# Commands And Jobs (Football Data)

## Purpose

This document explains the end-to-end flow used to populate and keep football tables updated using `FootballDataService`, queued events/listeners, and fixture cache commands.

## Database Population Flow

### 1. API entry point

- Endpoint: `POST /api/v1/admin/football-data/sync-league`
- Controller: `App\Http\Controllers\Api\V1\FootballDataServiceController::syncLeague`
- Required access:
  - Valid `X-API-Key`
  - Valid Bearer token
  - Scope: `football-data:sync`

Parameters:

- `league_id` (int)
- `season` (int)

### 2. Initial league sync

- Service: `App\Services\FootballSyncService::syncLeague(leagueId, season)`
- Provider client: `FootballDataClient` (current driver: `api_football`)
- Table updated:
  - `leagues`
- Event dispatched:
  - `App\Events\FootballData\LeagueSynced`

### 3. Queue: teams sync

- Listener: `App\Listeners\FootballData\SyncTeams`
- Queue: `football-data`
- Service call: `FootballSyncService::syncTeams(leagueId, season)`
- Table updated:
  - `teams`
- Event dispatched per saved team:
  - `App\Events\FootballData\TeamSynced`
- Event dispatched after all teams are saved:
  - `App\Events\FootballData\LeagueTeamsSynced`

### 4. Queue: players sync (per team)

- Listener: `App\Listeners\FootballData\SyncPlayers`
- Queue: `football-data`
- Service call: `FootballSyncService::syncPlayers(teamId, season)`
- Tables updated:
  - `players`
  - `team_player_seasons`
  - `player_league_stats`

### 5. Queue: fixtures and standings sync (per league)

Source event: `LeagueTeamsSynced`

- Listener `SyncFixtures`
  - Method: `FootballSyncService::syncFixtures(leagueId, season)`
  - Tables:
    - `fixtures`
    - `fixture_team_stats`

- Listener `SyncStandings`
  - Method: `FootballSyncService::syncStandings(leagueId, season)`
  - Tables:
    - `league_standings`
    - `league_standing_rows`

## Required Worker

Since `QUEUE_CONNECTION=database`, a worker must be running for this queue:

```bash
php artisan queue:work --queue=football-data --tries=3 --timeout=60
```

## Fixture Cache Commands

Service: `App\Services\FootballFixturesCacheService`

Cache keys:

- `CACHE_FIXTURES = cache-fixtures`
- `CACHE_FIXTURES_CHANGES = cache-fixtures-changes`
- `CACHE_FIXTURES_ID = cache-fixtures-id`

### 1. Full fixtures cache

Command:

```bash
php artisan football-data:cache-fixtures
```

What it does:

- Rebuilds the full fixtures object (`leagues`, `teams`, `matches`, `players`, `indexes`)
- Stores it in `cache-fixtures`
- Updates `cache-fixtures-id` (full snapshot version)

Recommended frequency:

- Every 1 hour

### 2. Incremental fixtures changes cache

Command:

```bash
php artisan football-data:cache-fixtures-changes
```

What it does:

- Processes only relevant fixtures:
  - live matches
  - upcoming matches starting in <= 5 minutes
  - recently finished matches (<= 5 minutes)
- Compares current state against the previous internal snapshot and builds a delta
- Stores only the delta in `cache-fixtures-changes`
- Does **not** modify `cache-fixtures-id`

Recommended frequency:

- Every 1 minute (especially during active match windows)

## Scheduler suggestion (Laravel)

In `app/Console/Kernel.php` (or your project scheduler equivalent):

```php
$schedule->command('football-data:cache-fixtures')->hourly();
$schedule->command('football-data:cache-fixtures-changes')->everyMinute();
```

At infrastructure level, run scheduler every minute:

```bash
* * * * * php /path/to/tike-api/artisan schedule:run >> /dev/null 2>&1
```
