<?php

/**
 * TAYPI PHP SDK
 *
 * SDK oficial para integrar pagos QR de TAYPI en aplicaciones PHP.
 *
 * Uso:
 *   $taypi = new Taypi\Taypi('taypi_pk_test_...', 'taypi_sk_test_...');
 *   $session = $taypi->createCheckoutSession([
 *       'amount'      => '25.00',
 *       'reference'   => 'ORD-123',
 *       'description' => 'Mi producto',
 *   ], 'ORD-123');  // Idempotency-Key explícito
 *   echo $session['checkout_token'];
 *
 * @version 1.0.0
 * @link https://taypi.pe/docs
 */

namespace Taypi;

class Taypi
{
    const VERSION = '1.0.0';

    /** @var string Clave pública (taypi_pk_...) */
    public string $publicKey;

    /** @var string Clave secreta (taypi_sk_...) — nunca se envía en requests */
    private string $secretKey;

    /** @var string URL base del API */
    private string $baseUrl;

    /** @var int Timeout en segundos */
    private int $timeout;

    /** Entornos permitidos */
    private const ENVIRONMENTS = [
        'https://app.taypi.pe',
                'https://sandbox.taypi.pe',
    ];

    public function __construct(
        string $publicKey,
        string $secretKey,
        array $options = []
    ) {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->timeout = $options['timeout'] ?? 15;

        if (isset($options['base_url'])) {
            $url = rtrim($options['base_url'], '/');
            $baseHost = preg_replace('#/v1$#', '', $url);
            if (!in_array($baseHost, self::ENVIRONMENTS, true)) {
                throw new TaypiException(
                    'URL no permitida. Usa: app.taypi.pe o sandbox.taypi.pe',
                    'INVALID_BASE_URL'
                );
            }
            $this->baseUrl = $baseHost;
        } else {
            $this->baseUrl = self::ENVIRONMENTS[0]; // app.taypi.pe
        }
    }

    // ─── Checkout Sessions ───────────────────────────────────

    /**
     * Crea una sesión de checkout.
     * Retorna un array con 'checkout_token' para usar en checkout.js.
     *
     * @param array{amount: string, reference: string, description?: string, metadata?: array} $params
     * @param string $idempotencyKey Clave única para evitar pagos duplicados (ej: ID de orden)
     * @return array{checkout_token: string}
     * @throws TaypiException
     */
    public function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        $response = $this->post('/v1/checkout/sessions', $params, $idempotencyKey);

        return $response['data'];
    }

    // ─── Payments ────────────────────────────────────────────

    /**
     * Crea un pago con QR.
     *
     * @param array{amount: string, reference: string, description?: string, metadata?: array} $params
     * @param string $idempotencyKey Clave única para evitar pagos duplicados (ej: ID de orden)
     * @return array
     * @throws TaypiException
     */
    public function createPayment(array $params, string $idempotencyKey): array
    {
        $response = $this->post('/api/v1/payments', $params, $idempotencyKey);

        return $response['data'];
    }

    /**
     * Consulta un pago por ID.
     *
     * @param string $paymentId UUID del pago
     * @return array
     * @throws TaypiException
     */
    public function getPayment(string $paymentId): array
    {
        $response = $this->get("/api/v1/payments/{$paymentId}");

        return $response['data'];
    }

    /**
     * Lista pagos del comercio.
     *
     * @param array{status?: string, reference?: string, from?: string, to?: string, per_page?: int} $filters
     * @return array{data: array, meta: array}
     * @throws TaypiException
     */
    public function listPayments(array $filters = []): array
    {
        $query = $filters ? '?' . http_build_query($filters) : '';

        return $this->get("/api/v1/payments{$query}");
    }

    /**
     * Cancela un pago pendiente.
     *
     * @param string $paymentId UUID del pago
     * @param string $idempotencyKey Clave única para evitar cancelaciones duplicadas
     * @return array
     * @throws TaypiException
     */
    public function cancelPayment(string $paymentId, string $idempotencyKey): array
    {
        $response = $this->post("/api/v1/payments/{$paymentId}/cancel", [], $idempotencyKey);

        return $response['data'];
    }

    // ─── Webhooks ────────────────────────────────────────────

    /**
     * Verifica la firma de un webhook recibido.
     *
     * @param string $payload Body crudo del webhook (raw)
     * @param string $signature Valor del header Taypi-Signature (sha256=...)
     * @param string $webhookSecret Secret del webhook del comercio
     * @return bool
     */
    public function verifyWebhook(string $payload, string $signature, string $webhookSecret): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    // ─── HTTP ────────────────────────────────────────────────

    private function post(string $path, array $params, string $idempotencyKey): array
    {
        return $this->request('POST', $path, $params, $idempotencyKey);
    }

    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function request(string $method, string $path, ?array $params = null, ?string $idempotencyKey = null): array
    {
        $url = $this->baseUrl . $path;
        $timestamp = (string) time();
        $body = $params !== null ? json_encode($params) : '';

        // Firma HMAC-SHA256
        $signaturePath = parse_url($path, PHP_URL_PATH);
        $message = $timestamp . "\n" . $method . "\n" . $signaturePath . "\n" . $body;
        $signature = hash_hmac('sha256', $message, $this->secretKey);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->publicKey,
            'Taypi-Signature: ' . $signature,
            'Taypi-Timestamp: ' . $timestamp,
            'User-Agent: taypi-php/' . self::VERSION,
        ];

        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($method === 'POST' && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlError) {
            throw new TaypiException(
                "Error de conexión: {$curlError}",
                'CONNECTION_ERROR',
                0,
                $curlErrno
            );
        }

        $data = json_decode($response, true);

        if ($data === null) {
            throw new TaypiException(
                'Respuesta inválida del servidor',
                'INVALID_RESPONSE',
                $httpCode
            );
        }

        if ($httpCode >= 400) {
            throw new TaypiException(
                $data['message'] ?? 'Error del API',
                $data['error'] ?? 'API_ERROR',
                $httpCode,
                null,
                $data
            );
        }

        return $data;
    }

}
