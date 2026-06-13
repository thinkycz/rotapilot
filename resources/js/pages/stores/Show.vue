<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { formatDateRange } from '@/lib/date';

const { t, tm } = useI18n();
const { auth } = useSharedProps();

useBoundLocale();

interface Store {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    timezone: string;
    is_active: boolean;
}

interface BusinessHour {
    day_of_week: number;
    opens_at: string | null;
    closes_at: string | null;
    is_closed: boolean;
}

interface Manager {
    id: number;
    email: string;
}

interface EmployeeRow {
    id: number;
    name: string;
    role_label: string | null;
}

interface Schedule {
    id: number;
    name: string;
    status: string;
    period_start: string;
    period_end: string;
}

const props = defineProps<{
    store: Store;
    business_hours: BusinessHour[];
    managers: Manager[];
    employees: EmployeeRow[];
    schedules: Schedule[];
}>();

const dayNames = computed(() => tm('common.weekdays') as string[]);

const canManageStore = computed(
    () => auth.value.user?.role === 'store_manager',
);

function formatHour(h: BusinessHour): string {
    if (h.is_closed) return t('common.closed');
    if (!h.opens_at || !h.closes_at) return t('common.not_set');
    return `${h.opens_at.substring(0, 5)} – ${h.closes_at.substring(0, 5)}`;
}

function statusVariant(status: string): string {
    if (status === 'published') return 'bg-emerald-50 text-emerald-700';
    if (status === 'draft') return 'bg-amber-50 text-amber-700';
    return 'bg-zinc-100 text-zinc-700';
}

function businessHourClass(h: BusinessHour): string {
    if (h.is_closed) return 'text-on-surface-variant';
    if (!h.opens_at || !h.closes_at) return 'text-on-surface-variant';
    return 'font-semibold text-on-surface';
}

function destroyStore(): void {
    if (confirm(t('common.confirm_title'))) {
        router.post(`/stores/destroy?id=${props.store.id}`);
    }
}
</script>

<template>
    <AppLayout :title="store.name">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-heading text-2xl font-bold text-on-surface">
                        {{ store.name }}
                    </h1>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                            store.is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-rose-50 text-rose-700',
                        ]"
                    >
                        {{
                            store.is_active
                                ? t('stores.status_active')
                                : t('stores.status_inactive')
                        }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ store.address ?? t('common.not_set') }} ·
                    {{ store.city ?? t('common.not_set') }} ·
                    {{ store.timezone }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    v-if="canManageStore"
                    :href="`/stores/edit?id=${store.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('stores.edit_link') }}
                </Link>
                <Link
                    v-if="canManageStore"
                    :href="`/stores/business-hours?id=${store.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    {{ t('stores.business_hours_link') }}
                </Link>
                <button
                    v-if="canManageStore"
                    type="button"
                    @click="destroyStore"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                >
                    {{ t('common.delete') }}
                </button>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('stores.title_business_hours') }}
                </h2>
                <table class="w-full text-xs">
                    <tbody>
                        <tr
                            v-for="h in business_hours"
                            :key="h.day_of_week"
                            class="border-t border-outline-glass/30 first:border-0"
                        >
                            <td
                                class="py-1.5 pr-3 font-semibold text-on-surface-variant w-24"
                            >
                                {{ dayNames[h.day_of_week - 1] }}
                            </td>
                            <td :class="['py-1.5', businessHourClass(h)]">
                                {{ formatHour(h) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('stores.managers') }}
                </h2>
                <ul v-if="managers.length > 0" class="space-y-1.5">
                    <li
                        v-for="m in managers"
                        :key="m.id"
                        class="text-xs text-on-surface"
                    >
                        {{ m.email }}
                    </li>
                </ul>
                <p v-else class="text-xs text-on-surface-variant">
                    {{ t('stores.no_managers') }}
                </p>

                <h2
                    class="mb-3 mt-5 font-heading text-sm font-bold text-on-surface"
                >
                    {{ t('employees.title_index') }} ({{ employees.length }})
                </h2>
                <ul v-if="employees.length > 0" class="space-y-1.5">
                    <li
                        v-for="e in employees"
                        :key="e.id"
                        class="text-xs text-on-surface"
                    >
                        {{ e.name
                        }}<span
                            v-if="e.role_label"
                            class="ml-1 text-on-surface-variant"
                            >· {{ e.role_label }}</span
                        >
                    </li>
                </ul>
                <p v-else class="text-xs text-on-surface-variant">
                    {{ t('employees.empty') }}
                </p>
            </section>
        </div>

        <section
            class="mt-6 rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.recent_schedules') }}
                </h2>
                <Link
                    href="/schedules/create"
                    class="inline-flex h-7 items-center rounded-lg border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-3 text-[11px] font-semibold text-white shadow-sm hover:brightness-105"
                >
                    {{ t('schedules.title_create') }}
                </Link>
            </div>
            <table v-if="schedules.length > 0" class="w-full text-xs">
                <tbody>
                    <tr
                        v-for="s in schedules"
                        :key="s.id"
                        class="border-t border-outline-glass/30 first:border-0"
                    >
                        <td class="py-2 pr-3">
                            <Link
                                :href="`/schedules/show?id=${s.id}`"
                                class="font-semibold text-primary hover:underline"
                            >
                                {{ s.name }}
                            </Link>
                        </td>
                        <td class="py-2 pr-3 text-on-surface-variant">
                            {{ formatDateRange(s.period_start, s.period_end) }}
                        </td>
                        <td class="py-2 text-right">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    statusVariant(s.status),
                                ]"
                            >
                                {{ t('schedules.status_' + s.status) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-xs text-on-surface-variant">
                {{ t('dashboard.no_schedules') }}
            </p>
        </section>
    </AppLayout>
</template>
