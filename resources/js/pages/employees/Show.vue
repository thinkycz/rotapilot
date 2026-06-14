<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    ChevronLeft,
    Clock,
    Edit,
    Phone,
    Trash2,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import FieldError from '@/components/ui/FieldError.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { formatDate } from '@/lib/date';

const { t, te } = useI18n();
const { app, flash } = useSharedProps();

useBoundLocale();

interface LoginAccount {
    id: number;
    email: string;
    locale: string;
}

interface Employee {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role_label: string | null;
    max_hours_per_week: number | null;
    hourly_rate: number | null;
    is_active: boolean;
    has_login: boolean;
    login: LoginAccount | null;
    public_schedule_url: string;
}

interface Store {
    id: number;
    name: string;
}

interface Stats {
    upcoming_shifts: number;
    hours_this_week: number;
    hours_this_month: number;
    hours_total: number;
    conflicts: number;
}

interface UpcomingShift {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    role_label: string | null;
    status: string;
    schedule_id: number;
    store_id: number;
    store_name: string | null;
}

interface AvailabilityDay {
    date: string;
    weekday: 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';
    has_unavailable_entry: boolean;
}

const props = defineProps<{
    employee: Employee;
    stores: Store[];
    stats: Stats;
    upcoming_shifts: UpcomingShift[];
    availability: AvailabilityDay[];
}>();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);

const generatedPassword = computed(
    () => flash.value.employee_login_generated_password ?? null,
);

const createLoginForm = useForm({
    email: props.employee.email ?? '',
    locale: app.value.locale,
    password: '',
    password_confirmation: '',
    generate_random: false,
    login: '',
});

const updateLoginForm = useForm({
    email: props.employee.login?.email ?? props.employee.email ?? '',
    locale: props.employee.login?.locale ?? app.value.locale,
    login: '',
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
    login: '',
});

function unassign(storeId: number): void {
    if (confirm(t('employees.confirm_unassign'))) {
        router.post('/employees/stores/destroy', {
            employee_id: props.employee.id,
            store_id: storeId,
        });
    }
}

function destroyEmployee(): void {
    if (confirm(t('common.confirm_title'))) {
        router.post(`/employees/destroy?id=${props.employee.id}`);
    }
}

