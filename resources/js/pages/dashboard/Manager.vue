<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Calendar,
    Coffee,
    Sparkles,
    ArrowRight,
    AlertTriangle,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

const { t } = useI18n();
const { auth } = useSharedProps();

useBoundLocale();

interface Stat {
    managed_stores: number;
    active_employees: number;
    shifts_this_month: number;
    open_conflicts: number;
}
interface StoreRef {
    id: number;
    name: string;
}
interface ScheduleRef {
    id: number;
    name: string;
    status: string;
    period_start: string;
    period_end: string;
}
interface ShiftRef {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    store_id: number;
}

defineProps<{
    stats?: Stat;
    stores?: StoreRef[];
    recent_schedules?: ScheduleRef[];
    upcoming_shifts?: ShiftRef[];
    user_role?: string;
}>();
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <div class="mb-8 flex flex-col items-start gap-4">
            <div
                class="inline-flex items-center gap-2 rounded-full border border-outline-glass bg-surface-container px-3 py-1 font-mono text-[10px] font-bold tracking-wider text-primary"
            >
                <span
                    class="h-2 w-2 animate-pulse rounded-full bg-primary"
                ></span>
                {{ t('dashboard.welcome') }}
            </div>

            <h1
                class="font-heading text-3xl font-black tracking-tight text-on-surface md:text-4xl"
            >
                {{ t('dashboard.greeting_manager') }}
                <span
                    class="bg-gradient-to-r from-primary to-secondary-cyan bg-clip-text text-transparent"
                    >{{ auth.user?.email?.split('@')[0] }}.</span
                >
            </h1>
        </div>

        <section
            v-if="(stats?.managed_stores ?? 0) === 0"
            class="mb-8 rounded-2xl border border-outline-glass bg-gradient-to-br from-primary-container/15 via-surface-container-lowest to-secondary-cyan/10 p-6 shadow-sm"
        >
            <div class="mb-3 flex items-center gap-2">
                <Coffee :size="16" class="text-primary" />
                <span
                    class="font-mono text-[10px] font-extrabold tracking-wider text-primary uppercase"
                    >{{ t('dashboard.get_started') }}</span
                >
            </div>
            <h2 class="font-heading text-xl font-bold text-on-surface">
                {{ t('dashboard.get_started') }}
            </h2>
            <p class="mt-1 text-xs font-medium text-on-surface-variant">
                {{ t('dashboard.get_started_description') }}
            </p>
            <Link
                href="/stores/create"
                class="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-5 text-xs font-semibold text-white shadow-sm hover:brightness-105"
            >
                <Coffee :size="14" />
                {{ t('dashboard.create_store_cta') }}
            </Link>
        </section>

        <div class="grid gap-6 md:grid-cols-4">
            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"
                >
                    <Coffee :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.stat_stores') }}
                </h3>
                <p
                    class="mt-2 font-heading text-3xl font-black tracking-tight text-on-surface"
                >
                    {{ stats?.managed_stores ?? 0 }}
                </p>
                <Link
                    href="/stores/index"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-container"
                >
                    {{ t('dashboard.open_stores') }} <ArrowRight :size="14" />
                </Link>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600"
                >
                    <Activity :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.stat_employees') }}
                </h3>
                <p
                    class="mt-2 font-heading text-3xl font-black tracking-tight text-on-surface"
                >
                    {{ stats?.active_employees ?? 0 }}
                </p>
                <Link
                    href="/employees/index"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-container"
                >
                    {{ t('dashboard.open_employees') }}
                    <ArrowRight :size="14" />
                </Link>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600"
                >
                    <Calendar :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.stat_shifts') }}
                </h3>
                <p
                    class="mt-2 font-heading text-3xl font-black tracking-tight text-on-surface"
                >
                    {{ stats?.shifts_this_month ?? 0 }}
                </p>
                <Link
                    href="/schedules/index"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-container"
                >
                    {{ t('dashboard.open_schedules') }}
                    <ArrowRight :size="14" />
                </Link>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 text-rose-600"
                >
                    <AlertTriangle :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.stat_conflicts') }}
                </h3>
                <p
                    class="mt-2 font-heading text-3xl font-black tracking-tight text-on-surface"
                >
                    {{ stats?.open_conflicts ?? 0 }}
                </p>
                <Link
                    href="/conflicts"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-container"
                >
                    {{ t('dashboard.open_conflicts') }}
                    <ArrowRight :size="14" />
                </Link>
            </section>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm lg:col-span-2"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.recent_schedules') }}
                </h2>
                <div
                    v-if="!recent_schedules || recent_schedules.length === 0"
                    class="rounded-xl border border-dashed border-outline-glass bg-white/40 p-6 text-center text-xs text-on-surface-variant italic"
                >
                    {{ t('dashboard.no_schedules') }}
                </div>
                <ul v-else class="space-y-2">
                    <li
                        v-for="s in recent_schedules"
                        :key="s.id"
                        class="flex items-center justify-between rounded-xl border border-outline-glass/40 bg-white px-3 py-2"
                    >
                        <div>
                            <p
                                class="font-heading text-sm font-semibold text-on-surface"
                            >
                                {{ s.name }}
                            </p>
                            <p
                                class="font-mono text-[10px] text-on-surface-variant"
                            >
                                {{ s.period_start }} – {{ s.period_end }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-primary-container/30 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-primary"
                        >
                            {{ s.status }}
                        </span>
                    </li>
                </ul>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.upcoming_shifts') }}
                </h2>
                <div
                    v-if="!upcoming_shifts || upcoming_shifts.length === 0"
                    class="rounded-xl border border-dashed border-outline-glass bg-white/40 p-6 text-center text-xs text-on-surface-variant italic"
                >
                    {{ t('dashboard.no_upcoming') }}
                </div>
                <ul v-else class="space-y-2">
                    <li
                        v-for="s in upcoming_shifts"
                        :key="s.id"
                        class="rounded-xl border border-outline-glass/40 bg-white px-3 py-2 text-xs"
                    >
                        <p class="font-mono font-bold text-on-surface">
                            {{ s.date }}
                        </p>
                        <p class="text-on-surface">
                            {{ s.start_time }} – {{ s.end_time }}
                        </p>
                    </li>
                </ul>
            </section>
        </div>

        <section
            class="mt-8 rounded-2xl border border-outline-glass bg-gradient-to-br from-primary-container/10 via-surface-container-lowest to-secondary-cyan/5 p-6 shadow-sm"
        >
            <div class="mb-3 flex items-center gap-2">
                <Sparkles :size="16" class="text-primary" />
                <span
                    class="font-mono text-[10px] font-extrabold tracking-wider text-primary uppercase"
                    >{{ t('dashboard.ai_planner_label') }}</span
                >
            </div>
            <h2 class="font-heading text-2xl font-bold text-on-surface">
                {{ t('dashboard.ai_planner_title') }}
            </h2>
            <p
                class="mt-2 text-xs font-medium text-on-surface-variant max-w-2xl"
            >
                {{ t('dashboard.ai_planner_description') }}
            </p>
            <div class="mt-3 flex gap-2">
                <Link
                    href="/ai-planner"
                    class="inline-flex h-9 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    {{ t('dashboard.ai_planner_cta') }}
                    <ArrowRight :size="14" class="ml-1.5" />
                </Link>
                <Link
                    href="/schedules/create"
                    class="inline-flex h-9 items-center justify-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('dashboard.ai_planner_create') }}
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
