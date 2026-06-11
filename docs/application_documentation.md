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

RotaPilot is an AI-powered shift planner for small multi-store businesses. Store managers create stores, employees, availability, and shift requirements. Employees log in to view their published shifts. The AI planner turns natural-language prompts into editable shift requirements.

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
    - `/stores/*` (admin/manager)
    - `/employees/*` (admin/manager)
    - `/availability` (admin/manager)
    - `/schedules/*` (admin/manager)
    - `/shift-requirements/*`, `/shift-assignments/*` (admin/manager)
    - `/ai-planner` (admin/manager)
    - `/conflicts` (admin/manager)
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
- `User` model has the `role` column (`admin`/`store_manager`/`employee`).
- `User` model uses `Laravel\Ai\Concerns\HasConversations` for AI planner chat storage.

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
- AI provider keys (one or more of): `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `GROQ_API_KEY`, `MISTRAL_API_KEY`, `DEEPSEEK_API_KEY`, `XAI_API_KEY`, `OPENROUTER_API_KEY`, `OLLAMA_API_KEY`
- Optional: `AI_DEFAULT_PROVIDER` (`openai` / `anthropic` / `gemini` / etc.), `AI_DEFAULT_MODEL`

When no provider key is set, `AppServiceProvider` binds `SchedulePlannerFakeAgent` so the planner page works end-to-end with deterministic sample output.

## Cron jobs

- Standard Laravel scheduler for queued jobs, broadcasts cleanup, etc. (In production.)

## Queue workers

- `php artisan queue:work --queue=default` for the `ApplyAiPreviewAction` job and any future async work.

## Deferred

- Inertia SSR is intentionally not enabled in v1.
- The old reference catalog/order sample domain is intentionally omitted.
- Payroll, mobile app, notifications, vacation approvals, employee shift swaps, multi-tenant billing are out of MVP scope.
