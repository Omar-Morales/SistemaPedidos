<?php

use App\Models\Customer;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

$path = storage_path('import/TIENDAS.csv');

if (! file_exists($path)) {
    fwrite(STDERR, "Archivo no encontrado: {$path}" . PHP_EOL);
    exit(1);
}

$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (! $lines) {
    fwrite(STDERR, "El archivo TIENDAS.csv no contiene datos." . PHP_EOL);
    exit(1);
}

$allowedLocations = [
    'ATE',
    'AYACUCHO',
    'BARRANCA',
    'CAJAMARCA',
    'CAÑETE',
    'CARABAYLLO',
    'CARAZ',
    'CHAMCHAMAYO',
    'CHANCAY',
    'CHICLAYO',
    'CHIMBOTE',
    'COMAS',
    'CONSTRUCENTER',
    'CUADRA 10',
    'CUADRA 11',
    'CUADRA 6',
    'CUADRA 7',
    'CUADRA 8',
    'CUADRA 9',
    'FRENTE',
    'GALERIA 1019',
    'GALERIA 1069',
    'GALERIA 907',
    'GALERIA 931',
    'GALERIA 955 ZOTANO',
    'HUACHIPA',
    'HUANCAYO',
    'HUANUCO',
    'HUARAL',
    'HUARAZ',
    'HUAROCHIRI',
    'HUAYCAN',
    'LAMBAYEQUE',
    'PIURA',
    'PTE. PIEDRA',
    'SAN JUAN DE LURIGANCHO',
    'SAN JUAN DE MIRAFLORES',
    'STA. CAROLINA',
    'STA. CLORINDA',
    'STA. INES',
    'STA. MERCEDEZ',
    'SURQUILLO',
    'TRUJILLO',
    'TUMBES',
    'VILLA EL SALVADOR',
    'SIN UBICACIÓN',
];

array_shift($lines); // remove header

$count = 0;
$generatedRucCounter = 1;
$skipped = [];

foreach ($lines as $line) {
    $columns = str_getcsv($line, ';');

    if (count($columns) < 4) {
        continue;
    }

    [$ruc, $name, $location, $phone] = $columns;

    $name = mb_strtoupper(trim($name));

    if ($name === '') {
        continue;
    }

    $location = mb_strtoupper(trim($location));
    if ($location === '-' || $location === '') {
        $location = 'SIN UBICACIÓN';
    }

    if (! in_array($location, $allowedLocations, true)) {
        $skipped[] = $name . ' (ubicación: ' . $location . ')';
        continue;
    }

    $phone = trim($phone);
    $phone = ($phone === '-' || $phone === '') ? null : $phone;

    $ruc = trim($ruc);
    if ($ruc === '-' || $ruc === '') {
        do {
            $ruc = 'RUC' . str_pad((string) $generatedRucCounter, 8, '0', STR_PAD_LEFT);
            $generatedRucCounter++;
        } while (Customer::where('ruc', $ruc)->exists());
    }

    Customer::updateOrCreate(
        ['ruc' => $ruc],
        [
            'name' => $name,
            'phone' => $phone,
            'address' => $location,
            'status' => 'active',
        ]
    );

    $count++;
}

echo "Tiendas importadas: {$count}" . PHP_EOL;

if ($skipped) {
    echo "Registros omitidos por ubicación no permitida:" . PHP_EOL;
    foreach ($skipped as $item) {
        echo " - {$item}" . PHP_EOL;
    }
}
