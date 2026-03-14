<?php
/**
 * TAYPI Checkout.js — Demo de integración
 *
 * Requisitos:
 *   composer require taypi/taypi-php
 *
 * Uso:
 *   1. Reemplaza las API keys con las tuyas (panel.taypi.pe → API Keys)
 *   2. php -S localhost:8000
 *   3. Abre http://localhost:8000 en tu navegador
 *
 * El frontend NUNCA ve las credenciales.
 * Subir este archivo a cualquier hosting PHP.
 */

require __DIR__ . '/../../vendor/autoload.php';

// ─── Configura tus credenciales ───────────────────────────
$taypi = new Taypi\Taypi(
    'taypi_pk_test_TU_PUBLIC_KEY',   // Reemplaza con tu public key
    'taypi_sk_test_TU_SECRET_KEY',   // Reemplaza con tu secret key
    ['base_url' => 'https://dev.taypi.pe'],
);

// ─── Datos del pedido ─────────────────────────────────────
$amount    = '25.00';
$reference = 'ORD-12345'; // ID de la orden en tu sistema

$session_token = null;
$error = null;

try {
    $session = $taypi->createCheckoutSession([
        'amount'      => $amount,
        'reference'   => $reference,
        'description' => 'Zapatillas Nike Air - Talla 42',
    ], $reference); // Idempotency-Key = referencia de la orden
    $session_token = $session['checkout_token'];
} catch (Taypi\TaypiException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Tienda - Demo TAYPI Checkout</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f4f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,.1);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            padding: 24px;
            color: #fff;
            text-align: center;
        }
        .card-header h1 { font-size: 20px; font-weight: 700; }
        .card-header p { font-size: 13px; opacity: .75; margin-top: 4px; }
        .card-body { padding: 24px; }
        .product {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: #fafafa;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .product-icon {
            width: 48px; height: 48px;
            background: #e0e7ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .product-name { font-size: 14px; font-weight: 600; color: #18181b; }
        .product-desc { font-size: 12px; color: #71717a; margin-top: 2px; }
        .product-price { font-size: 18px; font-weight: 700; color: #18181b; margin-left: auto; }
        .btn-pay {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #4f46e5;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .1s;
        }
        .btn-pay:hover { background: #4338ca; }
        .btn-pay:active { transform: scale(.98); }
        .btn-pay:disabled { background: #a5b4fc; cursor: not-allowed; transform: none; }
        .status {
            padding: 12px 24px;
            font-size: 12px;
            color: #71717a;
            border-top: 1px solid #f4f4f5;
            text-align: center;
            min-height: 42px;
        }
        .status.ok { background: #ecfdf5; color: #065f46; }
        .status.err { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h1>Mi Tienda Online</h1>
        <p>Demo de integración TAYPI Checkout</p>
    </div>

    <div class="card-body">
        <div class="product">
            <div class="product-icon">&#x1f45f;</div>
            <div>
                <div class="product-name">Zapatillas Nike Air</div>
                <div class="product-desc">Talla 42 - Color negro</div>
            </div>
            <div class="product-price">S/ <?= htmlspecialchars($amount) ?></div>
        </div>

        <button id="btn_pagar" class="btn-pay" <?= $error ? 'disabled' : '' ?>>Pagar con QR</button>
    </div>

    <div id="status" class="status <?= $error ? 'err' : '' ?>">
        <?= $error ? htmlspecialchars($error) : 'Haz click en "Pagar con QR" para abrir el checkout' ?>
    </div>
</div>

<?php if ($session_token): ?>
<!-- TAYPI Checkout.js -->
<script src="https://dev.taypi.pe/v1/checkout.js"></script>

<script>
    Taypi.publicKey = '<?= htmlspecialchars($taypi->publicKey) ?>';

    var btn = document.getElementById('btn_pagar');
    var status = document.getElementById('status');

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Abriendo...';

        Taypi.open({
            sessionToken: '<?= htmlspecialchars($session_token) ?>',

            onSuccess: function(result) {
                status.className = 'status ok';
                status.textContent = 'Pago completado: ' + result.paid_at;
                btn.textContent = 'Pagado';
            },
            onExpired: function() {
                status.className = 'status err';
                status.textContent = 'QR expirado. Recarga la página para intentar de nuevo.';
                btn.disabled = false;
                btn.textContent = 'Pagar con QR';
            },
            onClose: function() {
                btn.disabled = false;
                btn.textContent = 'Pagar con QR';
            }
        });
    });
</script>
<?php endif; ?>

</body>
</html>
