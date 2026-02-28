<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FootballData\GetFixturesRequest;
use App\Models\LeagueSeason;
use App\Services\FootballFixturesCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FootballDataController extends Controller
{
    public function getFixtures(GetFixturesRequest $request): JsonResponse
    {
        try {
            $requestedCacheId = $request->validatedCacheFixturesId();
            $fullCacheId = $this->stringCacheValue(FootballFixturesCacheService::CACHE_FIXTURES_ID);
            $changesCacheId = $this->stringCacheValue(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID);

            if ($requestedCacheId === null) {
                return $this->fullPayloadResponse($fullCacheId, $changesCacheId);
            }

            if ($changesCacheId !== null && $requestedCacheId === $changesCacheId) {
                return $this->emptyPayloadResponse($fullCacheId, $changesCacheId);
            }

            if ($fullCacheId !== null && strcmp($fullCacheId, $requestedCacheId) > 0) {
                return $this->fullPayloadResponse($fullCacheId, $changesCacheId);
            }

            if (
                $fullCacheId !== null
                && strcmp($fullCacheId, $requestedCacheId) === 0
                && $changesCacheId !== null
                && strcmp($changesCacheId, $requestedCacheId) > 0
            ) {
                return $this->changesPayloadResponse($fullCacheId, $changesCacheId);
            }

            return $this->emptyPayloadResponse($fullCacheId, $changesCacheId);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving fixtures data.',
                'error' => 'Fixtures data retrieval failed. Please try again.',
            ], 500);
        }
    }

    public function getLeagueStructure(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'league_id' => 'required|integer|min:1',
                'season' => 'required|integer|min:1900|max:2100',
            ]);

            $leagueSeason = LeagueSeason::query()
                ->where('league_id', (int) $data['league_id'])
                ->where('year', (int) $data['season'])
                ->first();

            if ($leagueSeason === null) {
                return response()->json([
                    'message' => 'League season not found.',
                ], 404);
            }

            return response()->json([
                'message' => 'League structure loaded.',
                'league_id' => (int) $data['league_id'],
                'season' => (int) $data['season'],
                'structure' => $leagueSeason->structure,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving league structure.',
                'error' => 'League structure retrieval failed. Please try again.',
            ], 500);
        }
    }

    private function fullPayloadResponse(?string $fullCacheId, ?string $changesCacheId): JsonResponse
    {
        $fixtures = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_MERGED);
        if ($fixtures === null) {
            $fixtures = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES, (object) []);
        }

        return response()->json([
            'message' => 'Fixtures cache loaded.',
            'cache_fixtures_id' => $fullCacheId,
            'cache_fixtures_changes_id' => $changesCacheId,
            'fixtures' => $fixtures,
        ]);
    }

    private function changesPayloadResponse(?string $fullCacheId, ?string $changesCacheId): JsonResponse
    {
        return response()->json([
            'message' => 'Fixtures changes cache loaded.',
            'cache_fixtures_id' => $fullCacheId,
            'cache_fixtures_changes_id' => $changesCacheId,
            'fixtures' => Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES, (object) []),
        ]);
    }

    private function emptyPayloadResponse(?string $fullCacheId, ?string $changesCacheId): JsonResponse
    {
        return response()->json([
            'message' => 'Fixtures cache is already up to date.',
            'cache_fixtures_id' => $fullCacheId,
            'cache_fixtures_changes_id' => $changesCacheId,
            'fixtures' => (object) [],
        ]);
    }

    private function stringCacheValue(string $key): ?string
    {
        $value = Cache::get($key);

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
