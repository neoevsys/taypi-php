<?php
/**
 * TAYPI Pagos — Ejemplo de gestión directa
 *
 * Requisitos:
 *   composer require taypi/taypi-php
 *
 * Este ejemplo muestra cómo crear, consultar, listar y cancelar pagos
 * directamente desde el backend sin usar checkout.js.
 *
 * Útil para: integraciones backend-to-backend, sistemas POS,
 * facturación automática, o cuando necesitas control total del QR.
 */

require __DIR__ . '/../../vendor/autoload.php';

$taypi = new Taypi\Taypi(
    'taypi_pk_test_TU_PUBLIC_KEY',
    'taypi_sk_test_TU_SECRET_KEY',
    ['base_url' => 'https://dev.taypi.pe'],
);

// ─── 1. Crear pago (retorna QR completo) ─────────────────
try {
    $payment = $taypi->createPayment([
        'amount'      => '50.00',
        'reference'   => 'ORD-789',
        'description' => 'Curso de programación PHP',
        'metadata'    => ['course_id' => 42, 'student' => 'Juan Pérez'],
    ], 'ORD-789'); // Idempotency-Key

    echo "Pago creado:\n";
    echo "  ID:           {$payment['payment_id']}\n";
    echo "  QR Code:      {$payment['qr_code']}\n";
    echo "  Checkout URL: {$payment['checkout_url']}\n";
    echo "  Expira:       {$payment['expires_at']}\n\n";
} catch (Taypi\TaypiException $e) {
    echo "Error creando pago: {$e->getMessage()} ({$e->errorCode})\n";
    exit(1);
}

// ─── 2. Consultar pago por ID ─────────────────────────────
try {
    $consulta = $taypi->getPayment($payment['payment_id']);

    echo "Consulta de pago:\n";
    echo "  Status:    {$consulta['status']}\n";
    echo "  Amount:    S/ {$consulta['amount']}\n";
    echo "  Reference: {$consulta['reference']}\n\n";
} catch (Taypi\TaypiException $e) {
    echo "Error consultando: {$e->getMessage()}\n";
}

// ─── 3. Listar pagos con filtros ──────────────────────────
try {
    $lista = $taypi->listPayments([
        'status'   => 'completed',
        'from'     => '2026-03-01',
        'to'       => '2026-03-31',
        'per_page' => 10,
    ]);

    echo "Pagos completados en marzo 2026:\n";
    foreach ($lista['data'] as $p) {
        echo "  - {$p['reference']}: S/ {$p['amount']} ({$p['paid_at']})\n";
    }
    echo "  Total: {$lista['meta']['total']} pagos\n\n";
} catch (Taypi\TaypiException $e) {
    echo "Error listando: {$e->getMessage()}\n";
}

// ─── 4. Cancelar pago pendiente ───────────────────────────
try {
    $cancelado = $taypi->cancelPayment($payment['payment_id'], 'cancel-ORD-789');

    echo "Pago cancelado:\n";
    echo "  Status: {$cancelado['status']}\n";
} catch (Taypi\TaypiException $e) {
    echo "Error cancelando: {$e->getMessage()} ({$e->errorCode})\n";
}
