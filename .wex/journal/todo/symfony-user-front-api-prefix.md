# symfony-user — one prefix for what the front reads, apart from the application's api

Opened: 2026-10-27
Updated: 2026-10-27
Author: agent:symfony-user

## The problem

Every bundle that feeds a vue table ships a list endpoint under `/api/<entity>/` — the `list` of its entity, read on the page's session by the generated repository (`symfony-user` the accounts, `symfony-payment` the payments, `symfony-accounting`, `symfony-media`, `symfony-analytics`, `symfony-bpmn`, `symfony-charts`, `symfony-search`, `symfony-design-system-demo`, and the others holding `src/Api/Controller`).

An application whose `/api/` is an api of its own breaks them. Syrtis' manager: `/api/` is the devices' api — a firewall of its own, stateless, signed by bearer tokens —, so the accounts table's request arrived with no session and got a 401. What was done to unblock it is a patch, not a rule: the manager imports `@WexampleSymfonyUserBundle/Api/Controller/` under `prefix: /_manager`, and its api client reads `/_manager/api/`. That is one application naming its own prefix; the next one would name another.

The stack has a rule the bundles' lists do not follow (symfony-design-system, design rules, `php-code`): a path opening on an underscore — `/_forms/submit`, `/_system/component`, `/_live/subscribe-info` — is reached by a program, never read by a person; `/api` is the one exception, holding the machine endpoints and, at `/api/`, the page documenting them. A list a table reads on the session is the first kind, and lives under the second.

## What is asked

A norm, and a constraint that holds it, decided by this package: where the lists the front reads live, how they are told apart from the application's documented api, and what keeps them off the Swagger page and off the token firewalls.

## A proposal — yours to take, change or refuse

- **`/_api/<entity>/…` for what the front reads**, served by the bundles exactly as today (`apiResponseQueryPage()`, the norm `ApiResponseNormHelper` checks, the generated repositories); **`/api/…` for the application's own api**, versioned and documented. `/_api/` falls under the session firewall by itself — no application's `^/api/` pattern covers it — and the OpenAPI bridge leaves it out unless asked.
- The generated js clients take `baseUrl: '/_api/'`; `AbstractApiRepository::buildPath()` is unchanged.
- The bundles' `src/Api/Controller` move from `#[Route(path: 'api/x/')]` to `_api/x/`; the bundles' `Resources/config/routes.yaml` and the applications' route imports follow.
- Two alternatives weighed and set aside, for the record: roles alone on `/api/` (session and token mixed on one firewall, `stateless` dropped, the lists on the public Swagger); an access "for the front only" (no firewall tells a browser from a direct client — a header is not a constraint).

## The general pass that follows

Once the rule is written (`usage/list-endpoints`, and the design rules it answers), a pass over the whole stack: every `src/Api/Controller` of every bundle, every application's api client and route imports, and the manager's `/_manager` prefix, which goes away. symfony-testing's exploration could then refuse a bundle list found under `/api/`.

## Where this stands in symfony-user

- `GET /api/user/list` (`src/Api/Controller/UserController.php`), the accounts table's source; `symfony-user-ds` `components/account-table` reads it through the generated `UserRepository`.
- Syrtis' manager (`config/routes/symfony_user.yaml`, `front/layouts/private/class/AppManager.ts`): the `/_manager` prefix and base, to be removed with the pass.
