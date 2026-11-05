<?php

namespace Wexample\SymfonyApi\Api\Controller\Traits;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;

/**
 * `DELETE delete/{identifier}`: the entity removed, if the caller may delete
 * it and nothing keeps it — what the application says
 * (getApiEntityRemovalRefusal()), or the database, a row still referencing
 * it: both a conflict, the request sound but the entity's state against it.
 */
trait EntityDeleteApiControllerTrait
{
    use EntityApiControllerTrait;

    final public const string MESSAGE_ENTITY_REFERENCED = 'Still referenced: remove what uses it first.';

    #[Route(
        path: AbstractController::DEFAULT_ROUTE_NAME_DELETE . '/{identifier}',
        name: AbstractController::DEFAULT_ROUTE_NAME_DELETE,
        methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_DELETE
    )]
    public function delete(
        string $identifier,
        EntityManagerInterface $entityManager
    ): ApiResponse {
        $entity = $this->getApiEntity($identifier, AbstractEntityVoter::DELETE);

        if ($refusal = $this->getApiEntityRemovalRefusal($entity)) {
            return self::apiResponseError(message: $refusal, code: Response::HTTP_CONFLICT);
        }

        try {
            $this->removeApiEntity($entity, $entityManager);
        } catch (ForeignKeyConstraintViolationException) {
            return self::apiResponseError(message: self::MESSAGE_ENTITY_REFERENCED, code: Response::HTTP_CONFLICT);
        }

        return self::apiResponseSuccess();
    }

    /**
     * Why the entity may not be removed, or null when it may.
     */
    protected function getApiEntityRemovalRefusal(object $entity): ?string
    {
        return null;
    }

    protected function removeApiEntity(
        object $entity,
        EntityManagerInterface $entityManager
    ): void {
        $entityManager->remove($entity);
        $entityManager->flush();
    }
}
