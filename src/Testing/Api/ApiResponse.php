<?php

namespace mindtwo\TwoTility\Testing\Api;

/**
 * Represents an API response with data and status code.
 */
class ApiResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly int $status = 200,
        public readonly array $headers = []
    ) {}

    /**
     * Create a successful response (200).
     */
    public static function ok(mixed $data, array $headers = []): self
    {
        return new self($data, 200, $headers);
    }

    /**
     * Create a created response (201).
     */
    public static function created(mixed $data, array $headers = []): self
    {
        return new self($data, 201, $headers);
    }

    /**
     * Create a not found response (404).
     */
    public static function notFound(mixed $data = ['error' => 'Not found'], array $headers = []): self
    {
        return new self($data, 404, $headers);
    }

    /**
     * Create a bad request response (400).
     */
    public static function badRequest(mixed $data = ['error' => 'Bad request'], array $headers = []): self
    {
        return new self($data, 400, $headers);
    }

    /**
     * Create an unauthorized response (401).
     */
    public static function unauthorized(mixed $data = ['error' => 'Unauthorized'], array $headers = []): self
    {
        return new self($data, 401, $headers);
    }

    /**
     * Create a forbidden response (403).
     */
    public static function forbidden(mixed $data = ['error' => 'Forbidden'], array $headers = []): self
    {
        return new self($data, 403, $headers);
    }

    /**
     * Create an unprocessable entity response (422).
     */
    public static function unprocessableEntity(mixed $data = ['error' => 'Unprocessable entity'], array $headers = []): self
    {
        return new self($data, 422, $headers);
    }

    /**
     * Create an internal server error response (500).
     */
    public static function serverError(mixed $data = ['error' => 'Internal server error'], array $headers = []): self
    {
        return new self($data, 500, $headers);
    }

    /**
     * Create a custom response with specific status code.
     */
    public static function withStatus(mixed $data, int $status, array $headers = []): self
    {
        return new self($data, $status, $headers);
    }

    /**
     * Get the response data.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the status code.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Get the headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if this is a successful response (2xx status codes).
     */
    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Check if this is a client error response (4xx status codes).
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Check if this is a server error response (5xx status codes).
     */
    public function isServerError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }
}