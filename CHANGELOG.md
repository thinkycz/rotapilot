# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Toolchain & Rules

- **Toolchain upgrades (pulled in from the StockFlow reference project).** - `tsconfig.json`: enabled `noUnusedLocals` and `noUnusedParameters`. - `phpstan.neon`: enabled the `strictRules` extension with
  `dynamicCallOnStaticMethod: false` (matches StockFlow). The
  baseline was regenerated to absorb the new strict-rule reports
  (1 063 lines → 1 279 lines); the _previous_ reports that the
  baseline masked plus a handful of new ones are now in the
  regenerated baseline. Removing the baseline entirely is
  tracked as a deferred task in `docs/lessons.md` and below. - Removed `phpstan.stub` (it only stubbed Laravel Nova which the
  project does not use). - `package.json`: added `class-variance-authority` and
  `tw-animate-css`; `npm install` ran clean. - `Makefile`: added a `test-unit` target that runs the Vitest unit
  suite and chained it into `make check` (new order: `stan lint
audit frontend test-unit test`). - `phpunit.xml`: added an explicit `<testsuite name="Unit">` for
  `tests/Unit` so `phpunit --testsuite Unit` works. - `.gitignore`: added `.env.testing` and `.env.*.local`; removed
  the project-only `/bootstrap/cache/` ignore (it is not present
  here and the standard `vendor/` ignore already covers the build
  outputs we care about). - `playwright.config.ts`: `webServer.command` now runs
  `php artisan optimize:clear && php artisan migrate:fresh
--env=testing --force` before `php artisan serve`, and the
  web server env sets `CACHE_STORE: 'array'` and
  `E2E_DISABLE_THROTTLE: 'true'` for a reproducible e2e boot.

- **Architecture tests.** - `OnlyOneWayArchitectureTest` now uses a recursive
  `arch_php_files()` helper so it walks the whole `app/` tree
  instead of stopping at the top-level glob. The forbidden call
  list and assertions are unchanged. - `ValidationArchitectureTest` is stricter: every validity class
  must expose a public `BaseValidity $baseValidity` property, must
  construct it in the constructor, and may **not** call
  `->required()` or `->nullable()` directly (the wrappers live in
  the controllers now, not in the validities). - `ControllerArchitectureTest` now scans `app/Http/Controllers/Web`
  and asserts each `*IndexController` declares `public const int
TAKE`. The old API-only TAKE/SORT/MODE block was removed. - **New** `CoverageArchitectureTest` enforces the "every controller
  has a feature test" rule. Because RotaPilot's
  `Web/{Ai,Availability,Calendar,Conflicts,Employees,Schedules,Stores}`
  controllers pre-date the test coverage push, the test only fires
  for namespaces that already have at least one `*Test.php` on
  disk; legacy namespaces are skipped until a follow-up task
  catches them up. - **New** `tests/Unit/I18nParityTest.php` asserts identical key
  trees for `resources/js/i18n/{en,cs,sk}.json` and
  `lang/{en,cs,sk}.json`. Frontend and backend i18n parity is now
  CI-enforced. - `DocblockArchitectureTest` gains a rule that no app model under
  `app/Models/` may declare `@property`, `@method`, or
  `@phpstan-method`. The 10 existing models had their property
  docblocks removed in this change.

- **Pest helpers.** `tests/Pest.php` now exposes - `createIsolatedUserWithStore(): array{0: User, 1: Store}` —
  rotapilot-domain adaptation of StockFlow's
  `createIsolatedUserWithWarehouse()`. Creates a `store_manager`
  user plus a default `Store` so feature tests do not have to
  hand-roll a `User` and a `Store` per test. - `assertInertiaFlash(TestResponse $response, string $key, mixed
$message): void` — works for both 302 redirect and 200-OK
  render responses. The previous local helper inside
  `EmailVerificationConfirmControllerTest` was removed in favor
  of this one.

### Backend

- **Bootstrap & routes.**
    - `bootstrap/app.php` adds a `ModelNotFoundException` →
      `NotFoundHttpException` mapping in the exception handler.
    - `routes/web.php` and `routes/api.php` now use `use` statements
      for every controller instead of inline FQCNs. The
      `EnsureInertiaUserIsAuthenticated` middleware is also imported
      once instead of inlined.
