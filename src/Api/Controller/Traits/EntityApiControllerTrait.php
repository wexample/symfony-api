<?php

namespace Wexample\SymfonyApi\Api\Controller\Traits;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyHelpers\Helper\DataHelper;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;

/**
 * What the entity actions share: the entity a route names, read as the
 * voters allow, and its normalization. An action never answers without its
 * voter: one the caller may not view is not found — a refusal would tell an
 * outsider it exists —, one it views but may not change is refused.
 */
trait EntityApiControllerTrait
{
    abstract protected function getApiEntityRepository(): EntityRepository;

    abstract protected function getApiEntityNormalizer(): NormalizerInterface;

    /**
     * The entity a url names, by its id; an application addressing entities
     * otherwise (a slug, a former identifier) reads them here.
     */
    protected function findApiEntity(string $identifier): ?object
    {
        return $this->getApiEntityRepository()->find($identifier);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws AccessDeniedException
     */
    protected function getApiEntity(
        string $identifier,
        string $attribute = AbstractEntityVoter::VIEW
    ): object {
        $entity = $this->findApiEntity($identifier);

        if (! $entity || ! $this->isGranted(AbstractEntityVoter::VIEW, $entity)) {
            throw $this->createNotFoundException();
        }

        if (AbstractEntityVoter::VIEW !== $attribute) {
            $this->denyAccessUnlessGranted($attribute, $entity);
        }

        return $entity;
    }

    protected function normalizeApiEntity(object $entity): mixed
    {
        return $this->getApiEntityNormalizer()->normalize($entity, DataHelper::FORMAT_JSON);
    }

    /**
     * @param list<object> $entities
     */
    protected function normalizeApiEntities(array $entities): array
    {
        return array_map($this->normalizeApiEntity(...), $entities);
    }
}
