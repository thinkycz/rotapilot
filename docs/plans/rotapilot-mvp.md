# RotaPilot MVP — Implementation Plan

(See `docs/specs/rotapilot-mvp.md` for the product spec.)

## Decisions

- **Project conventions win.** Honor `AGENTS.md` and `docs/guidelines.md`: flat-action REST routes with `?id=` query params, GET/POST only, `ApiFormRequest + SymfonyResponse`, JSON:API resources, invokable controllers, no `app/Http/Requests` or `app/Http/Validation` folders, `Resolver::resolve*` helpers, `Trans::inject`, mandatory `declare(strict_types=1)` + docblocks, mandatory architecture tests stay green.
- **Extend the existing `ui/` kit** (teal primary, light theme). No shadcn-vue dep.
- **Flat routes** with `?id=`.
- **Sync AI parse + queue only the heavy Apply step.** Uses `laravel/ai` SDK with `HasConversations` on `User`. Bound `SchedulePlannerFakeAgent` when no API key.
- **Build all listed components from scratch** (no extra deps).
- **Provisioning is part of the plan** (composer + npm + sqlite + seeders).

## Stack

- Laravel 13, PHP 8.3.
- Inertia 3, Vue 3 (`<script setup lang="ts">`), TypeScript, Tailwind 4, Vite.
- Local package `thinkycz/laravel-core` (provides `BaseModel`, `Resolver`, `Env`, `AuthValidity`, etc.).
- `laravel/ai` SDK for the planner/parser/agent agents.
- SQLite for dev (configured in `.env`); MySQL config retained in `.env.example`.

## Phases

| #   | Phase                                                          | Done when                                                                                     |
| --- | -------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| 0   | Provisioning + docs                                            | `make check` green; `docs/specs/`, `docs/plans/`, `docs/progress/` populated.                 |
| 1   | Roles + User updates                                           | `User` has `role` + `is_active`; `RoleEnum`; login redirects to role-aware dashboard.         |
| 2   | Stores + Business Hours                                        | CRUD, weekly editor, manager-store pivot, seed stores.                                        |
| 3   | Employees + Employee-Store + Availability                      | CRUD, attach/detach stores, monthly availability grid + AI parser stub.                       |
| 4   | Schedules + Shift Requirements + Shift Assignments + Conflicts | Calendar UI, manual assignment, conflict detection (5 types).                                 |
| 5   | Employee calendar                                              | `/my-calendar` shows only the logged-in employee's published shifts.                          |
| 6a  | laravel/ai SDK + fake agent                                    | Composer dep, `HasConversations` on User, `SchedulePlannerFakeAgent`, `AiPreviewMapper`.      |
| 6b  | Real AI agents + planner page                                  | Real `SchedulePlannerAgent`, `AvailabilityParserAgent`, `ConflictExplainerAgent`; planner UI. |
| 7   | Apply AI preview (queue)                                       | `ApplyAiPreviewAction` job, conflicts regenerated.                                            |
| 8   | Conflicts page + Ask AI per conflict                           | `/conflicts` page with grouped cards and per-conflict Ask-AI.                                 |
| 9   | UI polish                                                      | Empty states, loading states, responsive tables, role-gated sidebar.                          |
| 10  | Final verification                                             | `make fix && make check` green; manual smoke run.                                             |

Each phase ends with `make check` green.

## AI Architecture (final)

- `app/Ai/Agents/SchedulePlannerAgent.php` — `implements Agent, HasStructuredOutput`, constructor takes `User, Store, CarbonImmutable periodStart, CarbonImmutable periodEnd, Collection employees, Collection businessHours, ?Schedule existingSchedule`. `instructions()` interpolates them. `schema(JsonSchema)` returns the discriminated intent.
- `app/Ai/Agents/SchedulePlannerFakeAgent.php` — implements same contract with deterministic return.
- `app/Ai/Agents/AvailabilityParserAgent.php` — structured output for the `availability/parse-ai` endpoint.
- `app/Ai/Agents/ConflictExplainerAgent.php` — small structured output for the per-conflict Ask-AI.
- `app/Support/Ai/AiPreviewMapper.php` — pure mapper from the SDK's structured array to `AiPreview` DTO, enforcing guardrails.
- `app/Jobs/ApplyAiPreviewAction.php` — queued job that consumes `AiPreview` and rebuilds the schedule transactionally.
- `app/Http/Controllers/Web/Ai/{PlannerIndexController, PlannerMessageController, PlannerApplyPreviewController, AvailabilityParseController, ConflictAskAiController}.php`.

`AppServiceProvider::boot()`:

- Detect first available provider from `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `GEMINI_API_KEY`.
- Bind the real `SchedulePlannerAgent` to its FQCN when a key exists, otherwise bind the fake.

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
POST   /availability/parse-ai
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
GET    /ai-planner?store_id=&schedule_id=
POST   /ai-planner/message
POST   /ai-planner/apply-preview
GET    /conflicts
POST   /conflicts/resolve?id=
POST   /conflicts/ask-ai?id=
GET    /my-calendar
```

## Enums (`app/Enums/`)

- `UserRoleEnum` (admin, store_manager, employee).
- `AvailabilityTypeEnum` (available, unavailable, preferred).
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
- Unit: scheduling services, AI mapper, fake agent.
- Architecture: untouched (must stay green).

## Definition of Done

1. All 15 success criteria from the brief.
2. `make check` is green after every phase.
3. `make local` provisions the app end-to-end.
4. `docs/` artifacts are coherent.
5. A manager can complete the full flow: log in → create store → create employee → assign to store → enter availability → create schedule → create shift requirements → assign employees → see conflicts → ask AI for a preview → apply it → publish.
6. An employee can log in and see only their published shifts for their assigned stores.
7. With no AI key, the app behaves identically except the AI preview is served by the fake agent.
