## The entity actions

Four traits give a controller the routine actions on one entity, each behind its voter (`VIEW`, `EDIT`, `DELETE` of `AbstractEntityVoter`):

| Trait | Route | Answer |
|---|---|---|
| `EntityListApiControllerTrait` | `GET list` (`?page`, `?length`) | a page of the entities, counted on the same query |
| `EntityShowApiControllerTrait` | `GET show/{identifier}` | the entity |
| `EntityUpdateApiControllerTrait` | `PATCH update/{identifier}`, body `DTO_UPDATE` | the entity as changed |
| `EntityDeleteApiControllerTrait` | `DELETE delete/{identifier}` | success, or a `409` |

```php
#[Route(path: '/api/2026-1/source/', name: 'source_')]
class SourceController extends AbstractApiController
{
    use EntityListApiControllerTrait;
    use EntityShowApiControllerTrait;
    use EntityUpdateApiControllerTrait;

    final public const string DTO_UPDATE = UpdateSourceDto::class;

    public function __construct(
        private readonly SourceRepository $repository,
        private readonly SourceNormalizer $normalizer,
        private readonly MembershipScopeService $membershipScope,
    ) {
    }

    protected function getApiEntityRepository(): EntityRepository
    {
        return $this->repository;
    }

    protected function getApiEntityNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    protected function createApiEntityListQueryBuilder(): QueryBuilder
    {
        return $this->membershipScope->restrictToCurrentMember(
            $this->repository->createQueryBuilder('source'),
            'source.project'
        );
    }
}
```

- **Not viewable is not found.** An entity the caller may not view answers `404`, as one that does not exist — a `403` would tell an outsider it does. One it views but may not change or delete answers `403`, `Access Denied.` alone.
- **The query scopes the list, the voter guards it.** `createApiEntityListQueryBuilder()` reads every row by default; a list whose rows are not all public restricts it. A page holding a row the caller may not view is refused whole (`403`, the reason in the logs) rather than thinned: a thinned page would keep a total and pages counting what it hides.
- **Identifiers**: `findApiEntity()` reads by id; one the database cannot read (not a uuid) is a `404`. A repository addressing entities otherwise — a former secure id, a slug — implements `IdentifierRepositoryInterface`, read instead. Overriding `findApiEntity()` in a trait of the application's would collide with each action trait's copy.
- **Update** goes through the repository's `updateByDto<Entity>()` (`ApiDtoRepositoryTrait`) and saves; `updateApiEntity()` is the place to do it otherwise. The body is validated by `#[ValidateRequestContent]` as on any route.
- **Delete** asks `getApiEntityRemovalRefusal()` first — a reason answers `409` with it —, then removes; a row still referencing the entity is a `409` too, never a `500`.
- **Normalization**: `normalizeApiEntity()` / `normalizeApiEntities()`, by the normalizer given; override the second to read a page's relations together.
- A list sorted by `?sort=` redeclares the route with its `#[SortQueryOption]` and returns `$this->listApiEntities($request)` (`usage/list-endpoints`).

Under `/_api/` for what the pages read on the session, `/api/` for programs with their own credentials (`usage/route-prefixes`).
