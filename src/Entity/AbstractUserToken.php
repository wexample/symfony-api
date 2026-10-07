<?php

namespace Wexample\SymfonyApi\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * One token of a person, for the scripts they run in their own name: it
 * authenticates as that user and opens what their roles open. The
 * application's subclass maps the relation to its user entity.
 */
abstract class AbstractUserToken extends AbstractApiToken
{
    abstract public function setClient(UserInterface $client): static;
}
