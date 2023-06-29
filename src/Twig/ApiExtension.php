<?php

namespace Wexample\SymfonyApi\Twig;

use Twig\TwigFunction;
use Wexample\SymfonyApi\Service\ApiService;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;

class ApiExtension extends AbstractExtension
{
    public function __construct(
        readonly private ApiService $apiService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'api_build_example_url',
                [
                    $this,
                    'apiBuildExampleUrl',
                ]
            ),
        ];
    }

    public function apiBuildExampleUrl(string $routeName): string
    {
        return $this->apiService->buildExampleUrl($routeName);
    }
}
