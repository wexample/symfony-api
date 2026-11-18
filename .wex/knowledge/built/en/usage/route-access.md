## Every api route says who reaches it

`/_api/` is not "signed in only": the pages read it, anonymous visitors' included. So nothing closes it by default — and an access forgotten, or an access rule left on a path its routes moved away from, opens a route silently. Each api route therefore says who reaches it, in one of three ways:

| | |
|---|---|
| an `access_control` rule matching its path | `- { path: ^/_api/currency/import, roles: ROLE_ADMIN }` |
| `#[IsGranted]` on its class or action | `#[IsGranted('ROLE_USER')]` |
| `#[ApiAccess]` on its class or action | `#[ApiAccess(ApiAccess::PUBLIC)]` — anyone, anonymous visitors included |
| | `#[ApiAccess(ApiAccess::CONTROLLER, 'the conversation's voter')]` — the action decides in its code; the reason is required |

## Checked by a test

```php
class ApiRouteAccessTest extends KernelTestCase
{
    use ApiRouteAccessTestCaseTrait;

    public function testApiRoutesDeclareTheirAccess(): void
    {
        self::bootKernel();
        $this->assertApiRoutesDeclareTheirAccess();
    }
}
```

Every route of an `AbstractApiController` saying nothing is reported, with its controller: the application's, and those its bundles bring. An `access_control` rule rewritten for a moved prefix is the case it was written for — `^/api/currency/import` matching nothing once the route lives under `/_api/`.

It says whether access is declared, not whether it is right: what the declaration grants is still the voters' and the rules' business, and the tests of each route's.
