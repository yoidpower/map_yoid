<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$sourceUrl = getenv('PARTNERS_MAP_URL') ?: 'https://partners.yoidpower.com/devicesMap';

/* ── Demo fallback — shown when the partners page is unreachable ── */
$demoStations = [
    [
        'id'      => 1,
        'title'   => 'Nayax B.V.',
        'serial'  => '',
        'online'  => true,
        'lat'     => 52.25325489999999,
        'lng'     => 4.6354539,
        'address' => 'Pondweg 7, 2153 PK Nieuw-Vennep, Netherlands',
    ],
    [
        'id'      => 2,
        'title'   => 'Savor Restaurant',
        'serial'  => '',
        'online'  => true,
        'lat'     => 51.30639739999999,
        'lng'     => 3.3884127,
        'address' => 'Nieuwstraat 20, 4524 EE Sluis, Nederland',
    ],
    [
        'id'      => 3,
        'title'   => 'YOID Office',
        'serial'  => '',
        'online'  => true,
        'lat'     => 51.9155,
        'lng'     => 4.2537,
        'address' => 'Zomerdijk 70, 3143 CT Maassluis, Netherlands',
    ],
];

/* ── Fetch partners map page ── */
$ctx = stream_context_create([
    'http' => [
        'timeout'    => 10,
        'user_agent' => 'YOID-Map/1.0',
    ],
]);

$html = @file_get_contents($sourceUrl, false, $ctx);

if ($html === false) {
    echo json_encode(['stations' => $demoStations, 'source' => 'demo', 'error' => 'Could not fetch partners page']);
    exit;
}

/* ── Parse hidden inputs ── */
$dom = new DOMDocument();
@$dom->loadHTML($html, LIBXML_NOERROR);

$stations = [];
$i = 1;

foreach ($dom->getElementsByTagName('input') as $input) {
    if ($input->getAttribute('devices_map_input') !== 'true') {
        continue;
    }

    $raw = urldecode($input->getAttribute('data'));
    $geo = json_decode($raw, true);

    if (!isset($geo['geometry']['lat'], $geo['geometry']['lng'])) {
        continue;
    }

    $stations[] = [
        'id'      => $i++,
        'title'   => html_entity_decode($input->getAttribute('station-title'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'serial'  => '',
        'online'  => true,
        'lat'     => (float)$geo['geometry']['lat'],
        'lng'     => (float)$geo['geometry']['lng'],
        'address' => str_replace('+', ' ', $geo['formatted_address'] ?? ''),
    ];
}

if (empty($stations)) {
    echo json_encode(['stations' => $demoStations, 'source' => 'demo', 'error' => 'No stations parsed from partners page']);
    exit;
}

echo json_encode(['stations' => $stations, 'source' => 'partners']);
