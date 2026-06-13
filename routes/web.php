<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Agent\AgentConversationDestroyController;
use App\Http\Controllers\Web\Agent\AgentIndexController;
use App\Http\Controllers\Web\Agent\AgentProposalApplyController;
use App\Http\Controllers\Web\Agent\AgentProposalRejectController;
use App\Http\Controllers\Web\Agent\AgentStreamController;
use App\Http\Controllers\Web\Auth\EmailVerificationConfirmController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\LogoutController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\VerifyEmailController;
use App\Http\Controllers\Web\Availability\AvailabilityDestroyController;
use App\Http\Controllers\Web\Availability\AvailabilityIndexController;
use App\Http\Controllers\Web\Availability\AvailabilityStoreController;
use App\Http\Controllers\Web\Availability\AvailabilityUpdateController;
use App\Http\Controllers\Web\Calendar\MyCalendarController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Employees\EmployeeCreateController;
use App\Http\Controllers\Web\Employees\EmployeeDestroyController;
use App\Http\Controllers\Web\Employees\EmployeeEditController;
use App\Http\Controllers\Web\Employees\EmployeeIndexController;
use App\Http\Controllers\Web\Employees\EmployeeLoginController;
use App\Http\Controllers\Web\Employees\EmployeeShowController;
use App\Http\Controllers\Web\Employees\EmployeeStoreAssignController;
use App\Http\Controllers\Web\Employees\EmployeeStoreController;
use App\Http\Controllers\Web\Employees\EmployeeStoreUnassignController;
use App\Http\Controllers\Web\Employees\EmployeeUpdateController;
use App\Http\Controllers\Web\EmployeeSchedules\PublicEmployeeScheduleController;
use App\Http\Controllers\Web\MyAvailabilities\MyAvailabilityIndexController;
use App\Http\Controllers\Web\MyAvailabilities\MyAvailabilityWriteController;
use App\Http\Controllers\Web\Schedules\ScheduleArchiveController;
use App\Http\Controllers\Web\Schedules\ScheduleCreateController;
use App\Http\Controllers\Web\Schedules\ScheduleDestroyController;
use App\Http\Controllers\Web\Schedules\ScheduleEditController;
use App\Http\Controllers\Web\Schedules\ScheduleIndexController;
use App\Http\Controllers\Web\Schedules\SchedulePublishController;
use App\Http\Controllers\Web\Schedules\ScheduleShowController;
use App\Http\Controllers\Web\Schedules\ScheduleStoreController;
use App\Http\Controllers\Web\Schedules\ScheduleUpdateController;
use App\Http\Controllers\Web\Schedules\ShiftAssignmentDestroyController;
use App\Http\Controllers\Web\Schedules\ShiftAssignmentStoreController;
use App\Http\Controllers\Web\Schedules\ShiftAutoFillController;
use App\Http\Controllers\Web\Schedules\ShiftRequirementDestroyController;
use App\Http\Controllers\Web\Schedules\ShiftRequirementStoreController;
use App\Http\Controllers\Web\Schedules\ShiftRequirementUpdateController;
use App\Http\Controllers\Web\Settings\SettingsController;
use App\Http\Controllers\Web\Stores\StoreBusinessHoursEditController;
use App\Http\Controllers\Web\Stores\StoreBusinessHoursUpdateController;
use App\Http\Controllers\Web\Stores\StoreCreateController;
use App\Http\Controllers\Web\Stores\StoreDestroyController;
use App\Http\Controllers\Web\Stores\StoreEditController;
use App\Http\Controllers\Web\Stores\StoreIndexController;
use App\Http\Controllers\Web\Stores\StoreShowController;
use App\Http\Controllers\Web\Stores\StoreStoreController;
use App\Http\Controllers\Web\Stores\StoreUpdateController;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use App\Models\User;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (User::auth() instanceof User) {
        return Resolver::resolveRedirector()->to('/dashboard');
    }

    return Resolver::resolveRedirector()->to('/login');
});

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [LoginController::class, 'create']);
        $router->post('login', [LoginController::class, 'store']);
        $router->get('register', [RegisterController::class, 'create']);
        $router->post('register', [RegisterController::class, 'store']);
        $router->get('forgot-password', [ForgotPasswordController::class, 'create']);
        $router->post('forgot-password', [ForgotPasswordController::class, 'store']);
        $router->get('reset-password', [ResetPasswordController::class, 'create']);
        $router->post('reset-password', [ResetPasswordController::class, 'store']);
    });

Resolver::resolveRouteRegistrar()->get('email/verify', EmailVerificationConfirmController::class);
Resolver::resolveRouteRegistrar()->get('public/employee-schedules', PublicEmployeeScheduleController::class);

