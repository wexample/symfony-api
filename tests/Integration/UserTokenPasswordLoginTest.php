<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Exception\UserTokenLoginException;
use Wexample\SymfonyApi\Service\UserTokenPasswordLoginService;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;
use Wexample\SymfonyApi\Tests\Traits\UserTokenTestTrait;

/**
 * A user token for an identifier and a password: the fixture allows three
 * failures an hour per identifier and address, and gives the token 30 days.
 */
class UserTokenPasswordLoginTest extends WebTestCase
{
    use MachineTokenTestTrait;
    use UserTokenTestTrait;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->createPerson('ada@example.com')->setPassword('secret');
        $this->createPerson('off@example.com')->setPassword('secret')->setEnabled(false);
        $this->entityManager->flush();
    }

    public function testTheTokenSignsInAndLivesItsLifetime(): void
    {
        [$secret, $user] = $this->getLogin()->login(' ada@example.com ', 'secret', '10.0.0.1');

        $this->assertSame('ada@example.com', $user->getUserIdentifier());
        $this->assertStringStartsWith('fx_usr_', $secret);
        $this->requestScript('GET', 'whoami', $secret);
        $this->assertResponseIsSuccessful();

        $token = $this->getUserTokenService()->findTokenBySecret($secret);
        $this->assertSame('password login', $token->getLabel());
        $this->assertEqualsWithDelta((new DateTimeImmutable('+30 days'))->getTimestamp(), $token->getDateExpiration()->getTimestamp(), 5);
    }

    public function testUnknownWrongAndDisabledAnswerAlike(): void
    {
        $reasons = [];
        foreach ([['nobody@example.com', 'secret'], ['ada@example.com', 'wrong'], ['off@example.com', 'secret']] as [$identifier, $password]) {
            try {
                $this->getLogin()->login($identifier, $password, '10.0.0.' . count($reasons));
                $this->fail($identifier . ' signed in.');
            } catch (UserTokenLoginException $exception) {
                $reasons[] = [$exception->reason, $exception->getMessage()];
            }
        }

        $this->assertSame(array_fill(0, 3, [UserTokenLoginException::REASON_INVALID, 'Invalid credentials.']), $reasons);
    }

    public function testFailuresAreThrottledPerIdentifierAndAddress(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertLoginRefused('ada@example.com', 'wrong', '10.0.0.1', UserTokenLoginException::REASON_INVALID);
        }

        // Even the right password, from there.
        $this->assertLoginRefused('ada@example.com', 'secret', '10.0.0.1', UserTokenLoginException::REASON_THROTTLED);

        // The same account from elsewhere still signs in.
        [$secret] = $this->getLogin()->login('ada@example.com', 'secret', '10.0.0.2');
        $this->assertNotEmpty($secret);
    }

    public function testASuccessForgetsTheFailures(): void
    {
        $this->assertLoginRefused('ada@example.com', 'wrong', '10.0.0.1', UserTokenLoginException::REASON_INVALID);
        $this->assertLoginRefused('ada@example.com', 'wrong', '10.0.0.1', UserTokenLoginException::REASON_INVALID);
        $this->getLogin()->login('ada@example.com', 'secret', '10.0.0.1');

        $this->assertLoginRefused('ada@example.com', 'wrong', '10.0.0.1', UserTokenLoginException::REASON_INVALID);
        $this->assertLoginRefused('ada@example.com', 'wrong', '10.0.0.1', UserTokenLoginException::REASON_INVALID);
        [$secret] = $this->getLogin()->login('ada@example.com', 'secret', '10.0.0.1');
        $this->assertNotEmpty($secret);
    }

    private function assertLoginRefused(string $identifier, string $password, string $ip, string $reason): void
    {
        try {
            $this->getLogin()->login($identifier, $password, $ip);
            $this->fail('Signed in.');
        } catch (UserTokenLoginException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }

    private function getLogin(): UserTokenPasswordLoginService
    {
        return self::getContainer()->get(UserTokenPasswordLoginService::class);
    }
}
