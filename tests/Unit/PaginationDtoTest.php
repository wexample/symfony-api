<?php

declare(strict_types=1);

namespace Wexample\SymfonyApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyApi\Api\Dto\PaginationDto;

/**
 * Pages counted from zero, a negative one counted back from the end; a null
 * length is no limit, a null total no count.
 */
class PaginationDtoTest extends TestCase
{
    public function testAPageIsCutAtItsOffset(): void
    {
        $pagination = new PaginationDto(page: 2, length: 10, total: 45);

        $this->assertSame(20, $pagination->getOffset());
        $this->assertSame(5, $pagination->getPagesCount());
        $this->assertTrue($pagination->hasMore());
        $this->assertSame(
            ['page' => 2, 'length' => 10, 'total' => 45, 'pagesCount' => 5, 'hasMore' => true],
            $pagination->toArray()
        );
    }

    public function testTheLastPageHasNoMore(): void
    {
        $this->assertFalse((new PaginationDto(page: 4, length: 10, total: 45))->hasMore());
        // Exactly full: the page ending on the total is the last one.
        $this->assertFalse((new PaginationDto(page: 1, length: 10, total: 20))->hasMore());
        $this->assertFalse((new PaginationDto(page: 0, length: 10, total: 0))->hasMore());
    }

    public function testANegativePageCountsBackFromTheEnd(): void
    {
        $this->assertSame(4, (new PaginationDto(page: -1, length: 10, total: 45))->page);
        $this->assertSame(3, (new PaginationDto(page: -2, length: 10, total: 45))->page);
        // Further back than the first page is the first page.
        $this->assertSame(0, (new PaginationDto(page: -9, length: 10, total: 45))->page);
        // Without a total, the end is unknown: the first page.
        $this->assertSame(0, (new PaginationDto(page: -1, length: 10))->page);
    }

    public function testNoLengthIsOnePageOfEverything(): void
    {
        $pagination = new PaginationDto(page: 3, length: null, total: 45);

        $this->assertSame(0, $pagination->getOffset());
        $this->assertSame(1, $pagination->getPagesCount());
        $this->assertFalse($pagination->hasMore());

        // A zero length reads as none.
        $this->assertSame(0, (new PaginationDto(page: 3, length: 0, total: 45))->getOffset());
        $this->assertSame(1, (new PaginationDto(page: 3, length: 0, total: 45))->getPagesCount());
    }

    public function testWithoutATotalHasMoreIsWhatWasProbed(): void
    {
        $pagination = new PaginationDto(page: 1, length: 10);

        $this->assertNull($pagination->getPagesCount());
        $this->assertNull($pagination->hasMore());
        $this->assertTrue($pagination->withProbedHasMore(true)->hasMore());
        $this->assertFalse($pagination->withProbedHasMore(false)->hasMore());
        $this->assertSame(1, $pagination->withProbedHasMore(true)->page);
    }

    public function testATotalGivenLaterCountsThePages(): void
    {
        $pagination = (new PaginationDto(page: 1, length: 10))->withTotal(25);

        $this->assertSame(3, $pagination->getPagesCount());
        $this->assertTrue($pagination->hasMore());
    }
}
