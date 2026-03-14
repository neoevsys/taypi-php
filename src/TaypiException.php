<?php

namespace Taypi;

class TaypiException extends \Exception
{
    /** @var string Código de error TAYPI (ej: AMOUNT_TOO_LOW, RATE_LIMIT_EXCEEDED) */
    public string $errorCode;

    /** @var int HTTP status code (0 si es error de conexión) */
    public int $httpCode;

    /** @var array|null Respuesta completa del API */
    public ?array $response;

    public function __construct(
        string $message,
        string $errorCode = 'UNKNOWN',
        int $httpCode = 0,
        ?int $curlErrno = null,
        ?array $response = null
    ) {
        parent::__construct($message, $curlErrno ?? $httpCode);

        $this->errorCode = $errorCode;
        $this->httpCode = $httpCode;
        $this->response = $response;
    }
}
