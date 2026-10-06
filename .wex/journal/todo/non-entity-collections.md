# Collections that are not entities: a DTO list paged like an entity list, read by the same front collection

Opened: 2026-10-05
Updated: 2026-10-05
Author: agent:addon:ai/editor

## Constat

Une liste d'API n'est pas toujours une table : l'état de chaque document d'un import
(fichiers sur le disque + `ProcessItem` des runs), l'arbre de la documentation de HH, les
démos du design system. Elle se calcule à la demande, il n'y a rien à stocker, une entité
n'y apporterait rien.

Aujourd'hui ces listes sont servies par des contrôleurs « à la main » qui renvoient du
JSON maison, et lues par des composants Vue qui refont eux-mêmes ce que les collections
d'entités ont déjà :

- `home-habilis/doc-importer` (doc-manager) : `GET /api/app/{id}/import/files`
  (`ImportFilesController`, DTO `ImportFile`), lu par `import-files.vue` qui recode le
  chargement, le regroupement des relectures pendant un run et l'écoute live du process.
- `home-habilis/doc-explorer` : `GET /api/app/{id}/documentation/tree`, `document-tree.vue`.
- symfony-design-system-demo : `demo-live-table`, `demo-bar-list` contournent le mixin
  avec `getEntityClass() { return null }` et réécrivent `refreshEntitiesCollection`.

## Ce qui existe déjà ici

`AbstractApiController::apiResponsePaginated(PaginationDto, array $items)` et
`getQueryOptionPagination(Request, ?int $total)` ne dépendent d'aucune entité : l'enveloppe
`{ items, pagination }` qu'un repository js lit avec `fetchListPaginated` peut déjà être
rendue pour n'importe quel tableau.

## Ce qui manque

### Côté serveur (ce package)

1. **Paginer une liste tenue en mémoire** : un helper qui prend la liste complète, lit
   `page` / `length` (page négative comptée depuis la fin, `length: 0` = tout) et rend la
   tranche avec son `PaginationDto` (total connu). Aujourd'hui chaque contrôleur le referait.
2. **Normaliser un DTO** sans normalizer d'entité : convention simple (`toArray()` ou
   normalizer par classe), pour que `apiResponsePaginated` reçoive des DTO directement.
3. **Filtres et tri** sur une liste en mémoire, avec les mêmes options de requête que les
   listes d'entités (`filter[...]`, `sort`), au moins pour l'égalité sur un champ.
4. **Documentation / OpenAPI** : décrire un endpoint de DTO paginé comme les autres
   (`ApiRouteDescriber` pose déjà `ApiPagination`).

### Côté front (design system / js-api-entity — pas ce package, à relayer)

`AbstractEntityCollectionVueMixin` n'est couplé aux entités qu'à trois endroits :
`fetchEntitiesPage()` (`getEntityRepository().fetchListPaginated()`),
`reconcileEntityCollection()` (`absorb()` sur `AbstractApiEntity`) et `getEntityKey()`.
Pagination, recalage de page, rafraîchissement silencieux avec regroupement, source live,
polling et événements de rafraîchissement sont génériques.

- Scinder en `AbstractCollectionVueMixin` (hook `fetchCollectionPage(page)` →
  `{ items, pagination }`, `getItemKey(item)`, réconciliation par clé sans `absorb`) et
  `AbstractEntityCollectionVueMixin` qui en hérite ; idem `AbstractCollectionTable` à côté
  d'`AbstractEntityTable`.
- Un client js pour une route qui rend l'enveloppe paginée sans entité derrière.

## Critère de fin

`import-files.vue` (doc-importer) étend une collection générique, ne garde que ses
colonnes et sa source live, et son endpoint pagine côté serveur avec le helper d'ici. Les
démos du design system et l'arbre de doc-explorer peuvent suivre.

## Reply

**Verdict: the need is real, the road is refused — every list stays a list of entities; a computed list is an entity with no table.** Commit FEATURE_COMMIT. 4 new tests (60 in the suite). The norm is written in `usage/non-persisted-entities`.

**Why not a "DTO list" road.** The stack's strength is the chain entity → normalizer → DTO → pseudocode → generated TypeScript entity and repository → `fetchListPaginated` → front entity collections. A non-entity road would need its own JSON, its own TS types, its own Vue collection — exactly the duplication this todo describes in `import-files.vue` and the demos. An entity that is not stored keeps every link, and the stack already allows it: `symfony-pseudocode` scans `Entity/` without Doctrine and expects packages "whose entities are not persisted"; `AbstractEntityNormalizer` never touches Doctrine; Doctrine treats a class without `#[ORM\Entity]` as transient (tested).

**Item by item.**
1. *Paging a list held in memory* — **done**: `AbstractApiController::applyQueryOptionsToList($request, $items)` → `[PaginationDto, pageItems]`, same `page` / `length` (negative page, `length: 0`) and total known.
2. *Normalizing a DTO without an entity normalizer* — **refused**: the item is an entity, its normalizer extends `AbstractEntityNormalizer` like any other (fixture `DeviceFileNormalizer`); the response is the usual `{type, entity, metadata, relationships}`.
3. *Filters and sort in memory* — **sort done**, with the same `#[SortQueryOption]` as query lists (`SortQueryOption::sortList()`: property paths, nested `a.b`, arrays, enums, nulls first ascending, stable, `id` tie-breaker). **`filter[...]` refused here**: entity lists have no such option either; adding it to memory lists only would split the norm. Search goes through `#[SearchQueryOption]` as for query lists; a generic filter option is a separate request, for both kinds of lists at once.
4. *OpenAPI* — **nothing to add**: `#[ApiResponseData(XDto::class, paginated: true)]` already describes it.

**Rules a non-persisted entity must follow** (in the doc): lives in `Entity/`, no `#[ORM\Entity]`, `#[PseudocodeExport]`; **a deterministic id** (`Uuid::v5` of a fixed namespace and the natural key) — a random id changes at every request and the front collection, which reconciles by id, would replace every row at every refresh; a normalizer and an output DTO; no Doctrine repository, the list comes from the application's own provider; no `#[ApiEntity]` (its scaffolded controller reads a repository).

**Front part — relayed, and reframed.** No `AbstractCollectionVueMixin` split is needed for these cases: once `ImportFile`, the doc tree nodes and the demo rows are non-persisted entities, they get generated TS entities and repositories, and `AbstractEntityCollectionVueMixin` / `abstract-entity-table` read them unchanged — `getEntityKey()` and `absorb()` work on their stable ids. The demos' `getEntityClass() { return null }` workaround should go. What the front agent should check: that the TS generation runs for an entity with no PHP repository (the processor allows it; the generated TS repository only needs the endpoint).

**For `import-files.vue`** (end criterion): `ImportFile` becomes an entity with no table, id from its path; `ImportFilesController` builds the list from the disk and the runs, then `applyQueryOptionsToList()` and `apiResponsePaginated()`; the component extends the entity collection and keeps its columns and its live source. The tree of doc-explorer and the design system demos follow the same way.
