<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * World countries, so international shipping legs can pick an origin/destination
 * country. Egypt is included (updated in place, not duplicated) and stays the
 * anchor for domestic legs; the rest are only surfaced in international mode.
 * Re-runnable (updateOrCreate on iso2).
 */
class WorldCountriesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->countries() as [$ar, $en, $iso2, $iso3, $phone, $currency]) {
            Country::updateOrCreate(
                ['iso2' => $iso2],
                ['name_ar' => $ar, 'name_en' => $en, 'iso3' => $iso3, 'phone_code' => $phone, 'currency' => $currency]
            );
        }
    }

    /** @return array<int, array{0:string,1:string,2:string,3:string,4:string,5:string}> */
    private function countries(): array
    {
        return [
            // Arab world
            ['مصر', 'Egypt', 'EG', 'EGY', '20', 'EGP'],
            ['السعودية', 'Saudi Arabia', 'SA', 'SAU', '966', 'SAR'],
            ['الإمارات', 'United Arab Emirates', 'AE', 'ARE', '971', 'AED'],
            ['الكويت', 'Kuwait', 'KW', 'KWT', '965', 'KWD'],
            ['قطر', 'Qatar', 'QA', 'QAT', '974', 'QAR'],
            ['البحرين', 'Bahrain', 'BH', 'BHR', '973', 'BHD'],
            ['عُمان', 'Oman', 'OM', 'OMN', '968', 'OMR'],
            ['الأردن', 'Jordan', 'JO', 'JOR', '962', 'JOD'],
            ['لبنان', 'Lebanon', 'LB', 'LBN', '961', 'LBP'],
            ['سوريا', 'Syria', 'SY', 'SYR', '963', 'SYP'],
            ['العراق', 'Iraq', 'IQ', 'IRQ', '964', 'IQD'],
            ['فلسطين', 'Palestine', 'PS', 'PSE', '970', 'ILS'],
            ['اليمن', 'Yemen', 'YE', 'YEM', '967', 'YER'],
            ['ليبيا', 'Libya', 'LY', 'LBY', '218', 'LYD'],
            ['تونس', 'Tunisia', 'TN', 'TUN', '216', 'TND'],
            ['الجزائر', 'Algeria', 'DZ', 'DZA', '213', 'DZD'],
            ['المغرب', 'Morocco', 'MA', 'MAR', '212', 'MAD'],
            ['موريتانيا', 'Mauritania', 'MR', 'MRT', '222', 'MRU'],
            ['السودان', 'Sudan', 'SD', 'SDN', '249', 'SDG'],
            ['الصومال', 'Somalia', 'SO', 'SOM', '252', 'SOS'],
            ['جيبوتي', 'Djibouti', 'DJ', 'DJI', '253', 'DJF'],
            ['جزر القمر', 'Comoros', 'KM', 'COM', '269', 'KMF'],

            // Africa
            ['نيجيريا', 'Nigeria', 'NG', 'NGA', '234', 'NGN'],
            ['كينيا', 'Kenya', 'KE', 'KEN', '254', 'KES'],
            ['إثيوبيا', 'Ethiopia', 'ET', 'ETH', '251', 'ETB'],
            ['جنوب أفريقيا', 'South Africa', 'ZA', 'ZAF', '27', 'ZAR'],
            ['غانا', 'Ghana', 'GH', 'GHA', '233', 'GHS'],
            ['تنزانيا', 'Tanzania', 'TZ', 'TZA', '255', 'TZS'],
            ['أوغندا', 'Uganda', 'UG', 'UGA', '256', 'UGX'],
            ['السنغال', 'Senegal', 'SN', 'SEN', '221', 'XOF'],
            ['ساحل العاج', 'Ivory Coast', 'CI', 'CIV', '225', 'XOF'],

            // Middle East / Asia (non-Arab)
            ['تركيا', 'Turkey', 'TR', 'TUR', '90', 'TRY'],
            ['إيران', 'Iran', 'IR', 'IRN', '98', 'IRR'],
            ['إسرائيل', 'Israel', 'IL', 'ISR', '972', 'ILS'],
            ['باكستان', 'Pakistan', 'PK', 'PAK', '92', 'PKR'],
            ['الهند', 'India', 'IN', 'IND', '91', 'INR'],
            ['بنغلاديش', 'Bangladesh', 'BD', 'BGD', '880', 'BDT'],
            ['الصين', 'China', 'CN', 'CHN', '86', 'CNY'],
            ['اليابان', 'Japan', 'JP', 'JPN', '81', 'JPY'],
            ['كوريا الجنوبية', 'South Korea', 'KR', 'KOR', '82', 'KRW'],
            ['إندونيسيا', 'Indonesia', 'ID', 'IDN', '62', 'IDR'],
            ['ماليزيا', 'Malaysia', 'MY', 'MYS', '60', 'MYR'],
            ['سنغافورة', 'Singapore', 'SG', 'SGP', '65', 'SGD'],
            ['تايلاند', 'Thailand', 'TH', 'THA', '66', 'THB'],
            ['الفلبين', 'Philippines', 'PH', 'PHL', '63', 'PHP'],
            ['فيتنام', 'Vietnam', 'VN', 'VNM', '84', 'VND'],
            ['أفغانستان', 'Afghanistan', 'AF', 'AFG', '93', 'AFN'],
            ['أذربيجان', 'Azerbaijan', 'AZ', 'AZE', '994', 'AZN'],
            ['كازاخستان', 'Kazakhstan', 'KZ', 'KAZ', '7', 'KZT'],

            // Europe
            ['المملكة المتحدة', 'United Kingdom', 'GB', 'GBR', '44', 'GBP'],
            ['ألمانيا', 'Germany', 'DE', 'DEU', '49', 'EUR'],
            ['فرنسا', 'France', 'FR', 'FRA', '33', 'EUR'],
            ['إيطاليا', 'Italy', 'IT', 'ITA', '39', 'EUR'],
            ['إسبانيا', 'Spain', 'ES', 'ESP', '34', 'EUR'],
            ['هولندا', 'Netherlands', 'NL', 'NLD', '31', 'EUR'],
            ['بلجيكا', 'Belgium', 'BE', 'BEL', '32', 'EUR'],
            ['اليونان', 'Greece', 'GR', 'GRC', '30', 'EUR'],
            ['السويد', 'Sweden', 'SE', 'SWE', '46', 'SEK'],
            ['سويسرا', 'Switzerland', 'CH', 'CHE', '41', 'CHF'],
            ['روسيا', 'Russia', 'RU', 'RUS', '7', 'RUB'],
            ['أوكرانيا', 'Ukraine', 'UA', 'UKR', '380', 'UAH'],
            ['بولندا', 'Poland', 'PL', 'POL', '48', 'PLN'],
            ['رومانيا', 'Romania', 'RO', 'ROU', '40', 'RON'],
            ['البرتغال', 'Portugal', 'PT', 'PRT', '351', 'EUR'],

            // Americas
            ['الولايات المتحدة', 'United States', 'US', 'USA', '1', 'USD'],
            ['كندا', 'Canada', 'CA', 'CAN', '1', 'CAD'],
            ['المكسيك', 'Mexico', 'MX', 'MEX', '52', 'MXN'],
            ['البرازيل', 'Brazil', 'BR', 'BRA', '55', 'BRL'],
            ['الأرجنتين', 'Argentina', 'AR', 'ARG', '54', 'ARS'],
            ['تشيلي', 'Chile', 'CL', 'CHL', '56', 'CLP'],
            ['كولومبيا', 'Colombia', 'CO', 'COL', '57', 'COP'],

            // Oceania
            ['أستراليا', 'Australia', 'AU', 'AUS', '61', 'AUD'],
            ['نيوزيلندا', 'New Zealand', 'NZ', 'NZL', '64', 'NZD'],
        ];
    }
}
