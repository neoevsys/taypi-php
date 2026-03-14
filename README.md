# TAYPI PHP SDK

SDK oficial para integrar pagos QR de [TAYPI](https://taypi.pe) en aplicaciones PHP.

Acepta pagos con Yape, Plin y cualquier app bancaria conectada a la CCE.

## Requisitos

- PHP 7.4 o superior
- Extensiones: `curl`, `json`

## Instalación

```bash
composer require taypi/taypi-php
```

## Uso rápido

```php
<?php
require 'vendor/autoload.php';

$taypi = new Taypi\Taypi(
    'taypi_pk_test_...',  // Public key
    'taypi_sk_test_...',  // Secret key
);

// Crear sesión de checkout
$session = $taypi->createCheckoutSession([
    'amount'      => '25.00',
    'reference'   => 'ORD-12345',
    'description' => 'Zapatillas Nike Air',
], 'ORD-12345'); // Idempotency-Key

echo $session['checkout_token'];
```

### Checkout completo (PHP + checkout.js)

```php
<?php
require 'vendor/autoload.php';

$taypi = new Taypi\Taypi('taypi_pk_test_...', 'taypi_sk_test_...');

$reference = 'ORD-12345';
$session = $taypi->createCheckoutSession([
    'amount'      => '25.00',
    'reference'   => $reference,
    'description' => 'Mi producto',
], $reference);
?>
<script src="https://app.taypi.pe/v1/checkout.js"></script>
<script>
    Taypi.publicKey = '<?= $taypi->publicKey ?>';
    Taypi.open({
        sessionToken: '<?= $session["checkout_token"] ?>',
        onSuccess: function(result) { console.log('Pagado:', result.paid_at); },
        onExpired: function() { console.log('QR expirado'); },
        onClose: function() { console.log('Modal cerrado'); }
    });
</script>
```

## Métodos disponibles

### Checkout Sessions

```php
// Crear sesión para checkout.js (retorna solo checkout_token)
$session = $taypi->createCheckoutSession([
    'amount'      => '50.00',
    'reference'   => 'ORD-789',
    'description' => 'Descripción del pago',
    'metadata'    => ['source' => 'web'],
], 'ORD-789');
```

### Pagos

```php
// Crear pago (retorna datos completos: QR, checkout_url, etc.)
$payment = $taypi->createPayment([
    'amount'      => '50.00',
    'reference'   => 'ORD-789',
    'description' => 'Descripción del pago',
], 'ORD-789');

// Consultar pago
$payment = $taypi->getPayment('uuid-del-pago');

// Listar pagos
$result = $taypi->listPayments([
    'status'   => 'completed',
    'from'     => '2026-03-01',
    'to'       => '2026-03-31',
    'per_page' => 50,
]);

// Cancelar pago pendiente
$payment = $taypi->cancelPayment('uuid-del-pago', 'cancel-ORD-789');
```

### Webhooks

```php
// Verificar firma de webhook recibido
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_TAYPI_SIGNATURE'];
$secret    = 'tu_webhook_secret';

if ($taypi->verifyWebhook($payload, $signature, $secret)) {
    // Webhook válido, procesar
    $event = json_decode($payload, true);
} else {
    // Firma inválida, rechazar
    http_response_code(403);
}
```

## Entornos

```php
// Producción (default)
$taypi = new Taypi\Taypi('pk', 'sk');

// Desarrollo
$taypi = new Taypi\Taypi('pk', 'sk', ['base_url' => 'https://dev.taypi.pe']);

// Sandbox
$taypi = new Taypi\Taypi('pk', 'sk', ['base_url' => 'https://sandbox.taypi.pe']);
```

## Idempotencia

Todos los métodos que crean recursos (`createCheckoutSession`, `createPayment`, `cancelPayment`) requieren un `Idempotency-Key` explícito. Esto protege contra pagos duplicados por reintentos de red.

```php
// Usar la referencia de orden como idempotency key
$taypi->createCheckoutSession($params, 'ORD-12345');

// Si el mismo key se envía dentro de los 15 minutos, retorna la respuesta cacheada
// sin crear un pago nuevo.
```

## Manejo de errores

```php
try {
    $session = $taypi->createCheckoutSession($params, $reference);
} catch (Taypi\TaypiException $e) {
    echo $e->getMessage();    // "El monto mínimo es S/ 1.00"
    echo $e->errorCode;       // "AMOUNT_TOO_LOW"
    echo $e->httpCode;        // 422
    echo $e->response;        // Respuesta completa del API (array)
}
```

## Licencia

MIT - [NEO TECHNOLOGY PERÚ E.I.R.L.](https://neotecperu.com)
