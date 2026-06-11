# RotaPilot

RotaPilot is an Inertia-first Laravel 13 shift planner for small
multi-store businesses. Store managers create stores, employees,
availability windows, and shift requirements. Employees log in to view
their published shifts. The AI planner turns natural-language prompts
into editable shift requirements. Vue 3 + TypeScript + Tailwind on the
frontend, the Thinkycz Laravel core database-token guard underneath.

## Development

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

## Checks

```sh
make fix       # Prettier + Pint auto-format
make check     # PHPStan + lint + audit + frontend type-check + build + tests
make local     # provision the dev environment end-to-end
```

## Routes

- Auth: `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`
- `/dashboard` (role-aware)
- Settings: `/settings/profile`, `/settings/password`
- Stores: `/stores/index`, `/stores/create`, `/stores/store`, `/stores/show`, `/stores/edit`, `/stores/update`, `/stores/destroy`, `/stores/business-hours`
- Employees: `/employees/index`, `/employees/create`, `/employees/store`, `/employees/show`, `/employees/edit`, `/employees/update`, `/employees/destroy`, `/employees/stores/store`, `/employees/stores/destroy`
- Availability: `/availability`, `/availability/store`, `/availability/update`, `/availability/destroy`, `/availability/parse-ai`
- Schedules: `/schedules/index`, `/schedules/create`, `/schedules/store`, `/schedules/show`, `/schedules/edit`, `/schedules/update`, `/schedules/destroy`, `/schedules/publish`, `/schedules/archive`
- Shift requirements: `/shift-requirements/store`, `/shift-requirements/update`, `/shift-requirements/destroy`, `/shift-requirements/auto-fill`
- Shift assignments: `/shift-assignments/store`, `/shift-assignments/destroy`
- Employee self-service: `/my-calendar`
- AI planner: `/ai-planner`, `/ai-planner/message`, `/ai-planner/apply-preview`
- Conflicts: `/conflicts`, `/conflicts/resolve`, `/conflicts/ask-ai`

Minimal API-compatible auth endpoints remain under `/api/v1/auth`,
`/api/v1/me`, `/api/v1/password`, and `/api/v1/email_verification`.

Application documentation is maintained in
[docs/application_documentation.md](docs/application_documentation.md).
Architecture overview in [docs/architecture.md](docs/architecture.md).
Coding rules in [AGENTS.md](AGENTS.md) and
[docs/guidelines.md](docs/guidelines.md).
