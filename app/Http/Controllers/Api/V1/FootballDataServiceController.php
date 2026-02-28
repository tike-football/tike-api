<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FootballSyncLeagueStructureService;
use App\Services\FootballSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FootballDataServiceController extends Controller
{
    /**
     * Sync one league from football provider into local DB.
     */
    public function syncLeague(Request $request, FootballSyncService $footballSyncService): JsonResponse
    {
        try {
            $data = $request->validate([
                'league_id' => 'required|integer|min:1',
                'season' => 'required|integer|min:1900|max:2100',
            ]);

            $league = $footballSyncService->syncLeague(
                (int) $data['league_id'],
                (int) $data['season']
            );

            return response()->json([
                'message' => 'League synchronized successfully.',
                'league' => [
                    'id' => $league->id,
                    'provider' => $league->provider,
                    'provider_league_id' => $league->provider_league_id,
                    'name' => $league->name,
                    'season' => (int) $data['season'],
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Failed to sync league from provider.',
                'error' => $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while syncing league data.',
                'error' => 'League synchronization failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Sync league season structure using local DB data.
     */
    public function syncLeagueStructure(
        Request $request,
        FootballSyncLeagueStructureService $footballSyncLeagueStructureService
    ): JsonResponse {
        try {
            $data = $request->validate([
                'league_id' => 'required|integer|min:1',
                'season' => 'required|integer|min:1900|max:2100',
            ]);

            $updated = $footballSyncLeagueStructureService->syncLeagueStructure(
                (int) $data['league_id'],
                (int) $data['season']
            );

            return response()->json([
                'message' => 'League structure synchronization completed.',
                'league_id' => (int) $data['league_id'],
                'season' => (int) $data['season'],
                'updated' => $updated,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while syncing league structure.',
                'error' => 'League structure synchronization failed. Please try again.',
            ], 500);
        }
    }
}
