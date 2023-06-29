<?php

namespace Wexample\SymfonyApi\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyDesignSystem\Translation\Translator;
use Wexample\SymfonyHelpers\Helper\TextHelper;

readonly class ApiService
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private Translator $translator,
        private RouterInterface $symfonyRouter
    ) {
    }

    public function buildExampleUrl(string $routeName): string
    {
        // Get expected route parameters
        $route = $this->symfonyRouter->getRouteCollection()->get($routeName);
        $parameters = $route->compile()->getPathVariables();

        $routeParameters = [];

        foreach ($parameters as $parameter) {
            $snakeName = TextHelper::toSnake($parameter);
            $routeParameters[$parameter] = $this->translator->trans(
                id: 'example.'.$snakeName,
                domain: 'api.'.$routeName,
            );
        }

        return $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost().
            $this->router->generate($routeName, $routeParameters);
    }
}
