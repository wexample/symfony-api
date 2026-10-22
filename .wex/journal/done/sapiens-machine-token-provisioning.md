# Sapiens — provision, rotate and revoke machine tokens, and journal refusals

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`), whose Lucie medical devices call a REST API with one token each. Machine authentication already ships (`234b6e9`, `usage/machine-authentication`): this todo covers what comes after authenticating — getting a token into a device, taking it back, and tracing every refusal. It closes two lines of Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`, section *Machine-facing API*: **Provisioning and revoking a token per device** and **Invalid token rejected and journalized**. Plus one fix found by the previous task.

## 0. Fix first: `services.yaml` requires a host directory

`src/Resources/config/services.yaml` line 8 registers `%kernel.project_dir%/src/Api/Controller/*` unconditionally: an application without that directory does not compile. Sapiens has none. Register it only when the directory exists (in the extension, not in YAML), or drop it and let applications register their own controllers. Test: a kernel without `src/Api/Controller/` boots.

## 1. Provisioning

- `MachineTokenService::issue(MachineClientInterface $client, ?DateTimeImmutable $expiresAt = null, ?string $label = null)`: creates the token entity of `token_class`, persists only the SHA-256 and the hint, and returns the **plain secret once**. Nothing can read it back afterwards.
- Secret: the configured prefix plus at least 32 bytes of randomness, URL-safe. Hint: prefix plus the last 4 characters, enough to recognise a token in a ticket or a log.
- A free `label` (e.g. "commissioning 2026-10", "rotation") helps support tell tokens of one device apart.

## 2. Revocation and rotation

- `revoke(token)`: one token, effective on the next request (the handler already checks `dateRevoked`).
- `revokeAll(client)`: every token of a client — a decommissioned or stolen device.
- `rotate(client, overlap)`: issues a new token and sets the old ones to expire after `overlap`, so a device can switch over without a window where it is locked out. Returns the new plain secret once.
- Optionally, a client-level switch (e.g. `MachineClientInterface::isMachineEnabled()`), so that disabling a device refuses every token it holds without touching them. Your call whether it belongs in the interface.

## 3. Console

Devices are provisioned by an operator, not through a page — the admin surface is Sapiens' business. Commands, generic over `token_class`:

- `api:machine-token:issue <client> [--expires=] [--label=]` — prints the secret once, and says so.
- `api:machine-token:revoke <hint|id>` and `--all <client>`.
- `api:machine-token:list <client>` — hints, labels, dates (created, last used, expires, revoked). **Never** a secret or a hash.

Resolving `<client>` needs to know the client class: a `client_class` option, or read from the `ManyToOne` of `token_class`.

## 4. Journal of refusals and lifecycle

Same contract as `symfony-user`'s security journal (`SecurityEvent`, channel `user_security`, see its `usage/overview`), so that one future audit package listens to both:

- a typed Symfony event per fact — `machine_token.issued`, `.revoked`, `.rotated`, `.refused` — with a stable cause for refusals: `missing`, `unknown`, `revoked`, `expired`, `role_not_allowed`, `client_disabled`;
- carrying the token hint (never the secret), client identifier when resolved, IP, user agent, request id (`X-Request-Id` or generated), UTC timestamp;
- written by default to a dedicated Monolog channel, `machine_security`, at `warning` for refusals.

The response stays the single `401 invalid_token`: the cause goes to the journal only. Make sure nothing else logs the `Authorization` header (request logging, exception messages) — the `symfony-user` test that searches every record of every channel for each secret it used is the model.

## Open on the Sapiens side, not to decide here

The client specifications say the device "reactivates its token" every night at 00h00. If that means daily rotation, Sapiens will expose a device-authenticated endpoint calling `rotate()`; the package only needs `rotate()` to be callable from a request authenticated by the token being rotated.

## Tests

- `issue()` returns a secret that authenticates, and the database holds no clear secret.
- `revoke()` and `revokeAll()` refuse on the next request; other clients unaffected.
- `rotate()`: both tokens work during the overlap, only the new one after.
- Commands never print a hash, and print a secret only on `issue`.
- Each refusal cause produces its journal entry with the right cause, and the HTTP response is identical across causes.
- No log record of any channel contains a secret used in the test.
- A kernel without `src/Api/Controller/` boots.

