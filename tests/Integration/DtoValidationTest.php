<?php

declare(strict_types=1);

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyApi\Exception\ExtraPropertyException;
use Wexample\SymfonyApi\Exception\FieldValidationException;
use Wexample\SymfonyApi\Exception\MissingRequiredPropertyException;
use Wexample\SymfonyApi\Service\DtoValidationService;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\Validation\AuthorDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\Validation\BookCollectionDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\Validation\BookDto;

/**
 * A DTO read from an array as a request body gives it: its shape first —
 * what is required, what is unknown, down nested DTOs and collection items —,
 * then the constraints of what was built.
 */
class DtoValidationTest extends KernelTestCase
{
    private DtoValidationService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(DtoValidationService::class);
    }

    public function testAValidPayloadBuildsTheDto(): void
    {
        $dto = $this->service->createDto(['title' => 'Dune', 'author' => ['name' => 'Herbert']], BookDto::class);

        $this->assertInstanceOf(BookDto::class, $dto);
        $this->assertSame('Dune', $dto->title);
        $this->assertInstanceOf(AuthorDto::class, $dto->author);
        $this->assertSame('Herbert', $dto->author->name);
    }

    public function testAMissingRequiredPropertyIsRefused(): void
    {
        $exception = $this->catch(fn () => $this->service->createDto(['note' => 'x'], BookDto::class));

        $this->assertInstanceOf(MissingRequiredPropertyException::class, $exception);
        $this->assertSame('title', $exception->getPropertyName());
    }

    public function testAnUnknownPropertyIsRefused(): void
    {
        $exception = $this->catch(fn () => $this->service->createDto(['title' => 'Dune', 'isbn' => '1'], BookDto::class));

        $this->assertInstanceOf(ExtraPropertyException::class, $exception);
        $this->assertSame(['isbn'], $exception->getExtraProperties());
    }

    public function testANestedDtoIsValidatedToo(): void
    {
        $exception = $this->catch(fn () => $this->service->createDto(['title' => 'Dune', 'author' => []], BookDto::class));

        $this->assertInstanceOf(MissingRequiredPropertyException::class, $exception);
        $this->assertSame('name', $exception->getPropertyName());
    }

    public function testTheConstraintsOfTheBuiltDtoApply(): void
    {
        $exception = $this->catch(fn () => $this->service->createDto(['title' => str_repeat('a', 21)], BookDto::class));

        $this->assertInstanceOf(FieldValidationException::class, $exception);
    }

    public function testAnUntypedPropertyTakesAnythingNullAmongIt(): void
    {
        $this->assertSame('a note', $this->service->createDto(['title' => 'Dune', 'note' => 'a note'], BookDto::class)->note);
        $this->assertSame(['any' => 'thing'], $this->service->createDto(['title' => 'Dune', 'note' => ['any' => 'thing']], BookDto::class)->note);
        $this->assertNull($this->service->createDto(['title' => 'Dune', 'note' => null], BookDto::class)->note);
    }

    public function testACollectionBuildsEachOfItsItems(): void
    {
        $dto = $this->service->createDto(
            ['items' => [['title' => 'Dune'], ['title' => 'Emma', 'author' => ['name' => 'Austen']]]],
            BookCollectionDto::class
        );

        $this->assertInstanceOf(BookCollectionDto::class, $dto);
        $items = $dto->getCollectionItems();
        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(BookDto::class, $items);
        $this->assertSame(['Dune', 'Emma'], array_map(static fn (BookDto $book) => $book->title, $items));
        $this->assertSame('Austen', $items[1]->author->name);
    }

    public function testAnInvalidItemRefusesTheCollection(): void
    {
        $missing = $this->catch(fn () => $this->service->createDto(['items' => [['title' => 'Dune'], ['note' => 'x']]], BookCollectionDto::class));
        $this->assertInstanceOf(MissingRequiredPropertyException::class, $missing);
        $this->assertSame('title', $missing->getPropertyName());

        $extra = $this->catch(fn () => $this->service->createDto(['items' => [['title' => 'Dune', 'isbn' => '1']]], BookCollectionDto::class));
        $this->assertInstanceOf(ExtraPropertyException::class, $extra);

        $constrained = $this->catch(fn () => $this->service->createDto(['items' => [['title' => str_repeat('a', 21)]]], BookCollectionDto::class));
        $this->assertInstanceOf(FieldValidationException::class, $constrained);
    }

    private function catch(callable $action): ?\Throwable
    {
        try {
            $action();
        } catch (\Throwable $exception) {
            return $exception;
        }

        return null;
    }
}
