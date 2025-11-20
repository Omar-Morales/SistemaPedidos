<?php

use App\Models\Customer;
use App\Models\DetalleVenta;
use App\Models\Product;
use App\Models\TipoDocumento;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

$path = storage_path('import/VENTAS.csv');

if (! file_exists($path)) {
    fwrite(STDERR, "Archivo no encontrado: {$path}" . PHP_EOL);
    exit(1);
}

$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if (! $lines) {
    fwrite(STDERR, "El archivo VENTAS.csv no contiene datos." . PHP_EOL);
    exit(1);
}

array_shift($lines);

$customerCache = [];
$productCache = [];
$documentCache = [];

$skipped = [
    'customers' => [],
    'products' => [],
    'documents' => [],
];

$warehouseMap = [
    'CURVA' => 'curva',
    'MILLA' => 'milla',
    'SANTA CAROLINA' => 'santa_carolina',
    'TIENDA' => 'tienda',
];

$deliveryMap = [
    'ENVIAR' => 'delivery',
    'DELIVERY' => 'delivery',
    'ENVIO' => 'delivery',
    'RECOJO' => 'pickup',
    'RECOGER' => 'pickup',
    'PICKUP' => 'pickup',
];

$statusMap = [
    'ENTREGADO' => 'delivered',
    'PENDIENTE' => 'pending',
    'EN PROCESO' => 'in_progress',
    'CANCELADO' => 'cancelled',
    'ANULADO' => 'cancelled',
    'ANULADA' => 'cancelled',
];

$paymentStatusMap = [
    'PAGADO' => 'paid',
    'PENDIENTE' => 'pending',
    'POR COBRAR' => 'to_collect',
    'COBRAR' => 'to_collect',
    'CAMBIO' => 'change',
    'ANULADO' => 'cancelled',
    'ANULADA' => 'cancelled',
];

$paymentMethodMap = [
    'EFECTIVO' => 'efectivo',
    'CASH' => 'efectivo',
    'TRANSFERENCIA' => 'trans_bcp',
    'TRANSFERENCIA BCP' => 'trans_bcp',
    'TRANS BCP' => 'trans_bcp',
    'TRANS B.C.P' => 'trans_bcp',
    'TRANS B B C P' => 'trans_bcp',
    'TRANS. BCP' => 'trans_bcp',
    'DEP BCP' => 'trans_bcp',
    'DEPOSITO BCP' => 'trans_bcp',
    'TRANSFERENCIA BBVA' => 'trans_bbva',
    'TRANS BBVA' => 'trans_bbva',
    'TRANS. BBVA' => 'trans_bbva',
    'DEPOSITO BBVA' => 'trans_bbva',
    'YAPE' => 'yape',
    'PLIN' => 'plin',
];

$userId = 1;
$imported = 0;

