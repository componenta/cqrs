# CQRS operation lifecycle / retry semantics — decision record

Status: **Open / requires integration-level decision**. Updated 2026-11-22.

## Problem

A synchronous command handler can complete successfully and be followed by a failing `CommandProcessedEvent` listener. A configured clock can also fail while recording completion or constructing the processed event, after the handler has already run. If a retry wrapper surrounds `EventMiddleware` and treats all thrown exceptions as retryable, a new attempt may reexecute a handler with side effects. A completed built-in `Operation` guard prevents the direct *completed-object* reuse case, but does not protect a fresh retry of the still-uncompleted outer operation.

## Design alternatives

### A. Event → Retry → handler

Lifecycle events describe one outer dispatch, not individual attempts. An exception from the processed listener or construction of the processed event is thrown outside the inner retry. A clock error during result construction still occurs inside the terminal handler stage and may be retried by a surrounding retry middleware. Intermediate handler exceptions retried successfully are not externally emitted as `CommandFailedEvent`. Simple configuration solution if dispatch-level events are correct.

### B. Retry → Event → handler, with failure classification

Events can describe each attempt. To avoid duplicate command effects following post-handler failures, a retry middleware must reliably distinguish handler errors from event notification errors. Changing the exception type or `OperationInterface` requires a compatibility analysis (existing catches, interceptors, metrics, transaction middleware). Do not change the public core contract without this evidence.

### C. Transactional outbox / event delivery isolation

Where post-commit delivery must be reliable, persist notifications with the domain transaction; process them separately with deduplication and retry. Requires application storage and transaction guarantees, not just a generic CQRS core change.

## Recommendation

Do **not** introduce a new public interface solely for a speculative retry ordering. First choose event granularity and transaction boundaries in the consuming application. For dispatch-level events, prefer A with an explicit warning that an *outer* retry can still reexecute the whole dispatch. For durable side effects, combine idempotency with C.

## Test plan

- Controlled handler side effect counter and processed listener that throws once.
- Retry wrapper in both middleware orders; check the number of handler calls and event sequence.
- Distinguish original exception identity and any suppressed secondary error.
- Transaction integration: verify commit/rollback and outbox atomicity where available.
- Fiber/concurrent case: never assume a shared in-memory guard enforces distributed exactly-once execution.
- Backward-compatibility: third-party `OperationInterface`, user invokers, and optional middleware packages.

## Non-goals

No implicit auto-reordering of middleware, transparent transaction creation, global deduplication cache, or change of historical event names.
