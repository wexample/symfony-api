## Two prefixes, by who calls

An api route of the stack lives under one of two prefixes, chosen by **who calls it and how it is signed in** — not by whether a bundle or the application ships it:

| Prefix | Called by | Signed in by | Documented |
|---|---|---|---|
| `/_api/` | the application's own pages: a table's list, a screen's action | the session of the page | no — reached by a program, never read by a person |
| `/api/` | programs with credentials of their own: a device, a script, a partner, a supervisor | a token firewall, or nothing (`/api/health`) | yes — versioned, on the OpenAPI documents, at the `/api/` page |

The underscore says what it says everywhere else in the stack (`/_forms/submit`, `/_system/component`, `/_live/subscribe-info`): a path a program reaches. `/api/` stays the application's api, whatever the application makes of it — a devices' api on a stateless bearer firewall, a partners' one, or none.

Route **names** do not change with the path: `api_user_list` under `/_api/user/list`, as the design rules want (a name never carries the underscore).

## Why the split holds

- **Firewalls.** An application's token firewall matches `^/api/…`; `/_api/` never falls under it, and lands on the session firewall with no pattern to write. A bundle's list no longer breaks when the application's `/api/` is a stateless bearer api.
- **Documentation.** The OpenAPI areas list `^/api/` patterns only; what the pages read stays off the public description of the api. An area on `^/_api/` can still document it, in development.
- **Exposure.** A list of the pages is not a promise to third parties: it changes with the screen that reads it, without a version.

Not a constraint: nothing stops a direct client holding a session cookie from calling `/_api/` — the session and the access rules guard it, as they guard the pages. The prefix tells where a route belongs and keeps the firewalls apart; it is not access control.

## In a bundle or an application

```php
#[Route(path: '/_api/user/', name: 'api_user_')]
final class UserController extends AbstractApiController
{
    #[Route(path: 'list', name: 'list', methods: ['GET'])]
    …
}
```

```ts
// the front's api client
new AppApiClient({ baseUrl: '/_api/' });
```

The generated repositories build their paths on that base, unchanged.

`ApiHelper::PATH_PREFIX_API` and `ApiHelper::PATH_PREFIX_FRONT` name the two prefixes for code that needs them.

## Holding it in a test suite

```php
class ApiRoutePrefixTest extends WebTestCase
{
    use ApiRoutePrefixTestCaseTrait;

    public function testApiRoutesFollowThePrefixNorm(): void
    {
        $this->assertApiRoutesFollowPrefixNorm();
    }
}
```

Every route of an `AbstractApiController` is checked against the application's security configuration:

- under `/_api/`, it must fall under a session firewall;
- under `/api/`, it must not be signed in by the session — a token firewall, or a public route (no access rule, `PUBLIC_ACCESS`, no `#[IsGranted]` on the controller);
- anywhere else, it is reported.

The package's own test endpoints (`/_test/api/…`) are skipped; others can be passed to `assertApiRoutesFollowPrefixNorm()` by name.
