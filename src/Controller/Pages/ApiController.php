<?php

namespace Wexample\SymfonyApi\Controller\Pages;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyApi\Interface\ApiDocumentationSectionInterface;
use Wexample\SymfonyApi\Service\ApiDocumentationService;
use Wexample\SymfonyApi\Traits\SymfonyApiBundleClassTrait;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;

/**
 * The api's page: its OpenAPI document drawn — the whole of it for whoever
 * holds `documentation.access_role`, the preview's paths for the others, and
 * refused to them when there is no preview.
 */
#[Route(path: 'api/', name: 'api_')]
final class ApiController extends AbstractPagesController
{
    use SymfonyApiBundleClassTrait;

    final public const ROUTE_INDEX = VariableHelper::INDEX;

    /**
     * The route of Swagger UI on the same document, when the application
     * imports it (`routes_swagger.yaml`).
     */
    final public const string ROUTE_SWAGGER = 'api_doc_swagger';

    /**
     * @param iterable<ApiDocumentationSectionInterface> $sections
     */
    #[Route(name: self::ROUTE_INDEX)]
    public function index(
        ApiDocumentationService $documentation,
        RouterInterface $router,
        #[AutowireIterator(ApiDocumentationSectionInterface::TAG)]
        iterable $sections,
    ): Response {
        $role = $documentation->getAccessRole();
        $preview = null !== $role && ! $this->isGranted($role);

        if ($preview && ! $documentation->hasPreview()) {
            $this->denyAccessUnlessGranted($role);
        }

        $document = $documentation->getDocument();
        $shownSections = [];

        foreach ($sections as $section) {
            if ($section->isShown($preview)) {
                $shownSections[] = ['template' => $section->getTemplate(), 'context' => $section->getContext($preview)];
            }
        }

        return $this->renderPage(
            self::ROUTE_INDEX,
            [
                'info' => $document['info'] ?? null,
                'groups' => null === $document ? [] : $documentation->getOperationGroups($document, $preview),
                'documented' => null !== $document,
                'preview' => $preview,
                'swaggerUrl' => ! $preview && null !== $router->getRouteCollection()->get(self::ROUTE_SWAGGER)
                    ? $router->generate(self::ROUTE_SWAGGER)
                    : null,
                'sections' => $shownSections,
            ]
        );
    }
}
