# Sapiens — batch receipt with per-item validation, idempotent on replay

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`). A Lucie device that loses connectivity buffers up to **15 measurements** and sends them on reconnection; the connection can drop again mid-send, so the same measurements may arrive twice or more. Closes two lines of Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`, section *Machine-facing API*: **Batch receipt with per-item validation and partial rejection** and **Idempotent receipt of a replayed batch, so an offline buffer syncing twice stores once**. Generic: nothing here knows about measurements.

## 1. Batch receipt with per-item validation

- An endpoint receives a list of items, each validated against an item DTO, the way `#[ValidateRequestContent]` validates one body today (an attribute variant, or a wrapper DTO with a typed list — your call).
- **One bad item does not reject the batch.** Valid items are processed; invalid ones are reported. The response lists, per item, its index (and its client key, below), its outcome — `accepted`, `duplicate`, `rejected` — and for a rejected one the validation errors, in the existing error envelope's vocabulary.
- An HTTP status that tells the device what to do: all accepted or duplicate → success; some rejected → still a success status with the per-item report (the device must drop the accepted ones from its buffer and keep or discard the rejected ones), never a whole-request error that makes it resend everything. Document the choice.
- A bound on the batch size, configurable, refused as a whole-request `413` / `422` beyond it.
- Each item is processed in its own unit of work: an exception on item 7 does not roll back items 1–6.

## 2. Idempotency on replay

- Each item carries a **client-generated key**, unique per client (a UUID created by the device when it records the item). The key, not the payload, identifies the item.
- The platform stores processed keys **per authenticated machine client**; a key already seen returns `duplicate` with the original outcome, and nothing is stored twice. Two devices may reuse the same key without colliding.
- Concurrency: the same batch sent twice at the same time stores once — a unique constraint on (client, key) with the insert as the check, not a read-then-write.
- Retention of seen keys: configurable, long enough to cover the longest offline period (Sapiens: days, not minutes).
- A replayed key with a **different** payload is not silently accepted: report it as `conflict`.

Optionally, a whole-request `Idempotency-Key` header for non-batch endpoints, same storage — useful, not required by Sapiens.

## 3. Journal

Reuse `MachineSecurityEvent` or a sibling: one fact per batch (client, counts per outcome), not one per item — the item details belong to the application.

## Tests

- A batch with valid and invalid items stores the valid ones and reports each item.
- The same batch replayed stores nothing more and reports `duplicate` for each.
- Two concurrent identical batches store once.
- Same key, different payload → `conflict`.
- Same key from two different clients → both stored.
- An exception on one item leaves the others stored.
- A batch over the size limit is refused whole.

## Reply

**Verdict: real gap, implemented, no demo** — both lines. Commit `2106365`. 10 new integration tests (31 in the suite) over the HTTP kernel of the SQLite fixture app; mutation-checked: without the entity manager reset, the failing-item test fails; without the unique constraint, the receiver refuses to run. Not checked in a real app: Sapiens does not install `symfony-api`.

**The package now.** `BatchReceiverService::receive($request, ItemDto::class, $processor, ?maxItems)` and `AbstractApiController::apiResponseBatch($report)`. Body `{"items": [{"key", "data"}]}`. Per item: shape check, DTO validation with the existing validation vocabulary (`ApiValidationErrorData`), then one transaction that **inserts the key first** (DBAL insert, unique `(scope, idempotency_key)` — the insert is the check), runs the processor, flushes, commits. Outcomes `accepted | duplicate | rejected | conflict | error`. Options `wexample_symfony_api.batch.{record_class, max_items=100, retention=P30D}`; `api:batch:purge-keys`. One `ApiBatchEvent` per batch, channel `api_batch`. Doc: `usage/batch-receipt`.

**Choices and divergences.**
- **Status**: `200` whenever the batch is readable, all-rejected included; `400` unreadable, `422` too many items — `422` rather than `413`, which is about bytes and belongs to the proxy.
- **Body shape**: `key` beside `data`, not inside it: the item DTO stays the application's own and carries no transport field.
- **Fifth outcome, `error`**: an exception on an item is not a rejection — it is rolled back with its key, so the sender keeps it and retries. The exception message is logged, never returned.
- **Duplicate returns the original result**, not just "duplicate": the processor's return value is stored with the key.
- **Rejected items consume no key**: a replay is validated again and rejected again.
- **Scope** is the authenticated user (`ClassShortName:identifier`), not only machine clients: any authenticated sender can batch. No sender, no batch.
- **Storage** is an abstract record the application extends (like the token), not a table imposed on every application installing the package. Its unique constraint is checked at first use.
- **Not done**: the optional `Idempotency-Key` header for single endpoints. Concurrency is guaranteed by the unique constraint and tested by a key pre-claimed in the database, not by two real connections.

**Notice for the application agent.**

> ```php
> #[ORM\Entity]
> #[ORM\UniqueConstraint(columns: ['scope', 'idempotency_key'])]
> class IdempotencyRecord extends AbstractIdempotencyRecord {}
> ```
>
> ```yaml
> wexample_symfony_api:
>   batch:
>     record_class: App\Entity\IdempotencyRecord
>     max_items: 15           # the Lucie buffer; or per endpoint, receive(..., maxItems: 15)
>     retention: P30D         # longer than the longest offline period
> ```
>
> Generate the migration. Schedule `php bin/console api:batch:purge-keys` (daily). Optionally route the `api_batch` Monolog channel.
>
> The endpoint: `return self::apiResponseBatch($batchReceiverService->receive($request, MeasurementDto::class, fn (MeasurementDto $item, Device $device) => …persist…, maxItems: 15));` — persist only, the receiver flushes; use the `$device` argument, not one captured before; return what the device needs back (an id), JSON-encodable.
>
> The device firmware contract to write down on the Sapiens side: a UUID per measurement generated at recording time; drop from the buffer on `accepted`, `duplicate`, `rejected`, `conflict`; keep and retry on `error` and on any non-200 response.
>
> Open, on other lines: rate limiting per token, versioning.
