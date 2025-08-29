<?php

namespace App\Infrastructure;

// Create a result object to pass back to the frontend
final class Result
{
    public function __construct(
        public bool $ok,
        public mixed $data = null,
        public ?string $code = null,
        public ?string $message = null,
        public array $meta = []
    ) {}

    // For the OK state
    public static function ok(mixed $data = null, array $meta = []): self {
        return new self(true, $data, null, null, $meta);
    }

    //For the error state
    public static function err(string $code, string $message, array $meta = []): self {
        return new self(false, null, $code, $message, $meta);
    }
}