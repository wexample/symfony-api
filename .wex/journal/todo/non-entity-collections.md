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
