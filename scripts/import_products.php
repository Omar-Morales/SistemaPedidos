<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

$path = storage_path('import/products.csv');

if (! file_exists($path)) {
    fwrite(STDERR, "Archivo no encontrado: {$path}\n");
    exit(1);
}

$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (! $lines) {
    fwrite(STDERR, "El archivo no contiene datos.\n");
    exit(1);
}

$header = str_getcsv(array_shift($lines), ';');

$categoryMap = [
    'CERAMICA' => 'CERAMICAS',
    'CERAMICAS' => 'CERAMICAS',
    'LOZA' => 'LOZAS',
    'CERAMICA ' => 'CERAMICAS',
];

$count = 0;
$skuCounter = 1;

foreach ($lines as $line) {
    $columns = str_getcsv($line, ';');

    if (count($columns) < 6) {
        continue;
    }

    [$image, $name, $productCode, $categoryName, $priceRaw, $quantityRaw] = $columns;

    $name = mb_strtoupper(trim($name));
    $categoryKey = mb_strtoupper(trim($categoryName));
    $categoryKey = $categoryMap[$categoryKey] ?? $categoryKey;

    $category = Category::where('name', $categoryKey)->first();

    if (! $category) {
        echo "Categoría no encontrada: {$categoryKey} para el producto {$name}" . PHP_EOL;
        continue;
    }

    $price = str_replace(['S/', 's/', ' ', ','], '', trim($priceRaw));
    $price = (float) $price;

    $quantity = (int) trim($quantityRaw);

    $sku = 'SKU' . str_pad((string) $skuCounter, 5, '0', STR_PAD_LEFT);
    $skuCounter++;

    $productCode = trim($productCode);
    if ($productCode === '-' || $productCode === '') {
        $productCode = $sku;
    }

    Product::updateOrCreate(
        ['sku' => $sku],
        [
            'user_id' => 1,
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'category_id' => $category->id,
            'status' => 'available',
            'product_code' => $productCode,
        ]
    );

    $count++;
}

echo "Productos importados: {$count}" . PHP_EOL;
