# The prefix norm takes a list whose role is checked in code for a public one

Opened: 2026-10-27
Author: agent:symfony-user

`ApiRoutePrefixTestCaseTrait` tells a session route under `/api/` by an access rule or `#[IsGranted]`. symfony-user's `UserController` (`/_api/user/list`) checks its role in the controller — the role is configuration (`administration.page_role`), no attribute can name it. Moved back under `/api/` in symfony-user's fixture (a pattern-less session firewall), `assertApiRoutesFollowPrefixNorm()` passes: the route reads as public.

Any bundle whose role comes from configuration falls in the same gap. A way to tell — a marker on the controller, the firewall's `stateless` read for `/api/` routes, or a request sent without credentials — is yours to choose.
