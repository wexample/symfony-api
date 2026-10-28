<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Bike;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\CarVehicle;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Vehicle;
use Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer\CarVehicleNormalizer;
use Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer\VehicleNormalizer;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;
use Wexample\SymfonyHelpers\Helper\EntityHierarchyHelper;
use Wexample\SymfonyHelpers\Normalizer\EntityNormalizerRegistry;

/**
 * A hierarchy of entities has one name, its root's: a subclass is one more
 * kind of the same entity to the api, which says which kind in the entity
 * itself, under the name the root gave the discriminator column.
 */
class EntityInheritanceTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private string $token;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->token = $this->getMachineTokenService()->issue($this->createDevice());

        $car = new CarVehicle();
        $car->name = 'sedan';
        $car->seats = 5;
        $bike = new Bike();
        $bike->name = 'gravel';
        $bike->gears = 11;
        $this->entityManager->persist($car);
        $this->entityManager->persist($bike);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testASubclassIsNamedAfterItsRoot(): void
    {
        $this->assertSame(Vehicle::class, EntityHierarchyHelper::getRootClass(CarVehicle::class));
        $this->assertSame(Vehicle::class, EntityHierarchyHelper::getRootClass(Bike::class));
        $this->assertSame(Vehicle::class, EntityHierarchyHelper::getRootClass(Vehicle::class));
        $this->assertSame('vehicle', CarVehicle::getEntityKeyName());
        $this->assertSame('vehicle', Bike::getTableizedName());
        $this->assertSame('vehicle', Bike::getCamelName());

        // Outside a hierarchy, a class is its own root and has no discriminator.
        $this->assertNull(EntityHierarchyHelper::getDiscriminatorName(\Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device::class));
    }

    public function testTheDiscriminatorIsTheWordLeftAfterTheRoot(): void
    {
        $this->assertSame('kind', EntityHierarchyHelper::getDiscriminatorName(Bike::class));
        $this->assertSame('car', EntityHierarchyHelper::getDiscriminatorValue(CarVehicle::class));
        $this->assertSame('bike', EntityHierarchyHelper::getDiscriminatorValue(Bike::class));
        $this->assertSame('vehicle', EntityHierarchyHelper::getDiscriminatorValue(Vehicle::class));

        // The mapping stores the same words.
        $metadata = $this->entityManager->getClassMetadata(Vehicle::class);
        $this->assertSame(['vehicle' => Vehicle::class, 'car' => CarVehicle::class, 'bike' => Bike::class], $metadata->discriminatorMap);
        $this->assertSame('car', $this->entityManager->getClassMetadata(CarVehicle::class)->discriminatorValue);
    }

    public function testTheNearestNormalizerAnswers(): void
    {
        $registry = self::getContainer()->get(EntityNormalizerRegistry::class);

        $this->assertInstanceOf(CarVehicleNormalizer::class, $registry->findFor(CarVehicle::class));
        // No normalizer of its own: the root's.
        $this->assertInstanceOf(VehicleNormalizer::class, $registry->findFor(Bike::class));
        $this->assertNotInstanceOf(CarVehicleNormalizer::class, $registry->findFor(Bike::class));

        // The serializer agrees, whichever class it is handed.
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $car = $this->entityManager->getRepository(Vehicle::class)->findOneBy(['name' => 'sedan']);
        $this->assertSame(5, $serializer->normalize($car)['entity']['seats']);
    }

    public function testAListOfTheRootSaysWhatEachRowIs(): void
    {
        $this->client->request('GET', '/api/device/vehicles', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
        $items = json_decode($this->client->getResponse()->getContent(), true)['data']['items'];

        $this->assertSame(['vehicle', 'vehicle'], array_column($items, 'type'));

        [$bike, $car] = $items;
        $this->assertSame(['id', 'name', 'kind'], array_keys($bike['entity']));
        $this->assertSame('bike', $bike['entity']['kind']);
        // Normalized through the root's normalizer, the car still went to its own.
        $this->assertSame(['id', 'name', 'seats', 'kind'], array_keys($car['entity']));
        $this->assertSame('car', $car['entity']['kind']);
        $this->assertSame(5, $car['entity']['seats']);
    }
}
