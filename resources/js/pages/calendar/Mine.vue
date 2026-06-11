<script setup lang="ts">
import { CalendarCheck2, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

interface Shift {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    role_label: string | null;
    note: string | null;
    store_name: string;
    schedule_name: string;
}

const props = defineProps<{
    shifts: Shift[];
    month: string;
    days: string[];
    has_profile: boolean;
}>();

const byDay = computed(() => {
    const map: Record<string, Shift[]> = {};
    for (const s of props.shifts) {
        if (!map[s.date]) map[s.date] = [];
        map[s.date].push(s);
    }
    return map;
});

function prevMonth(m: string): string {
    const d = new Date(m + '-01');
    d.setMonth(d.getMonth() - 1);
    return d.toISOString().slice(0, 7);
}

function nextMonth(m: string): string {
    const d = new Date(m + '-01');
    d.setMonth(d.getMonth() + 1);
    return d.toISOString().slice(0, 7);
}

function weekdayLabel(date: string): string {
    const d = new Date(date);
    return ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'][
        d.getDay() === 0 ? 6 : d.getDay() - 1
    ];
}

function dayLabel(date: string): string {
    return new Date(date).getDate().toString();
}
</script>

<template>
    <AppLayout :title="t('my_calendar.title')">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ t('my_calendar.title') }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">{{ month }}</p>
            </div>
            <div class="flex items-center gap-1">
                <a
                    :href="`/my-calendar?month=${prevMonth(month)}`"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-outline-glass bg-white hover:bg-surface-container-low"
                >
                    <ChevronLeft :size="14" />
                </a>
                <a
                    :href="`/my-calendar?month=${nextMonth(month)}`"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-outline-glass bg-white hover:bg-surface-container-low"
                >
                    <ChevronRight :size="14" />
                </a>
            </div>
        </div>

        <div
            v-if="!has_profile"
            class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
        >
            <CalendarCheck2
                :size="32"
                class="mx-auto mb-3 text-on-surface-variant opacity-40"
            />
            <p class="text-sm text-on-surface-variant">
                No employee profile is linked to your account yet. Ask a manager
                to assign one.
            </p>
        </div>

        <div
            v-else-if="shifts.length === 0"
            class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
        >
            <CalendarCheck2
                :size="32"
                class="mx-auto mb-3 text-on-surface-variant opacity-40"
            />
            <p class="text-sm text-on-surface-variant">
                {{ t('my_calendar.empty') }}
            </p>
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        >
            <table class="min-w-full text-xs">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th
                            v-for="d in days"
                            :key="d"
                            class="border-l border-outline-glass/30 px-1 py-2 text-center font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                        >
                            <div>{{ weekdayLabel(d) }}</div>
                            <div>{{ dayLabel(d) }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td
                            v-for="d in days"
                            :key="d"
                            class="h-32 align-top border-l border-t border-outline-glass/30 p-1"
                        >
                            <div
                                v-for="s in byDay[d] ?? []"
                                :key="s.id"
                                class="mb-1 rounded-lg border border-emerald-200 bg-emerald-50 p-1.5 text-[10px] text-emerald-700"
                            >
                                <p class="font-mono font-bold">
                                    {{ s.start_time }} – {{ s.end_time }}
                                </p>
                                <p class="mt-0.5 font-semibold">
                                    {{ s.store_name }}
                                </p>
                                <p
                                    v-if="s.role_label"
                                    class="text-[9px] opacity-75"
                                >
                                    {{ s.role_label }}
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section v-if="shifts.length > 0" class="mt-6">
            <h2 class="mb-2 font-heading text-sm font-bold text-on-surface">
                Upcoming shifts
            </h2>
            <ul class="space-y-1.5">
                <li
                    v-for="s in shifts"
                    :key="s.id"
                    class="rounded-xl border border-outline-glass bg-surface-container-lowest p-3 text-xs shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-mono font-bold text-on-surface">
                                {{ s.date }} · {{ s.start_time }} –
                                {{ s.end_time }}
                            </p>
                            <p class="mt-0.5 text-on-surface-variant">
                                {{ s.store_name }} · {{ s.schedule_name }}
                                <span v-if="s.role_label">
                                    · {{ s.role_label }}</span
                                >
                            </p>
                            <p
                                v-if="s.note"
                                class="mt-0.5 italic text-on-surface-variant"
                            >
                                {{ s.note }}
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
