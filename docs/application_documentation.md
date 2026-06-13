# Application documentation

## Tech stack

- PHP 8.3
- Laravel 13
- Inertia 3
- Vue 3 with TypeScript
- Tailwind 4
- Composer 2
- Node 22 or newer recommended for Vite tooling
- SQLite for local dev (MySQL configured in `.env.example` for production)
- `laravel/ai` for AI agents (OpenAI, Anthropic, Gemini, Groq, Mistral, etc.)

## Packages

| package                     | description                                                      |
| --------------------------- | ---------------------------------------------------------------- |
| `thinkycz/laravel-core`     | internal Laravel core package                                    |
| `inertiajs/inertia-laravel` | Laravel server adapter for Inertia                               |
| `laravel/ai`                | Laravel AI SDK (agents, structured output, conversation storage) |
| `@inertiajs/vue3`           | Vue client adapter for Inertia                                   |
| `@inertiajs/vite`           | Inertia Vite integration                                         |
| `@lucide/vue`               | icon set                                                         |
| `vue`                       | frontend framework                                               |
| `tailwindcss`               | styling system                                                   |
| `clsx`                      | class-name helper                                                |
| `tailwind-merge`            | class-name merge helper                                          |

## Domain overview

RotaPilot is an AI-assisted shift planner for small multi-store businesses. Store managers create stores, employees, availability, schedules, shift requirements, and assignments. Employees log in to view their published shifts. The `/agent` assistant answers manager questions about stores, employees, shifts, and availability by using scoped Laravel AI tools over live application data.

Tables: `users`, `stores`, `store_business_hours`, `store_manager_store`, `employee_profiles`, `employee_store`, `employee_availabilities`, `schedules`, `shift_requirements`, `shift_assignments`, `schedule_conflicts`, plus Laravel AI SDK's `agent_conversations` and `agent_conversation_messages`.

## Runtime services

- SQLite for dev (zero-config). MySQL 8 for production.
- File cache/session in dev; Redis in production.
- Queue: `sync` in dev/tests, `database` in production.
- Cron for Laravel scheduler (in production).
- Supervisor for queue workers (in production).

## HTTP surfaces

- Inertia web app (auth + role-gated):
    - `/login`, `/register`, `/forgot-password`, `/reset-password`
    - `/dashboard` (role-aware)
    - `/verify-email`
    - `/settings/profile`, `/settings/password`
    - `/stores/*` (manager)
    - `/employees/*` (manager)
    - `/availability` (manager)
    - `/schedules/*` (manager)
    - `/shift-requirements/*`, `/shift-assignments/*` (manager)
    - `/agent` (manager)
    - `/my-calendar` (employee)
- Minimal API compatibility (unchanged from starter):
    - `/api/v1/auth/*`
    - `/api/v1/me/*`
    - `/api/v1/password/*`
    - `/api/v1/email_verification/*`

## Authentication

- Default guard: `users`.
- Guard driver: `database_token`.
- Login/register issue an HTTP-only bearer cookie through `Thinkycz\LaravelCore\Guards\DatabaseTokenGuard`.
- Inertia pages receive the current user through `HandleInertiaRequests` shared props.
- Web form submissions use Laravel redirects, validation errors, and flash messages.
- `User` model has the `role` column (`store_manager`/`employee`).
- `User` model uses `Laravel\Ai\Concerns\HasConversations` for AI assistant chat storage.

## Cookies

| name pattern                                      | description              |
| ------------------------------------------------- | ------------------------ |
| `{app_name}_{env}_database_token_users`           | local bearer token       |
| `__Host-{app_name}_{env}_database_token_users`    | non-local bearer token   |
| `{app_name}_{env}_session` / `__Host-..._session` | Laravel session/CSRF use |

## Tooling

| command              | description                          |
| -------------------- | ------------------------------------ |
| `composer run dev`   | Laravel server, queue, logs, Vite    |
| `npm run dev`        | Vite development server              |
| `npm run type-check` | Vue TypeScript check                 |
| `npm run build`      | production frontend build            |
| `composer test`      | Laravel test suite                   |
| `make fix`           | Prettier and Pint formatting         |
| `make check`         | static analysis, lint, audit, tests  |
| `make local`         | provision dev environment end-to-end |

## Env

Copy `.env.example` to `.env` and set:

- `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`
- `DB_CONNECTION` (sqlite for dev), `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `TRUSTED_PROXIES`
- AI provider key for the assistant: `OPENROUTER_API_KEY`
- Optional assistant model: `OPENROUTER_MODEL` (defaults to `anthropic/claude-sonnet-4.6`)

The assistant defaults to the OpenRouter provider. In `local` and `testing`, when `OPENROUTER_API_KEY` is empty, `AppServiceProvider` fakes `SchedulingAgent` through Laravel AI's fake gateway so chat streaming and conversation persistence remain testable without outbound HTTP. In production-like environments, configure `OPENROUTER_API_KEY`; otherwise the provider call will fail and the chat UI shows a localized connection error.

Assistant write requests use confirmed proposals. The model may create a pending proposal for store, availability, shift, assignment, unassignment, safe delete, or auto-fill changes, but production data changes only after the manager clicks Apply in `/agent`. Proposal application is transactional and reports schedule conflicts after successful writes.

## Cron jobs

- Standard Laravel scheduler for queued jobs, broadcasts cleanup, etc. (In production.)

## Queue workers

- `php artisan queue:work --queue=default` for future async work. The current `/agent` assistant streams responses synchronously.

## Deferred

- Inertia SSR is intentionally not enabled in v1.
- The old reference catalog/order sample domain is intentionally omitted.
- Payroll, mobile app, notifications, vacation approvals, employee shift swaps, multi-tenant billing are out of MVP scope.
