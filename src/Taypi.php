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

    /** @var bool Indica si el cliente está en modo sandbox (true) o producción (false) */
    public bool $isSandbox;

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
        // ── Validar formato de API keys ──
        self::validateKeyFormat($publicKey, 'publicKey', 'taypi_pk_', 32);
        self::validateKeyFormat($secretKey, 'secretKey', 'taypi_sk_', 64);

        // ── Detectar ambiente desde las keys ──
        $publicIsTest = str_starts_with($publicKey, 'taypi_pk_test_');
        $secretIsTest = str_starts_with($secretKey, 'taypi_sk_test_');

        if ($publicIsTest !== $secretIsTest) {
            throw new TaypiException(
                'Las keys no coinciden: una es de test y otra de producción. '
                . 'Ambas deben ser del mismo ambiente (taypi_pk_test_ + taypi_sk_test_ o taypi_pk_live_ + taypi_sk_live_).',
                'KEY_ENVIRONMENT_MISMATCH'
            );
        }

        $isTestMode = $publicIsTest;

        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->timeout = $options['timeout'] ?? 15;

        if (isset($options['base_url'])) {
            $url = rtrim($options['base_url'], '/');
            $baseHost = preg_replace('#/v1$#', '', $url);
            if (!in_array($baseHost, self::ENVIRONMENTS, true)) {
                throw new TaypiException(
                    'URL no permitida. Usa: https://app.taypi.pe (producción) o https://sandbox.taypi.pe (sandbox).',
                    'INVALID_BASE_URL'
                );
            }

            // ── Validar consistencia key ↔ ambiente ──
            $urlIsSandbox = $baseHost === self::ENVIRONMENTS[1];
            if ($isTestMode && !$urlIsSandbox) {
                throw new TaypiException(
                    'Keys de test (taypi_pk_test_) solo funcionan con sandbox. '
                    . 'Usa base_url => "https://sandbox.taypi.pe" o cambia a keys de producción (taypi_pk_live_).',
                    'KEY_URL_MISMATCH'
                );
            }
            if (!$isTestMode && $urlIsSandbox) {
                throw new TaypiException(
                    'Keys de producción (taypi_pk_live_) solo funcionan con producción. '
                    . 'Usa base_url => "https://app.taypi.pe" o cambia a keys de test (taypi_pk_test_).',
                    'KEY_URL_MISMATCH'
                );
            }

            $this->baseUrl = $baseHost;
        } else {
            // ── Auto-detectar ambiente desde el key ──
            $this->baseUrl = $isTestMode ? self::ENVIRONMENTS[1] : self::ENVIRONMENTS[0];
        }

        $this->isSandbox = $isTestMode;
    }

    private static function validateKeyFormat(string $key, string $paramName, string $expectedPrefix, int $expectedTokenLength): void
    {
        if (empty($key) || !str_starts_with($key, $expectedPrefix)) {
            throw new TaypiException(
                "Formato de {$paramName} inválido. Debe iniciar con \"{$expectedPrefix}live_\" o \"{$expectedPrefix}test_\". Recibido: \"" . substr($key, 0, 20) . '..."',
                'INVALID_KEY_FORMAT'
            );
        }

        $afterPrefix = substr($key, strlen($expectedPrefix));
        if (!str_starts_with($afterPrefix, 'live_') && !str_starts_with($afterPrefix, 'test_')) {
            throw new TaypiException(
                "Formato de {$paramName} inválido. Después de \"{$expectedPrefix}\" debe seguir \"live_\" o \"test_\".",
                'INVALID_KEY_FORMAT'
            );
        }

        $fullPrefix = $expectedPrefix . (str_starts_with($afterPrefix, 'live_') ? 'live_' : 'test_');
        $token = substr($key, strlen($fullPrefix));
        if (strlen($token) !== $expectedTokenLength) {
            throw new TaypiException(
                "Longitud de {$paramName} inválida. Se esperan {$expectedTokenLength} caracteres después de \"{$fullPrefix}\", se recibieron " . strlen($token) . '.',
                'INVALID_KEY_FORMAT'
            );
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
