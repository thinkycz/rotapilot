<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (App\Models\User::auth() instanceof App\Models\User) {
        return Resolver::resolveRedirector()->to('/dashboard');
    }

    return Resolver::resolveRedirector()->to('/login');
});

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [App\Http\Controllers\Web\Auth\LoginController::class, 'create']);
        $router->post('login', [App\Http\Controllers\Web\Auth\LoginController::class, 'store']);
        $router->get('register', [App\Http\Controllers\Web\Auth\RegisterController::class, 'create']);
        $router->post('register', [App\Http\Controllers\Web\Auth\RegisterController::class, 'store']);
        $router->get('forgot-password', [App\Http\Controllers\Web\Auth\ForgotPasswordController::class, 'create']);
        $router->post('forgot-password', [App\Http\Controllers\Web\Auth\ForgotPasswordController::class, 'store']);
        $router->get('reset-password', [App\Http\Controllers\Web\Auth\ResetPasswordController::class, 'create']);
        $router->post('reset-password', [App\Http\Controllers\Web\Auth\ResetPasswordController::class, 'store']);
    });

Resolver::resolveRouteRegistrar()->get('email/verify', App\Http\Controllers\Web\Auth\EmailVerificationConfirmController::class);

Resolver::resolveRouteRegistrar()
    ->middleware(App\Http\Middleware\EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', App\Http\Controllers\Web\Auth\LogoutController::class);
        $router->get('dashboard', App\Http\Controllers\Web\DashboardController::class);
        $router->get('verify-email', [App\Http\Controllers\Web\Auth\VerifyEmailController::class, 'create']);
        $router->post('verify-email', [App\Http\Controllers\Web\Auth\VerifyEmailController::class, 'store']);
        $router->get('settings/profile', [App\Http\Controllers\Web\Settings\ProfileController::class, 'edit']);
        $router->post('settings/profile', [App\Http\Controllers\Web\Settings\ProfileController::class, 'update']);
        $router->get('settings/password', [App\Http\Controllers\Web\Settings\PasswordController::class, 'edit']);
        $router->post('settings/password', [App\Http\Controllers\Web\Settings\PasswordController::class, 'update']);

        // Stores
        $router->get('stores/index', App\Http\Controllers\Web\Stores\StoreIndexController::class);
        $router->get('stores/create', App\Http\Controllers\Web\Stores\StoreCreateController::class);
        $router->post('stores/store', App\Http\Controllers\Web\Stores\StoreStoreController::class);
        $router->get('stores/show', App\Http\Controllers\Web\Stores\StoreShowController::class);
        $router->get('stores/edit', App\Http\Controllers\Web\Stores\StoreEditController::class);
        $router->post('stores/update', App\Http\Controllers\Web\Stores\StoreUpdateController::class);
        $router->post('stores/destroy', App\Http\Controllers\Web\Stores\StoreDestroyController::class);
        $router->get('stores/business-hours', App\Http\Controllers\Web\Stores\StoreBusinessHoursEditController::class);
        $router->post('stores/business-hours/update', App\Http\Controllers\Web\Stores\StoreBusinessHoursUpdateController::class);

        // Employees
        $router->get('employees/index', App\Http\Controllers\Web\Employees\EmployeeIndexController::class);
        $router->get('employees/create', App\Http\Controllers\Web\Employees\EmployeeCreateController::class);
        $router->post('employees/store', App\Http\Controllers\Web\Employees\EmployeeStoreController::class);
        $router->get('employees/show', App\Http\Controllers\Web\Employees\EmployeeShowController::class);
        $router->get('employees/edit', App\Http\Controllers\Web\Employees\EmployeeEditController::class);
        $router->post('employees/update', App\Http\Controllers\Web\Employees\EmployeeUpdateController::class);
        $router->post('employees/destroy', App\Http\Controllers\Web\Employees\EmployeeDestroyController::class);
        $router->post('employees/stores/store', App\Http\Controllers\Web\Employees\EmployeeStoreAssignController::class);
        $router->post('employees/stores/destroy', App\Http\Controllers\Web\Employees\EmployeeStoreUnassignController::class);

        // Availability
        $router->get('availability', App\Http\Controllers\Web\Availability\AvailabilityIndexController::class);
        $router->post('availability/store', App\Http\Controllers\Web\Availability\AvailabilityStoreController::class);
        $router->post('availability/update', App\Http\Controllers\Web\Availability\AvailabilityUpdateController::class);
        $router->post('availability/destroy', App\Http\Controllers\Web\Availability\AvailabilityDestroyController::class);
        $router->post('availability/parse-ai', App\Http\Controllers\Web\Availability\AvailabilityParseAiController::class);

        // Schedules
        $router->get('schedules/index', App\Http\Controllers\Web\Schedules\ScheduleIndexController::class);
        $router->get('schedules/create', App\Http\Controllers\Web\Schedules\ScheduleCreateController::class);
        $router->post('schedules/store', App\Http\Controllers\Web\Schedules\ScheduleStoreController::class);
        $router->get('schedules/show', App\Http\Controllers\Web\Schedules\ScheduleShowController::class);
        $router->get('schedules/edit', App\Http\Controllers\Web\Schedules\ScheduleEditController::class);
        $router->post('schedules/update', App\Http\Controllers\Web\Schedules\ScheduleUpdateController::class);
        $router->post('schedules/destroy', App\Http\Controllers\Web\Schedules\ScheduleDestroyController::class);
        $router->post('schedules/publish', App\Http\Controllers\Web\Schedules\SchedulePublishController::class);
        $router->post('schedules/archive', App\Http\Controllers\Web\Schedules\ScheduleArchiveController::class);
        $router->post('shift-requirements/store', App\Http\Controllers\Web\Schedules\ShiftRequirementStoreController::class);
        $router->post('shift-requirements/update', App\Http\Controllers\Web\Schedules\ShiftRequirementUpdateController::class);
        $router->post('shift-requirements/destroy', App\Http\Controllers\Web\Schedules\ShiftRequirementDestroyController::class);
        $router->post('shift-requirements/auto-fill', App\Http\Controllers\Web\Schedules\ShiftAutoFillController::class);
        $router->post('shift-assignments/store', App\Http\Controllers\Web\Schedules\ShiftAssignmentStoreController::class);
        $router->post('shift-assignments/destroy', App\Http\Controllers\Web\Schedules\ShiftAssignmentDestroyController::class);

        // My calendar (employee self-service)
        $router->get('my-calendar', App\Http\Controllers\Web\Calendar\MyCalendarController::class);

        // AI planner
        $router->get('ai-planner', App\Http\Controllers\Web\Ai\PlannerIndexController::class);
        $router->post('ai-planner/message', App\Http\Controllers\Web\Ai\PlannerMessageController::class);
        $router->post('ai-planner/apply-preview', App\Http\Controllers\Web\Ai\PlannerApplyPreviewController::class);

        // Conflicts
        $router->get('conflicts', App\Http\Controllers\Web\Conflicts\ConflictIndexController::class);
        $router->post('conflicts/resolve', App\Http\Controllers\Web\Conflicts\ConflictResolveController::class);
        $router->post('conflicts/ask-ai', App\Http\Controllers\Web\Conflicts\ConflictAskAiController::class);
    });
