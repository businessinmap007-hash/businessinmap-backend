<?php

$projectRoot = dirname(__DIR__, 2); // يرجع إلى مجلد المشروع الأساسي

$input  = $projectRoot . '/database/seeders/data/egypt_cities.csv';
$output = $projectRoot . '/database/seeders/data/egypt_cities_with_latlng.csv';



if (!file_exists($input)) {
    die("Input CSV not found\n");
}

$in  = fopen($input, 'r');
$out = fopen($output, 'w');

// اقرأ الهيدر
$header = fgetcsv($in);

// أضف أعمدة lat/lng
$header[] = 'latitude';
$header[] = 'longitude';

fputcsv($out, $header);

while (($row = fgetcsv($in)) !== false) {

    // CSV: id, governorate_id, city_name_ar, city_name_en
    if (count($row) < 4) {
        continue;
    }

    [$id, $gov_id, $city_ar, $city_en] = $row;

    $query = urlencode("{$city_en}, Egypt");

    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$query}&limit=1";

    // مهم: User-Agent
    $opts = [
        'http' => [
            'header' => "User-Agent: EgyptCitiesSeeder/1.0\r\n"
        ]
    ];

    $context = stream_context_create($opts);
    $json = file_get_contents($url, false, $context);
    $data = json_decode($json, true);

    $lat = $lng = null;

    if (!empty($data)) {
        $lat = $data[0]['lat'] ?? null;
        $lng = $data[0]['lon'] ?? null;
    }

    fputcsv($out, [
        $id,
        $gov_id,
        $city_ar,
        $city_en,
        $lat,
        $lng
    ]);

    echo "✔ {$city_en} => {$lat}, {$lng}\n";

    // احترام الـ rate limit
    sleep(1);
}

fclose($in);
fclose($out);

echo "\nDONE ✅ File created: egypt_cities_with_latlng.csv\n";
