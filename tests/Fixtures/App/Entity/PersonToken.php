<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Entity\AbstractUserToken;

#[ORM\Entity]
class PersonToken extends AbstractUserToken
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Person $person;

    public function getClient(): Person
    {
        return $this->person;
    }

    public function setClient(UserInterface $client): static
    {
        $this->person = $client;

        return $this;
    }
}
