<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Plus,
    Trash2,
    X,
    Wand2,
    AlertTriangle,
    Edit,
    Clock,
    Briefcase,
    User,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSharedProps } from '@/composables/useSharedProps';
import { formatDate, formatDateRange } from '@/lib/date';

const { t, tm } = useI18n();
const { confirm } = useConfirmDialog();
const { auth } = useSharedProps();

useBoundLocale();

interface Assignment {
    id: number;
    employee_profile_id: number;
    employee_name: string;
    start_time: string;
    end_time: string;
    status: string;
}

interface Shift {
    id: number;
    start_time: string;
    end_time: string;
    role_label: string | null;
    note: string | null;
    source: string;
    assignments: Assignment[];
}

interface Conflict {
    id: number;
    type: string;
    severity: string;
    message: string;
    suggested_fix: string | null;
    employee_id: number | null;
    shift_requirement_id: number | null;
}

interface Schedule {
    id: number;
    name: string;
    status: string;
    period_start: string;
    period_end: string;
    store_id: number;
    store_name: string;
}

const props = defineProps<{
    schedule: Schedule;
    days: Record<string, { shifts: Shift[] }>;
    conflicts: Conflict[];
    employees: {
        id: number;
        name: string;
        role_label: string | null;
        hourly_rate: number | null;
    }[];
}>();

const isPublished = computed(() => props.schedule.status === 'published');
const isManager = computed(() => auth.value.user?.role === 'store_manager');
const criticalConflicts = computed(() =>
    props.conflicts.filter(
        (c) => c.severity === 'critical' && c.shift_requirement_id === null,
    ),
);

const dayKeys = computed(() => Object.keys(props.days).sort());
const weekdays = computed(() => tm('common.weekdays') as string[]);

const employeeStats = computed(() => {
    const statsMap: Record<
        number,
        {
            id: number;
            name: string;
            role_label: string | null;
            hourly_rate: number | null;
            shifts_count: number;
            total_hours: number;
        }
    > = {};

    props.employees.forEach((e) => {
        statsMap[e.id] = {
            id: e.id,
            name: e.name,
            role_label: e.role_label,
            hourly_rate: e.hourly_rate,
            shifts_count: 0,
            total_hours: 0,
        };
    });

    const parseTimeToMinutes = (timeStr: string): number => {
        const parts = timeStr.split(':');
        if (parts.length < 2) return 0;
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        return h * 60 + m;
    };

    const getDurationHours = (start: string, end: string): number => {
        const startMins = parseTimeToMinutes(start);
        const endMins = parseTimeToMinutes(end);
        const diff = endMins - startMins;
        return diff > 0 ? diff / 60 : 0;
    };

    Object.values(props.days).forEach((day) => {
        day.shifts.forEach((shift) => {
            shift.assignments.forEach((assignment) => {
                if (
                    assignment.status !== 'cancelled' &&
                    statsMap[assignment.employee_profile_id]
                ) {
                    statsMap[assignment.employee_profile_id].shifts_count += 1;
                    statsMap[assignment.employee_profile_id].total_hours +=
                        getDurationHours(
                            assignment.start_time,
                            assignment.end_time,
                        );
                }
            });
        });
    });

    return Object.values(statsMap).sort((a, b) => a.name.localeCompare(b.name));
});

const currentView = ref<'calendar' | 'list'>('calendar');

interface CalendarDay {
    date: string;
    dayOfMonth: string;
    inPeriod: boolean;
    shifts: Shift[];
}

const calendarWeeks = computed(() => {
    const startStr = props.schedule.period_start;
    const endStr = props.schedule.period_end;
    if (!startStr || !endStr) return [];

    const parseLocalDate = (dateStr: string): Date => {
        const [y, m, d] = dateStr.split('-').map(Number);
        return new Date(y, m - 1, d);
    };

    const start = parseLocalDate(startStr);
    const end = parseLocalDate(endStr);

    // Align to the first day of the starting month
    const firstDay = new Date(start.getFullYear(), start.getMonth(), 1);
    const startDay = firstDay.getDay();
    const startOffset = startDay === 0 ? -6 : 1 - startDay;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() + startOffset);

    // Align to the last day of the ending month
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

        const inPeriod = dateStr >= startStr && dateStr <= endStr;
        const shifts = props.days[dateStr]?.shifts || [];

        currentWeek.push({
            date: dateStr,
            dayOfMonth: iter.getDate().toString(),
            inPeriod,
            shifts,
        });

        if (currentWeek.length === 7) {
            weeks.push(currentWeek);
            currentWeek = [];
        }

        iter.setDate(iter.getDate() + 1);
    }

    return weeks;
});

