<?php

namespace Wexample\SymfonyApi\Api\Class;

interface ApiErrorDataInterface
{
    public function getErrorCode(): string;

    public function toArray(): array;
}