foreach ($lines as $index => $line) {
    $columns = str_getcsv($line, ';');

    if (count($columns) < 14) {
        continue;
    }

    [
        $fecha,
        $almacen,
        $clienteNombre,
        $tipoDocumentoNombre,
        $productoNombre,
        $cantidad,
        $unidad,
        $total,
        $montoPagado,
        $diferencia,
        $estadoEntrega,
        $estadoPago,
        $tipoEntrega,
        $metodoPago,
    ] = $columns;

    $clienteKey = mb_strtoupper(trim($clienteNombre), 'UTF-8');
    if ($clienteKey === '') {
        continue;
    }

    if (! isset($customerCache[$clienteKey])) {
        $customerCache[$clienteKey] = Customer::whereRaw('UPPER(TRIM(name)) = ?', [$clienteKey])->first();
    }

    $customer = $customerCache[$clienteKey];

    if (! $customer) {
        $skipped['customers'][$clienteKey] = true;
        continue;
    }

    $documentKey = normalizeKey($tipoDocumentoNombre);
    if ($documentKey === '') {
        $documentKey = 'FACTURA';
    }

    if (! isset($documentCache[$documentKey])) {
        $documentCache[$documentKey] = TipoDocumento::whereRaw('UPPER(name) = ?', [$documentKey])->first();
    }

    $tipoDocumento = $documentCache[$documentKey];

    if (! $tipoDocumento) {
        $skipped['documents'][$documentKey] = true;
        continue;
    }

    $productKey = mb_strtoupper(trim($productoNombre), 'UTF-8');
    if ($productKey === '') {
        continue;
    }

    if (! isset($productCache[$productKey])) {
        $productCache[$productKey] = Product::whereRaw('UPPER(TRIM(name)) = ?', [$productKey])->first();
    }

    $product = $productCache[$productKey];

    if (! $product) {
        $skipped['products'][$productKey] = true;
        continue;
    }

    try {
        $saleDate = Carbon::createFromFormat('d/m/Y', trim($fecha));
    } catch (\Exception $e) {
        $saleDate = Carbon::parse($fecha);
    }

    $warehouseKey = normalizeKey($almacen);
    $warehouse = $warehouseMap[$warehouseKey] ?? 'curva';

    $deliveryTypeKey = normalizeKey($tipoEntrega);
    $deliveryType = $deliveryMap[$deliveryTypeKey] ?? 'pickup';

    $statusKey = normalizeKey($estadoEntrega);
    $status = $statusMap[$statusKey] ?? 'pending';

    $paymentStatusKey = normalizeKey($estadoPago);
    $paymentStatus = $paymentStatusMap[$paymentStatusKey] ?? null;

    $paymentMethodKey = normalizeKey($metodoPago);
    $paymentMethod = $paymentMethodMap[$paymentMethodKey] ?? null;

    $quantity = (int) filter_var($cantidad, FILTER_SANITIZE_NUMBER_INT);
    if ($quantity <= 0) {
        $quantity = 1;
    }

    $totalAmount = parseMoneyValue($total);
    $paidAmount = parseMoneyValue($montoPagado);
    $differenceAmount = parseMoneyValue($diferencia);

    if (! $paymentStatus) {
        $paymentStatus = calculatePaymentStatus($totalAmount, $paidAmount);
    }

    $unitPrice = $quantity > 0 ? round($totalAmount / $quantity, 2) : $totalAmount;

    $venta = Venta::create([
        'customer_id' => $customer->id,
        'user_id' => $userId,
        'tipodocumento_id' => $tipoDocumento->id,
        'total_price' => $totalAmount,
        'sale_date' => $saleDate->format('Y-m-d'),
        'codigo' => 'IMP-' . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
        'payment_method' => $paymentMethod,
        'amount_paid' => $paidAmount,
        'payment_status' => $paymentStatus,
        'delivery_type' => $deliveryType,
        'status' => $status,
        'warehouse' => $warehouse,
        'difference' => $differenceAmount,
    ]);

    DetalleVenta::create([
        'sale_id' => $venta->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => $totalAmount,
        'unit' => trim($unidad) === '' ? null : trim($unidad),
        'status' => $status,
        'payment_status' => $paymentStatus,
        'amount_paid' => $paidAmount,
        'difference' => $differenceAmount,
        'warehouse' => $warehouse,
        'delivery_type' => $deliveryType,
        'payment_method' => $paymentMethod,
    ]);

    $imported++;
}

echo "Ventas importadas: {$imported}" . PHP_EOL;

foreach ($skipped as $type => $items) {
    if (! empty($items)) {
        echo "Registros omitidos por {$type} inexistentes:" . PHP_EOL;
        foreach (array_keys($items) as $item) {
            echo " - {$item}" . PHP_EOL;
        }
    }
}

function parseMoneyValue(?string $value): float
{
    if ($value === null) {
        return 0.0;
    }

    $clean = str_replace(
        ['$', 'S/', 's/', ',', ' '],
        '',
        trim($value)
    );

    $clean = str_replace(['(', ')'], '', $clean);

    return (float) $clean;
}

function calculatePaymentStatus(float $total, float $paid): string
{
    if ($paid <= 0) {
        return 'pending';
    }

    if ($paid >= $total) {
        return abs($paid - $total) <= 0.009 ? 'paid' : 'change';
    }

    return 'to_collect';
}

function normalizeKey(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $key = mb_strtoupper(trim($value), 'UTF-8');
    $key = str_replace(['.', ',', '-', '_', '/', '\\'], ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);

    return trim($key);
}
