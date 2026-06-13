<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Briefcase, CalendarRange, Clock, User, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatDate, formatDateRange } from '@/lib/date';

const { t, tm } = useI18n();

interface StoreOption {
    id: number;
    name: string;
}

interface ScheduleOption {
    id: number;
    name: string;
    period_start: string;
    period_end: string;
}

interface Assignment {
    id: number;
    employee_name: string;
    start_time: string;
    end_time: string;
}

interface Shift {
    id: number;
    start_time: string;
    end_time: string;
    role_label: string | null;
    note: string | null;
    assignments: Assignment[];
}

interface CalendarDay {
    date: string;
    dayOfMonth: string;
    inPeriod: boolean;
    shifts: Shift[];
}

const props = withDefaults(
    defineProps<{
        stores: StoreOption[];
        selectedStoreId: number | null;
        schedules: ScheduleOption[];
        selectedSchedule: ScheduleOption | null;
        days: Record<string, { shifts: Shift[] }>;
        routeBase: string;
        token?: string | null;
    }>(),
    {
        token: null,
    },
);

const weekdays = computed(() => tm('common.weekdays') as string[]);
const dayKeys = computed(() => Object.keys(props.days).sort());
const currentView = ref<'calendar' | 'list'>('calendar');

const selectedStore = computed(
    () =>
        props.stores.find((store) => store.id === props.selectedStoreId) ??
        null,
);

const scheduleRange = computed(() =>
    props.selectedSchedule
        ? formatDateRange(
              props.selectedSchedule.period_start,
              props.selectedSchedule.period_end,
          )
        : '',
);

const calendarWeeks = computed(() => {
    const startStr = props.selectedSchedule?.period_start;
    const endStr = props.selectedSchedule?.period_end;
    if (!startStr || !endStr) return [];

    const parseLocalDate = (dateStr: string): Date => {
        const [y, m, d] = dateStr.split('-').map(Number);
        return new Date(y, m - 1, d);
    };

    const start = parseLocalDate(startStr);
    const end = parseLocalDate(endStr);
    const firstDay = new Date(start.getFullYear(), start.getMonth(), 1);
    const startDay = firstDay.getDay();
    const startOffset = startDay === 0 ? -6 : 1 - startDay;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() + startOffset);

    const lastDay = new Date(end.getFullYear(), end.getMonth() + 1, 0);
    const endDay = lastDay.getDay();
    const endOffset = endDay === 0 ? 0 : 7 - endDay;
    const gridEnd = new Date(lastDay);
    gridEnd.setDate(lastDay.getDate() + endOffset);

    const weeks: CalendarDay[][] = [];
    let currentWeek: CalendarDay[] = [];

    const iter = new Date(gridStart);
    while (iter <= gridEnd) {
        const y = iter.getFullYear();
        const m = String(iter.getMonth() + 1).padStart(2, '0');
        const d = String(iter.getDate()).padStart(2, '0');
        const dateStr = `${y}-${m}-${d}`;

        currentWeek.push({
            date: dateStr,
            dayOfMonth: iter.getDate().toString(),
            inPeriod: dateStr >= startStr && dateStr <= endStr,
            shifts: props.days[dateStr]?.shifts || [],
        });

        if (currentWeek.length === 7) {
            weeks.push(currentWeek);
            currentWeek = [];
        }

        iter.setDate(iter.getDate() + 1);
    }

    return weeks;
});

function viewerUrl(params: Record<string, number | null | undefined>): string {
    const query = new URLSearchParams();
    if (props.token) {
        query.set('token', props.token);
    }
    for (const [key, value] of Object.entries(params)) {
        if (typeof value === 'number') {
            query.set(key, String(value));
        }
    }

    const queryString = query.toString();
    return queryString ? `${props.routeBase}?${queryString}` : props.routeBase;
}

function dateLabel(date: string): string {
    return formatDate(date);
}
</script>

