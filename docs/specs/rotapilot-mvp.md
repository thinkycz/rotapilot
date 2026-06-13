# RotaPilot MVP — Product Spec

## 1. Summary

RotaPilot is an AI-powered shift planning app for small businesses with multiple stores. Store managers describe staffing needs in natural language; RotaPilot turns that into an editable shift schedule grounded in store hours, employee availability, and employee-store assignments. Employees log in to read their published shifts.

The MVP proves one thing: a store manager can describe staffing needs in plain English, and RotaPilot produces a useful, editable schedule.

## 2. Personas and Permissions

### Admin

- Full access to all stores, managers, employees.
- Can create stores, assign store managers, assign employees to stores.
- Can publish any schedule.

### Store Manager

- Can only access stores they are assigned to in `store_manager_store`.
- Can create and edit employees assigned to their stores.
- Can enter employee availability for those employees.
- Can create schedules, shift requirements, and shift assignments for their stores.
- Can use the AI planner for their stores.
- Can publish schedules in their stores (blocked if there are critical conflicts).

### Employee

- Can only see their own published shifts for stores they are assigned to via `employee_store`.
- Cannot edit availability, shifts, or view the AI planner.
- Cannot see draft schedules or other employees' private availability.

## 3. Domain Model

13 tables. Pivots are explicit (not `BelongsToMany` hidden tables):

| Table                         | Purpose                                                                      |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `users`                       | Authentication. Adds `role` (admin/store_manager/employee) and `is_active`.  |
| `stores`                      | A business location. Has timezone, address, status.                          |
| `store_business_hours`        | One row per `(store_id, day_of_week)`. `is_closed` skips times.              |
| `store_manager_store`         | Many-to-many: managers ↔ stores.                                             |
| `employee_profiles`           | Person who can work shifts. May or may not have a `User` login.              |
| `employee_store`              | Many-to-many: employees ↔ stores.                                            |
| `employee_availabilities`     | Daily availability windows. `source` tracks who entered.                     |
| `schedules`                   | A planning period for a single store. Has status (draft/published/archived). |
| `shift_requirements`          | "Need N people on date D from start to end." The unit of planning.           |
| `shift_assignments`           | Employee assigned to a requirement.                                          |
| `schedule_conflicts`          | Detected problem for a schedule (understaffed, unavailable, etc.).           |
| `agent_conversations`         | (Laravel AI SDK) Chat sessions for the AI planner.                           |
| `agent_conversation_messages` | (Laravel AI SDK) Individual messages.                                        |

The original spec called for our own `ai_conversations` and `ai_messages` tables. We reuse the Laravel AI SDK's tables (`HasConversations` trait on `User`) and add `store_id`/`schedule_id` context on the conversation if needed.

## 4. Routing Style

All routes follow the project's flat-action, query-param convention:

```
GET  /{resource}/index
GET  /{resource}/show?id=
GET  /{resource}/create
POST /{resource}/store
GET  /{resource}/edit?id=
POST /{resource}/update?id=
POST /{resource}/destroy?id=
```

Verb-specific actions publish as additional `POST /{resource}/{action}?id=` endpoints (e.g. `POST /schedules/publish?id=`). No `{id}` path placeholders. No PUT/PATCH/DELETE.

## 5. Screen Inventory

