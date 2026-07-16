<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * World countries, so international shipping legs can pick an origin/destination
 * country. Egypt is included (updated in place, not duplicated) and stays the
 * anchor for domestic legs; the rest are only surfaced in international mode.
 * Re-runnable (updateOrCreate on iso2).
 *
 * The list mirrors ISO 3166-1 officially assigned codes (249) rather than a
 * hand-picked subset — territories like Hong Kong, Puerto Rico and Réunion are
 * real shipping destinations with their own codes, so "which places count" is
 * the standard's call, not ours. Grouped by region for review only; nothing
 * reads the grouping. The flag emoji is derived from the ISO2 code, so it can
 * never drift out of sync with the row.
 */
class WorldCountriesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->countries() as [$ar, $en, $iso2, $iso3, $phone, $currency]) {
            Country::updateOrCreate(
                ['iso2' => $iso2],
                [
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'iso3' => $iso3,
                    'phone_code' => $phone,
                    'currency' => $currency,
                    'flag' => $this->flagFor($iso2),
                ]
            );
        }
    }

    /**
     * The flag emoji for an ISO2 code: each letter maps to its regional
     * indicator symbol (U+1F1E6 is 'A'), and the pair renders as one flag.
     */
    private function flagFor(string $iso2): string
    {
        $out = '';

        foreach (str_split(strtoupper($iso2)) as $letter) {
            $out .= mb_chr(0x1F1E6 + (ord($letter) - ord('A')), 'UTF-8');
        }

        return $out;
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
            ['أنغولا', 'Angola', 'AO', 'AGO', '244', 'AOA'],
            ['بنين', 'Benin', 'BJ', 'BEN', '229', 'XOF'],
            ['بوتسوانا', 'Botswana', 'BW', 'BWA', '267', 'BWP'],
            ['بوركينا فاسو', 'Burkina Faso', 'BF', 'BFA', '226', 'XOF'],
            ['بوروندي', 'Burundi', 'BI', 'BDI', '257', 'BIF'],
            ['الرأس الأخضر', 'Cabo Verde', 'CV', 'CPV', '238', 'CVE'],
            ['الكاميرون', 'Cameroon', 'CM', 'CMR', '237', 'XAF'],
            ['أفريقيا الوسطى', 'Central African Republic', 'CF', 'CAF', '236', 'XAF'],
            ['تشاد', 'Chad', 'TD', 'TCD', '235', 'XAF'],
            ['الكونغو', 'Congo', 'CG', 'COG', '242', 'XAF'],
            ['الكونغو الديمقراطية', 'DR Congo', 'CD', 'COD', '243', 'CDF'],
            ['غينيا الاستوائية', 'Equatorial Guinea', 'GQ', 'GNQ', '240', 'XAF'],
            ['إريتريا', 'Eritrea', 'ER', 'ERI', '291', 'ERN'],
            ['إسواتيني', 'Eswatini', 'SZ', 'SWZ', '268', 'SZL'],
            ['الغابون', 'Gabon', 'GA', 'GAB', '241', 'XAF'],
            ['غامبيا', 'Gambia', 'GM', 'GMB', '220', 'GMD'],
            ['غينيا', 'Guinea', 'GN', 'GIN', '224', 'GNF'],
            ['غينيا بيساو', 'Guinea-Bissau', 'GW', 'GNB', '245', 'XOF'],
            ['ليسوتو', 'Lesotho', 'LS', 'LSO', '266', 'LSL'],
            ['ليبيريا', 'Liberia', 'LR', 'LBR', '231', 'LRD'],
            ['مدغشقر', 'Madagascar', 'MG', 'MDG', '261', 'MGA'],
            ['مالاوي', 'Malawi', 'MW', 'MWI', '265', 'MWK'],
            ['مالي', 'Mali', 'ML', 'MLI', '223', 'XOF'],
            ['موريشيوس', 'Mauritius', 'MU', 'MUS', '230', 'MUR'],
            ['موزمبيق', 'Mozambique', 'MZ', 'MOZ', '258', 'MZN'],
            ['ناميبيا', 'Namibia', 'NA', 'NAM', '264', 'NAD'],
            ['النيجر', 'Niger', 'NE', 'NER', '227', 'XOF'],
            ['رواندا', 'Rwanda', 'RW', 'RWA', '250', 'RWF'],
            ['ساو تومي وبرينسيبي', 'Sao Tome and Principe', 'ST', 'STP', '239', 'STN'],
            ['سيشل', 'Seychelles', 'SC', 'SYC', '248', 'SCR'],
            ['سيراليون', 'Sierra Leone', 'SL', 'SLE', '232', 'SLE'],
            ['جنوب السودان', 'South Sudan', 'SS', 'SSD', '211', 'SSP'],
            ['توغو', 'Togo', 'TG', 'TGO', '228', 'XOF'],
            ['زامبيا', 'Zambia', 'ZM', 'ZMB', '260', 'ZMW'],
            ['زيمبابوي', 'Zimbabwe', 'ZW', 'ZWE', '263', 'ZWG'],
            ['ريونيون', 'Réunion', 'RE', 'REU', '262', 'EUR'],
            ['مايوت', 'Mayotte', 'YT', 'MYT', '262', 'EUR'],
            ['سانت هيلانة', 'Saint Helena', 'SH', 'SHN', '290', 'SHP'],
            ['الصحراء الغربية', 'Western Sahara', 'EH', 'ESH', '212', 'MAD'],

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
            ['أرمينيا', 'Armenia', 'AM', 'ARM', '374', 'AMD'],
            ['بوتان', 'Bhutan', 'BT', 'BTN', '975', 'BTN'],
            ['بروناي', 'Brunei', 'BN', 'BRN', '673', 'BND'],
            ['كمبوديا', 'Cambodia', 'KH', 'KHM', '855', 'KHR'],
            ['جورجيا', 'Georgia', 'GE', 'GEO', '995', 'GEL'],
            ['هونغ كونغ', 'Hong Kong', 'HK', 'HKG', '852', 'HKD'],
            ['قيرغيزستان', 'Kyrgyzstan', 'KG', 'KGZ', '996', 'KGS'],
            ['لاوس', 'Laos', 'LA', 'LAO', '856', 'LAK'],
            ['ماكاو', 'Macao', 'MO', 'MAC', '853', 'MOP'],
            ['المالديف', 'Maldives', 'MV', 'MDV', '960', 'MVR'],
            ['منغوليا', 'Mongolia', 'MN', 'MNG', '976', 'MNT'],
            ['ميانمار', 'Myanmar', 'MM', 'MMR', '95', 'MMK'],
            ['نيبال', 'Nepal', 'NP', 'NPL', '977', 'NPR'],
            ['كوريا الشمالية', 'North Korea', 'KP', 'PRK', '850', 'KPW'],
            ['سريلانكا', 'Sri Lanka', 'LK', 'LKA', '94', 'LKR'],
            ['تايوان', 'Taiwan', 'TW', 'TWN', '886', 'TWD'],
            ['طاجيكستان', 'Tajikistan', 'TJ', 'TJK', '992', 'TJS'],
            ['تيمور الشرقية', 'Timor-Leste', 'TL', 'TLS', '670', 'USD'],
            ['تركمانستان', 'Turkmenistan', 'TM', 'TKM', '993', 'TMT'],
            ['أوزبكستان', 'Uzbekistan', 'UZ', 'UZB', '998', 'UZS'],
            ['إقليم المحيط الهندي البريطاني', 'British Indian Ocean Territory', 'IO', 'IOT', '246', 'USD'],
            ['جزيرة الكريسماس', 'Christmas Island', 'CX', 'CXR', '61', 'AUD'],
            ['جزر كوكوس', 'Cocos (Keeling) Islands', 'CC', 'CCK', '61', 'AUD'],

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
            ['ألبانيا', 'Albania', 'AL', 'ALB', '355', 'ALL'],
            ['أندورا', 'Andorra', 'AD', 'AND', '376', 'EUR'],
            ['النمسا', 'Austria', 'AT', 'AUT', '43', 'EUR'],
            ['بيلاروسيا', 'Belarus', 'BY', 'BLR', '375', 'BYN'],
            ['البوسنة والهرسك', 'Bosnia and Herzegovina', 'BA', 'BIH', '387', 'BAM'],
            ['بلغاريا', 'Bulgaria', 'BG', 'BGR', '359', 'EUR'],
            ['كرواتيا', 'Croatia', 'HR', 'HRV', '385', 'EUR'],
            ['قبرص', 'Cyprus', 'CY', 'CYP', '357', 'EUR'],
            ['التشيك', 'Czechia', 'CZ', 'CZE', '420', 'CZK'],
            ['الدنمارك', 'Denmark', 'DK', 'DNK', '45', 'DKK'],
            ['إستونيا', 'Estonia', 'EE', 'EST', '372', 'EUR'],
            ['فنلندا', 'Finland', 'FI', 'FIN', '358', 'EUR'],
            ['المجر', 'Hungary', 'HU', 'HUN', '36', 'HUF'],
            ['أيسلندا', 'Iceland', 'IS', 'ISL', '354', 'ISK'],
            ['أيرلندا', 'Ireland', 'IE', 'IRL', '353', 'EUR'],
            ['لاتفيا', 'Latvia', 'LV', 'LVA', '371', 'EUR'],
            ['ليختنشتاين', 'Liechtenstein', 'LI', 'LIE', '423', 'CHF'],
            ['ليتوانيا', 'Lithuania', 'LT', 'LTU', '370', 'EUR'],
            ['لوكسمبورغ', 'Luxembourg', 'LU', 'LUX', '352', 'EUR'],
            ['مالطا', 'Malta', 'MT', 'MLT', '356', 'EUR'],
            ['مولدوفا', 'Moldova', 'MD', 'MDA', '373', 'MDL'],
            ['موناكو', 'Monaco', 'MC', 'MCO', '377', 'EUR'],
            ['الجبل الأسود', 'Montenegro', 'ME', 'MNE', '382', 'EUR'],
            ['مقدونيا الشمالية', 'North Macedonia', 'MK', 'MKD', '389', 'MKD'],
            ['النرويج', 'Norway', 'NO', 'NOR', '47', 'NOK'],
            ['سان مارينو', 'San Marino', 'SM', 'SMR', '378', 'EUR'],
            ['صربيا', 'Serbia', 'RS', 'SRB', '381', 'RSD'],
            ['سلوفاكيا', 'Slovakia', 'SK', 'SVK', '421', 'EUR'],
            ['سلوفينيا', 'Slovenia', 'SI', 'SVN', '386', 'EUR'],
            ['الفاتيكان', 'Vatican City', 'VA', 'VAT', '379', 'EUR'],
            ['جزر آلاند', 'Åland Islands', 'AX', 'ALA', '358', 'EUR'],
            ['جزر فارو', 'Faroe Islands', 'FO', 'FRO', '298', 'DKK'],
            ['جبل طارق', 'Gibraltar', 'GI', 'GIB', '350', 'GIP'],
            ['غيرنزي', 'Guernsey', 'GG', 'GGY', '44', 'GBP'],
            ['جزيرة مان', 'Isle of Man', 'IM', 'IMN', '44', 'GBP'],
            ['جيرزي', 'Jersey', 'JE', 'JEY', '44', 'GBP'],
            ['سفالبارد ويان ماين', 'Svalbard and Jan Mayen', 'SJ', 'SJM', '47', 'NOK'],

            // Americas
            ['الولايات المتحدة', 'United States', 'US', 'USA', '1', 'USD'],
            ['كندا', 'Canada', 'CA', 'CAN', '1', 'CAD'],
            ['المكسيك', 'Mexico', 'MX', 'MEX', '52', 'MXN'],
            ['البرازيل', 'Brazil', 'BR', 'BRA', '55', 'BRL'],
            ['الأرجنتين', 'Argentina', 'AR', 'ARG', '54', 'ARS'],
            ['تشيلي', 'Chile', 'CL', 'CHL', '56', 'CLP'],
            ['كولومبيا', 'Colombia', 'CO', 'COL', '57', 'COP'],
            ['أنغويلا', 'Anguilla', 'AI', 'AIA', '1264', 'XCD'],
            ['أنتيغوا وبربودا', 'Antigua and Barbuda', 'AG', 'ATG', '1268', 'XCD'],
            ['أروبا', 'Aruba', 'AW', 'ABW', '297', 'AWG'],
            ['الباهاما', 'Bahamas', 'BS', 'BHS', '1242', 'BSD'],
            ['بربادوس', 'Barbados', 'BB', 'BRB', '1246', 'BBD'],
            ['بليز', 'Belize', 'BZ', 'BLZ', '501', 'BZD'],
            ['برمودا', 'Bermuda', 'BM', 'BMU', '1441', 'BMD'],
            ['بوليفيا', 'Bolivia', 'BO', 'BOL', '591', 'BOB'],
            ['بونير', 'Caribbean Netherlands', 'BQ', 'BES', '599', 'USD'],
            ['جزر العذراء البريطانية', 'British Virgin Islands', 'VG', 'VGB', '1284', 'USD'],
            ['جزر كايمان', 'Cayman Islands', 'KY', 'CYM', '1345', 'KYD'],
            ['كوستاريكا', 'Costa Rica', 'CR', 'CRI', '506', 'CRC'],
            ['كوبا', 'Cuba', 'CU', 'CUB', '53', 'CUP'],
            ['كوراساو', 'Curaçao', 'CW', 'CUW', '599', 'XCG'],
            ['دومينيكا', 'Dominica', 'DM', 'DMA', '1767', 'XCD'],
            ['جمهورية الدومينيكان', 'Dominican Republic', 'DO', 'DOM', '1809', 'DOP'],
            ['الإكوادور', 'Ecuador', 'EC', 'ECU', '593', 'USD'],
            ['السلفادور', 'El Salvador', 'SV', 'SLV', '503', 'USD'],
            ['جزر فوكلاند', 'Falkland Islands', 'FK', 'FLK', '500', 'FKP'],
            ['غويانا الفرنسية', 'French Guiana', 'GF', 'GUF', '594', 'EUR'],
            ['غرينلاند', 'Greenland', 'GL', 'GRL', '299', 'DKK'],
            ['غرينادا', 'Grenada', 'GD', 'GRD', '1473', 'XCD'],
            ['غوادلوب', 'Guadeloupe', 'GP', 'GLP', '590', 'EUR'],
            ['غواتيمالا', 'Guatemala', 'GT', 'GTM', '502', 'GTQ'],
            ['غيانا', 'Guyana', 'GY', 'GUY', '592', 'GYD'],
            ['هايتي', 'Haiti', 'HT', 'HTI', '509', 'HTG'],
            ['هندوراس', 'Honduras', 'HN', 'HND', '504', 'HNL'],
            ['جامايكا', 'Jamaica', 'JM', 'JAM', '1876', 'JMD'],
            ['مارتينيك', 'Martinique', 'MQ', 'MTQ', '596', 'EUR'],
            ['مونتسيرات', 'Montserrat', 'MS', 'MSR', '1664', 'XCD'],
            ['نيكاراغوا', 'Nicaragua', 'NI', 'NIC', '505', 'NIO'],
            ['بنما', 'Panama', 'PA', 'PAN', '507', 'PAB'],
            ['باراغواي', 'Paraguay', 'PY', 'PRY', '595', 'PYG'],
            ['بيرو', 'Peru', 'PE', 'PER', '51', 'PEN'],
            ['بورتوريكو', 'Puerto Rico', 'PR', 'PRI', '1787', 'USD'],
            ['سان بارتيلمي', 'Saint Barthélemy', 'BL', 'BLM', '590', 'EUR'],
            ['سانت كيتس ونيفيس', 'Saint Kitts and Nevis', 'KN', 'KNA', '1869', 'XCD'],
            ['سانت لوسيا', 'Saint Lucia', 'LC', 'LCA', '1758', 'XCD'],
            ['سان مارتن', 'Saint Martin', 'MF', 'MAF', '590', 'EUR'],
            ['سان بيير وميكلون', 'Saint Pierre and Miquelon', 'PM', 'SPM', '508', 'EUR'],
            ['سانت فنسنت والغرينادين', 'Saint Vincent and the Grenadines', 'VC', 'VCT', '1784', 'XCD'],
            ['سينت مارتن', 'Sint Maarten', 'SX', 'SXM', '1721', 'XCG'],
            ['سورينام', 'Suriname', 'SR', 'SUR', '597', 'SRD'],
            ['ترينيداد وتوباغو', 'Trinidad and Tobago', 'TT', 'TTO', '1868', 'TTD'],
            ['جزر توركس وكايكوس', 'Turks and Caicos Islands', 'TC', 'TCA', '1649', 'USD'],
            ['أوروغواي', 'Uruguay', 'UY', 'URY', '598', 'UYU'],
            ['جزر العذراء الأمريكية', 'United States Virgin Islands', 'VI', 'VIR', '1340', 'USD'],
            ['فنزويلا', 'Venezuela', 'VE', 'VEN', '58', 'VES'],

            // Oceania
            ['أستراليا', 'Australia', 'AU', 'AUS', '61', 'AUD'],
            ['نيوزيلندا', 'New Zealand', 'NZ', 'NZL', '64', 'NZD'],
            ['ساموا الأمريكية', 'American Samoa', 'AS', 'ASM', '1684', 'USD'],
            ['جزر كوك', 'Cook Islands', 'CK', 'COK', '682', 'NZD'],
            ['فيجي', 'Fiji', 'FJ', 'FJI', '679', 'FJD'],
            ['بولينيزيا الفرنسية', 'French Polynesia', 'PF', 'PYF', '689', 'XPF'],
            ['غوام', 'Guam', 'GU', 'GUM', '1671', 'USD'],
            ['كيريباتي', 'Kiribati', 'KI', 'KIR', '686', 'AUD'],
            ['جزر مارشال', 'Marshall Islands', 'MH', 'MHL', '692', 'USD'],
            ['ميكرونيزيا', 'Micronesia', 'FM', 'FSM', '691', 'USD'],
            ['ناورو', 'Nauru', 'NR', 'NRU', '674', 'AUD'],
            ['كاليدونيا الجديدة', 'New Caledonia', 'NC', 'NCL', '687', 'XPF'],
            ['نيوي', 'Niue', 'NU', 'NIU', '683', 'NZD'],
            ['جزيرة نورفولك', 'Norfolk Island', 'NF', 'NFK', '672', 'AUD'],
            ['جزر ماريانا الشمالية', 'Northern Mariana Islands', 'MP', 'MNP', '1670', 'USD'],
            ['بالاو', 'Palau', 'PW', 'PLW', '680', 'USD'],
            ['بابوا غينيا الجديدة', 'Papua New Guinea', 'PG', 'PNG', '675', 'PGK'],
            ['بيتكيرن', 'Pitcairn Islands', 'PN', 'PCN', '64', 'NZD'],
            ['ساموا', 'Samoa', 'WS', 'WSM', '685', 'WST'],
            ['جزر سليمان', 'Solomon Islands', 'SB', 'SLB', '677', 'SBD'],
            ['توكيلاو', 'Tokelau', 'TK', 'TKL', '690', 'NZD'],
            ['تونغا', 'Tonga', 'TO', 'TON', '676', 'TOP'],
            ['توفالو', 'Tuvalu', 'TV', 'TUV', '688', 'AUD'],
            ['فانواتو', 'Vanuatu', 'VU', 'VUT', '678', 'VUV'],
            ['واليس وفوتونا', 'Wallis and Futuna', 'WF', 'WLF', '681', 'XPF'],

            // Antarctic and uninhabited territories — carried for ISO completeness.
            ['أنتاركتيكا', 'Antarctica', 'AQ', 'ATA', '672', 'USD'],
            ['جزيرة بوفيه', 'Bouvet Island', 'BV', 'BVT', '47', 'NOK'],
            ['الأقاليم الجنوبية الفرنسية', 'French Southern Territories', 'TF', 'ATF', '262', 'EUR'],
            ['جزيرة هيرد وجزر ماكدونالد', 'Heard Island and McDonald Islands', 'HM', 'HMD', '61', 'AUD'],
            ['جورجيا الجنوبية وجزر ساندويتش', 'South Georgia and the South Sandwich Islands', 'GS', 'SGS', '500', 'GBP'],
            ['جزر الولايات المتحدة النائية', 'United States Minor Outlying Islands', 'UM', 'UMI', '1', 'USD'],
        ];
    }
}
