<?php

namespace Wexample\SymfonyApi\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\FilterTagQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\YearQueryOption;

class QueryOptionsTest extends TestCase
{
    public function testAYearCutsTheListOnItsField(): void
    {
        $queryBuilder = (new QueryBuilder($this->createStub(EntityManagerInterface::class)))
            ->select('invoice')
            ->from('App\Entity\Invoice', 'invoice');

        YearQueryOption::apply($queryBuilder, 2025, 'invoice.dateIssued');

        $this->assertStringContainsString('invoice.dateIssued >= :year_invoice_dateIssued_start AND invoice.dateIssued < :year_invoice_dateIssued_end', $queryBuilder->getDQL());
        $this->assertSame('2025-01-01', $queryBuilder->getParameter('year_invoice_dateIssued_start')->getValue()->format('Y-m-d'));
        $this->assertSame('2026-01-01', $queryBuilder->getParameter('year_invoice_dateIssued_end')->getValue()->format('Y-m-d'));
    }

    public function testOnlyTheDeclaredFilterTagsAreTaken(): void
    {
        $validator = Validation::createValidator();
        $option = new FilterTagQueryOption(tags: ['late', 'paid']);

        $this->assertCount(0, $validator->validate('late', $option->getConstraint()));
        $this->assertCount(1, $validator->validate('lost', $option->getConstraint()));
        $this->assertCount(0, $validator->validate('anything', (new FilterTagQueryOption())->getConstraint()));
    }
}
