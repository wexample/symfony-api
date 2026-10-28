<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The json the front reads says what the mapping alone knows of an entity:
 * its place in a hierarchy, and the discriminator served with every row.
 */
class ExportEntitiesCommandTest extends KernelTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->dir = sys_get_temp_dir() . '/api-export-' . uniqid();
        mkdir($this->dir . '/source', 0777, true);

        foreach (['vehicle' => '', 'car_vehicle' => "    extends: Vehicle\n", 'device' => ''] as $name => $extends) {
            file_put_contents($this->dir . '/source/' . $name . '.yml', "items:\n  - name: X\n    type: class\n" . $extends . "    properties:\n      - name: id\n        type: string\n");
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
        parent::tearDown();
    }

    public function testAnEntityOfAHierarchyCarriesItsDiscriminator(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('api:export:entities'));
        $tester->execute(['--source' => $this->dir . '/source', '--output' => $this->dir . '/output']);
        $tester->assertCommandIsSuccessful();

        $car = $this->exported('car_vehicle');
        $this->assertSame('Vehicle', $car['extends']);
        $this->assertSame(['property' => 'kind', 'value' => 'car'], $car['discriminator']);
        // Served with every row, so listed among what the browser may read.
        $this->assertSame(['id', 'kind'], array_column($car['properties'], 'name'));
        $this->assertTrue($car['properties'][1]['readOnly']);

        $this->assertSame(['property' => 'kind', 'value' => 'vehicle'], $this->exported('vehicle')['discriminator']);

        // Outside a hierarchy, nothing is added.
        $device = $this->exported('device');
        $this->assertArrayNotHasKey('discriminator', $device);
        $this->assertSame(['id'], array_column($device['properties'], 'name'));
    }

    /**
     * @return array<string, mixed>
     */
    private function exported(string $name): array
    {
        return json_decode(file_get_contents($this->dir . '/output/' . $name . '.json'), true);
    }
}