## Reply

**Verdict: real gap, implemented, no demo** — both lines, plus the fix. Commits `79980b0` (fix) and `70dbbe5` (feature). 21 integration tests over the HTTP kernel of the SQLite fixture app, the journal test mutation-checked (failure listener removed → fails). Not checked in a real app: Sapiens does not install `symfony-api`.

**The package now.**
- §0 — `src/Api/Controller/` of the host is registered only when it exists (extension, `services_app_api_controllers.yaml`). The fixture kernel boots without it.
- §1–2 — `MachineTokenService::issue(client, ?expiration, ?label)`, `rotate(client, DateInterval overlap, ?expiration, ?label)`, `revoke(token)`, `revokeAll(client)`; `label` column on `AbstractMachineToken`, which now also requires `setClient()`. `rotate()` works from a request authenticated by the token being rotated (tested). The client class is read from the token class mapping: no `client_class` option.
- §3 — `api:machine-token:issue|list|revoke` (`--all`). Only `issue` prints a secret; no hash anywhere; a hint is accepted with or without its `…`.
- §4 — `MachineSecurityEvent`, same shape as `symfony-user`'s `SecurityEvent` (`user_id` holds the client). Types `machine_token.issued|rotated|revoked|refused`; causes `missing|unknown|revoked|expired|role_not_allowed|client_disabled`; channel `machine_security`, `warning` for refusals. `X-Request-Id` honoured. `MachineTokenRedactionProcessor` replaces any token by its hint in every channel.
- `dateLastUsed` moved from the handler to `LoginSuccessEvent`: a switched-off client no longer counts as "used".

**Divergences from the request.**
- **Hint**: prefix + the **first** six characters, not the last four — already shipped that way, and what the logs and the console show.
- **Client switch**: not in `MachineClientInterface`. Symfony's firewall `user_checker` does it natively; the package journals its refusal as `client_disabled`.
- **Unknown token hint**: given only when the presented string starts with the configured prefix — a foreign string may be someone else's secret.

**Found and fixed.** A token in the query string reached the logs through the router's 404 exception, which quotes the referer: the processor only cleaned strings and arrays, not the exception the record carries. It now rewrites the messages of the exception chain. `symfony-user`'s `SecretRedactionProcessor` has the same hole for a link signature quoted in a referer — not my package, for its agent.

**Notice for the application agent.**

> In `DeviceToken`, add `setClient(MachineClientInterface $client): static`; regenerate the migration (`label` column).
>
> ```yaml
> security:
>   firewalls:
>     machine:
>       # … as before, plus, to switch a device off without touching its tokens:
>       user_checker: App\Security\DeviceChecker   # throws DisabledException in checkPreAuth()
>
> monolog:
>   channels: [machine_security]
>   handlers:
>     machine_security:
>       type: rotating_file            # or the collector's handler
>       path: '%kernel.logs_dir%/machine_security.log'
>       level: info
>       channels: [machine_security]
>       formatter: monolog.formatter.json
>     main:
>       channels: ['!machine_security', '!user_security']
> ```
>
> Provisioning: `php bin/console api:machine-token:issue <device id> --label=commissioning [--expires="+1 year"]` prints the secret once. `list`, `revoke <hint|id>`, `revoke <device id> --all`.
>
> Nightly renewal, if the specs mean rotation: expose a device-authenticated endpoint calling `$machineTokenService->rotate($device, new DateInterval('PT1H'))` and returning the new secret; the old one stays valid for the overlap.
>
> Guaranteed: one 401 whatever the cause, the cause in `machine_security`; no secret in any log channel with Monolog installed (the masking is a Monolog processor: no Monolog, no masking — and nothing logged either). Sapiens' share: the endpoint above if wanted, the log retention (LOG-001), the admin screen if one is ever wanted (the service is ready).
>
> Open: rate limiting per token, API versioning.
