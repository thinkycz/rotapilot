<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { CalendarRange, Edit, CalendarCheck2, Trash2, X } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import FieldError from '@/components/ui/FieldError.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

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

const props = defineProps<{
    employee: Employee;
    stores: Store[];
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
</script>

<template>
    <AppLayout :title="employee.name">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ employee.name }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ employee.email ?? t('common.not_set') }} ·
                    {{ employee.role_label ?? t('common.not_set') }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="`/employees/edit?id=${employee.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <Edit :size="14" class="mr-1.5" />
                    {{ t('employees.edit_link') }}
                </Link>
                <Link
                    :href="`/availability?employee_id=${employee.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    <CalendarCheck2 :size="14" class="mr-1.5" />
                    {{ t('employees.manage_availability') }}
                </Link>
                <a
                    :href="employee.public_schedule_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <CalendarRange :size="14" class="mr-1.5" />
                    {{ t('employees.view_public_schedules') }}
                </a>
                <button
                    type="button"
                    @click="destroyEmployee"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                >
                    <Trash2 :size="14" class="mr-1.5" />
                    {{ t('common.delete') }}
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('employees.profile') }}
                </h2>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">
                            {{ t('employees.phone') }}
                        </dt>
                        <dd class="text-on-surface">
                            {{ employee.phone ?? t('common.not_set') }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
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
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">
                            {{ t('employees.hourly_rate') }}
                        </dt>
                        <dd class="text-on-surface">
                            <span v-if="employee.hourly_rate !== null">
                                {{ employee.hourly_rate }} CZK/h
                            </span>
                            <span v-else>{{ t('common.not_set') }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
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
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('employees.login_account') }}
                </h2>

                <Alert v-if="generatedPassword" variant="success" class="mb-4">
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
                                v-model="createLoginForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                            />
                            <FieldError
                                :message="
                                    createLoginForm.errors.password_confirmation
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
                                    v-model="passwordForm.password_confirmation"
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

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('employees.assigned_stores') }}
                </h2>
                <ul v-if="stores.length > 0" class="space-y-1.5">
                    <li
                        v-for="s in stores"
                        :key="s.id"
                        class="flex items-center justify-between rounded-lg border border-outline-glass/40 bg-white px-3 py-2"
                    >
                        <span class="text-xs font-semibold text-on-surface">{{
                            s.name
                        }}</span>
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
        </div>
    </AppLayout>
</template>