<template>
    <section
        v-if="stores.length > 0"
        class="mb-5 flex flex-col gap-3 border-b border-outline-glass pb-5"
    >
        <div>
            <h2 class="font-heading text-sm font-bold">
                {{ selectedStore?.name ?? t('public_schedules.no_store') }}
            </h2>
            <p
                v-if="selectedSchedule"
                class="mt-1 text-xs text-on-surface-variant"
            >
                {{ selectedSchedule.name }} · {{ scheduleRange }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                v-for="store in stores"
                :key="store.id"
                :href="viewerUrl({ store_id: store.id })"
                :class="[
                    'inline-flex h-8 items-center rounded-lg border px-3 text-xs font-semibold',
                    store.id === selectedStoreId
                        ? 'border-primary/30 bg-primary text-white'
                        : 'border-outline-glass bg-white text-on-surface hover:bg-surface-container-low',
                ]"
            >
                {{ store.name }}
            </Link>
        </div>

        <div v-if="schedules.length > 1" class="flex flex-wrap gap-2">
            <Link
                v-for="schedule in schedules"
                :key="schedule.id"
                :href="
                    viewerUrl({
                        store_id: selectedStoreId,
                        schedule_id: schedule.id,
                    })
                "
                :class="[
                    'inline-flex h-8 items-center rounded-lg border px-3 text-xs font-semibold',
                    schedule.id === selectedSchedule?.id
                        ? 'border-emerald-400 bg-emerald-50 text-emerald-700'
                        : 'border-outline-glass bg-white text-on-surface hover:bg-surface-container-low',
                ]"
            >
                {{
                    formatDateRange(schedule.period_start, schedule.period_end)
                }}
            </Link>
        </div>
    </section>

    <section
        v-if="stores.length === 0"
        class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
    >
        <Users
            :size="32"
            class="mx-auto mb-3 text-on-surface-variant opacity-40"
        />
        <p class="text-sm text-on-surface-variant">
            {{ t('public_schedules.no_stores') }}
        </p>
    </section>

    <section
        v-else-if="selectedSchedule === null"
        class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
    >
        <CalendarRange
            :size="32"
            class="mx-auto mb-3 text-on-surface-variant opacity-40"
        />
        <p class="text-sm text-on-surface-variant">
            {{ t('public_schedules.no_schedules') }}
        </p>
    </section>

    <section v-else class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-heading text-sm font-bold text-on-surface">
                {{ selectedSchedule.name }}
            </h2>
            <div
                class="flex rounded-xl border border-outline-glass bg-surface-container-low p-0.5"
            >
                <button
                    type="button"
                    @click="currentView = 'calendar'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        currentView === 'calendar'
                            ? 'bg-white text-primary shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface',
                    ]"
                >
                    {{ t('schedules.calendar_view') }}
                </button>
                <button
                    type="button"
                    @click="currentView = 'list'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        currentView === 'list'
                            ? 'bg-white text-primary shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface',
                    ]"
                >
                    {{ t('schedules.list_view') }}
                </button>
            </div>
        </div>

        <div v-if="currentView === 'list'" class="space-y-4">
            <div
                v-for="date in dayKeys"
                :key="date"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
            >
                <div
                    class="border-b border-outline-glass/50 bg-surface-container-low px-5 py-3"
                >
                    <h3 class="font-heading text-sm font-bold text-on-surface">
                        {{ dateLabel(date) }}
                    </h3>
                </div>

                <div
                    v-if="days[date]?.shifts.length === 0"
                    class="flex items-center justify-center py-10 text-xs text-on-surface-variant/60"
                >
                    <div class="flex flex-col items-center gap-2">
                        <Clock :size="18" class="text-on-surface-variant/30" />
                        <span>{{ t('schedules.no_shifts') }}</span>
                    </div>
                </div>

                <div v-else class="divide-y divide-outline-glass/30">
                    <div v-for="shift in days[date]?.shifts" :key="shift.id">
                        <div class="flex items-center gap-4 px-5 py-3">
                            <div
                                :class="[
                                    'w-1 self-stretch rounded-full shrink-0',
                                    shift.assignments.length === 0
                                        ? 'bg-rose-400'
                                        : 'bg-emerald-400',
                                ]"
                            />
                            <div
                                class="flex w-32 shrink-0 items-center gap-1.5"
                            >
                                <Clock
                                    :size="13"
                                    class="shrink-0 text-on-surface-variant/60"
                                />
                                <span
                                    class="font-mono text-sm font-bold text-on-surface"
                                >
                                    {{ shift.start_time.substring(0, 5) }}–{{
                                        shift.end_time.substring(0, 5)
                                    }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <span
                                    v-if="shift.role_label"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant"
                                >
                                    <Briefcase
                                        :size="11"
                                        class="text-on-surface-variant/50"
                                    />
                                    {{ shift.role_label }}
                                </span>
                                <span
                                    v-if="shift.note"
                                    :class="[
                                        'block truncate text-[11px] italic text-on-surface-variant/70',
                                        shift.role_label ? 'mt-0.5' : '',
                                    ]"
                                >
                                    {{ shift.note }}
                                </span>
                                <span
                                    v-if="!shift.role_label && !shift.note"
                                    class="text-xs text-on-surface-variant/40 italic"
                                >
                                    &mdash;
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="shift.assignments.length > 0"
                            class="divide-y divide-outline-glass/10 border-t border-outline-glass/20"
                        >
                            <div
                                v-for="assignment in shift.assignments"
                                :key="assignment.id"
                                class="px-5 py-2.5 pl-10"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-container text-on-surface-variant/60"
                                    >
                                        <User :size="11" />
                                    </div>
                                    <span
                                        class="flex-1 text-xs font-semibold text-on-surface"
                                    >
                                        {{ assignment.employee_name }}
                                        <span
                                            class="ml-1.5 font-mono text-[10px] font-normal text-on-surface-variant/70"
                                        >
                                            ({{
                                                assignment.start_time.substring(
                                                    0,
                                                    5,
                                                )
                                            }}–{{
                                                assignment.end_time.substring(
                                                    0,
                                                    5,
                                                )
                                            }})
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="border-t border-outline-glass/20 px-5 py-2.5 pl-10"
                        >
                            <span
                                class="text-[11px] italic text-on-surface-variant/50"
                            >
                                {{ t('schedules.no_assignments') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else-if="currentView === 'calendar'"
            class="overflow-x-auto rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        >
            <div class="min-w-[700px]">
                <div
                    class="grid grid-cols-7 border-b border-outline-glass bg-surface-container-low"
                >
                    <div
                        v-for="dayName in weekdays"
                        :key="dayName"
                        class="py-3 text-center font-mono text-[9px] font-extrabold tracking-widest text-on-surface-variant/70 uppercase"
                    >
                        {{ dayName }}
                    </div>
                </div>

                <div class="divide-y divide-outline-glass/20">
                    <div
                        v-for="(week, weekIdx) in calendarWeeks"
                        :key="weekIdx"
                        class="grid min-h-[140px] grid-cols-7 divide-x divide-outline-glass/20"
                    >
                        <div
                            v-for="day in week"
                            :key="day.date"
                            :class="[
                                'relative flex flex-col gap-1.5 p-2',
                                day.inPeriod
                                    ? 'bg-white'
                                    : 'bg-surface-container-low/30',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex h-5 w-5 items-center justify-center rounded-full font-mono text-[11px] font-bold',
                                    !day.inPeriod
                                        ? 'text-on-surface-variant/25'
                                        : day.date ===
                                            new Date()
                                                .toISOString()
                                                .substring(0, 10)
                                          ? 'bg-primary text-white'
                                          : 'text-on-surface',
                                ]"
                            >
                                {{ day.dayOfMonth }}
                            </span>

                            <div class="flex flex-1 flex-col gap-1">
                                <div
                                    v-for="shift in day.shifts"
                                    :key="shift.id"
                                    :class="[
                                        'flex w-full overflow-hidden rounded-lg border text-left',
                                        shift.assignments.length === 0
                                            ? 'border-rose-200 bg-rose-50/60'
                                            : 'border-emerald-200 bg-emerald-50/60',
                                    ]"
                                >
                                    <div
                                        :class="[
                                            'w-1 shrink-0 self-stretch',
                                            shift.assignments.length === 0
                                                ? 'bg-rose-400'
                                                : 'bg-emerald-400',
                                        ]"
                                    />
                                    <div class="min-w-0 flex-1 px-1.5 py-1">
                                        <span
                                            :class="[
                                                'font-mono text-[9px] font-bold leading-none',
                                                shift.assignments.length === 0
                                                    ? 'text-rose-700'
                                                    : 'text-emerald-700',
                                            ]"
                                        >
                                            {{
                                                shift.start_time.substring(
                                                    0,
                                                    5,
                                                )
                                            }}–{{
                                                shift.end_time.substring(0, 5)
                                            }}
                                        </span>

                                        <div
                                            v-if="shift.role_label"
                                            :class="[
                                                'mt-0.5 truncate text-[8px] font-semibold leading-none',
                                                shift.assignments.length === 0
                                                    ? 'text-rose-600/80'
                                                    : 'text-emerald-600/80',
                                            ]"
                                        >
                                            {{ shift.role_label }}
                                        </div>

                                        <div
                                            v-if="shift.assignments.length > 0"
                                            class="mt-1 space-y-0.5 border-t border-current/10 pt-0.5"
                                        >
                                            <div
                                                v-for="assignment in shift.assignments"
                                                :key="assignment.id"
                                                class="flex min-w-0 items-center gap-1"
                                            >
                                                <span
                                                    class="h-1 w-1 shrink-0 rounded-full bg-emerald-400"
                                                />
                                                <span
                                                    class="truncate text-[7.5px] font-semibold leading-none text-on-surface/80"
                                                >
                                                    {{
                                                        assignment.employee_name
                                                    }}
                                                    <span
                                                        class="ml-0.5 font-mono text-[7px] font-normal text-on-surface-variant/70"
                                                    >
                                                        ({{
                                                            assignment.start_time.substring(
                                                                0,
                                                                5,
                                                            )
                                                        }}–{{
                                                            assignment.end_time.substring(
                                                                0,
                                                                5,
                                                            )
                                                        }})
                                                    </span>
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-1 text-[8px] leading-none text-rose-500/70 italic"
                                        >
                                            {{ t('schedules.no_assignments') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
