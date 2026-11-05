<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;

/**
 * A device views its readings; changes them unless sealed, deletes them
 * unless kept by their code.
 */
class ReadingVoter extends Voter
{
    final public const string CODE_SEALED = 'sealed';

    protected function supports(
        string $attribute,
        mixed $subject
    ): bool {
        return $subject instanceof Reading
            && in_array($attribute, [AbstractEntityVoter::VIEW, AbstractEntityVoter::EDIT, AbstractEntityVoter::DELETE], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        if ($subject->device->getUserIdentifier() !== $token->getUserIdentifier()) {
            return false;
        }

        return AbstractEntityVoter::VIEW === $attribute || self::CODE_SEALED !== $subject->code;
    }
}
