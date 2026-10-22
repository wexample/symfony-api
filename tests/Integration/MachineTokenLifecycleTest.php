<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use DateInterval;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * Issuing, rotating and revoking tokens, from the service and the console.
 */
class MachineTokenLifecycleTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private const string SECRET_PATTERN = '/fx_dev_[0-9A-Za-z]{43}/';

    private Device $device;

    private Device $otherDevice;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->device = $this->createDevice();
        $this->otherDevice = $this->createDevice();
    }

    public function testIssuedSecretAuthenticatesAndIsNotStored(): void
    {
        $plain = $this->getMachineTokenService()->issue($this->device, label: 'commissioning');

        $this->assertMatchesRegularExpression(self::SECRET_PATTERN, $plain);
        $this->requestWhoami($plain);
        $this->assertResponseIsSuccessful();

        [$token] = $this->getMachineTokenService()->findTokens($this->device);
        $this->assertSame('commissioning', $token->getLabel());
        $this->assertSame(MachineTokenHelper::hashToken($plain), $token->getTokenHash());

        $dump = var_export($this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM device_token'), true);
        $this->assertStringNotContainsString($plain, $dump);
    }

    public function testRevokedTokenIsRefusedOnTheNextRequest(): void
    {
        $plain = $this->getMachineTokenService()->issue($this->device);
        $kept = $this->getMachineTokenService()->issue($this->device);
        $this->requestWhoami($plain);
        $this->assertResponseIsSuccessful();

        $this->getMachineTokenService()->revoke(
            $this->getMachineTokenService()->findTokenBySecret($plain)
        );

        $this->requestWhoami($plain);
        $this->assertSame(401, $this->getStatusCode());
        $this->requestWhoami($kept);
        $this->assertResponseIsSuccessful();
    }

    public function testRevokeAllLeavesOtherClientsAlone(): void
    {
        $first = $this->getMachineTokenService()->issue($this->device);
        $second = $this->getMachineTokenService()->issue($this->device);
        $other = $this->getMachineTokenService()->issue($this->otherDevice);

        $this->assertSame(2, $this->getMachineTokenService()->revokeAll($this->device));

        foreach ([$first, $second] as $plain) {
            $this->requestWhoami($plain);
            $this->assertSame(401, $this->getStatusCode());
        }

        $this->requestWhoami($other);
        $this->assertResponseIsSuccessful();
    }

    public function testRotationKeepsBothTokensDuringTheOverlapOnly(): void
    {
        $old = $this->getMachineTokenService()->issue($this->device);
        $other = $this->getMachineTokenService()->issue($this->otherDevice);

        $new = $this->getMachineTokenService()->rotate($this->device, new DateInterval('P1D'));

        foreach ([$old, $new] as $plain) {
            $this->requestWhoami($plain);
            $this->assertResponseIsSuccessful();
        }

        $expiration = $this->getMachineTokenService()->findTokenBySecret($old)->getDateExpiration();
        $this->assertEqualsWithDelta((new DateTimeImmutable('+1 day'))->getTimestamp(), $expiration->getTimestamp(), 5);

        // No overlap: every previous token stops at once. Each request
        // resets the entity manager: the device is read again.
        $device = $this->entityManager->find(Device::class, $this->device->getId());
        $newest = $this->getMachineTokenService()->rotate($device, new DateInterval('PT0S'));

        foreach ([$old, $new] as $plain) {
            $this->requestWhoami($plain);
            $this->assertSame(401, $this->getStatusCode());
        }

        foreach ([$newest, $other] as $plain) {
            $this->requestWhoami($plain);
            $this->assertResponseIsSuccessful();
        }
    }

    public function testClientRotatesItsOwnToken(): void
    {
        $old = $this->getMachineTokenService()->issue($this->device);

        $this->client->request('POST', '/api/device/rotate', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $old]);
        $this->assertResponseIsSuccessful();
        $new = json_decode($this->client->getResponse()->getContent(), true)['data']['token'];

        $this->assertMatchesRegularExpression(self::SECRET_PATTERN, $new);
        foreach ([$old, $new] as $plain) {
            $this->requestWhoami($plain);
            $this->assertResponseIsSuccessful();
        }
    }

    public function testIssueCommandPrintsTheSecretOnce(): void
    {
        $tester = $this->runCommand('api:machine-token:issue', [
            'client' => (string) $this->device->getId(),
            '--label' => 'bench',
            '--expires' => '+1 year',
        ]);

        $tester->assertCommandIsSuccessful();
        preg_match_all(self::SECRET_PATTERN, $tester->getDisplay(), $matches);
        $this->assertCount(1, $matches[0]);

        $this->requestWhoami($matches[0][0]);
        $this->assertResponseIsSuccessful();
    }

    public function testListAndRevokeCommandsNeverPrintASecretOrAHash(): void
    {
        $plain = $this->getMachineTokenService()->issue($this->device, label: 'bench');
        $token = $this->getMachineTokenService()->findTokenBySecret($plain);

        $list = $this->runCommand('api:machine-token:list', ['client' => (string) $this->device->getId()]);
        $list->assertCommandIsSuccessful();
        $this->assertStringContainsString($token->getHint(), $list->getDisplay());
        $this->assertStringContainsString('bench', $list->getDisplay());

        // By hint, without its ellipsis, as an operator would type it.
        $revoke = $this->runCommand('api:machine-token:revoke', ['reference' => rtrim($token->getHint(), '…')]);
        $revoke->assertCommandIsSuccessful();

        $revokeAll = $this->runCommand('api:machine-token:revoke', [
            'reference' => (string) $this->device->getId(),
            '--all' => true,
        ]);
        $revokeAll->assertCommandIsSuccessful();

        foreach ([$list, $revoke, $revokeAll] as $tester) {
            $this->assertDoesNotMatchRegularExpression(self::SECRET_PATTERN, $tester->getDisplay());
            $this->assertStringNotContainsString($token->getTokenHash(), $tester->getDisplay());
        }

        $this->requestWhoami($plain);
        $this->assertSame(401, $this->getStatusCode());
    }

    private function runCommand(string $name, array $input): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }
}
