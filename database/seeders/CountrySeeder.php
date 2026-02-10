<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            // Americas
            ['code' => 'US', 'code_alpha3' => 'USA', 'name' => 'United States', 'native_name' => 'United States', 'phone_code' => '+1', 'flag_emoji' => '🇺🇸', 'currency_code' => 'USD', 'region' => 'Americas'],
            ['code' => 'CA', 'code_alpha3' => 'CAN', 'name' => 'Canada', 'native_name' => 'Canada', 'phone_code' => '+1', 'flag_emoji' => '🇨🇦', 'currency_code' => 'CAD', 'region' => 'Americas'],
            ['code' => 'MX', 'code_alpha3' => 'MEX', 'name' => 'Mexico', 'native_name' => 'México', 'phone_code' => '+52', 'flag_emoji' => '🇲🇽', 'currency_code' => 'MXN', 'region' => 'Americas'],
            ['code' => 'BR', 'code_alpha3' => 'BRA', 'name' => 'Brazil', 'native_name' => 'Brasil', 'phone_code' => '+55', 'flag_emoji' => '🇧🇷', 'currency_code' => 'BRL', 'region' => 'Americas'],
            ['code' => 'AR', 'code_alpha3' => 'ARG', 'name' => 'Argentina', 'native_name' => 'Argentina', 'phone_code' => '+54', 'flag_emoji' => '🇦🇷', 'currency_code' => 'ARS', 'region' => 'Americas'],
            ['code' => 'CL', 'code_alpha3' => 'CHL', 'name' => 'Chile', 'native_name' => 'Chile', 'phone_code' => '+56', 'flag_emoji' => '🇨🇱', 'currency_code' => 'CLP', 'region' => 'Americas'],
            ['code' => 'CO', 'code_alpha3' => 'COL', 'name' => 'Colombia', 'native_name' => 'Colombia', 'phone_code' => '+57', 'flag_emoji' => '🇨🇴', 'currency_code' => 'COP', 'region' => 'Americas'],
            ['code' => 'PE', 'code_alpha3' => 'PER', 'name' => 'Peru', 'native_name' => 'Perú', 'phone_code' => '+51', 'flag_emoji' => '🇵🇪', 'currency_code' => 'PEN', 'region' => 'Americas'],
            ['code' => 'VE', 'code_alpha3' => 'VEN', 'name' => 'Venezuela', 'native_name' => 'Venezuela', 'phone_code' => '+58', 'flag_emoji' => '🇻🇪', 'currency_code' => 'VES', 'region' => 'Americas'],
            ['code' => 'EC', 'code_alpha3' => 'ECU', 'name' => 'Ecuador', 'native_name' => 'Ecuador', 'phone_code' => '+593', 'flag_emoji' => '🇪🇨', 'currency_code' => 'USD', 'region' => 'Americas'],
            ['code' => 'BO', 'code_alpha3' => 'BOL', 'name' => 'Bolivia', 'native_name' => 'Bolivia', 'phone_code' => '+591', 'flag_emoji' => '🇧🇴', 'currency_code' => 'BOB', 'region' => 'Americas'],
            ['code' => 'PY', 'code_alpha3' => 'PRY', 'name' => 'Paraguay', 'native_name' => 'Paraguay', 'phone_code' => '+595', 'flag_emoji' => '🇵🇾', 'currency_code' => 'PYG', 'region' => 'Americas'],
            ['code' => 'UY', 'code_alpha3' => 'URY', 'name' => 'Uruguay', 'native_name' => 'Uruguay', 'phone_code' => '+598', 'flag_emoji' => '🇺🇾', 'currency_code' => 'UYU', 'region' => 'Americas'],
            ['code' => 'CR', 'code_alpha3' => 'CRI', 'name' => 'Costa Rica', 'native_name' => 'Costa Rica', 'phone_code' => '+506', 'flag_emoji' => '🇨🇷', 'currency_code' => 'CRC', 'region' => 'Americas'],
            ['code' => 'PA', 'code_alpha3' => 'PAN', 'name' => 'Panama', 'native_name' => 'Panamá', 'phone_code' => '+507', 'flag_emoji' => '🇵🇦', 'currency_code' => 'PAB', 'region' => 'Americas'],
            ['code' => 'GT', 'code_alpha3' => 'GTM', 'name' => 'Guatemala', 'native_name' => 'Guatemala', 'phone_code' => '+502', 'flag_emoji' => '🇬🇹', 'currency_code' => 'GTQ', 'region' => 'Americas'],
            ['code' => 'SV', 'code_alpha3' => 'SLV', 'name' => 'El Salvador', 'native_name' => 'El Salvador', 'phone_code' => '+503', 'flag_emoji' => '🇸🇻', 'currency_code' => 'USD', 'region' => 'Americas'],
            ['code' => 'HN', 'code_alpha3' => 'HND', 'name' => 'Honduras', 'native_name' => 'Honduras', 'phone_code' => '+504', 'flag_emoji' => '🇭🇳', 'currency_code' => 'HNL', 'region' => 'Americas'],
            ['code' => 'NI', 'code_alpha3' => 'NIC', 'name' => 'Nicaragua', 'native_name' => 'Nicaragua', 'phone_code' => '+505', 'flag_emoji' => '🇳🇮', 'currency_code' => 'NIO', 'region' => 'Americas'],
            ['code' => 'CU', 'code_alpha3' => 'CUB', 'name' => 'Cuba', 'native_name' => 'Cuba', 'phone_code' => '+53', 'flag_emoji' => '🇨🇺', 'currency_code' => 'CUP', 'region' => 'Americas'],
            ['code' => 'DO', 'code_alpha3' => 'DOM', 'name' => 'Dominican Republic', 'native_name' => 'República Dominicana', 'phone_code' => '+1', 'flag_emoji' => '🇩🇴', 'currency_code' => 'DOP', 'region' => 'Americas'],
            ['code' => 'PR', 'code_alpha3' => 'PRI', 'name' => 'Puerto Rico', 'native_name' => 'Puerto Rico', 'phone_code' => '+1', 'flag_emoji' => '🇵🇷', 'currency_code' => 'USD', 'region' => 'Americas'],
            
            // Europe
            ['code' => 'ES', 'code_alpha3' => 'ESP', 'name' => 'Spain', 'native_name' => 'España', 'phone_code' => '+34', 'flag_emoji' => '🇪🇸', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'FR', 'code_alpha3' => 'FRA', 'name' => 'France', 'native_name' => 'France', 'phone_code' => '+33', 'flag_emoji' => '🇫🇷', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'DE', 'code_alpha3' => 'DEU', 'name' => 'Germany', 'native_name' => 'Deutschland', 'phone_code' => '+49', 'flag_emoji' => '🇩🇪', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'IT', 'code_alpha3' => 'ITA', 'name' => 'Italy', 'native_name' => 'Italia', 'phone_code' => '+39', 'flag_emoji' => '🇮🇹', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'GB', 'code_alpha3' => 'GBR', 'name' => 'United Kingdom', 'native_name' => 'United Kingdom', 'phone_code' => '+44', 'flag_emoji' => '🇬🇧', 'currency_code' => 'GBP', 'region' => 'Europe'],
            ['code' => 'PT', 'code_alpha3' => 'PRT', 'name' => 'Portugal', 'native_name' => 'Portugal', 'phone_code' => '+351', 'flag_emoji' => '🇵🇹', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'NL', 'code_alpha3' => 'NLD', 'name' => 'Netherlands', 'native_name' => 'Nederland', 'phone_code' => '+31', 'flag_emoji' => '🇳🇱', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'BE', 'code_alpha3' => 'BEL', 'name' => 'Belgium', 'native_name' => 'België', 'phone_code' => '+32', 'flag_emoji' => '🇧🇪', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'CH', 'code_alpha3' => 'CHE', 'name' => 'Switzerland', 'native_name' => 'Schweiz', 'phone_code' => '+41', 'flag_emoji' => '🇨🇭', 'currency_code' => 'CHF', 'region' => 'Europe'],
            ['code' => 'AT', 'code_alpha3' => 'AUT', 'name' => 'Austria', 'native_name' => 'Österreich', 'phone_code' => '+43', 'flag_emoji' => '🇦🇹', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'SE', 'code_alpha3' => 'SWE', 'name' => 'Sweden', 'native_name' => 'Sverige', 'phone_code' => '+46', 'flag_emoji' => '🇸🇪', 'currency_code' => 'SEK', 'region' => 'Europe'],
            ['code' => 'NO', 'code_alpha3' => 'NOR', 'name' => 'Norway', 'native_name' => 'Norge', 'phone_code' => '+47', 'flag_emoji' => '🇳🇴', 'currency_code' => 'NOK', 'region' => 'Europe'],
            ['code' => 'DK', 'code_alpha3' => 'DNK', 'name' => 'Denmark', 'native_name' => 'Danmark', 'phone_code' => '+45', 'flag_emoji' => '🇩🇰', 'currency_code' => 'DKK', 'region' => 'Europe'],
            ['code' => 'FI', 'code_alpha3' => 'FIN', 'name' => 'Finland', 'native_name' => 'Suomi', 'phone_code' => '+358', 'flag_emoji' => '🇫🇮', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'PL', 'code_alpha3' => 'POL', 'name' => 'Poland', 'native_name' => 'Polska', 'phone_code' => '+48', 'flag_emoji' => '🇵🇱', 'currency_code' => 'PLN', 'region' => 'Europe'],
            ['code' => 'RU', 'code_alpha3' => 'RUS', 'name' => 'Russia', 'native_name' => 'Россия', 'phone_code' => '+7', 'flag_emoji' => '🇷🇺', 'currency_code' => 'RUB', 'region' => 'Europe'],
            ['code' => 'UA', 'code_alpha3' => 'UKR', 'name' => 'Ukraine', 'native_name' => 'Україна', 'phone_code' => '+380', 'flag_emoji' => '🇺🇦', 'currency_code' => 'UAH', 'region' => 'Europe'],
            ['code' => 'GR', 'code_alpha3' => 'GRC', 'name' => 'Greece', 'native_name' => 'Ελλάδα', 'phone_code' => '+30', 'flag_emoji' => '🇬🇷', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'IE', 'code_alpha3' => 'IRL', 'name' => 'Ireland', 'native_name' => 'Ireland', 'phone_code' => '+353', 'flag_emoji' => '🇮🇪', 'currency_code' => 'EUR', 'region' => 'Europe'],
            ['code' => 'CZ', 'code_alpha3' => 'CZE', 'name' => 'Czech Republic', 'native_name' => 'Česko', 'phone_code' => '+420', 'flag_emoji' => '🇨🇿', 'currency_code' => 'CZK', 'region' => 'Europe'],
            ['code' => 'RO', 'code_alpha3' => 'ROU', 'name' => 'Romania', 'native_name' => 'România', 'phone_code' => '+40', 'flag_emoji' => '🇷🇴', 'currency_code' => 'RON', 'region' => 'Europe'],
            
            // Asia
            ['code' => 'CN', 'code_alpha3' => 'CHN', 'name' => 'China', 'native_name' => '中国', 'phone_code' => '+86', 'flag_emoji' => '🇨🇳', 'currency_code' => 'CNY', 'region' => 'Asia'],
            ['code' => 'JP', 'code_alpha3' => 'JPN', 'name' => 'Japan', 'native_name' => '日本', 'phone_code' => '+81', 'flag_emoji' => '🇯🇵', 'currency_code' => 'JPY', 'region' => 'Asia'],
            ['code' => 'KR', 'code_alpha3' => 'KOR', 'name' => 'South Korea', 'native_name' => '대한민국', 'phone_code' => '+82', 'flag_emoji' => '🇰🇷', 'currency_code' => 'KRW', 'region' => 'Asia'],
            ['code' => 'IN', 'code_alpha3' => 'IND', 'name' => 'India', 'native_name' => 'भारत', 'phone_code' => '+91', 'flag_emoji' => '🇮🇳', 'currency_code' => 'INR', 'region' => 'Asia'],
            ['code' => 'ID', 'code_alpha3' => 'IDN', 'name' => 'Indonesia', 'native_name' => 'Indonesia', 'phone_code' => '+62', 'flag_emoji' => '🇮🇩', 'currency_code' => 'IDR', 'region' => 'Asia'],
            ['code' => 'TH', 'code_alpha3' => 'THA', 'name' => 'Thailand', 'native_name' => 'ประเทศไทย', 'phone_code' => '+66', 'flag_emoji' => '🇹🇭', 'currency_code' => 'THB', 'region' => 'Asia'],
            ['code' => 'VN', 'code_alpha3' => 'VNM', 'name' => 'Vietnam', 'native_name' => 'Việt Nam', 'phone_code' => '+84', 'flag_emoji' => '🇻🇳', 'currency_code' => 'VND', 'region' => 'Asia'],
            ['code' => 'PH', 'code_alpha3' => 'PHL', 'name' => 'Philippines', 'native_name' => 'Philippines', 'phone_code' => '+63', 'flag_emoji' => '🇵🇭', 'currency_code' => 'PHP', 'region' => 'Asia'],
            ['code' => 'MY', 'code_alpha3' => 'MYS', 'name' => 'Malaysia', 'native_name' => 'Malaysia', 'phone_code' => '+60', 'flag_emoji' => '🇲🇾', 'currency_code' => 'MYR', 'region' => 'Asia'],
            ['code' => 'SG', 'code_alpha3' => 'SGP', 'name' => 'Singapore', 'native_name' => 'Singapore', 'phone_code' => '+65', 'flag_emoji' => '🇸🇬', 'currency_code' => 'SGD', 'region' => 'Asia'],
            ['code' => 'PK', 'code_alpha3' => 'PAK', 'name' => 'Pakistan', 'native_name' => 'پاکستان', 'phone_code' => '+92', 'flag_emoji' => '🇵🇰', 'currency_code' => 'PKR', 'region' => 'Asia'],
            ['code' => 'BD', 'code_alpha3' => 'BGD', 'name' => 'Bangladesh', 'native_name' => 'বাংলাদেশ', 'phone_code' => '+880', 'flag_emoji' => '🇧🇩', 'currency_code' => 'BDT', 'region' => 'Asia'],
            ['code' => 'TR', 'code_alpha3' => 'TUR', 'name' => 'Turkey', 'native_name' => 'Türkiye', 'phone_code' => '+90', 'flag_emoji' => '🇹🇷', 'currency_code' => 'TRY', 'region' => 'Asia'],
            ['code' => 'IL', 'code_alpha3' => 'ISR', 'name' => 'Israel', 'native_name' => 'יִשְׂרָאֵל', 'phone_code' => '+972', 'flag_emoji' => '🇮🇱', 'currency_code' => 'ILS', 'region' => 'Asia'],
            ['code' => 'SA', 'code_alpha3' => 'SAU', 'name' => 'Saudi Arabia', 'native_name' => 'العربية السعودية', 'phone_code' => '+966', 'flag_emoji' => '🇸🇦', 'currency_code' => 'SAR', 'region' => 'Asia'],
            ['code' => 'AE', 'code_alpha3' => 'ARE', 'name' => 'United Arab Emirates', 'native_name' => 'الإمارات', 'phone_code' => '+971', 'flag_emoji' => '🇦🇪', 'currency_code' => 'AED', 'region' => 'Asia'],
            
            // Africa
            ['code' => 'ZA', 'code_alpha3' => 'ZAF', 'name' => 'South Africa', 'native_name' => 'South Africa', 'phone_code' => '+27', 'flag_emoji' => '🇿🇦', 'currency_code' => 'ZAR', 'region' => 'Africa'],
            ['code' => 'EG', 'code_alpha3' => 'EGY', 'name' => 'Egypt', 'native_name' => 'مصر', 'phone_code' => '+20', 'flag_emoji' => '🇪🇬', 'currency_code' => 'EGP', 'region' => 'Africa'],
            ['code' => 'NG', 'code_alpha3' => 'NGA', 'name' => 'Nigeria', 'native_name' => 'Nigeria', 'phone_code' => '+234', 'flag_emoji' => '🇳🇬', 'currency_code' => 'NGN', 'region' => 'Africa'],
            ['code' => 'KE', 'code_alpha3' => 'KEN', 'name' => 'Kenya', 'native_name' => 'Kenya', 'phone_code' => '+254', 'flag_emoji' => '🇰🇪', 'currency_code' => 'KES', 'region' => 'Africa'],
            ['code' => 'MA', 'code_alpha3' => 'MAR', 'name' => 'Morocco', 'native_name' => 'المغرب', 'phone_code' => '+212', 'flag_emoji' => '🇲🇦', 'currency_code' => 'MAD', 'region' => 'Africa'],
            ['code' => 'GH', 'code_alpha3' => 'GHA', 'name' => 'Ghana', 'native_name' => 'Ghana', 'phone_code' => '+233', 'flag_emoji' => '🇬🇭', 'currency_code' => 'GHS', 'region' => 'Africa'],
            
            // Oceania
            ['code' => 'AU', 'code_alpha3' => 'AUS', 'name' => 'Australia', 'native_name' => 'Australia', 'phone_code' => '+61', 'flag_emoji' => '🇦🇺', 'currency_code' => 'AUD', 'region' => 'Oceania'],
            ['code' => 'NZ', 'code_alpha3' => 'NZL', 'name' => 'New Zealand', 'native_name' => 'New Zealand', 'phone_code' => '+64', 'flag_emoji' => '🇳🇿', 'currency_code' => 'NZD', 'region' => 'Oceania'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['code' => $country['code']],
                $country
            );
        }

        $this->command->info('Countries seeded successfully! Total: ' . count($countries) . ' countries');
    }
}
