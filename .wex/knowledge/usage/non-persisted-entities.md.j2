## Every list is a list of entities

What an API endpoint lists is an entity — including what is computed on demand and never stored: the files of an import, a documentation tree, the demos of a design system. There is no second road for "plain DTO lists".

The reason is the chain the stack builds for entities, end to end: entity → normalizer → output DTO → pseudocode → generated TypeScript class and repository → `fetchListPaginated` → the front entity collections and tables. A list that is not an entity leaves that chain and has to rebuild each link by hand — its own JSON shape, its own TypeScript types, its own Vue collection. An entity that is not stored keeps every link.

## An entity with no table

```php
namespace App\Entity;

/**
 * One file of an import, read from the disk and the runs: never stored.
 */
#[PseudocodeExport]
class ImportFile extends AbstractEntity
{
    private const string ID_NAMESPACE = '…';   // a UUID of its own, fixed

    public function __construct(
        public readonly string $path,
        public readonly ImportFileStatus $status,
        public readonly ?DateTimeImmutable $dateModified = null,
    ) {
        $this->id = Uuid::v5(Uuid::fromString(self::ID_NAMESPACE), $path);
    }
}
```

- **It lives in `Entity/` like the others**, and carries no `#[ORM\Entity]`: Doctrine treats it as transient and creates no table for it. `symfony-pseudocode` scans `Entity/` without asking Doctrine, and expects a package to hold entities with no repository.
- **Its id derives from what identifies it** — `Uuid::v5` of a fixed namespace and the natural key (a path, a name). A random id would change at every request, and the front collection, which reconciles rows by id, would see every row replaced at every refresh.
- **It has a normalizer** extending `AbstractEntityNormalizer`, and an output DTO, as a stored entity does. Nothing in them needs Doctrine.
- **No Doctrine repository.** What builds the list — a service reading the disk, a projection of runs — is the application's code; the TypeScript repository generated for the front only needs the endpoint.
- `#[ApiEntity]` is for stored entities, whose default controller reads a repository; write the list controller of a non-persisted entity yourself.

## The list endpoint

```php
#[Route(path: 'files', name: 'files', methods: ['GET'])]
#[PageQueryOption]
#[LengthQueryOption]
#[SortQueryOption(allowed: ['path', 'status', 'dateModified'], default: 'path')]
#[ApiResponseData(ImportFileDto::class, paginated: true)]
public function files(Request $request, ImportFileProvider $provider, ImportFileNormalizer $normalizer): ApiResponse
{
    [$pagination, $page] = self::applyQueryOptionsToList($request, $provider->findAll());

    return self::apiResponsePaginated($pagination, $normalizer->normalizeCollection($page));
}
```

`applyQueryOptionsToList()` does for a list held in memory what `applyQueryOptionSort()` and `getQueryOptionPagination()` do for a query: the same `sort`, `page` and `length`, the total known, a negative page counted from the end. Sorting reads each allowed name on the items as a property path — getter, public property, `a.b` for nested values, array keys for arrays — and compares enums by their value; nulls come first ascending, last descending; equal items keep their order, then the `id` tie-breaker. Normalize the page only, after slicing.

The response is the one of any entity list: `data.items` of `{type, entity, metadata, relationships}`, `data.pagination`. The front reads it with the generated repository and the entity collection components, unchanged.

## When the list is a tree

A tree is still entities: each node an entity with its parent's id, listed flat, or one root entity whose children are its relationships. The front builds the tree from the ids, as it does for stored hierarchies.
