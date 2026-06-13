# RotaPilot MVP — Implementation Plan

(See `docs/specs/rotapilot-mvp.md` for the product spec.)

## Decisions

- **Project conventions win.** Honor `AGENTS.md` and `docs/guidelines.md`: flat-action REST routes with `?id=` query params, GET/POST only, `ApiFormRequest + SymfonyResponse`, JSON:API resources, invokable controllers, no `app/Http/Requests` or `app/Http/Validation` folders, `Resolver::resolve*` helpers, `Trans::inject`, mandatory `declare(strict_types=1)` + docblocks, mandatory architecture tests stay green.
- **Extend the existing `ui/` kit** (teal primary, light theme). No shadcn-vue dep.
- **Flat routes** with `?id=`.
- **Conversational AI assistant.** Uses `laravel/ai` SDK with `HasConversations` on `User`; `/agent` streams answers and can call scoped tools for stores, employees, shifts, and availability. Local/test no-key mode uses Laravel AI's fake gateway for `SchedulingAgent`.
- **Build all listed components from scratch** (no extra deps).
- **Provisioning is part of the plan** (composer + npm + sqlite + seeders).

## Stack

- Laravel 13, PHP 8.3.
- Inertia 3, Vue 3 (`<script setup lang="ts">`), TypeScript, Tailwind 4, Vite.
- Local package `thinkycz/laravel-core` (provides `BaseModel`, `Resolver`, `Env`, `AuthValidity`, etc.).
- `laravel/ai` SDK for the conversational scheduling assistant.
- SQLite for dev (configured in `.env`); MySQL config retained in `.env.example`.

## Phases

| #   | Phase                                                          | Done when                                                                                 |
| --- | -------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| 0   | Provisioning + docs                                            | `make check` green; `docs/specs/`, `docs/plans/`, `docs/progress/` populated.             |
| 1   | Roles + User updates                                           | `User` has `role` + `is_active`; `RoleEnum`; login redirects to role-aware dashboard.     |
| 2   | Stores + Business Hours                                        | CRUD, weekly editor, manager-store pivot, seed stores.                                    |
| 3   | Employees + Employee-Store + Availability                      | CRUD, attach/detach stores, monthly availability grid + AI parser stub.                   |
| 4   | Schedules + Shift Requirements + Shift Assignments + Conflicts | Calendar UI, manual assignment, conflict detection (5 types).                             |
| 5   | Employee calendar                                              | `/my-calendar` shows only the logged-in employee's published shifts.                      |
| 6a  | laravel/ai SDK + assistant storage                             | Composer dep, `HasConversations` on User, Laravel AI conversation migrations.             |
| 6b  | Conversational scheduling assistant                            | `/agent` page, `SchedulingAgent`, scoped tools, conversation sidebar, streamed responses. |
| 7   | Assistant hardening                                            | Safe text rendering, robust SSE parsing, local/test no-key fake, feature/unit coverage.   |
| 8   | Scheduling conflict support                                    | Conflict detection services and schedule-page conflict visibility.                        |
| 9   | UI polish                                                      | Empty states, loading states, responsive tables, role-gated sidebar.                      |
| 10  | Final verification                                             | `make fix && make check` green; manual smoke run.                                         |

Each phase ends with `make check` green.

## AI Architecture (final)

- `app/Ai/Agents/SchedulingAgent.php` — `implements Agent, Conversational, HasTools`; stores conversation history through Laravel AI and instructs the model to call tools for live data.
- `app/Ai/Tools/{GetStoresTool,GetEmployeesTool,GetShiftsTool,GetAvailabilityTool}.php` — manager-scoped read tools. Employee tool payloads intentionally omit private contact and pay fields.
- `ProposeSchedulingChangesTool` creates pending proposal batches only; `AgentProposalApplyService` applies confirmed batches transactionally and reports post-apply conflicts.
- `app/Http/Controllers/Web/Agent/*` — Inertia chat page, SSE stream endpoint, conversation deletion, and proposal apply/reject endpoints.
- `resources/js/pages/agent/Index.vue` — safe plain-text message rendering, localized UI copy, robust SSE parsing, and proposal review cards.

`AppServiceProvider::boot()`:

- Loads Laravel AI package migrations.
- Uses OpenRouter by default via `OPENROUTER_API_KEY` and `OPENROUTER_MODEL`.
- Fakes `SchedulingAgent` in `local` and `testing` when `OPENROUTER_API_KEY` is empty.

## Route Map (web)

```
GET    /dashboard
GET    /stores/index
GET    /stores/show?id=
GET    /stores/create
POST   /stores/store
GET    /stores/edit?id=
POST   /stores/update?id=
POST   /stores/destroy?id=
GET    /stores/business-hours?id=
POST   /stores/business-hours/update?id=
GET    /agent
POST   /agent/stream
POST   /agent/conversations/destroy
POST   /agent/proposals/apply
POST   /agent/proposals/reject
GET    /employees/index
GET    /employees/show?id=
GET    /employees/create
POST   /employees/store
GET    /employees/edit?id=
POST   /employees/update?id=
POST   /employees/destroy?id=
POST   /employees/stores/store?employee_id=
POST   /employees/stores/destroy?employee_id=&store_id=
GET    /availability
POST   /availability/store
POST   /availability/update?id=
POST   /availability/destroy?id=
GET    /schedules/index
GET    /schedules/show?id=
GET    /schedules/create
POST   /schedules/store
GET    /schedules/edit?id=
POST   /schedules/update?id=
POST   /schedules/destroy?id=
POST   /schedules/publish?id=
POST   /schedules/archive?id=
POST   /shift-requirements/store?schedule_id=
POST   /shift-requirements/update?id=
POST   /shift-requirements/destroy?id=
POST   /shift-assignments/store?shift_requirement_id=
POST   /shift-assignments/destroy?id=
GET    /agent
POST   /agent/stream
POST   /agent/conversations/destroy
GET    /my-calendar
```

## Enums (`app/Enums/`)

- `UserRoleEnum` (store_manager, employee).
- `AvailabilityTypeEnum` (available, unavailable, backup).
- `AvailabilitySourceEnum` (manager, employee, ai).
- `ScheduleStatusEnum` (draft, published, archived).
- `ShiftSourceEnum` (manual, ai).
- `ShiftAssignmentStatusEnum` (draft, confirmed, cancelled).
- `ConflictTypeEnum`.
- `ConflictSeverityEnum` (info, warning, critical).

## Services (`app/Services/Scheduling/`)

- `AvailabilityMatcherService`, `OverlapDetectorService`, `BusinessHourGuardService`, `ScheduleGeneratorService`, `AssignmentService`, `ConflictDetectionService`.

## Tests

- Feature: each controller (at least 1 happy-path test). Brief's 13 scenarios.
- Unit: scheduling services and frontend SSE parsing.
- Feature: AI assistant controllers and scoped tools.
- Architecture: untouched (must stay green).

## Definition of Done

1. All 15 success criteria from the brief.
2. `make check` is green after every phase.
3. `make local` provisions the app end-to-end.
4. `docs/` artifacts are coherent.
5. A manager can complete the full flow: log in → create store → create employee → assign to store → enter availability → create schedule → create shift requirements → assign employees → use `/agent` to inspect live scheduling data → publish.
6. An employee can log in and see only their published shifts for their assigned stores.
7. With no AI key in local/test, `/agent` streams deterministic fake responses while preserving the Laravel AI conversation flow.