const selectedShiftId = ref<number | null>(null);
const shiftModalDeleted = ref(false);
const showShiftModalFlash = ref(false);
const showCreateShiftModalFlash = ref(false);
const selectedShift = computed<Shift | null>(() => {
    if (selectedShiftId.value === null) return null;
    for (const date of Object.keys(props.days)) {
        const found = props.days[date].shifts.find(
            (s) => s.id === selectedShiftId.value,
        );
        if (found) return found;
    }
    return null;
});
const showShiftPanel = ref(false);
const showCreateShift = ref<string | null>(null);

const createForm = useForm({
    date: '',
    start_time: '10:00',
    end_time: '18:00',
    role_label: '',
    note: '',
    employee_profile_ids: [] as number[],
});

function openShift(shift: Shift): void {
    selectedShiftId.value = shift.id;
    shiftModalDeleted.value = false;
    showShiftModalFlash.value = false;
    showShiftPanel.value = true;
    assignForm.clearErrors();
    assignForm.start_time = shift.start_time.substring(0, 5);
    assignForm.end_time = shift.end_time.substring(0, 5);
}

function openCreate(date: string): void {
    showCreateShift.value = date;
    showCreateShiftModalFlash.value = false;
    createForm.date = date;
    createForm.employee_profile_ids = [];
    createForm.clearErrors();
}

function submitCreate(): void {
    showCreateShiftModalFlash.value = true;
    createForm.post(
        `/shift-requirements/store?schedule_id=${props.schedule.id}`,
        {
            onSuccess: () => {
                createForm.reset(
                    'start_time',
                    'end_time',
                    'role_label',
                    'note',
                    'employee_profile_ids',
                );
            },
        },
    );
}

function autoFill(shiftId: number): void {
    showShiftModalFlash.value = true;
    router.post('/shift-requirements/auto-fill', {
        shift_requirement_id: shiftId,
    });
}

const assignForm = useForm({
    employee_profile_id: 0,
    start_time: '',
    end_time: '',
});

function assignEmployee(shiftId: number): void {
    if (!assignForm.employee_profile_id) return;
    showShiftModalFlash.value = true;
    assignForm
        .transform((data) => ({
            ...data,
            shift_requirement_id: shiftId,
        }))
        .post('/shift-assignments/store', {
            onSuccess: () => {
                assignForm.employee_profile_id = 0;
            },
        });
}

async function removeAssignment(id: number): Promise<void> {
    if (
        await confirm(t('schedules.confirm_remove_assignment'), {
            variant: 'danger',
        })
    ) {
        showShiftModalFlash.value = true;
        router.post('/shift-assignments/destroy', { id });
    }
}

async function removeShift(id: number): Promise<void> {
    if (
        await confirm(t('schedules.confirm_delete_shift'), {
            variant: 'danger',
        })
    ) {
        showShiftModalFlash.value = true;
        router.post(
            '/shift-requirements/destroy',
            { id },
            {
                onSuccess: () => {
                    shiftModalDeleted.value = true;
                },
            },
        );
    }
}

function closeShiftPanel(): void {
    showShiftPanel.value = false;
    shiftModalDeleted.value = false;
    showShiftModalFlash.value = false;
}

function closeCreateShift(): void {
    showCreateShift.value = null;
    showCreateShiftModalFlash.value = false;
}

function publish(): void {
    router.post(`/schedules/publish?id=${props.schedule.id}`);
}

function archive(): void {
    router.post(`/schedules/archive?id=${props.schedule.id}`);
}

async function removeSchedule(): Promise<void> {
    if (
        await confirm(t('common.confirm_title'), {
            variant: 'danger',
        })
    ) {
        router.post(`/schedules/destroy?id=${props.schedule.id}`);
    }
}