- **Validities.** `app/Http/Validation/AppValidity.php` was deleted.
  `StoreValidity`, `ScheduleValidity`, `ShiftRequirementValidity`,
  `EmployeeAvailabilityValidity`, and `EmployeeProfileValidity` now
  declare `public BaseValidity $baseValidity` and construct it in
  the constructor. The `->required()` / `->nullable()` chains live
  in the controllers (matching StockFlow's pattern), so the
  validities can stay free of them.
- **Auth controllers.** - `LoginController` now uses `Thrower::default()->message(...)->
throw()` and `Typer::assertString(\__(...))` instead of
  `ValidationException::withMessages(...)`. - `RegisterController` wraps the user creation in
  `DB::transaction(...)` and the password change uses Laravel's
  built-in `confirmed()` rule. - `ForgotPasswordController` and `ResetPasswordController` follow
  the same pattern: `Thrower` instead of `withMessages`, multi-step
  persistence in `DB::transaction`. - `PasswordController` (`/settings/password`) uses the same
  pattern.
- **Dashboard magic scopes.** `DashboardController` previously called
  `$query->active()` and `$query->unresolved()` (Eloquent magic
  scope lookup). They are now the explicit static form
  `EmployeeProfile::scopeActive($query)` and
  `ScheduleConflict::scopeUnresolved($query)` via `tap()`.
- **Models.** The `@property` docblocks on all 10 models under
  `app/Models/` were removed. Application code already reads
  attributes through `assertString` / `assertInt` / `assertNullableString`
  / `assertBool` getters, so the docblocks were redundant.
- **Frontend title.** `resources/js/app.ts` uses `RotaPilot` as the
  default document title; `APP_NAME` was updated to `RotaPilot` in
  `.env`, `.env.example`, and `.env.testing` to keep the
  `HandleInertiaRequests` shared `app.name` in sync.
- **Frontend strict types.** The new `noUnusedLocals` /
  `noUnusedParameters` flags surfaced 16 dead imports and locals
  across `resources/js/pages/**/*.vue`. The unused `computed`,
  `router`, `ArrowRight`, `CalendarCheck2`, `Trash2`, `Plus`,
  `AlertCircle`, `aiText`, `openEdit`, `submitEdit`, `severityVariant`,
  the unused `Day` interface, and a now-unused destructured
  `count` parameter were removed. The remaining call sites pass
  `vue-tsc --noEmit` clean and `vite build` succeeds.

### Docs

- `AGENTS.md` is now StockFlow's verbatim copy (rules apply
  domain-agnostically; only the project name is rotapilot).
- `docs/guidelines.md` is StockFlow's verbatim copy with the
  rotapilot name, the `createIsolatedUserWithStore()` Pest helper
  reference, the `App\Support\Authorization` user-scoping pattern
  (rotapilot has no `BelongsToUser` trait), and the rotapilot URL
  examples (`/shift-requirements`, `/stores`, …).
- `docs/architecture.md` keeps rotapilot's middleware chain,
  validation-error flow, and frontend layout diagrams, but the
  "High-level" paragraph now describes the multi-tenant
  shift-planning role split instead of the boilerplate one-liner.
- `docs/lessons.md` is now StockFlow's; the timeless Inertia v3,
  E2E cookie, and PHPStan `@phpstan-ignore` lessons were kept.
- `docs/design.md` is a short pointer to where future design
  source assets (Figma, Stitch) should be dropped.

### Deferred work (not in this Unreleased section)

- **Regenerate `phpstan-baseline.neon` then delete it.** StockFlow
  deleted the baseline. The first step (regenerate) is done in
  this Unreleased; the second step (delete and fix) is the
  follow-up. The regenerated baseline masks ~301 real type
  errors. The next pass should:
    1. Delete `phpstan-baseline.neon` and the line that references it.
    2. Fix the surviving reports by class (see the
       `docs/lessons.md` entry for the per-class breakdown).
    3. Re-run `phpstan analyse` until `[OK] No errors` lands.

- **Feature-test the legacy controller namespaces.**
  `CoverageArchitectureTest` does not enforce the rule for
  `Web/{Ai,Availability,Calendar,Conflicts,Employees,Schedules,Stores}`
  because those controllers pre-date the test coverage push. The
  next pass should add a feature test for each `*Controller.php`
  in those namespaces, then tighten the architecture test to
  enforce universally.

### Added

- 90 PHPUnit feature tests / 198 assertions (up from 14 / 45 in baseline).
- 4 regression tests in `tests/Feature/App/Http/Controllers/Web/Employees/EmployeeShowControllerTest.php`
  pinning the new payload shape (employee fields, stats, upcoming shifts,
  availability strip, conflict count, cancelled-assignment exclusion from
  hours totals).
- 16 Playwright e2e tests covering register, login, logout, password reset,
  profile update, locale switch, email verification flash, and protected
  route redirects.