Resolver::resolveRouteRegistrar()
    ->middleware(EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', LogoutController::class);
        $router->get('dashboard', DashboardController::class);
        $router->get('verify-email', [VerifyEmailController::class, 'create']);
        $router->post('verify-email', [VerifyEmailController::class, 'store']);
        $router->get('settings', [SettingsController::class, 'edit']);
        $router->post('settings/profile', [SettingsController::class, 'updateProfile']);
        $router->post('settings/password', [SettingsController::class, 'updatePassword']);

        // Stores
        $router->get('stores/index', StoreIndexController::class);
        $router->get('stores/create', StoreCreateController::class);
        $router->post('stores/store', StoreStoreController::class);
        $router->get('stores/show', StoreShowController::class);
        $router->get('stores/edit', StoreEditController::class);
        $router->post('stores/update', StoreUpdateController::class);
        $router->post('stores/destroy', StoreDestroyController::class);
        $router->get('stores/business-hours', StoreBusinessHoursEditController::class);
        $router->post('stores/business-hours/update', StoreBusinessHoursUpdateController::class);

        // Employees
        $router->get('employees/index', EmployeeIndexController::class);
        $router->get('employees/create', EmployeeCreateController::class);
        $router->post('employees/store', EmployeeStoreController::class);
        $router->get('employees/show', EmployeeShowController::class);
        $router->get('employees/edit', EmployeeEditController::class);
        $router->post('employees/update', EmployeeUpdateController::class);
        $router->post('employees/destroy', EmployeeDestroyController::class);
        $router->post('employees/login/store', [EmployeeLoginController::class, 'store']);
        $router->post('employees/login/update', [EmployeeLoginController::class, 'update']);
        $router->post('employees/login/password', [EmployeeLoginController::class, 'password']);
        $router->post('employees/login/generate-password', [EmployeeLoginController::class, 'generatePassword']);
        $router->post('employees/login/destroy', [EmployeeLoginController::class, 'destroy']);
        $router->post('employees/stores/store', EmployeeStoreAssignController::class);
        $router->post('employees/stores/destroy', EmployeeStoreUnassignController::class);

        // Availability
        $router->get('availability', AvailabilityIndexController::class);
        $router->post('availability/store', AvailabilityStoreController::class);
        $router->post('availability/update', AvailabilityUpdateController::class);
        $router->post('availability/destroy', AvailabilityDestroyController::class);

        // Schedules
        $router->get('schedules/index', ScheduleIndexController::class);
        $router->get('schedules/create', ScheduleCreateController::class);
        $router->post('schedules/store', ScheduleStoreController::class);
        $router->get('schedules/show', ScheduleShowController::class);
        $router->get('schedules/edit', ScheduleEditController::class);
        $router->post('schedules/update', ScheduleUpdateController::class);
        $router->post('schedules/destroy', ScheduleDestroyController::class);
        $router->post('schedules/publish', SchedulePublishController::class);
        $router->post('schedules/archive', ScheduleArchiveController::class);
        $router->post('shift-requirements/store', ShiftRequirementStoreController::class);
        $router->post('shift-requirements/update', ShiftRequirementUpdateController::class);
        $router->post('shift-requirements/destroy', ShiftRequirementDestroyController::class);
        $router->post('shift-requirements/auto-fill', ShiftAutoFillController::class);
        $router->post('shift-assignments/store', ShiftAssignmentStoreController::class);
        $router->post('shift-assignments/destroy', ShiftAssignmentDestroyController::class);

        // AI Agent
        $router->get('agent', AgentIndexController::class);
        $router->post('agent/stream', AgentStreamController::class);
        $router->get('agent/conversations/destroy', static fn() => Resolver::resolveRedirector()->to('/agent'));
        $router->post('agent/conversations/destroy', AgentConversationDestroyController::class);
        $router->get('agent/proposals/apply', static fn() => Resolver::resolveRedirector()->to('/agent'));
        $router->post('agent/proposals/apply', AgentProposalApplyController::class);
        $router->get('agent/proposals/reject', static fn() => Resolver::resolveRedirector()->to('/agent'));
        $router->post('agent/proposals/reject', AgentProposalRejectController::class);

        // My calendar (employee self-service)
        $router->get('my-calendar', MyCalendarController::class);
        $router->get('my-availabilities', MyAvailabilityIndexController::class);
        $router->post('my-availabilities/store', [MyAvailabilityWriteController::class, 'store']);
        $router->post('my-availabilities/update', [MyAvailabilityWriteController::class, 'update']);
        $router->post('my-availabilities/destroy', [MyAvailabilityWriteController::class, 'destroy']);
    });
