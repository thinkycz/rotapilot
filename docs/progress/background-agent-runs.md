# Background Agent Runs Progress

## Status

Implemented durable agent runs with SSE reconnect.

## Decisions

- Use reconnectable SSE backed by stored run events.
- Keep one queued/running run per conversation.
- Support manager cancellation.
- Persist the manager message before dispatching the background job.

## Verification

- Focused backend agent tests passed.
- Frontend type-check and unit tests passed.
- Full project verification passed with `make check`.