function createLogin(generateRandom = false): void {
    createLoginForm.generate_random = generateRandom;
    createLoginForm.post(`/employees/login/store?id=${props.employee.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            createLoginForm.reset('password', 'password_confirmation');
        },
    });
}

function updateLogin(): void {
    updateLoginForm.post(`/employees/login/update?id=${props.employee.id}`, {
        preserveScroll: true,
    });
}

function updatePassword(): void {
    passwordForm.post(`/employees/login/password?id=${props.employee.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
}

function generatePassword(): void {
    router.post(
        `/employees/login/generate-password?id=${props.employee.id}`,
        {},
        { preserveScroll: true },
    );
}

function destroyLogin(): void {
    if (confirm(t('employees.confirm_delete_login'))) {
        router.post(
            `/employees/login/destroy?id=${props.employee.id}`,
            {},
            { preserveScroll: true },
        );
    }
}

function formatHours(hours: number): string {
    return hours.toFixed(hours % 1 === 0 ? 0 : 1);
}

function formatShiftTime(start: string, end: string): string {
    return `${start.substring(0, 5)}–${end.substring(0, 5)}`;
}

function formatHourlyRate(rate: number | null): string {
    if (rate === null) {
        return t('common.not_set');
    }
    try {
        return (
            new Intl.NumberFormat(app.value.locale, {
                style: 'currency',
                currency: 'CZK',
                maximumFractionDigits: 0,
            }).format(rate) + '/h'
        );
    } catch {
        return `${rate} CZK/h`;
    }
}

function availabilityStatus(d: AvailabilityDay): 'available' | 'unavailable' {
    return d.has_unavailable_entry ? 'unavailable' : 'available';
}

function availabilityDayLabel(d: AvailabilityDay): string {
    return t(`employees.weekday_short_${d.weekday}`);
}

function availabilityDateLabel(d: AvailabilityDay): string {
    return formatDate(d.date);
}
</script>

<template>
    <AppLayout :title="employee.name">
        <!-- Back link -->
        <div class="mb-3">
            <Link
                href="/employees/index"
                class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-1.5 text-[11px] font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
            >
                <ChevronLeft :size="14" />
                {{ t('employees.back_to_employees') }}
            </Link>
        </div>

        <!-- Header: status pill + name + action buttons -->
        <header
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-heading text-2xl font-bold text-on-surface">
                        {{ employee.name }}
                    </h1>
                    <span
                        :class="[
                            'inline-flex h-6 items-center gap-1 rounded-full border px-2 text-[10px] font-bold uppercase tracking-wider',
                            employee.is_active
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-zinc-200 bg-zinc-100 text-zinc-600',
                        ]"
                        :aria-label="
                            employee.is_active
                                ? t('employees.status_pill_active')
                                : t('employees.status_pill_inactive')
                        "
                    >
                        <span
                            :class="[
                                'h-1.5 w-1.5 rounded-full',
                                employee.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-zinc-400',
                            ]"
                        />
                        {{
                            employee.is_active
                                ? t('employees.status_active')
                                : t('employees.status_inactive')
                        }}
                    </span>
                </div>
                <p
                    class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-on-surface-variant"
                >
                    <span v-if="employee.email">{{ employee.email }}</span>
                    <span
                        v-if="employee.email && employee.role_label"
                        class="text-on-surface-variant/50"
                        >·</span
                    >
                    <span
                        v-if="employee.role_label"
                        class="rounded-md border border-outline-glass bg-white px-1.5 py-0.5 text-[10px] font-semibold text-on-surface-variant"
                    >
                        {{ employee.role_label }}
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="`/employees/edit?id=${employee.id}`"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-3.5 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <Edit :size="14" class="mr-1.5" />
                    {{ t('employees.edit_link') }}
                </Link>
                <Link
                    :href="`/availability?employee_id=${employee.id}`"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-3.5 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    <CalendarCheck2 :size="14" class="mr-1.5" />
                    {{ t('employees.manage_availability') }}
                </Link>
                <a
                    :href="employee.public_schedule_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-3.5 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <CalendarRange :size="14" class="mr-1.5" />
                    {{ t('employees.view_public_schedules') }}
                </a>
                <button
                    type="button"
                    @click="destroyEmployee"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-rose-200 bg-rose-50 px-3.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                >
                    <Trash2 :size="14" class="mr-1.5" />
                    {{ t('common.delete') }}
                </button>
            </div>
        </header>

        <!-- Stats row -->
        <section
            class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4"
            aria-label="Overview"
        >
            <div
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-4 shadow-sm"
            >
                <div class="flex items-center gap-2 text-on-surface-variant">
                    <CalendarClock :size="14" />
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider"
                    >
                        {{ t('employees.stats_upcoming_shifts') }}
                    </span>
                </div>
                <p class="mt-2 font-heading text-2xl font-bold text-on-surface">
                    {{ stats.upcoming_shifts }}
                </p>
            </div>
            <div
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-4 shadow-sm"
            >
                <div class="flex items-center gap-2 text-on-surface-variant">
                    <Clock :size="14" />
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider"
                    >
                        {{ t('employees.stats_hours_this_week') }}
                    </span>
                </div>
                <p class="mt-2 font-heading text-2xl font-bold text-on-surface">
                    {{ formatHours(stats.hours_this_week) }}
                    <span
                        class="ml-0.5 text-xs font-semibold text-on-surface-variant"
                    >
                        h
                    </span>
                </p>
            </div>
            <div
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-4 shadow-sm"
            >
                <div class="flex items-center gap-2 text-on-surface-variant">
                    <CalendarDays :size="14" />
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider"
                    >
                        {{ t('employees.stats_hours_this_month') }}
                    </span>
                </div>
                <p class="mt-2 font-heading text-2xl font-bold text-on-surface">
                    {{ formatHours(stats.hours_this_month) }}
                    <span
                        class="ml-0.5 text-xs font-semibold text-on-surface-variant"
                    >
                        h
                    </span>
                </p>
            </div>
            <div
                :class="[
                    'rounded-2xl border bg-surface-container-lowest p-4 shadow-sm',
                    stats.conflicts > 0
                        ? 'border-rose-200'
                        : 'border-outline-glass',
                ]"
            >
                <div
                    :class="[
                        'flex items-center gap-2',
                        stats.conflicts > 0
                            ? 'text-rose-700'
                            : 'text-on-surface-variant',
                    ]"
                >
                    <AlertTriangle :size="14" />
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider"
                    >
                        {{ t('employees.stats_conflicts') }}
                    </span>
                </div>
                <p
                    :class="[
                        'mt-2 font-heading text-2xl font-bold',
                        stats.conflicts > 0
                            ? 'text-rose-700'
                            : 'text-on-surface',
                    ]"
                >
                    {{ stats.conflicts }}
                </p>
            </div>
        </section>

        <!-- Main content -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left column: Upcoming shifts + Availability -->
            <div class="space-y-6 lg:col-span-2">
                <section
                    class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="font-heading text-sm font-bold text-on-surface"
                        >
                            {{ t('employees.upcoming_shifts_title') }}
                        </h2>
                        <span
                            v-if="upcoming_shifts.length > 0"
                            class="rounded-full bg-surface-container px-2 py-0.5 text-[10px] font-semibold text-on-surface-variant"
                        >
                            {{ upcoming_shifts.length }}
                        </span>
                    </div>

                    <ul v-if="upcoming_shifts.length > 0" class="space-y-2">
                        <li
                            v-for="s in upcoming_shifts"
                            :key="s.id"
                            class="flex flex-wrap items-center gap-3 rounded-xl border border-outline-glass/60 bg-white px-4 py-3"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-lg bg-primary-container text-primary"
                            >
                                <span
                                    class="text-[9px] font-bold uppercase tracking-wider"
                                >
                                    {{
                                        availabilityDayLabel(
                                            availability.find(
                                                (a) => a.date === s.date,
                                            ) ?? {
                                                date: s.date,
                                                weekday: 'mon' as const,
                                                has_unavailable_entry: false,
                                            },
                                        )
                                    }}
                                </span>
                                <span
                                    class="font-heading text-sm font-bold leading-none"
                                >
                                    {{ s.date.substring(8, 10) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0 space-y-0.5">
                                <p
                                    class="text-xs font-semibold text-on-surface"
                                >
                                    {{ s.store_name ?? '—' }}
                                </p>
                                <p
                                    class="font-mono text-[11px] text-on-surface-variant"
                                >
                                    {{
                                        formatShiftTime(
                                            s.start_time,
                                            s.end_time,
                                        )
                                    }}
                                    <span
                                        v-if="s.role_label"
                                        class="ml-1 rounded-md border border-outline-glass bg-white px-1.5 py-0.5 font-sans text-[10px] font-semibold text-on-surface-variant"
                                    >
                                        {{ s.role_label }}
                                    </span>
                                </p>
                            </div>
                            <Link
                                :href="`/schedules/show?id=${s.schedule_id}`"
                                class="inline-flex h-8 cursor-pointer items-center gap-1 rounded-md border border-outline-glass bg-white px-2.5 text-[11px] font-semibold text-on-surface hover:bg-surface-container-low"
                            >
                                {{ t('employees.upcoming_shift_view') }}
                            </Link>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="flex flex-col items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-8 text-center"
                    >
                        <CalendarCheck2 :size="20" class="text-emerald-500" />
                        <p class="text-xs font-semibold text-emerald-800">
                            {{ t('employees.upcoming_shifts_empty') }}
                        </p>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="font-heading text-sm font-bold text-on-surface"
                        >
                            {{ t('employees.availability_title') }}
                        </h2>
                        <div
                            class="flex items-center gap-3 text-[10px] font-semibold text-on-surface-variant"
                        >
                            <span class="inline-flex items-center gap-1">
                                <span
                                    class="h-2 w-2 rounded-full bg-emerald-500"
                                />
                                {{
                                    t('employees.availability_legend_available')
                                }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span
                                    class="h-2 w-2 rounded-full bg-rose-500"
                                />
                                {{
                                    t(
                                        'employees.availability_legend_unavailable',
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <ul
                        v-if="availability.length > 0"
                        class="grid grid-cols-7 gap-2"
                    >
                        <li
                            v-for="d in availability"
                            :key="d.date"
                            :class="[
                                'flex flex-col items-center gap-1.5 rounded-xl border p-2 text-center',
                                availabilityStatus(d) === 'unavailable'
                                    ? 'border-rose-200 bg-rose-50/60'
                                    : 'border-emerald-200 bg-emerald-50/60',
                            ]"
                        >
                            <span
                                class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ availabilityDayLabel(d) }}
                            </span>
                            <span
                                :class="[
                                    'font-heading text-base font-bold',
                                    availabilityStatus(d) === 'unavailable'
                                        ? 'text-rose-700'
                                        : 'text-emerald-700',
                                ]"
                            >
                                {{ availabilityDateLabel(d).substring(0, 2) }}
                            </span>
                            <span
                                :class="[
                                    'h-1.5 w-1.5 rounded-full',
                                    availabilityStatus(d) === 'unavailable'
                                        ? 'bg-rose-500'
                                        : 'bg-emerald-500',
                                ]"
                            />
                        </li>
                    </ul>

                    <p v-else class="text-xs text-on-surface-variant">
                        {{ t('employees.availability_no_entries') }}
                    </p>
                </section>
            </div>

            <!-- Right column: Profile, Stores, Login account -->
            <div class="space-y-6">
                <section
                    class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
                >
                    <h2
                        class="mb-3 font-heading text-sm font-bold text-on-surface"
                    >
                        {{ t('employees.profile') }}
                    </h2>
                    <dl class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-on-surface-variant">
                                <Phone
                                    :size="11"
                                    class="mr-1 inline-block align-middle"
                                />
                                {{ t('employees.phone') }}
                            </dt>
                            <dd class="text-on-surface">
                                {{ employee.phone ?? t('common.not_set') }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-on-surface-variant">
                                {{ t('employees.max_hours_per_week') }}
                            </dt>
                            <dd class="text-on-surface">
                                {{
                                    employee.max_hours_per_week ??
                                    t('common.not_set')
                                }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-on-surface-variant">
                                {{ t('employees.hourly_rate') }}
                            </dt>
                            <dd class="font-mono text-on-surface">
                                {{ formatHourlyRate(employee.hourly_rate) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-on-surface-variant">
                                {{ t('employees.login') }}
                            </dt>
                            <dd>
                                <span
                                    v-if="employee.has_login"
                                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                                    >{{ t('common.yes') }}</span
                                >
                                <span
                                    v-else
                                    class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-700"
                                    >{{ t('common.no') }}</span
                                >
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
                >
                    <h2
                        class="mb-3 font-heading text-sm font-bold text-on-surface"
                    >
                        {{ t('employees.assigned_stores') }}
                    </h2>
                    <ul v-if="stores.length > 0" class="space-y-1.5">
                        <li
                            v-for="s in stores"
                            :key="s.id"
                            class="flex items-center justify-between rounded-lg border border-outline-glass/40 bg-white px-3 py-2"
                        >
                            <span
                                class="text-xs font-semibold text-on-surface"
                                >{{ s.name }}</span
                            >
                            <button
                                @click="unassign(s.id)"
                                class="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 text-[10px] font-bold text-rose-700 hover:bg-rose-100"
                            >
                                <X :size="10" />
                                {{ t('employees.unassign') }}
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-on-surface-variant">
                        {{ t('employees.no_assigned_stores') }}
                    </p>
                </section>

                <section
                    class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
                >
                    <h2
                        class="mb-3 font-heading text-sm font-bold text-on-surface"
                    >
                        {{ t('employees.login_account') }}
                    </h2>

                    <Alert
                        v-if="generatedPassword"
                        variant="success"
                        class="mb-4"
                    >
                        <span class="block text-xs font-semibold">
                            {{ t('employees.generated_password') }}
                        </span>
                        <span
                            class="mt-2 block rounded-lg bg-white px-3 py-2 font-mono text-sm font-bold text-on-surface"
                        >
                            {{ generatedPassword }}
                        </span>
                    </Alert>

                    <form
                        v-if="!employee.login"
                        @submit.prevent="createLogin(false)"
                        class="space-y-4"
                    >
                        <div>
                            <label
                                class="mb-1 block text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t('fields.email') }}
                            </label>
                            <input
                                v-model="createLoginForm.email"
                                type="email"
                                required
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                            />
                            <FieldError
                                :message="createLoginForm.errors.email"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t('fields.locale') }}
                            </label>
                            <select
                                v-model="createLoginForm.locale"
                                required
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                            >
                                <option
                                    v-for="locale in localeOptions"
                                    :key="locale.value"
                                    :value="locale.value"
                                >
                                    {{ locale.label }}
                                </option>
                            </select>
                            <FieldError
                                :message="createLoginForm.errors.locale"
                                class="mt-1"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ t('fields.password') }}
                                </label>
                                <input
                                    v-model="createLoginForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                <FieldError
                                    :message="createLoginForm.errors.password"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ t('fields.password_confirmation') }}
                                </label>
                                <input
                                    v-model="
                                        createLoginForm.password_confirmation
                                    "
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                <FieldError
                                    :message="
                                        createLoginForm.errors
                                            .password_confirmation
                                    "
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <FieldError
                            :message="createLoginForm.errors.login"
                            class="mt-1"
                        />

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <button
                                type="submit"
                                :disabled="createLoginForm.processing"
                                class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                            >
                                {{ t('employees.create_login') }}
                            </button>
                            <button
                                type="button"
                                :disabled="createLoginForm.processing"
                                @click="createLogin(true)"
                                class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low disabled:opacity-50"
                            >
                                {{ t('employees.create_login_random') }}
                            </button>
                        </div>
                    </form>

                    <div v-else class="space-y-5">
                        <form @submit.prevent="updateLogin" class="space-y-4">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ t('fields.email') }}
                                </label>
                                <input
                                    v-model="updateLoginForm.email"
                                    type="email"
                                    required
                                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                />
                                <FieldError
                                    :message="updateLoginForm.errors.email"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ t('fields.locale') }}
                                </label>
                                <select
                                    v-model="updateLoginForm.locale"
                                    required
                                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                >
                                    <option
                                        v-for="locale in localeOptions"
                                        :key="locale.value"
                                        :value="locale.value"
                                    >
                                        {{ locale.label }}
                                    </option>
                                </select>
                                <FieldError
                                    :message="updateLoginForm.errors.locale"
                                    class="mt-1"
                                />
                            </div>
                            <FieldError
                                :message="updateLoginForm.errors.login"
                                class="mt-1"
                            />
                            <button
                                type="submit"
                                :disabled="updateLoginForm.processing"
                                class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                            >
                                {{ t('employees.update_login') }}
                            </button>
                        </form>

                        <form
                            @submit.prevent="updatePassword"
                            class="space-y-4 border-t border-outline-glass pt-5"
                        >
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ t('fields.new_password') }}
                                    </label>
                                    <input
                                        v-model="passwordForm.password"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                    />
                                    <FieldError
                                        :message="passwordForm.errors.password"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ t('fields.password_confirmation') }}
                                    </label>
                                    <input
                                        v-model="
                                            passwordForm.password_confirmation
                                        "
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                    />
                                    <FieldError
                                        :message="
                                            passwordForm.errors
                                                .password_confirmation
                                        "
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                            <FieldError
                                :message="passwordForm.errors.login"
                                class="mt-1"
                            />
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="submit"
                                    :disabled="passwordForm.processing"
                                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low disabled:opacity-50"
                                >
                                    {{ t('employees.set_password') }}
                                </button>
                                <button
                                    type="button"
                                    @click="generatePassword"
                                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                                >
                                    {{ t('employees.generate_password') }}
                                </button>
                                <button
                                    type="button"
                                    @click="destroyLogin"
                                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                >
                                    {{ t('employees.delete_login') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
