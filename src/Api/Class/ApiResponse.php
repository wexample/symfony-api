<?php

namespace Wexample\SymfonyApi\Api\Class;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse
{
    public function __construct(
        private readonly array|object $data,
        private readonly int $status = 200,
        private ?bool $prettyPrint = null
    ) {
    }

    public function toJsonResponse(): JsonResponse
    {
        $response = new JsonResponse(
            $this->getData(),
            $this->getStatus()
        );

        if ($this->getPrettyPrint()) {
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_PRETTY_PRINT
            );
        }

        return $response;
    }

    public function getData(): array|object
    {
        return $this->data;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getPrettyPrint(): ?bool
    {
        return $this->prettyPrint;
    }

    public function setPrettyPrint(bool $value): void
    {
        $this->prettyPrint = $value;
    }
}