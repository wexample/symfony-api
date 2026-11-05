<?php

namespace Wexample\SymfonyApi\Interface;

/**
 * A repository reading an entity from what a url names it by, when that is
 * not its id alone — a slug, a former identifier still in clients' hands.
 * The entity actions read through it.
 */
interface IdentifierRepositoryInterface
{
    public function findOneByIdentifier(string $identifier): ?object;
}
