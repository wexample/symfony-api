<?php

namespace Wexample\SymfonyApi\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyTranslations\Translation\Translator;

class ApiService
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        // We should avoid this dependency.
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
                'example.'.$snakeName,
                domain: 'api.'.$routeName,
            );
        }

        $request = $this->requestStack->getCurrentRequest();

        try {
            $path = $this->router->generate($routeName, $routeParameters);
        } catch (InvalidParameterException) {
            // An example that does not fit the route — none was written, and
            // what stands in for it fails a uuid requirement — is not worth a
            // broken page: the path is shown as declared, its parameters left
            // for the reader to fill.
            $path = $request->getBaseUrl().$route->getPath();
        }

        return $request->getSchemeAndHttpHost().$path;
    }
}
