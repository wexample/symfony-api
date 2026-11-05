<?php

namespace Wexample\SymfonyApi\Api\Controller\Traits;

use LogicException;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyApi\Repository\Traits\ApiDtoRepositoryTrait;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;

/**
 * `PATCH update/{identifier}`: the entity changed by the controller's
 * `DTO_UPDATE`, if the caller may edit it; answers it as it now is.
 */
trait EntityUpdateApiControllerTrait
{
    use EntityApiControllerTrait;

    #[Route(
        path: AbstractController::DEFAULT_ROUTE_NAME_UPDATE . '/{identifier}',
        name: AbstractController::DEFAULT_ROUTE_NAME_UPDATE,
        methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_PATCH
    )]
    #[ValidateRequestContent(dto: self::DTO_UPDATE, attributeName: 'entityDto')]
    public function update(
        string $identifier,
        AbstractDto $entityDto
    ): ApiResponse {
        $entity = $this->getApiEntity($identifier, AbstractEntityVoter::EDIT);

        return self::apiResponseSuccess(
            data: $this->normalizeApiEntity($this->updateApiEntity($entity, $entityDto))
        );
    }

    /**
     * By the repository's `updateByDto<Entity>()` (ApiDtoRepositoryTrait),
     * saved.
     */
    protected function updateApiEntity(
        object $entity,
        AbstractDto $dto
    ): object {
        $repository = $this->getApiEntityRepository();

        if (! method_exists($repository, 'updateByDtoAndClassType')) {
            throw new LogicException(sprintf('%s updates through its repository: give %s the %s, or override updateApiEntity().', static::class, $repository::class, ApiDtoRepositoryTrait::class));
        }

        return $repository->updateByDtoAndClassType($entity, $dto);
    }
}