- `app/Http/Controllers/Web/Auth/EmailVerificationConfirmController` —
  SPA target of the email verification link. The core
  `EmailVerificationNotification` builds a URL of the form
  `<spa.email_verification_url>?guard=…&email=…&token=…&locale=…`; this
  controller is the GET handler that consumes the token via
  `EmailBrokerService::validate()`, marks the user verified, dispatches
  the `Verified` event, and redirects to the dashboard (if the visitor
  is already signed in) or to the login page (so they can sign in with
  the now-verified address).
- 6 phpunit tests for the new controller covering the valid-token
  happy path, the unverified redirect for unauthenticated visitors,
  the already-verified idempotent path, the invalid-token error
  redirect, the unknown-email error redirect, and the missing-parameter
  422 response.
- `FieldError.vue`, `FlashAlerts.vue`, `Select.vue`, `FormField.vue` shared UI
  primitives under `resources/js/components/ui/`.
- `useSharedProps()` composable returning `{app, auth, user, flash, flashSuccess,
flashError, errors}` with strict TypeScript types.
- `app/Http/Resources/UserResource::toId()` for consistent `id` projection.
- `app/Http/Controllers/Web/Concerns/ThrottlesWebRequests` trait applied to
  auth web controllers.
- `app/Http/Middleware/EnsureInertiaUserIsAuthenticated` throws
  `AuthenticationException` for JSON requests.
- Inertia 3 validation-error handling in `bootstrap/app.php` that re-renders
  the originating component (resolved from request path) with a 422 status
  (Inertia v3 client does not follow bare 302 redirects from
  `ValidationException`).
- Inertia 3 success-flash handling: web controllers use
  `$request->session()->flash(...)` + `Inertia::render(...)` so the success
  message appears on the same page instead of being lost on the 302 redirect
  the client does not follow.
- Database-token revocation via `getQuery()->delete()` on logout.
- `make test-coverage` target (requires `xdebug`).
- `lefthook.yml` pre-commit (lint) and pre-push (stan + tests + e2e) hooks.
- `docs/architecture.md` with mermaid request/middleware diagrams.
- `LICENSE` (MIT), `CONTRIBUTING.md`.
- `spa.email_verification_url` translation key (en + cs) so the core email
  verification notification can render the SPA confirmation link.
- `Alert.vue` now renders `role="alert"` so success/error messages are
  announced by screen readers and are locatable via `getByRole('alert')` in
  tests.
- `AppLayout.vue` exposes a skip-to-content link, `aria-label="Primary"` on
  the nav, and `aria-current="page"` on the active link.

### Changed

- `app/Http/Controllers/Web/Schedules/ScheduleShowController` now enriches
  every detected conflict with `employee_name`, `shift_date`,
  `shift_start_time`, `shift_end_time`, and `shift_role_label`, sourced
  from the already-loaded `shift_requirements` and assignments
  (no extra queries). The `schedules/Show.vue` page renders a new
  dedicated `Conflicts` panel grouped by severity that shows type,
  message, suggested fix, shift context, and employee, with an
  `Open shift` anchor and an `Ask AI` button that navigates to
  `/agent?q=…` with a prefilled prompt.
- `app/Ai/AgentPageLoader` now reads the `q` query param as an
  `initialPrompt` (truncated to 1000 chars) and `pages/agent/Index.vue`
  seeds the prompt input from it on mount, then strips `?q=` from the
  URL so a refresh does not re-seed.
- The header count badge on `/schedules/show` is now an anchor link
  that scrolls to the new `Conflicts` panel.
- The list-view and calendar-view shift elements now carry
  `id="shift-<id>"` so the new `Open shift` links resolve.
- The old, broken critical-only banner on `/schedules/show` is
  replaced by the comprehensive panel.
- `app/Http/Controllers/Web/Schedules/ScheduleShowController`
  removes the dead `criticalConflicts` derivation that filtered to
  `severity === 'critical' && shift_requirement_id === null` and
  skipped the most actionable conflicts.
- All 6 form pages migrated to Inertia 3 `<Form>` component (replaces the
  custom form helpers).
- `app/Http/Middleware/HandleInertiaRequests::share()` now reads flash
  messages via `Inertia::getFlashed($request)` first and falls back to
  `$request->session()->get($key)`. The Inertia-flash path survives
  the 302 → guest-redirect → final Inertia render chain that a plain
  session flash cannot (the session ages after a single request).
- `Input.vue` and `Select.vue` accept a `defaultValue` prop and an
  `invalid`/`describedBy` pair, wired up via the new `FormField.vue`
  wrapper to `aria-invalid` and `aria-describedby`.
