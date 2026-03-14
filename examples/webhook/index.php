<?php
/**
 * TAYPI Webhook — Ejemplo de recepción
 *
 * Requisitos:
 *   composer require taypi/taypi-php
 *
 * Uso:
 *   1. Configura esta URL como webhook en panel.taypi.pe → Webhooks
 *   2. TAYPI enviará un POST cada vez que un pago se complete
 *   3. Verifica SIEMPRE la firma antes de procesar
 *
 * IMPORTANTE: Este endpoint debe responder 200 en menos de 5 segundos.
 * Si necesitas procesamiento pesado, encola el trabajo y responde 200 de inmediato.
 */

require __DIR__ . '/../../vendor/autoload.php';

// ─── Configura tus credenciales ───────────────────────────
$taypi = new Taypi\Taypi(
    'taypi_pk_test_TU_PUBLIC_KEY',
    'taypi_sk_test_TU_SECRET_KEY',
);

$webhookSecret = 'TU_WEBHOOK_SECRET'; // panel.taypi.pe → Webhooks

// ─── Recibir y verificar webhook ──────────────────────────
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_TAYPI_SIGNATURE'] ?? '';

if (!$taypi->verifyWebhook($payload, $signature, $webhookSecret)) {
    http_response_code(403);
    echo json_encode(['error' => 'Firma inválida']);
    exit;
}

// ─── Procesar evento ──────────────────────────────────────
$event = json_decode($payload, true);

switch ($event['event']) {
    case 'payment.completed':
        // El pago fue exitoso
        // Aquí actualizas tu orden en la base de datos
        $paymentId = $event['payment_id'];
        $reference = $event['reference'];
        $amount    = $event['amount'];
        $paidAt    = $event['paid_at'];

        // Ejemplo: marcar orden como pagada
        // Order::where('reference', $reference)->update(['status' => 'paid']);

        error_log("Pago completado: {$paymentId} - Ref: {$reference} - S/ {$amount}");
        break;

    case 'payment.expired':
        // El QR expiró sin ser pagado
        $reference = $event['reference'];

        // Ejemplo: liberar stock reservado
        // Order::where('reference', $reference)->update(['status' => 'expired']);

        error_log("Pago expirado: Ref {$reference}");
        break;

    default:
        error_log("Evento desconocido: {$event['event']}");
}

// Responder 200 para confirmar recepción
http_response_code(200);
echo json_encode(['received' => true]);
