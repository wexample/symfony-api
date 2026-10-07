# Poortere / LDP production planning: complete the platform checklist

Opened: 2026-10-07
Updated: 2026-10-07
Author: agent:app:main

## Context

A new client application is being built on the PHP suite: **LDP production planning** (Poortere, for Mojoe),
an internal steering tool for a rug factory — imports of production files, loom load, replenishment, delays,
on Symfony / PostgreSQL.

- The application: `/home/weeger/Desktop/WIP/WEB/MOJOE/local/poortere/ldp-production-planning`
- Its feature inventory, the document this todo is about: `/home/weeger/Desktop/WIP/WEB/MOJOE/local/poortere/ldp-production-planning/.wex/knowledge/specifications/platform-features.md.j2`

That document lists every feature the platform needs **outside data processing**. A first pass has already
been made against the sources of `PACKAGES/PHP/packages/wexample`:
- a line is ticked `[x]` when the suite already provides it, with `→` naming where it lives;
- lines describing this application rather than a framework capability were moved to
  `ldp-features.md.j2`, beside it: they are not the suite's to provide, leave that file alone;
- what is left unticked in `platform-features.md.j2` is generic and believed **missing from the suite**.

That first pass was made from outside your package and is known to contain mistakes (an initials avatar
that already exists was left unticked).

## References

- **Sapiens** — `/home/weeger/Desktop/WIP/WEB/HOME_HABILIS/local/sapiens`: the latest real application on the suite, where recent features are seen in
  use. Its own requirements list, checked by the package agents, is `/home/weeger/Desktop/WIP/WEB/HOME_HABILIS/local/sapiens/.wex/knowledge/contributing/stack-requirements.md.j2` — the same
  exercise, done earlier.
- **Mojoe design system** — `/home/weeger/Desktop/WIP/WEB/MOJOE/local/design-system`: the live demo and implementation of the components; it has to be
  kept working as the suite moves.

## The spirit of it

What goes into the packages are **generic blocks**: pieces any internal tool could ask for, not this
application's screens. They follow the conventions the suite has already set — its design rules
(`wex ai::design/rules`), its shapes, its naming, its documentation — and they **reuse what exists**
rather than writing it again: a new component built on `marker`, `zone`, `toolbar` or `data-table`, a form
built on `symfony-forms`, a check made through the security events already emitted. Before deciding a line
is missing, look for the piece that nearly does it, here or in a sibling package
(`wex app::source/search --scope stack`); extending it is usually the answer.

The call between « exists », « to build here », « belongs to another package » and « LDP-specific » is the
whole value of this exercise: make it carefully, and say why when it is not obvious. A line that would
only make sense for LDP stays out of the package, however easy it would be to add.

## Working alongside other agents

**Stay in your package** — and its twins, the packages carrying the same name suffixed `-ds` or `-demo`
(`symfony-user-demo`, `symfony-translations-ds`…): they are yours too, the rest is not. The same document has been handed to the agents of a dozen other packages, and
they are working on it at the same time as you. Touch only the lines that concern your package, leave the
others as you find them even when you disagree, and re-read the file just before writing it: someone else
may have changed it since you opened it. Never rewrite the document whole — edit your lines in place.

**Commit as you go.** Each step that stands on its own — your lines of the checklist, your feedback file,
later each feature — is committed when it is done, not saved up for the end. Commit only the files you
changed: other agents have uncommitted work in the same repositories, the application's included, and it
is theirs to commit. Use the `commit` tool rather than a raw `git commit`, and never `git add -A`.

**Installing your package in the design system.** To see a change of your package in the Mojoe design
system, run `wex app::setup/install --env local` from that repository
(`/home/weeger/Desktop/WIP/WEB/MOJOE/local/design-system`) — or leave it to weeger, who will run it at the
end. Do not install anything elsewhere.

## Your mission — phase 1, this todo

Lines concerning you: §14 API, and the generic side of the script import endpoint and the worker endpoint moved to `ldp-features.md.j2`.

Ticked from your sources: `ApiResponse`, machine tokens on their own firewall, `api:machine-token:issue|list|revoke` with `dateLastUsed`, versioning, `#[QueryOption]`, the error envelope, the rate limiter, `ApiRouteDescriber`. Open: a health-check endpoint (app, database, queue); tokens managed per user or service account rather than per client from the CLI; sorting in the query conventions; whether the error message is translated.

1. Read the lines of the document that fall to your package, ticked and unticked.
2. **Complete the checklist**: tick what your package provides that the first pass missed, untick what it
   ticked wrongly, and correct the `→` lead where it names the wrong thing. A tick means the capability
   exists in your package now, configuration allowed — say so on the line when it takes configuration.
3. For each line still unticked that belongs to you, keep it unticked; if it belongs to another package, or
   to no package, say so in your answer — do not edit that package's lines.
4. **Do not change your package's code in this phase**: only the document.
5. Write `/home/weeger/Desktop/WIP/WEB/MOJOE/local/poortere/ldp-production-planning/.wex/checklist-feedback/symfony-api.md` following the format of the `README.md` beside it: what you ticked,
   unticked, corrected, what belongs elsewhere, what is to build here, and what the first pass got wrong.
   That file is yours alone; it is how this exercise gets better next time.
6. Answer weeger briefly: what you ticked or unticked, and anything that was misfiled. **End your answer
   with the list of features to build in your package** — one line each, taken from the unticked lines
   that are yours. That list is what phase 2 starts from.

## Next — phase 2, not now

Implementing the missing features in your package, under weeger's supervision, once the checklist is
settled across every package.