| Path                                                    | Audience | Purpose                                                                                   |
| ------------------------------------------------------- | -------- | ----------------------------------------------------------------------------------------- |
| `/dashboard`                                            | all      | Role-aware: managers see scheduling stats and navigation; employees see their next shift. |
| `/stores/index`                                         | manager  | List of stores visible to the user.                                                       |
| `/stores/show?id=`                                      | manager  | Store detail with hours, managers, employees, recent schedules.                           |
| `/stores/create` and `/stores/edit?id=`                 | manager  | Create or edit a store.                                                                   |
| `/stores/business-hours?id=`                            | manager  | Weekly editor for business hours.                                                         |
| `/employees/index`                                      | manager  | Employee list with filters.                                                               |
| `/employees/show?id=`                                   | manager  | Profile, assigned stores, availability, upcoming shifts.                                  |
| `/employees/create` and `/employees/edit?id=`           | manager  | Create or edit an employee.                                                               |
| `/availability`                                         | manager  | Monthly availability grid; click a day to edit.                                           |
| `/schedules/index`                                      | manager  | List of schedules.                                                                        |
| `/schedules/show?id=`                                   | manager  | Calendar (week/month) with shift cards and side panel.                                    |
| `/schedules/create` and `/schedules/edit?id=`           | manager  | Create or edit a schedule.                                                                |
| `/agent`                                                | manager  | Conversational AI assistant with persisted chats and scoped scheduling tools.             |
| `/agent/stream`                                         | manager  | Streaming assistant endpoint used by the `/agent` page.                                   |
| `/agent/conversations/destroy`                          | manager  | Deletes the manager's own assistant conversations.                                        |
| `/agent/proposals/apply`                                | manager  | Applies the manager's own pending assistant proposal batch.                               |
| `/agent/proposals/reject`                               | manager  | Rejects the manager's own pending assistant proposal batch.                               |
| `/conflicts`                                            | manager  | Grouped conflicts with suggested fixes.                                                   |
| `/my-calendar`                                          | employee | Published shifts for the logged-in employee only.                                         |
| `/login` (existing)                                     | guest    | Sign in.                                                                                  |
| `/settings/profile` and `/settings/password` (existing) | all      | Personal settings.                                                                        |

## 6. AI Flow

1. Manager types a prompt in `/agent`.
2. `AgentStreamController` authorizes the manager and starts or continues the selected Laravel AI conversation.
3. `SchedulingAgent` answers using scoped tools for stores, employees, shifts, and availability.
4. When the manager asks for changes, `ProposeSchedulingChangesTool` stores a pending proposal attached to the conversation and does not mutate domain records.
5. The manager reviews the proposal card in `/agent` and applies or rejects the full batch.
6. Applying a proposal validates manager scope again, writes the batch transactionally, and reports detected conflicts afterward.
7. The UI streams plain text deltas, preserves line breaks, and renders escaped text only.
8. Conversations and messages are persisted so managers can return to prior chats.
9. Unknown or foreign conversation IDs are ignored and start a new manager-scoped conversation.

When no API key is configured in `local` or `testing`, `AppServiceProvider` fakes `SchedulingAgent` so the assistant remains deterministic and testable. Production-like environments should configure `OPENROUTER_API_KEY`; otherwise provider failures surface as a localized chat connection error.

## 7. Scheduling Logic

`ScheduleGeneratorService` and `AssignmentService` are deterministic. For each `shift_requirement`:

1. Get employees assigned to the store.
2. Filter by availability (block unavailable; allow within available/preferred windows; treat missing as not-assignable by default).
3. Exclude overlapping assignments (across all stores).
4. Sort by `max_hours_per_week` (asc), then by hours already scheduled in this period (asc).
5. Take the first N until `required_employee_count` is reached.
6. If fewer than N are assignable, create an `understaffed` conflict.

## 8. Conflict Types

| Type                     | Severity | Detected when                                                             |
| ------------------------ | -------- | ------------------------------------------------------------------------- |
| `understaffed`           | warning  | Fewer assignments than `required_employee_count`.                         |
| `unavailable_employee`   | critical | Assigned employee has an `unavailable` record covering the shift.         |
| `overlapping_shift`      | critical | Employee has two shifts whose time windows overlap.                       |
| `outside_business_hours` | warning  | Shift is outside the store's `store_business_hours`.                      |
| `max_hours_exceeded`     | warning  | Employee's total scheduled hours in the week exceed `max_hours_per_week`. |
| `missing_availability`   | info     | Employee has no availability record for the shift date.                   |

Publishing a schedule is blocked if any conflict is `critical`. Warnings and info can remain; the manager sees a confirmation.

## 9. Seed Data

- `admin@example.com` / `password` (role: admin).
- `manager@example.com` / `password` (role: store_manager, assigned to all 3 stores).
- `anna@example.com` / `password` (role: employee, has `EmployeeProfile` with `user_id`).
- `Peter Svoboda`, `Eva Dvorak`, `Martin Novak` — `EmployeeProfile` only, no login.
- 3 stores: `Teacha Prague Center`, `Teacha Westfield`, `Teacha Brno`.
- Business hours per the brief.
- Employee-store assignments per the brief.
- Current-month availability for every employee.
- One draft schedule for the current ISO week on `Teacha Prague Center` with the four conflict patterns (complete, multi-person, understaffed, conflict).
