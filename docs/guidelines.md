# Laravel Core Boilerplate Guidelines

## Enums

- Generate enums using the `make:enum` command

## Consistency

- Code must be consistent within the project and with internal libraries, boilerplates, and reference implementations

## Makefile

- Project must use `Makefile` for development, checking, minification, fixing code, and building applications in all environments (`testing|local|development|staging|production`)

## Linters and Static Analysis

- Code must pass static analysis and all linters defined in the project
- Developers must not modify or lower PHPStan levels and other linters
- Running `make check` must not produce warnings or errors

## Tests

- All controllers must be tested
- 100% coverage required for all success paths
- Error path testing is not mandatory
- Every controller must have at least one feature test

## Code Checking

- Developer must not commit code that doesn't pass `make local testing development staging production check` for APP_ENV="local|testing|development|staging|production"
- Before each commit, run `make fix check` to ensure properly formatted code and no linter errors

## Editorconfig

- Code must follow formatting defined in `.editorconfig`

## API Documentation

- Inertia-first projects do not require OpenAPI by default; if API documentation is added later, generated artifacts must not be manually edited

## Client Communication

- Input is always `multipart/form-data`, never `json` or other alternatives (because binary files can't be sent via `application/json`)
- Output is always `application/json`

## JSON to multipart/form-data Conversion

- Boolean: `true` → `'1'`, `false` → `'0'`
- Int/Float: Send as strings
- Null: Send as empty string `''`
- Arrays/Objects: Send in exploded form
- Empty arrays/objects: Send as empty string `''`

## Application Documentation

- Maintain current application documentation in `docs/application_documentation.md` or as defined in README.md
- Should include tech stack, server hardware/software requirements, packages, tooling, languages, services, ENV description, emails/notifications, cookies, cron jobs, queue workers

## PSR Standards

- Follow basic PSR rules: PSR1, PSR2, PSR4, PSR12, PSR5, PSR19

## Naming Conventions

- Classes should have type suffixes: `Controller`, `Action`, `Request`, `Resource`, `Service`, `Validity`
- Models exclude `Model` suffix
- Traits have `Trait` suffix
- Interfaces have `Interface` suffix
- Abstract classes have `Abstract` prefix
- Final classes have `Final` prefix
- Enums have `Enum` suffix
- Class names in singular: `UserStoreController.php`, `UserRoleEnum.php`

## ENV Naming

- Keys in `SCREAMING_SNAKE_CASE`: `APP_NAME='Laravel'`

## Config File Naming

- Files in `snake_case` plural: `config/google_services.php`
- Subfolders in `snake_case` plural
- Keys in `snake_case`

## Translation File Naming

- Files in `snake_case` plural
- Subfolders in `snake_case` plural
- Keys in `snake_case`

## Blade Naming

- Files in `snake_case` singular
- Subfolders in `snake_case` plural: `resources/views/pages/about_us.blade.php`

## Table Naming

- Tables in plural `snake_case`: `blog_posts`
- Pivot tables in singular alphabetical `snake_case`: `role_user`
- Columns in `snake_case`: `users.full_name`
- Foreign keys in `snake_case` with `_id` suffix: `groups.user_id`

## URL Format

- URLs in `snake_case` plural: `/api/v1/email_verification/resend`

## Flat URLs

- Endpoints written flat without nesting relations
- Wrong: `GET /api/v1/users/1/notifications`
- Right: `GET /api/v1/notifications/index?filter[user_id]=1`

## Route Params and Slugs

- Don't use route params, use query params instead: `GET /api/v1/users/show?id=1` instead of `GET /api/v1/users/1`

## HTTP Methods

- Only use `GET` and `POST` methods
- Replace `PUT`, `PATCH`, `DELETE` with `POST` and postfix: `POST /api/v1/users/update?id=1`

## Basic Endpoint Structure

- No endpoints on root (ending with `/`)
- Index: `GET /api/v1/{models}/index`
- Show: `GET /api/v1/{models}/show?id=number && GET /api/v1/{models}/show?slug=string`
- Store: `POST /api/v1/{models}/store`
- Update: `POST /api/v1/{models}/update?id=number`
- Destroy: `POST /api/v1/{models}/destroy?id=number`
- Attach: `POST /api/v1/{modelas_modelbs}/store`
- Detach: `POST /api/v1/{modelas_modelbs}/destroy`

## Test Naming

- Test classes named after tested class with `Test` suffix
- Same namespace as tested class: `tests/Feature/App/Http/Controllers/Api/Auth/RegisterControllerTest.php`

## Controller Naming

- Model name + action + `Controller` suffix
- Preferred actions: index, show, store, update, destroy
- Example: `UserStoreController.php`

## Route Naming

- Don't use route names, use `Resolver::resolveUrlFactory()->action(Controller::class)`

## Function Naming with "Must"

- Functions that throw errors instead of fallback/null have `must` prefix: `mustResolveUser(): User`

## Getting Data from ENV

- Use typed methods from `Thinkycz\LaravelCore\Support\Env` and `Typer`
- Never hardcode keys/passwords/SMTP/database settings in code/config files
- Forbidden to use `env()` function outside config files

## Getting Data from Config Files

- Use type-checked methods from `Thinkycz\LaravelCore\Support\Config` and `Typer`

## Getting Data from Translation Files

- Use typed methods that check translation presence and throw errors if missing

## Functional Annotations

- Don't use phpdocblocks that affect functionality (removed by opcache)

## Docblocks

- Don't use phpdocblocks for `@param`, `@property`, `@method` - use getters/setters with runtime type checks

## Throwing Errors

- Errors with status >= 400 must use `Symfony\Component\HttpKernel\Exception\HttpException()`
- Each error must have unique code tracked in `ClientErrorEnum`

## Request Attribute Naming

- Attributes in `snake_case`

## Route Parameters

- Route parameters are forbidden

## Transactional Request Processing

- Code modifying database must run in transactional mode

## Strict Request Validation

- Request must reject all data not defined in `rules()` function
- Reference: SecureFormRequest, SignedRequest

## JSON:API

- Application must follow JSON:API standard (https://jsonapi.org/)
- Reference: JsonApiResource, JsonApiCollectionResponse, ModelJsonApiResource

## Request Validation

- Attributes validated only in dedicated request classes
- Rules come from validation classes created with `artisan make:validity`
- Validity defines rules except uniqueness and partiality
- Uniqueness handled at controller level for throttling

## String Interpolation

- Use string interpolation instead of concatenation and printf: `"{$id}|{$bearer}"`

## Artificial IDs

- All tables must have artificial primary key
- Never use other columns like email, ID numbers, or composite primary keys

## Controller Response

- Only allowed return type is SymfonyResponse: `public function __invoke(RegisterRequest $request): SymfonyResponse`

## Store Controller Response

- Store controller returns generic ModelJsonApiResource instead of specific index/show resource
- For multiple models, return JsonApiCollectionResponse

## Update and Destroy Controller Response

- Update and destroy controllers must return 204 No Content

## Mandatory Static Typing

- Static typing is mandatory where possible

## Idempotent Seeder

- Seeders must be repeatable without producing changes on repeated calls
- Use `firstOrCreate`, environment checks

## Composer Dev Dependencies

- Dev dependencies only installed in `development` environment
- Code must handle missing dev dependencies in `staging` and `production`

## Controller Folders and Namespaces

- Controllers not in `app/Http/Controllers` root
- Subfolders by type: `Api|Admin|Web`
- API for API endpoints, Admin for admin interfaces, Web for HTML endpoints

## Invocable Controllers

- Controllers must be invocable (single `__invoke` method)

## Authorization and Validation

- Authorization and validation performed in controller, not in Request class

## On-demand Dependency Injection

- Don't use constructor/method DI, use `Resolver::resolve*()` methods

## Contracts

- Imported classes from `Illuminate\Contracts` must have `Contract` suffix: `AuthenticatableContract`

## Down Migration

- Skip `down()` method in migrations

## Comments

- Every property, method, function must have comments
- All must have docblocks

## Throttling

- Throttle at controller level after validation
- Failed validation must not increase throttle hits

## Request Data

- Access request data outside request class using `validatedInput()`
- Inside request class use `allInput()`

## SymfonyResponse

- Alias `Symfony\Component\HttpFoundation\Response` as `SymfonyResponse`

## ID Getter

- Access model IDs only via `getKey()`, `getAuthIdentifier()`, `getRouteKey()`

## Mail Queue

- Emails and notifications must implement `ShouldQueue`

## Mail After Commit

- Send emails and notifications only after database transaction commit

## Working with Logged-in User

- Use template helper methods: `Model::resolve()`, `Model::mustResolve()` (throws 401)

## Inheritdoc

- Overridden methods must inherit phpdoc with `@inheritDoc`

## Cron Schedule

- Define cron tasks using Jobs, not Artisan commands

## Single Job

- Jobs processing collections must use recursive processing
- Fetch all models, dispatch self with single model for transaction processing

## CRUD Command

- Generate boilerplate code exclusively with CRUD scaffolding commands (see `packages/thinkycz/laravel-core/src/Providers/CoreServiceProvider.php`)
