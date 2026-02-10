<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class CountryControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed countries for testing
        $this->seedCountries();
    }

    private function seedCountries(): void
    {
        Country::create([
            'code' => 'MX',
            'code_alpha3' => 'MEX',
            'name' => 'Mexico',
            'native_name' => 'México',
            'phone_code' => '+52',
            'flag_emoji' => '🇲🇽',
            'currency_code' => 'MXN',
            'region' => 'Americas',
        ]);

        Country::create([
            'code' => 'US',
            'code_alpha3' => 'USA',
            'name' => 'United States',
            'native_name' => 'United States',
            'phone_code' => '+1',
            'flag_emoji' => '🇺🇸',
            'currency_code' => 'USD',
            'region' => 'Americas',
        ]);

        Country::create([
            'code' => 'ES',
            'code_alpha3' => 'ESP',
            'name' => 'Spain',
            'native_name' => 'España',
            'phone_code' => '+34',
            'flag_emoji' => '🇪🇸',
            'currency_code' => 'EUR',
            'region' => 'Europe',
        ]);
    }

    public function test_can_get_all_countries(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'countries' => [
                    '*' => [
                        'code',
                        'code_alpha3',
                        'name',
                        'native_name',
                        'phone_code',
                        'flag_emoji',
                        'currency_code',
                        'region',
                    ]
                ],
                'total'
            ])
            ->assertJson([
                'total' => 3
            ]);
    }

    public function test_countries_are_returned_ordered_by_name(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries');

        $response->assertStatus(200);
        
        $countries = $response->json('countries');
        
        // Mexico (M) should come before Spain (S) and United States (U)
        $this->assertEquals('Mexico', $countries[0]['name']);
        $this->assertEquals('Spain', $countries[1]['name']);
        $this->assertEquals('United States', $countries[2]['name']);
    }

    public function test_can_get_country_by_alpha2_code(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries/MX');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'country' => [
                    'id',
                    'code',
                    'code_alpha3',
                    'name',
                    'native_name',
                    'phone_code',
                    'flag_emoji',
                    'currency_code',
                    'region',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'country' => [
                    'code' => 'MX',
                    'code_alpha3' => 'MEX',
                    'name' => 'Mexico',
                    'native_name' => 'México',
                    'phone_code' => '+52',
                    'flag_emoji' => '🇲🇽',
                    'currency_code' => 'MXN',
                    'region' => 'Americas',
                ]
            ]);
    }

    public function test_can_get_country_by_alpha3_code(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries/USA');

        $response->assertStatus(200)
            ->assertJson([
                'country' => [
                    'code' => 'US',
                    'code_alpha3' => 'USA',
                    'name' => 'United States',
                    'phone_code' => '+1',
                ]
            ]);
    }

    public function test_country_code_search_is_case_insensitive(): void
    {
        $responseLower = $this->getJsonWithApiKey('/api/v1/countries/mx');
        $responseUpper = $this->getJsonWithApiKey('/api/v1/countries/MX');
        $responseMixed = $this->getJsonWithApiKey('/api/v1/countries/Mx');

        $responseLower->assertStatus(200)
            ->assertJson(['country' => ['code' => 'MX']]);
        
        $responseUpper->assertStatus(200)
            ->assertJson(['country' => ['code' => 'MX']]);
        
        $responseMixed->assertStatus(200)
            ->assertJson(['country' => ['code' => 'MX']]);
    }

    public function test_returns_404_when_country_not_found(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries/XX');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Country not found.'
            ]);
    }

    public function test_country_includes_flag_emoji(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries/ES');

        $response->assertStatus(200);
        
        $flagEmoji = $response->json('country.flag_emoji');
        
        $this->assertEquals('🇪🇸', $flagEmoji);
        $this->assertNotEmpty($flagEmoji);
    }

    public function test_all_countries_have_required_fields(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries');

        $response->assertStatus(200);
        
        $countries = $response->json('countries');
        
        foreach ($countries as $country) {
            $this->assertArrayHasKey('code', $country);
            $this->assertArrayHasKey('code_alpha3', $country);
            $this->assertArrayHasKey('name', $country);
            $this->assertArrayHasKey('phone_code', $country);
            $this->assertArrayHasKey('flag_emoji', $country);
            $this->assertArrayHasKey('region', $country);
            
            // Validate code formats
            $this->assertEquals(2, strlen($country['code']));
            $this->assertEquals(3, strlen($country['code_alpha3']));
            $this->assertStringStartsWith('+', $country['phone_code']);
        }
    }

    public function test_countries_endpoint_does_not_require_authentication(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries');

        $response->assertStatus(200);
    }

    public function test_country_show_endpoint_does_not_require_authentication(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries/MX');

        $response->assertStatus(200);
    }

    public function test_can_filter_countries_by_region(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries');

        $response->assertStatus(200);
        
        $countries = $response->json('countries');
        
        // Check that we have countries from different regions
        $regions = array_unique(array_column($countries, 'region'));
        
        $this->assertContains('Americas', $regions);
        $this->assertContains('Europe', $regions);
    }

    public function test_total_count_matches_number_of_countries(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/countries');

        $response->assertStatus(200);
        
        $total = $response->json('total');
        $countries = $response->json('countries');
        
        $this->assertEquals($total, count($countries));
        $this->assertEquals(3, $total);
    }
}
