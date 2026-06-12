<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Calendar, Coffee, ArrowRight, CheckCircle2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { formatDate } from '@/lib/date';

const { t } = useI18n();
const { auth } = useSharedProps();

useBoundLocale();

interface Stat {
    upcoming_shifts: number;
}
interface ShiftRef {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    store_id: number;
}
interface Unavailability {
    id: number;
    date: string;
    note: string | null;
}
interface StoreRef {
    id: number;
    name: string;
}

defineProps<{
    stats?: Stat;
    upcoming_shifts?: ShiftRef[];
    unavailabilities?: Unavailability[];
    assigned_stores?: StoreRef[];
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
                {{ t('dashboard.greeting_employee') }}
                <span
                    class="bg-gradient-to-r from-primary to-secondary-cyan bg-clip-text text-transparent"
                    >{{ auth.user?.email?.split('@')[0] }}.</span
                >
            </h1>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"
                >
                    <CheckCircle2 :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.next_shift') }}
                </h3>
                <div
                    v-if="upcoming_shifts && upcoming_shifts.length > 0"
                    class="mt-2 space-y-1"
                >
                    <p class="font-mono text-xs font-bold text-on-surface">
                        {{ formatDate(upcoming_shifts[0]?.date) }}
                    </p>
                    <p class="text-xs text-on-surface">
                        {{ upcoming_shifts[0]?.start_time }} –
                        {{ upcoming_shifts[0]?.end_time }}
                    </p>
                </div>
                <p
                    v-else
                    class="mt-2 text-xs leading-normal text-on-surface-variant font-medium"
                >
                    {{ t('dashboard.next_shift_empty') }}
                </p>
                <Link
                    href="/my-calendar"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-container"
                >
                    {{ t('dashboard.open_my_calendar') }}
                    <ArrowRight :size="14" />
                </Link>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600"
                >
                    <Coffee :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.assigned_stores') }}
                </h3>
                <ul
                    v-if="assigned_stores && assigned_stores.length > 0"
                    class="mt-2 space-y-1"
                >
                    <li
                        v-for="store in assigned_stores"
                        :key="store.id"
                        class="text-xs font-semibold text-on-surface"
                    >
                        {{ store.name }}
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-2 text-xs leading-normal text-on-surface-variant font-medium"
                >
                    {{ t('dashboard.assigned_stores_empty') }}
                </p>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
            >
                <div
                    class="mb-4 flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600"
                >
                    <Calendar :size="18" />
                </div>
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('dashboard.this_week') }}
                </h3>
                <p
                    class="mt-2 font-heading text-3xl font-black tracking-tight text-on-surface"
                >
                    {{ stats?.upcoming_shifts ?? 0 }}
                </p>
                <Link
                    href="/my-calendar"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-container"
                >
                    {{ t('dashboard.open_my_calendar') }}
                    <ArrowRight :size="14" />
                </Link>
            </section>
        </div>

        <section
            v-if="unavailabilities && unavailabilities.length > 0"
            class="mt-8 rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                {{ t('dashboard.unavailabilities') }}
            </h2>
            <ul class="space-y-2">
                <li
                    v-for="u in unavailabilities"
                    :key="u.id"
                    class="rounded-xl border border-outline-glass/40 bg-white px-3 py-2 text-xs"
                >
                    <p class="font-mono font-bold text-on-surface">
                        {{ formatDate(u.date) }}
                    </p>
                    <p v-if="u.note" class="text-on-surface-variant">
                        {{ u.note }}
                    </p>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
