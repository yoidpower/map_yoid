<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

$host  = env('DB_HOST');
$name  = env('DB_NAME');
$user  = env('DB_USER');
$pass  = env('DB_PASS');

$table      = env('DB_STATIONS_TABLE', 'stations');
$colId      = env('DB_COL_ID',      'station_id');
$colTitle   = env('DB_COL_TITLE',   'station_location_title');
$colOnline  = env('DB_COL_ONLINE',  'station_is_online');
$colAddress = env('DB_COL_ADDRESS', 'station_address_data');
$colSerial  = env('DB_COL_SERIAL',  'station_serial_number');
$colActive  = env('DB_ACTIVE_FILTER', 'station_is_active');

/* ── Demo stations — used when DB is not yet configured ── */
$demoStations = [
    [
        'id'      => 1,
        'title'   => 'Nayax B.V.',
        'serial'  => 'GT042250901224',
        'online'  => true,
        'lat'     => 52.25325489999999,
        'lng'     => 4.6354539,
        'address' => 'Pondweg 7, 2153 PK Nieuw-Vennep, Netherlands',
    ],
    [
        'id'      => 2,
        'title'   => 'Savor Restaurant',
        'serial'  => 'GT042250805332',
        'online'  => true,
        'lat'     => 51.30639739999999,
        'lng'     => 3.3884127,
        'address' => 'Nieuwstraat 20, 4524 EE Sluis, Nederland',
    ],
    [
        'id'      => 3,
        'title'   => 'YOID Office',
        'serial'  => 'GT042241008260',
        'online'  => true,
        'lat'     => 51.9155,
        'lng'     => 4.2537,
        'address' => 'Zomerdijk 70, 3143 CT Maassluis, Netherlands',
    ],
];

if (!$host || !$name) {
    echo json_encode(['stations' => $demoStations, 'source' => 'demo']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );

    $onlineOnly = isset($_GET['online']) && $_GET['online'] === '1';
    $sql = "SELECT `$colId`, `$colTitle`, `$colOnline`, `$colAddress`, `$colSerial`
            FROM `$table`
            WHERE `$colActive` = 1"
         . ($onlineOnly ? " AND `$colOnline` = 1" : "")
         . " ORDER BY `$colTitle`";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $stations = [];
    foreach ($rows as $row) {
        $raw = $row[$colAddress] ?? '';
        $geo = json_decode(urldecode($raw), true);

        if (!isset($geo['geometry']['lat'], $geo['geometry']['lng'])) {
            continue;
        }

        $stations[] = [
            'id'      => $row[$colId],
            'title'   => $row[$colTitle] ?? '',
            'serial'  => $row[$colSerial] ?? '',
            'online'  => (bool)(int)($row[$colOnline] ?? 0),
            'lat'     => (float)$geo['geometry']['lat'],
            'lng'     => (float)$geo['geometry']['lng'],
            'address' => str_replace('+', ' ', $geo['formatted_address'] ?? ''),
        ];
    }

    echo json_encode(['stations' => $stations, 'source' => 'db']);

} catch (Exception $e) {
    // DB unreachable — serve demo data so the map still works
    echo json_encode(['stations' => $demoStations, 'source' => 'demo', 'db_error' => $e->getMessage()]);
}
