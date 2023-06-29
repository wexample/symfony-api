<?php

namespace Wexample\SymfonyApi\Tests\Traits\TestCase\Application;

use Symfony\Component\HttpFoundation\Response;

trait ApiTestCaseTrait
{
    public function apiParseResponse(Response $response): object
    {
        try {
            return (object) json_decode(
                $response->getContent(),
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\Exception $e) {
            $this->logBodyExtract();
            $this->error('Unable to parse response JSON content : '.$e->getMessage());

            return (object) [];
        }
    }
}
