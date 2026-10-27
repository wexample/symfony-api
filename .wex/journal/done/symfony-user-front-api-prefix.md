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

## Reply

**Verdict: taken, with one change of criterion — `/_api/` for what the application's pages read on the session, `/api/` for what programs call with credentials of their own.** Commit `0d3b6e7`. 2 new tests (90 in the suite). The norm: `usage/route-prefixes`.

**The change of criterion.** The proposal splits bundles' lists from the application's api. The line that holds is who calls and how it signs in: a list the application itself serves to its own tables (Sapiens' `/api/app/patient/list`) is the same kind as a bundle's, and goes under `/_api/` too; a bundle endpoint called by programs with a token stays under `/api/`. In this package: `/api/user-tokens` (session only, for a screen) **moved to `/_api/user-tokens`**; `/api/health` (load balancers, monitors, no session) **stays**.

**What the package holds now.**
- `ApiHelper::PATH_PREFIX_API` (`/api/`) and `ApiHelper::PATH_PREFIX_FRONT` (`/_api/`).
- **The constraint**: `ApiRoutePrefixTestCaseTrait::assertApiRoutesFollowPrefixNorm()`, for any bundle's or application's suite. It reads the router, the firewall map and the access map: a route of an `AbstractApiController` under `/_api/` must fall under a session firewall; under `/api/` it must not be signed in by the session (token firewall, or public — no access rule, `PUBLIC_ACCESS`, no `#[IsGranted]` on the controller); anywhere else it is reported. Checked to report a session list left under `/api/` by an access rule and by `#[IsGranted]` alone (removing the `#[IsGranted]` reading makes the test fail).
- OpenAPI: the doc's areas list `^/api/` only; an area on `^/_api/` still documents front routes in development, with the session scheme.
- Route names unchanged (`api_…`): the path says who, the name does not (design rules).

**Set aside, as the request did.** Roles alone on `/api/` and a "front only" header: same reasons. Also not done: serving both prefixes during a transition — two paths for every list doubles what the firewalls and the access rules have to cover; the pass is a clean switch, one bundle major at a time.

**The pass — for the owner to hand out, not done here (other agents' repositories).**
1. **Design rules** (`PYTHON/wex/wex-addon-dev-php/…/design_rules/php_code.md`), replace the `/api` paragraph by: *"The one exception is `/api`, which holds the application's api — what programs call with credentials of their own, versioned and documented — and, at `/api/`, the page documenting it. What the application's pages read on the session — a table's list, a screen's action — is reached by a program and lives under `/_api/`, like any other program path. See symfony-api `usage/route-prefixes`."*
2. **Every bundle with `src/Api/Controller`** (found: symfony-accounting, symfony-ai, symfony-analytics, symfony-bpmn, symfony-charts, symfony-data-sync-ds, symfony-design-system-demo, symfony-file, symfony-git, symfony-media, symfony-money, symfony-payment, symfony-platform, symfony-remote-ds, symfony-search, symfony-user): route by route, `/_api/` if the pages read it on the session, `/api/` if programs call it with a token; add `ApiRoutePrefixTestCaseTrait` to its suite; major version bump.
3. **Applications**: the front api client's `baseUrl: '/_api/'`; route imports unchanged except prefixes; Sapiens' `/api/app/…` → `/_api/app/…` with its access rules; Syrtis' manager drops the `/_manager` prefix import and base.
4. **symfony-testing**: its exploration can call the trait's `findApiRoutePrefixProblems()` rather than re-implement the check.
