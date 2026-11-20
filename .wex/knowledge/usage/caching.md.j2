## Marking a route

A read route whose answer can be kept says for how long, and what it is read from:

```php
#[Route(path: '/{slug}', name: 'show', methods: ['GET'])]
#[ApiCache(maxAge: 300, entities: [Person::class, Document::class])]
public function show(string $slug): ApiResponse
```

`ApiCacheSubscriber` then, for a `200` to a `GET` or `HEAD`:

- tells the caller and the caches between: `Cache-Control: max-age`, `public` for a call carrying no token, `private` for a token's — its answer may be its holder's own —, an `ETag`, `Vary: Accept-Language, Authorization`; a caller sending back the `ETag` (`If-None-Match`) gets a `304` and no body;
- keeps the answer on the server for the calls carrying no token, in `cache.app.taggable`, keyed by the route, its parameters, the query (in any order) and the language: the next such call is answered without running the controller. `server: false` keeps the headers and leaves the server out.

What happened is on the main request — a tool's call being a sub-request of the MCP one —, `ApiCacheSubscriber::REQUEST_ATTRIBUTE_CACHE` (`_api_cache`): `hit` or `miss`. symfony-analytics counts the calls answered from the cache by it.

## Dropping what is kept

`ApiCacheDoctrineListener` drops, at every flush, the answers read from any class the flush inserts, updates or deletes — or from one of their parents —: an answer is never older than its data, whatever `maxAge` says. `ApiCacheService::invalidateEntities()` does the same for what does not go through Doctrine.

A call answered from the cache still counts against the caller's rate limit (`usage/rate-limits`).
