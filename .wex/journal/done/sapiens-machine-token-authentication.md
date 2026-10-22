# Machine token authentication on a stateless firewall

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:main

Checklist line (Sapiens, "Machine-facing API"): *Machine token authentication, on a firewall separate from the page session*.

## Done
- Symfony native `access_token` authenticator; package ships `MachineTokenHandler`, `MachineTokenAuthenticationFailureHandler` (failure handler + entry point), `AbstractMachineToken`, `MachineClientInterface` + `MachineClientTrait`, `MachineTokenService::generateSecret()`, `MachineTokenHelper`.
- Options `wexample_symfony_api.machine_token.{token_class, prefix=mt_, roles=[ROLE_MACHINE], last_used_interval=60}`.
- Test infra (first in this package): phpunit.xml, fixture kernel on SQLite, `tests/Integration/MachineTokenAuthenticationTest` — 10 tests, mutation-checked (stateless removed, query extractor added → failures).
- Knowledge: `usage/machine-authentication`, architecture parameters line.

## Found on the way
- `Symfony\Component\Routing\Annotation\Route` used by the package's controllers, gone in symfony/routing 8 → `Attribute\Route`.
- `symfony/validator` used directly but not required → added. `doctrine/orm`, `symfony/security-bundle` added.
- The bundle registers the host's `src/Api/Controller/` unconditionally: a host without it fails to compile. Left as is (fixture creates the dir).

## Divergences from the application's detail
- Role `ROLE_MACHINE`, prefix `mt_` by default — `ROLE_DEVICE` / `sap_dev_` are the application's settings.
- "Never a human role" → allow-list `machine_token.roles`, failing closed with the same 401 and an `error` log.
- HTTPS: proxy concern, not enforced by the package.
- Missing token: same envelope, `Authentication required.`, `WWW-Authenticate: Bearer` (RFC 6750) vs `invalid_token` for a bad one.

## Not done
- Not checked in a real app over HTTP: Sapiens does not install symfony-api; tests go through the HTTP kernel of the fixture app.
- Built knowledge not regenerated (no build command found).
- Deprecations from php-helpers / symfony-loader in the suite: not this package's files.

## Reply

**Verdict: real gap, implemented, no demo.** `234b6e9`, plus `b98eba9` for the route attribute. 10 integration tests over the HTTP kernel of a SQLite fixture app, mutation-checked. Not checked in a real app: Sapiens does not install `symfony-api`.

**The package now.** Native `access_token` authenticator; the package ships `MachineTokenHandler`, `MachineTokenAuthenticationFailureHandler` (failure handler and entry point), `AbstractMachineToken`, `MachineClientInterface` + `MachineClientTrait`, `MachineTokenService::generateSecret()`. Options under `wexample_symfony_api.machine_token`: `token_class` (null, feature off), `prefix` (`mt_`), `roles` (`[ROLE_MACHINE]`, any other refuses the client), `last_used_interval` (60 s).

**Found and fixed.** `Routing\Annotation\Route` (gone in routing 8) → `Attribute\Route`; `symfony/validator` used but not required. Left for the next task: `services.yaml` requires the host's `src/Api/Controller/`.

**Notice for the application agent.**

> Entities: `Device` implements `MachineClientInterface` (`use MachineClientTrait;`, provide `getUserIdentifier()`); `DeviceToken extends AbstractMachineToken` with a `ManyToOne` to `Device` and `getClient()`. Not a mapped superclass: generate a migration.
>
> ```yaml
> wexample_symfony_api:
>   machine_token:
>     token_class: App\Entity\DeviceToken
>     prefix: sap_dev_
>     roles: [ROLE_MACHINE, ROLE_DEVICE]
>
> security:
>   firewalls:
>     machine:                     # before main
>       pattern: ^/api/device/
>       stateless: true
>       access_token:
>         token_handler: Wexample\SymfonyApi\Security\MachineTokenHandler
>         failure_handler: Wexample\SymfonyApi\Security\MachineTokenAuthenticationFailureHandler
>       entry_point: Wexample\SymfonyApi\Security\MachineTokenAuthenticationFailureHandler
>   access_control:
>     - { path: ^/api/device/, roles: ROLE_MACHINE }
> ```
>
> Guaranteed: token read from `Authorization: Bearer` only (leave `token_extractors` unset); SHA-256 + hint in the database; one 401 envelope with `WWW-Authenticate: Bearer error="invalid_token"` for unknown, revoked or expired; no session cookie opens the machine API, no response sets a cookie; no bearer opens a page as long as `main` carries no `access_token`; a client with a role outside `roles` is refused. Controllers read `#[CurrentUser] MachineClientInterface`.
>
> Sapiens' share: the route prefix, HTTPS at the proxy (no plain HTTP listener on `/api/device/`), the migration.
>
> Open: provisioning and revocation, journal of refusals (`sapiens-machine-token-provisioning`), rate limit per token, versioning.
