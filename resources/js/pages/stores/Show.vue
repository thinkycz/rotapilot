<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

const { t } = useI18n();
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

const dayNames = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const isAdmin = computed(() => auth.value.user?.role === 'admin');

function formatHour(h: BusinessHour): string {
    if (h.is_closed) return 'Closed';
    if (!h.opens_at || !h.closes_at) return '—';
    return `${h.opens_at} – ${h.closes_at}`;
}

function statusVariant(status: string): string {
    if (status === 'published') return 'bg-emerald-50 text-emerald-700';
    if (status === 'draft') return 'bg-amber-50 text-amber-700';
    return 'bg-zinc-100 text-zinc-700';
}
</script>

<template>
    <AppLayout :title="store.name">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ store.name }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ store.address ?? '—' }} · {{ store.city ?? '—' }} ·
                    {{ store.timezone }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    v-if="isAdmin"
                    :href="`/stores/edit?id=${store.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('stores.edit_link') }}
                </Link>
                <Link
                    :href="`/stores/business-hours?id=${store.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    {{ t('stores.business_hours_link') }}
                </Link>
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
                            v-for="(h, idx) in business_hours"
                            :key="idx"
                            class="border-t border-outline-glass/30 first:border-0"
                        >
                            <td
                                class="py-1.5 pr-3 font-semibold text-on-surface-variant w-24"
                            >
                                {{ dayNames[h.day_of_week - 1] }}
                            </td>
                            <td class="py-1.5 text-on-surface">
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
                    Managers
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
                <p v-else class="text-xs text-on-surface-variant">—</p>

                <h2
                    class="mb-3 mt-5 font-heading text-sm font-bold text-on-surface"
                >
                    Employees ({{ employees.length }})
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
                <p v-else class="text-xs text-on-surface-variant">—</p>
            </section>
        </div>

        <section
            class="mt-6 rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-heading text-sm font-bold text-on-surface">
                    Recent schedules
                </h2>
                <Link
                    href="/schedules/create"
                    class="inline-flex h-7 items-center rounded-lg border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-3 text-[11px] font-semibold text-white shadow-sm hover:brightness-105"
                >
                    New schedule
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
                            {{ s.period_start }} → {{ s.period_end }}
                        </td>
                        <td class="py-2 text-right">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    statusVariant(s.status),
                                ]"
                            >
                                {{ s.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-xs text-on-surface-variant">
                No schedules yet.
            </p>
        </section>
    </AppLayout>
</template>