function getShiftConflicts(shiftId: number): Conflict[] {
    return props.conflicts.filter((c) => c.shift_requirement_id === shiftId);
}

function getEmployeeConflicts(shiftId: number, employeeId: number): Conflict[] {
    const seen = new Set<string>();
    return props.conflicts.filter((c) => {
        if (c.shift_requirement_id !== shiftId || c.employee_id !== employeeId)
            return false;
        if (seen.has(c.message)) return false;
        seen.add(c.message);
        return true;
    });
}

function getUnattributedShiftConflicts(shiftId: number): Conflict[] {
    return props.conflicts.filter(
        (c) => c.shift_requirement_id === shiftId && c.employee_id === null,
    );
}

function dateLabel(d: string): string {
    return formatDate(d);
}
</script>

<template>
    <AppLayout :title="schedule.name">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ schedule.name }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ schedule.store_name }} ·
                    {{
                        formatDateRange(
                            schedule.period_start,
                            schedule.period_end,
                        )
                    }}
                </p>
            </div>
            <div class="flex gap-2 items-center">
                <!-- View Toggle -->
                <div
                    class="flex rounded-xl bg-surface-container-low p-0.5 border border-outline-glass mr-2"
                >
                    <button
                        type="button"
                        @click="currentView = 'calendar'"
                        :class="[
                            'px-3 py-1.5 text-xs font-semibold rounded-lg cursor-pointer transition-colors',
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
                            'px-3 py-1.5 text-xs font-semibold rounded-lg cursor-pointer transition-colors',
                            currentView === 'list'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-on-surface-variant hover:text-on-surface',
                        ]"
                    >
                        {{ t('schedules.list_view') }}
                    </button>
                </div>

                <Link
                    v-if="isManager"
                    :href="`/schedules/edit?id=${schedule.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <Edit :size="14" class="mr-1.5" />
                    {{ t('schedules.edit_link') }}
                </Link>
                <div
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-surface-container px-4 text-xs font-semibold text-on-surface-variant cursor-default"
                >
                    <AlertTriangle
                        :size="14"
                        :class="[
                            'mr-1.5',
                            conflicts.length > 0
                                ? 'text-rose-500 animate-pulse'
                                : 'text-on-surface-variant/50',
                        ]"
                    />
                    {{ conflicts.length }}
                </div>
                <button
                    v-if="isManager && !isPublished"
                    @click="publish"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                >
                    {{ t('schedules.publish_cta') }}
                </button>
                <button
                    v-else-if="isManager"
                    @click="archive"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('schedules.archive_cta') }}
                </button>
                <button
                    v-if="isManager"
                    type="button"
                    @click="removeSchedule"
                    class="inline-flex h-9 cursor-pointer items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                >
                    <Trash2 :size="14" />
                    {{ t('common.delete') }}
                </button>
            </div>
        </div>

        <div
            v-if="criticalConflicts.length > 0"
            class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700"
        >
            <p class="font-semibold mb-2">
                {{ t('schedules.critical_conflicts_warning') }}
            </p>
            <ul class="list-disc pl-4 space-y-1">
                <li v-for="c in criticalConflicts" :key="c.id">
                    {{ c.message }}
                    <span v-if="c.suggested_fix" class="italic opacity-85 ml-1">
                        ({{ c.suggested_fix }})
                    </span>
                </li>
            </ul>
        </div>

        <!-- Employee Statistics section -->
        <section
            class="mb-6 rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <h2 class="mb-4 font-heading text-sm font-bold text-on-surface">
                {{ t('schedules.employee_statistics_title') }}
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-outline-glass/30 font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                        >
                            <th class="pb-2 pr-3 font-semibold">
                                {{ t('employees.name') }}
                            </th>
                            <th class="pb-2 px-3 font-semibold">
                                {{ t('employees.role_label') }}
                            </th>
                            <th class="pb-2 px-3 font-semibold">
                                {{ t('employees.hourly_rate') }}
                            </th>
                            <th class="pb-2 px-3 font-semibold">
                                {{ t('schedules.assigned_shifts') }}
                            </th>
                            <th class="pb-2 px-3 font-semibold">
                                {{ t('schedules.assigned_hours') }}
                            </th>
                            <th class="pb-2 pl-3 font-semibold text-right">
                                {{ t('schedules.total_wage') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="e in employeeStats"
                            :key="e.id"
                            class="border-b border-outline-glass/20 last:border-0 hover:bg-surface-container-low/40"
                        >
                            <td class="py-3 pr-3 font-semibold text-on-surface">
                                {{ e.name }}
                            </td>
                            <td class="py-3 px-3 text-on-surface-variant">
                                {{ e.role_label ?? '—' }}
                            </td>
                            <td class="py-3 px-3 font-mono text-on-surface">
                                <span v-if="e.hourly_rate !== null">
                                    {{ e.hourly_rate }} CZK/h
                                </span>
                                <span v-else class="text-on-surface-variant/50"
                                    >—</span
                                >
                            </td>
                            <td class="py-3 px-3 font-mono text-on-surface">
                                {{ e.shifts_count }}
                            </td>
                            <td class="py-3 px-3 font-mono text-on-surface">
                                {{ e.total_hours.toFixed(1) }} h
                            </td>
                            <td
                                class="py-3 pl-3 font-mono font-bold text-on-surface text-right"
                            >
                                <span v-if="e.hourly_rate !== null">
                                    {{
                                        (
                                            e.total_hours * e.hourly_rate
                                        ).toLocaleString()
                                    }}
                                    CZK
                                </span>
                                <span v-else class="text-on-surface-variant/50"
                                    >—</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="currentView === 'list'" class="space-y-4">
            <div
                v-for="date in dayKeys"
                :key="date"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm overflow-hidden"
            >
                <!-- Date header -->
                <div
                    class="flex items-center justify-between bg-surface-container-low border-b border-outline-glass/50 px-5 py-3"
                >
                    <h3 class="font-heading text-sm font-bold text-on-surface">
                        {{ dateLabel(date) }}
                    </h3>
                    <button
                        v-if="isManager"
                        @click="openCreate(date)"
                        class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-lg border border-outline-glass bg-white px-2.5 text-[11px] font-semibold text-on-surface hover:bg-surface-container transition-colors"
                    >
                        <Plus :size="11" />
                        {{ t('schedules.new_shift') }}
                    </button>
                </div>

                <!-- No shifts state -->
                <div
                    v-if="props.days[date]?.shifts.length === 0"
                    class="flex items-center justify-center py-10 text-xs text-on-surface-variant/60"
                >
                    <div class="flex flex-col items-center gap-2">
                        <Clock :size="18" class="text-on-surface-variant/30" />
                        <span>{{ t('schedules.no_shifts') }}</span>
                    </div>
                </div>

                <!-- Shifts -->
                <div v-else class="divide-y divide-outline-glass/30">
                    <div
                        v-for="s in props.days[date]?.shifts"
                        :key="s.id"
                        @click="openShift(s)"
                        class="cursor-pointer transition-colors hover:bg-surface-container-lowest/60"
                    >
                        <!-- Shift top row: time + role + actions -->
                        <div class="flex items-center gap-4 px-5 py-3">
                            <!-- Accent stripe -->
                            <div
                                :class="[
                                    'w-1 self-stretch rounded-full shrink-0',
                                    s.assignments.length === 0
                                        ? 'bg-rose-400'
                                        : getShiftConflicts(s.id).length > 0
                                          ? 'bg-amber-400'
                                          : 'bg-emerald-400',
                                ]"
                            />

                            <!-- Time -->
                            <div
                                class="flex items-center gap-1.5 w-32 shrink-0"
                            >
                                <Clock
                                    :size="13"
                                    class="text-on-surface-variant/60 shrink-0"
                                />
                                <span
                                    class="font-mono text-sm font-bold text-on-surface"
                                >
                                    {{ s.start_time.substring(0, 5) }}–{{
                                        s.end_time.substring(0, 5)
                                    }}
                                </span>
                            </div>

                            <!-- Role label + note -->
                            <div class="flex-1 min-w-0">
                                <span
                                    v-if="s.role_label"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant"
                                >
                                    <Briefcase
                                        :size="11"
                                        class="text-on-surface-variant/50"
                                    />
                                    {{ s.role_label }}
                                </span>
                                <span
                                    v-if="s.note"
                                    :class="[
                                        'block text-[11px] italic text-on-surface-variant/70 truncate',
                                        s.role_label ? 'mt-0.5' : '',
                                    ]"
                                >
                                    {{ s.note }}
                                </span>
                                <span
                                    v-if="!s.role_label && !s.note"
                                    class="text-xs text-on-surface-variant/40 italic"
                                >
                                    &mdash;
                                </span>
                            </div>
                        </div>

                        <!-- Assignments section -->
                        <div
                            v-if="s.assignments.length > 0"
                            class="border-t border-outline-glass/20 divide-y divide-outline-glass/10"
                        >
                            <div
                                v-for="a in s.assignments"
                                :key="a.id"
                                class="px-5 py-2.5 pl-10"
                            >
                                <!-- Employee row -->
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-container text-on-surface-variant/60"
                                    >
                                        <User :size="11" />
                                    </div>
                                    <span
                                        class="flex-1 text-xs font-semibold text-on-surface"
                                    >
                                        {{ a.employee_name }}
                                        <span
                                            class="ml-1.5 text-[10px] font-normal text-on-surface-variant/70 font-mono"
                                        >
                                            ({{
                                                a.start_time.substring(0, 5)
                                            }}–{{ a.end_time.substring(0, 5) }})
                                        </span>
                                    </span>
                                    <button
                                        v-if="isManager"
                                        @click.stop="removeAssignment(a.id)"
                                        class="rounded-md p-1 text-rose-400 hover:bg-rose-50 hover:text-rose-600 transition-colors"
                                        :title="
                                            t(
                                                'schedules.confirm_remove_assignment',
                                            )
                                        "
                                    >
                                        <X :size="11" />
                                    </button>
                                </div>

                                <!-- Per-employee conflict messages -->
                                <div
                                    v-if="
                                        getEmployeeConflicts(
                                            s.id,
                                            a.employee_profile_id,
                                        ).length > 0
                                    "
                                    class="mt-1.5 ml-9 space-y-1"
                                >
                                    <div
                                        v-for="c in getEmployeeConflicts(
                                            s.id,
                                            a.employee_profile_id,
                                        )"
                                        :key="c.id"
                                        class="flex items-start gap-1.5 text-[11px] font-medium text-amber-700"
                                    >
                                        <AlertTriangle
                                            :size="11"
                                            class="shrink-0 mt-0.5 text-amber-500"
                                        />
                                        <span>{{ c.message }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No assignment state -->
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

                        <!-- Shift-level conflicts (not tied to a specific employee) -->
                        <div
                            v-if="
                                getUnattributedShiftConflicts(s.id).length > 0
                            "
                            class="border-t border-rose-100 bg-rose-50/60 px-5 py-2.5 pl-10 space-y-1"
                        >
                            <div
                                v-for="c in getUnattributedShiftConflicts(s.id)"
                                :key="c.id"
                                class="flex items-start gap-1.5 text-[11px] font-medium text-rose-700"
                            >
                                <AlertTriangle
                                    :size="11"
                                    class="shrink-0 mt-0.5 text-rose-500"
                                />
                                <span>{{ c.message }}</span>
                            </div>
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
                <!-- Weekday header -->
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

                <!-- Week rows -->
                <div class="divide-y divide-outline-glass/20">
                    <div
                        v-for="(week, weekIdx) in calendarWeeks"
                        :key="weekIdx"
                        class="grid grid-cols-7 divide-x divide-outline-glass/20 min-h-[140px]"
                    >
                        <div
                            v-for="day in week"
                            :key="day.date"
                            :class="[
                                'flex flex-col gap-1.5 p-2 transition-colors relative group',
                                day.inPeriod
                                    ? 'bg-white hover:bg-surface-container-lowest'
                                    : 'bg-surface-container-low/30',
                            ]"
                        >
                            <!-- Day number -->
                            <div class="flex items-center justify-between">
                                <span
                                    :class="[
                                        'flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-bold font-mono transition-colors',
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
                                <!-- Add shift button -->
                                <button
                                    v-if="day.inPeriod && isManager"
                                    type="button"
                                    @click="openCreate(day.date)"
                                    class="opacity-0 group-hover:opacity-100 flex h-5 w-5 items-center justify-center rounded-md text-primary hover:bg-primary/10 transition-all cursor-pointer"
                                    :title="t('schedules.new_shift')"
                                >
                                    <Plus :size="11" />
                                </button>
                            </div>

                            <!-- Shifts -->
                            <div class="flex flex-col gap-1 flex-1">
                                <button
                                    v-for="s in day.shifts"
                                    :key="s.id"
                                    type="button"
                                    @click="openShift(s)"
                                    :class="[
                                        'w-full text-left rounded-lg overflow-hidden flex transition-all hover:shadow-sm cursor-pointer border',
                                        s.assignments.length === 0
                                            ? 'border-rose-200 bg-rose-50/60 hover:bg-rose-50'
                                            : getShiftConflicts(s.id).length > 0
                                              ? 'border-amber-200 bg-amber-50/60 hover:bg-amber-50'
                                              : 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-50',
                                    ]"
                                >
                                    <!-- Accent strip -->
                                    <div
                                        :class="[
                                            'w-1 shrink-0 self-stretch',
                                            s.assignments.length === 0
                                                ? 'bg-rose-400'
                                                : getShiftConflicts(s.id)
                                                        .length > 0
                                                  ? 'bg-amber-400'
                                                  : 'bg-emerald-400',
                                        ]"
                                    />
                                    <!-- Card content -->
                                    <div class="flex-1 min-w-0 px-1.5 py-1">
                                        <!-- Time + conflict icon -->
                                        <div
                                            class="flex items-center justify-between gap-1"
                                        >
                                            <span
                                                :class="[
                                                    'font-mono text-[9px] font-bold leading-none',
                                                    s.assignments.length === 0
                                                        ? 'text-rose-700'
                                                        : getShiftConflicts(
                                                                s.id,
                                                            ).length > 0
                                                          ? 'text-amber-700'
                                                          : 'text-emerald-700',
                                                ]"
                                            >
                                                {{
                                                    s.start_time.substring(
                                                        0,
                                                        5,
                                                    )
                                                }}–{{
                                                    s.end_time.substring(0, 5)
                                                }}
                                            </span>
                                            <AlertTriangle
                                                v-if="
                                                    getShiftConflicts(s.id)
                                                        .length > 0
                                                "
                                                :size="8"
                                                class="shrink-0 text-amber-500"
                                            />
                                        </div>

                                        <!-- Role label -->
                                        <div
                                            v-if="s.role_label"
                                            :class="[
                                                'mt-0.5 truncate text-[8px] font-semibold leading-none',
                                                s.assignments.length === 0
                                                    ? 'text-rose-600/80'
                                                    : getShiftConflicts(s.id)
                                                            .length > 0
                                                      ? 'text-amber-600/80'
                                                      : 'text-emerald-600/80',
                                            ]"
                                        >
                                            {{ s.role_label }}
                                        </div>

                                        <!-- Assignments -->
                                        <div
                                            v-if="s.assignments.length > 0"
                                            class="mt-1 space-y-0.5 border-t border-current/10 pt-0.5"
                                        >
                                            <div
                                                v-for="a in s.assignments"
                                                :key="a.id"
                                                class="flex items-center gap-1 min-w-0"
                                            >
                                                <span
                                                    :class="[
                                                        'h-1 w-1 shrink-0 rounded-full',
                                                        getEmployeeConflicts(
                                                            s.id,
                                                            a.employee_profile_id,
                                                        ).length > 0
                                                            ? 'bg-amber-400'
                                                            : 'bg-emerald-400',
                                                    ]"
                                                />
                                                <span
                                                    class="truncate text-[7.5px] font-semibold text-on-surface/80 leading-none"
                                                >
                                                    {{ a.employee_name }}
                                                    <span
                                                        class="text-[7px] font-normal text-on-surface-variant/70 font-mono ml-0.5"
                                                    >
                                                        ({{
                                                            a.start_time.substring(
                                                                0,
                                                                5,
                                                            )
                                                        }}–{{
                                                            a.end_time.substring(
                                                                0,
                                                                5,
                                                            )
                                                        }})
                                                    </span>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Unassigned label -->
                                        <div
                                            v-else
                                            class="mt-1 text-[8px] italic text-rose-500/70 leading-none"
                                        >
                                            {{ t('schedules.no_assignments') }}
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shift details modal -->
        <ModalOverlay
            :open="
                showShiftPanel && (selectedShift !== null || shiftModalDeleted)
            "
            panel-class="max-w-lg gap-4 flex flex-col"
            @close="closeShiftPanel"
        >
            <template v-if="selectedShift">
                <div class="flex items-center justify-between">
                    <h3
                        class="font-heading text-base font-bold text-on-surface"
                    >
                        {{ selectedShift.start_time.substring(0, 5) }} –
                        {{ selectedShift.end_time.substring(0, 5) }}
                    </h3>
                    <button
                        @click="closeShiftPanel"
                        class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low"
                    >
                        <X :size="16" />
                    </button>
                </div>

                <FlashAlerts
                    v-if="showShiftModalFlash"
                    success-key="shift_modal_success"
                    error-key="shift_modal_error"
                />

                <div
                    v-if="selectedShift.role_label || selectedShift.note"
                    class="rounded-xl border border-outline-glass bg-surface-container-lowest p-3 text-xs"
                >
                    <p v-if="selectedShift.role_label">
                        {{ t('schedules.role') }}:
                        <strong>{{ selectedShift.role_label }}</strong>
                    </p>
                    <p
                        v-if="selectedShift.note"
                        class="mt-1 italic text-on-surface-variant"
                    >
                        {{ selectedShift.note }}
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4
                            class="font-heading text-xs font-bold text-on-surface animate-none"
                        >
                            {{ t('schedules.assigned_employees') }}
                        </h4>
                        <button
                            v-if="
                                selectedShift.assignments.length === 0 &&
                                isManager
                            "
                            @click="autoFill(selectedShift.id)"
                            class="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-2 text-[9px] font-bold text-white hover:brightness-105 transition-all active:scale-95"
                        >
                            <Wand2 :size="8" />
                            {{ t('schedules.auto_fill') }}
                        </button>
                    </div>
                    <ul
                        v-if="selectedShift.assignments.length > 0"
                        class="space-y-1.5"
                    >
                        <li
                            v-for="a in selectedShift.assignments"
                            :key="a.id"
                            class="flex items-center justify-between rounded-lg border border-outline-glass/40 bg-white px-3 py-1.5"
                        >
                            <span class="text-xs">
                                {{ a.employee_name }}
                                <span
                                    class="ml-1 font-mono text-[10px] text-on-surface-variant"
                                >
                                    ({{ a.start_time.substring(0, 5) }}–{{
                                        a.end_time.substring(0, 5)
                                    }})
                                </span>
                            </span>
                            <button
                                @click="removeAssignment(a.id)"
                                class="rounded p-0.5 text-rose-500 hover:bg-rose-50"
                            >
                                <X :size="12" />
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-on-surface-variant italic">
                        {{ t('schedules.no_assignments') }}
                    </p>
                </div>

                <div>
                    <h4
                        class="mb-2 font-heading text-xs font-bold text-on-surface"
                    >
                        {{ t('schedules.add_employee') }}
                    </h4>
                    <div class="space-y-2">
                        <select
                            v-model.number="assignForm.employee_profile_id"
                            class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                        >
                            <option :value="0">
                                {{ t('schedules.select_employee') }}
                            </option>
                            <option
                                v-for="e in employees"
                                :key="e.id"
                                :value="e.id"
                            >
                                {{ e.name }}
                            </option>
                        </select>
                        <p
                            v-if="assignForm.errors.employee_profile_id"
                            class="text-[10px] text-rose-600"
                        >
                            {{ assignForm.errors.employee_profile_id }}
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label
                                    class="mb-0.5 block text-[9px] font-bold uppercase tracking-wider text-on-surface-variant"
                                >
                                    {{ t('schedules.start') }}
                                </label>
                                <input
                                    v-model="assignForm.start_time"
                                    type="time"
                                    required
                                    class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                                />
                                <p
                                    v-if="assignForm.errors.start_time"
                                    class="mt-0.5 text-[10px] text-rose-600"
                                >
                                    {{ assignForm.errors.start_time }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-0.5 block text-[9px] font-bold uppercase tracking-wider text-on-surface-variant"
                                >
                                    {{ t('schedules.end') }}
                                </label>
                                <input
                                    v-model="assignForm.end_time"
                                    type="time"
                                    required
                                    class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                                />
                                <p
                                    v-if="assignForm.errors.end_time"
                                    class="mt-0.5 text-[10px] text-rose-600"
                                >
                                    {{ assignForm.errors.end_time }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="assignEmployee(selectedShift.id)"
                            :disabled="
                                assignForm.processing ||
                                !assignForm.employee_profile_id ||
                                !assignForm.start_time ||
                                !assignForm.end_time
                            "
                            class="w-full rounded-lg border border-primary/20 bg-gradient-to-b from-primary-container to-primary py-1.5 text-xs font-bold text-white disabled:opacity-50"
                        >
                            {{ t('schedules.add') }}
                        </button>
                    </div>
                </div>

                <button
                    @click="removeShift(selectedShift.id)"
                    class="mt-auto inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-4 text-xs font-bold text-rose-700 hover:bg-rose-100"
                >
                    <Trash2 :size="12" />
                    {{ t('schedules.delete_shift') }}
                </button>
            </template>
            <template v-else-if="shiftModalDeleted">
                <div class="flex items-center justify-between">
                    <h3 class="font-heading text-sm font-bold text-on-surface">
                        {{ t('schedules.delete_shift') }}
                    </h3>
                    <button
                        @click="closeShiftPanel"
                        class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low"
                    >
                        <X :size="16" />
                    </button>
                </div>

                <FlashAlerts
                    v-if="showShiftModalFlash"
                    success-key="shift_modal_success"
                    error-key="shift_modal_error"
                />

                <button
                    type="button"
                    @click="closeShiftPanel"
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.close') }}
                </button>
            </template>
        </ModalOverlay>

        <!-- Create shift modal -->
        <ModalOverlay
            :open="showCreateShift !== null"
            panel-class="max-w-lg"
            @close="closeCreateShift"
        >
            <form @submit.prevent="submitCreate" class="space-y-3">
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('schedules.new_shift') }}
                </h3>
                <FlashAlerts
                    v-if="showCreateShiftModalFlash"
                    success-key="create_shift_modal_success"
                    error-key="create_shift_modal_error"
                />
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                            >{{ t('schedules.start') }}</label
                        >
                        <input
                            v-model="createForm.start_time"
                            type="time"
                            required
                            class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                            >{{ t('schedules.end') }}</label
                        >
                        <input
                            v-model="createForm.end_time"
                            type="time"
                            required
                            class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                        />
                    </div>
                </div>
                <div>
                    <label
                        class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                        >{{ t('schedules.role_label_optional') }}</label
                    >
                    <input
                        v-model="createForm.role_label"
                        type="text"
                        class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                    />
                </div>
                <div>
                    <label
                        class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                    >
                        {{ t('schedules.employees') }}
                    </label>
                    <p class="mb-2 text-xs text-on-surface-variant">
                        {{ t('schedules.create_shift_employees_help') }}
                    </p>
                    <div
                        v-if="employees.length > 0"
                        class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-outline-glass bg-white p-2"
                    >
                        <label
                            v-for="employee in employees"
                            :key="employee.id"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-on-surface hover:bg-surface-container-low"
                        >
                            <input
                                v-model="createForm.employee_profile_ids"
                                type="checkbox"
                                :value="employee.id"
                                class="rounded border-outline-glass text-primary"
                            />
                            <span>{{ employee.name }}</span>
                        </label>
                    </div>
                    <p v-else class="text-xs text-on-surface-variant italic">
                        {{ t('schedules.no_employees_available') }}
                    </p>
                    <p
                        v-if="createForm.errors.employee_profile_ids"
                        class="mt-1 text-xs text-rose-600"
                    >
                        {{ createForm.errors.employee_profile_ids }}
                    </p>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                    >
                        {{ t('common.save') }}
                    </button>
                    <button
                        type="button"
                        @click="closeCreateShift"
                        class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                    >
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </form>
        </ModalOverlay>
    </AppLayout>
</template>