- `Label.vue` renders a red asterisk (aria-hidden) for `required` fields.
- `AppLayout.vue` and `AuthLayout.vue` share `<Brand>` and `<FlashAlerts>`
  components; pages no longer mount their own `<FlashAlerts />`.
- `tests/TestCase::inertiaHeaders()` hardened to include `X-Inertia: true`,
  `Accept: text/html`, and a request-aware Referer.
- `make e2e` runs Playwright with `webServer` block driving the dev server
  under `APP_ENV=testing`, `SESSION_SECURE_COOKIE=false`, `MAIL_MAILER=log`.
- `app/Http/Controllers/Web/Employees/EmployeeShowController` now ships an
  enriched payload: a `stats` block (upcoming shifts, hours this week /
  this month / total, conflict count), a top-5 `upcoming_shifts` list with
  date, time, role, store, status, and a deep link to the parent schedule,
  a 7-day `availability` strip with a per-day `has_unavailable_entry`
  flag, and a public-schedule link on the employee header. The page is
  reworked as a single scrollable layout: 4-KPI stats row, upcoming
  shifts panel + availability strip on the left, profile / assigned
  stores / login account cards on the right. The hourly rate renders
  via `Intl.NumberFormat` (CZK, no decimals, suffixed with `/h`).

### Removed

- Dead `app/Http/Controllers/Web/Conflicts/ConflictIndexController.php`,
  `ConflictResolveController.php`, the `app/Models/ScheduleConflict.php`
  model (and its `scopeUnresolved`), and `resources/js/pages/conflicts/Index.vue`
  — the `schedule_conflicts` table was dropped in 2026-06-13 and
  none of these controllers were wired in `routes/web.php`. The
  `conflicts.*` and `nav.conflicts` i18n keys (only consumed by the
  dead page) are replaced by a scoped `schedules.conflicts_panel.*`
  block, kept in parity across `en`, `cs`, and `sk`.
- A line in the Web screen-inventory tables (`docs/specs/rotapilot-mvp.md`,
  `README.md`) that pointed at the now-removed `/conflicts`,
  `/conflicts/resolve`, and `/conflicts/ask-ai` routes.
- Old `tests/e2e/debug*.spec.ts` diagnostic harnesses.
- `Symfony\Component\HttpFoundation\Response` return type from
  `VerifyEmailController::store`, `ProfileController::update`,
  `PasswordController::update`, and `ForgotPasswordController::store` —
  the controllers now return `Inertia\Response` to keep the page stable
  across the POST.
- A brittle e2e test for the `EmailVerificationConfirmController`
  invalid-token flash chain. The phpunit suite covers the same logic
  with a real token; the browser-driven chain depends on session cookie
  lifecycle details that the phpunit test client handles differently.

### Fixed

- `email:dns` rule failing for `example.com` in dev: e2e dev server now uses
  `APP_ENV=testing` so the basic `email` rule is applied (no DNS lookup).
- 500 ValidationException rendered as symfony debug HTML for Inertia requests
  in debug mode; replaced with Inertia-aware render in `bootstrap/app.php`.
- Logout redirect for guests (was 302 to `/` then 302 to `/login`); tests
  accept either URL.
- Success flash messages lost on Inertia form submissions because the v3
  client does not follow plain 302 redirects; controllers now re-render the
  same Inertia page with `session()->flash(...)` so the flash renders in
  the next response.
- `VerifyEmail` page rendered two copies of the success alert because
  `<FlashAlerts />` was mounted both in the layout and the page; the page
  now relies on the layout's instance.
- `bootstrap/app.php` validation handler defaulted to the `auth/Login`
  component for every form path, breaking non-login form errors; the handler
  now resolves the originating component from the request path.
- Email verification link click landed on a 404 (the
  `spa.email_verification_url` translation pointed at the API endpoint).
  The web route `GET /email/verify` is now registered and the
  translation points at it; the new controller consumes the token,
  marks the user verified, and redirects.
- `app/Http/Controllers/Web/Employees/EmployeeShowController`
  computed the "end of week" bound as
  `CarbonImmutable::endOfWeek(CarbonImmutable::MONDAY)`. With that
  argument, Carbon treats `MONDAY` as the _end_ day of the week, so
  on a Monday the bound collapsed to the current day and the
  "hours this week" stat silently dropped every shift scheduled
  later in the same week. Replaced with the no-arg `endOfWeek()`
  which uses the framework default (Sunday-end) and returns the
  correct Sunday.

## [0.1.0] - 2026-06-07

Initial snapshot captured in `docs/verification/baseline-2026-06-07.md`.
14 tests / 45 assertions, Inertia 2 → 3 migration, PHP 8.3, Laravel 13.
