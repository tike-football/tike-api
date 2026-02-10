<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Get all countries.
     */
    public function index(): JsonResponse
    {
        $countries = Country::select('code', 'code_alpha3', 'name', 'native_name', 'phone_code', 'flag_emoji', 'currency_code', 'region')
            ->orderBy('name')
            ->get();

        return response()->json([
            'countries' => $countries,
            'total' => $countries->count()
        ]);
    }

    /**
     * Get a specific country by code.
     */
    public function show(string $code): JsonResponse
    {
        $country = Country::where('code', strtoupper($code))
            ->orWhere('code_alpha3', strtoupper($code))
            ->first();

        if (!$country) {
            return response()->json([
                'message' => 'Country not found.'
            ], 404);
        }

        return response()->json([
            'country' => $country
        ]);
    }
}
