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
    FileText,
    User,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
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
    status: string;
}

interface Shift {
    id: number;
    start_time: string;
    end_time: string;
    required_employee_count: number;
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
    employees: { id: number; name: string }[];
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

const selectedShift = ref<Shift | null>(null);
const showShiftPanel = ref(false);
const showCreateShift = ref<string | null>(null);

const createForm = useForm({
    date: '',
    start_time: '10:00',
    end_time: '18:00',
    required_employee_count: 1,
    role_label: '',
    note: '',
    employee_profile_ids: [] as number[],
});

function openShift(shift: Shift): void {
    selectedShift.value = shift;
    showShiftPanel.value = true;
}

function openCreate(date: string): void {
    showCreateShift.value = date;
    createForm.date = date;
    createForm.employee_profile_ids = [];
    createForm.clearErrors();
}

function submitCreate(): void {
    createForm.post(
        `/shift-requirements/store?schedule_id=${props.schedule.id}`,
        {
            onSuccess: () => {
                showCreateShift.value = null;
                createForm.reset(
                    'start_time',
                    'end_time',
                    'required_employee_count',
                    'role_label',
                    'note',
                    'employee_profile_ids',
                );
            },
        },
    );
}

function autoFill(shiftId: number): void {
    router.post('/shift-requirements/auto-fill', {
        shift_requirement_id: shiftId,
    });
}

const assignForm = useForm({
    employee_profile_id: 0,
});

function assignEmployee(shiftId: number): void {
    if (!assignForm.employee_profile_id) return;
    router.post('/shift-assignments/store', {
        shift_requirement_id: shiftId,
        employee_profile_id: assignForm.employee_profile_id,
    });
    assignForm.employee_profile_id = 0;
    showShiftPanel.value = false;
}

async function removeAssignment(id: number): Promise<void> {
    if (
        await confirm(t('schedules.confirm_remove_assignment'), {
            variant: 'danger',
        })
    ) {
        router.post('/shift-assignments/destroy', { id });
    }
}

async function removeShift(id: number): Promise<void> {
    if (
        await confirm(t('schedules.confirm_delete_shift'), {
            variant: 'danger',
        })
    ) {
        router.post('/shift-requirements/destroy', { id });
        showShiftPanel.value = false;
    }
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

function statusColor(
    _count: number,
    required: number,
    assigned: number,
): string {
    if (assigned === 0) return 'bg-rose-50 border-rose-300 text-rose-700';
    if (assigned < required)
        return 'bg-amber-50 border-amber-300 text-amber-700';
    return 'bg-emerald-50 border-emerald-300 text-emerald-700';
}

function getShiftConflicts(shiftId: number): Conflict[] {
    return props.conflicts.filter((c) => c.shift_requirement_id === shiftId);
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
                <Link
                    :href="`/conflicts?schedule_id=${schedule.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <AlertTriangle :size="14" class="mr-1.5" />
                    {{ conflicts.length }}
                    {{ t('schedules.conflict_count').toLowerCase() }}
                </Link>
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

        <div v-if="currentView === 'list'" class="space-y-3">
            <div
                v-for="date in dayKeys"
                :key="date"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-4 shadow-sm"
            >
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="font-heading text-sm font-bold text-on-surface">
                        {{ dateLabel(date) }}
                    </h3>
                    <button
                        @click="openCreate(date)"
                        class="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-outline-glass bg-white px-2 text-[10px] font-bold text-on-surface hover:bg-surface-container-low"
                    >
                        <Plus :size="10" />
                        {{ t('schedules.new_shift') }}
                    </button>
                </div>
                <div
                    v-if="props.days[date]?.shifts.length === 0"
                    class="flex items-center justify-center rounded-xl border border-dashed border-outline-glass/60 bg-surface-container-lowest py-8 text-center text-xs text-on-surface-variant/80"
                >
                    <div class="flex flex-col items-center gap-1.5">
                        <Clock :size="16" class="text-on-surface-variant/50" />
                        <span>{{ t('schedules.no_shifts') }}</span>
                    </div>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="s in props.days[date]?.shifts"
                        :key="s.id"
                        @click="openShift(s)"
                        :class="[
                            'group relative flex flex-col justify-between rounded-xl border border-outline-glass/40 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:border-outline-glass cursor-pointer border-l-4',
                            s.assignments.length === 0
                                ? 'border-l-rose-500'
                                : s.assignments.length <
                                    s.required_employee_count
                                  ? 'border-l-amber-500'
                                  : 'border-l-emerald-500',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <Clock
                                        :size="14"
                                        class="text-on-surface-variant/70"
                                    />
                                    <span
                                        class="font-mono text-sm font-bold text-on-surface"
                                    >
                                        {{ s.start_time }} – {{ s.end_time }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-if="s.role_label"
                                        class="inline-flex items-center gap-1 rounded-md bg-surface-container-low px-2 py-0.5 text-xs font-semibold text-on-surface-variant border border-outline-glass/30"
                                    >
                                        <Briefcase
                                            :size="12"
                                            class="text-on-surface-variant/70"
                                        />
                                        {{ s.role_label }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2 shrink-0">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold border',
                                        s.assignments.length === 0
                                            ? 'bg-rose-50 text-rose-700 border-rose-200'
                                            : s.assignments.length <
                                                s.required_employee_count
                                              ? 'bg-amber-50 text-amber-700 border-amber-200'
                                              : 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    ]"
                                >
                                    {{ s.assignments.length }}/{{
                                        s.required_employee_count
                                    }}
                                    {{ t('schedules.assigned').toLowerCase() }}
                                </span>
                                <button
                                    v-if="
                                        s.assignments.length <
                                        s.required_employee_count
                                    "
                                    @click.stop="autoFill(s.id)"
                                    class="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-2 text-[10px] font-bold text-white hover:brightness-105 transition-all active:scale-95"
                                >
                                    <Wand2 :size="10" />
                                    {{ t('schedules.auto_fill') }}
                                </button>
                            </div>
                        </div>

                        <p
                            v-if="s.note"
                            class="mt-2.5 flex items-start gap-1 text-xs text-on-surface-variant/80 italic border-t border-dashed border-outline-glass/20 pt-2"
                        >
                            <FileText
                                :size="12"
                                class="mt-0.5 shrink-0 text-on-surface-variant/60"
                            />
                            <span>{{ s.note }}</span>
                        </p>

                        <div v-if="s.assignments.length > 0" class="mt-3">
                            <ul class="flex flex-wrap gap-1.5">
                                <li
                                    v-for="a in s.assignments"
                                    :key="a.id"
                                    class="flex items-center gap-1 rounded-full bg-surface-container-low border border-outline-glass/40 px-2 py-0.5 text-[11px] font-semibold text-on-surface hover:bg-surface-container-medium transition-colors"
                                >
                                    <User
                                        :size="10"
                                        class="text-on-surface-variant/70"
                                    />
                                    {{ a.employee_name }}
                                    <button
                                        @click.stop="removeAssignment(a.id)"
                                        class="rounded-full p-0.5 text-rose-500 hover:bg-rose-100 transition-colors"
                                    >
                                        <X :size="10" />
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Shift-specific conflicts -->
                        <div
                            v-if="getShiftConflicts(s.id).length > 0"
                            class="mt-3 space-y-1 rounded-lg bg-rose-50/70 p-2 border border-rose-100 text-[11px] text-rose-700"
                        >
                            <div
                                v-for="c in getShiftConflicts(s.id)"
                                :key="c.id"
                                class="flex items-start gap-1 font-semibold"
                            >
                                <AlertTriangle
                                    :size="12"
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
            <div class="min-w-[768px]">
                <!-- Grid Weekdays Header -->
                <div
                    class="grid grid-cols-7 bg-surface-container-low border-b border-outline-glass"
                >
                    <div
                        v-for="dayName in weekdays"
                        :key="dayName"
                        class="py-2.5 text-center font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                    >
                        {{ dayName }}
                    </div>
                </div>

                <!-- Grid Cells -->
                <div class="divide-y divide-outline-glass/30">
                    <div
                        v-for="(week, weekIdx) in calendarWeeks"
                        :key="weekIdx"
                        class="grid grid-cols-7 divide-x divide-outline-glass/30 min-h-[120px]"
                    >
                        <div
                            v-for="day in week"
                            :key="day.date"
                            :class="[
                                'p-2 flex flex-col justify-between transition-colors relative group',
                                day.inPeriod
                                    ? 'bg-surface-container-lowest hover:bg-surface-container-lowest/80'
                                    : 'bg-surface-container-low/40 text-on-surface-variant/40 select-none',
                            ]"
                        >
                            <!-- Cell Header -->
                            <div class="flex items-center justify-between">
                                <span
                                    :class="[
                                        'text-xs font-bold font-mono',
                                        day.inPeriod
                                            ? 'text-on-surface'
                                            : 'text-on-surface-variant/30',
                                    ]"
                                >
                                    {{ day.dayOfMonth }}
                                </span>
                                <!-- Add Shift Button on Hover -->
                                <button
                                    v-if="day.inPeriod && isManager"
                                    type="button"
                                    @click="openCreate(day.date)"
                                    class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-surface-container-low text-primary transition-opacity cursor-pointer"
                                    title="Add shift"
                                >
                                    <Plus :size="12" />
                                </button>
                            </div>

                            <!-- Shifts inside Cell -->
                            <div
                                class="mt-2 flex-1 space-y-1.5 overflow-y-auto"
                            >
                                <button
                                    v-for="s in day.shifts"
                                    :key="s.id"
                                    type="button"
                                    @click="openShift(s)"
                                    :class="[
                                        'w-full text-left rounded-lg border p-1.5 transition-all hover:brightness-95 cursor-pointer flex flex-col justify-between',
                                        statusColor(
                                            s.required_employee_count,
                                            s.required_employee_count,
                                            s.assignments.length,
                                        ),
                                    ]"
                                >
                                    <div
                                        class="flex items-center justify-between w-full"
                                    >
                                        <span
                                            class="font-mono text-[10px] font-bold"
                                        >
                                            {{
                                                s.start_time.substring(0, 5)
                                            }}–{{ s.end_time.substring(0, 5) }}
                                        </span>
                                        <!-- Conflict Indicator -->
                                        <AlertTriangle
                                            v-if="
                                                getShiftConflicts(s.id).length >
                                                0
                                            "
                                            :size="10"
                                            class="text-rose-500 animate-pulse"
                                        />
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center justify-between text-[9px] font-medium opacity-85"
                                    >
                                        <span>
                                            {{ s.assignments.length }}/{{
                                                s.required_employee_count
                                            }}
                                        </span>
                                        <span
                                            v-if="s.role_label"
                                            class="truncate max-w-[50px]"
                                        >
                                            {{ s.role_label }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shift side panel -->
        <ModalOverlay
            :open="showShiftPanel && selectedShift !== null"
            variant="drawer"
            panel-class="gap-4"
            @close="showShiftPanel = false"
        >
            <template v-if="selectedShift">
                <div class="flex items-center justify-between">
                    <h3
                        class="font-heading text-base font-bold text-on-surface"
                    >
                        {{ selectedShift.start_time }} –
                        {{ selectedShift.end_time }}
                    </h3>
                    <button
                        @click="showShiftPanel = false"
                        class="rounded p-1 text-on-surface-variant hover:bg-surface-container-low"
                    >
                        <X :size="16" />
                    </button>
                </div>

                <div
                    class="rounded-xl border border-outline-glass bg-surface-container-lowest p-3 text-xs"
                >
                    <p>
                        {{ t('schedules.required') }}:
                        <strong>{{
                            selectedShift.required_employee_count
                        }}</strong>
                    </p>
                    <p>
                        {{ t('schedules.assigned') }}:
                        <strong>{{ selectedShift.assignments.length }}</strong>
                    </p>
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
                    <h4
                        class="mb-2 font-heading text-xs font-bold text-on-surface"
                    >
                        {{ t('schedules.assigned_employees') }}
                    </h4>
                    <ul
                        v-if="selectedShift.assignments.length > 0"
                        class="space-y-1.5"
                    >
                        <li
                            v-for="a in selectedShift.assignments"
                            :key="a.id"
                            class="flex items-center justify-between rounded-lg border border-outline-glass/40 bg-white px-3 py-1.5"
                        >
                            <span class="text-xs">{{ a.employee_name }}</span>
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
                    <div class="flex gap-2">
                        <select
                            v-model.number="assignForm.employee_profile_id"
                            class="flex-1 rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
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
                        <button
                            @click="assignEmployee(selectedShift.id)"
                            :disabled="!assignForm.employee_profile_id"
                            class="rounded-lg border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-3 text-xs font-bold text-white disabled:opacity-50"
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
        </ModalOverlay>

        <!-- Create shift modal -->
        <ModalOverlay
            :open="showCreateShift !== null"
            panel-class="max-w-lg"
            @close="showCreateShift = null"
        >
            <form @submit.prevent="submitCreate" class="space-y-3">
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('schedules.new_shift') }}
                </h3>
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
                        >{{ t('schedules.required_count') }}</label
                    >
                    <input
                        v-model.number="createForm.required_employee_count"
                        type="number"
                        min="1"
                        max="50"
                        required
                        class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                    />
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
                        @click="showCreateShift = null"
                        class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                    >
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </form>
        </ModalOverlay>
    </AppLayout>
</template>
