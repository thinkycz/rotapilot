<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Sparkles, Plus, Trash2, X, Wand2, AlertTriangle } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { t } = useI18n();
const { confirm } = useConfirmDialog();

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
const criticalConflicts = computed(() =>
    props.conflicts.filter((c) => c.severity === 'critical'),
);

const dayKeys = computed(() => Object.keys(props.days).sort());

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
});

function openShift(shift: Shift): void {
    selectedShift.value = shift;
    showShiftPanel.value = true;
}

function openCreate(date: string): void {
    showCreateShift.value = date;
    createForm.date = date;
}

function submitCreate(): void {
    createForm.post(
        `/shift-requirements/store?schedule_id=${props.schedule.id}`,
    );
    showCreateShift.value = null;
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

function dateLabel(d: string): string {
    return new Date(d).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
    });
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
                    {{ schedule.store_name }} · {{ schedule.period_start }} →
                    {{ schedule.period_end }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="`/ai-planner?schedule_id=${schedule.id}&store_id=${schedule.store_id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    <Sparkles :size="14" class="mr-1.5" />
                    AI Planner
                </Link>
                <Link
                    :href="`/conflicts?schedule_id=${schedule.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <AlertTriangle :size="14" class="mr-1.5" />
                    {{ conflicts.length }} conflicts
                </Link>
                <button
                    v-if="!isPublished"
                    @click="publish"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                >
                    {{ t('schedules.publish_cta') }}
                </button>
                <button
                    v-else
                    @click="archive"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('schedules.archive_cta') }}
                </button>
            </div>
        </div>

        <div
            v-if="criticalConflicts.length > 0"
            class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700"
        >
            <p class="font-semibold">
                {{ criticalConflicts.length }} critical conflict{{
                    criticalConflicts.length === 1 ? '' : 's'
                }}. Publishing is blocked.
            </p>
        </div>

        <div class="space-y-3">
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
                        Shift
                    </button>
                </div>
                <div
                    v-if="props.days[date]?.shifts.length === 0"
                    class="text-xs text-on-surface-variant italic"
                >
                    No shifts.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="s in props.days[date]?.shifts"
                        :key="s.id"
                        :class="[
                            'rounded-xl border-2 p-3',
                            statusColor(
                                s.required_employee_count,
                                s.required_employee_count,
                                s.assignments.length,
                            ),
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="font-mono text-sm font-bold text-on-surface"
                                >
                                    {{ s.start_time }} – {{ s.end_time }}
                                </p>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{ s.assignments.length }}/{{
                                        s.required_employee_count
                                    }}
                                    assigned
                                    <span v-if="s.role_label">
                                        · {{ s.role_label }}</span
                                    >
                                </p>
                            </div>
                            <div class="flex gap-1">
                                <button
                                    v-if="
                                        s.assignments.length <
                                        s.required_employee_count
                                    "
                                    @click="autoFill(s.id)"
                                    class="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-2 text-[10px] font-bold text-white hover:brightness-105"
                                >
                                    <Wand2 :size="10" />
                                    Auto-fill
                                </button>
                                <button
                                    @click="openShift(s)"
                                    class="inline-flex h-6 items-center rounded-md border border-outline-glass bg-white px-2 text-[10px] font-bold text-on-surface hover:bg-surface-container-low"
                                >
                                    Open
                                </button>
                            </div>
                        </div>
                        <ul
                            v-if="s.assignments.length > 0"
                            class="mt-2 flex flex-wrap gap-1.5"
                        >
                            <li
                                v-for="a in s.assignments"
                                :key="a.id"
                                class="flex items-center gap-1 rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-on-surface"
                            >
                                {{ a.employee_name }}
                                <button
                                    @click="removeAssignment(a.id)"
                                    class="rounded-full p-0.5 text-rose-500 hover:bg-rose-50"
                                >
                                    <X :size="10" />
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shift side panel -->
        <div
            v-if="showShiftPanel && selectedShift"
            class="fixed inset-0 z-30 flex justify-end bg-black/30"
            @click.self="showShiftPanel = false"
        >
            <aside
                class="flex h-full w-full max-w-md flex-col gap-4 overflow-y-auto bg-white p-6 shadow-lg"
            >
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
                        Required:
                        <strong>{{
                            selectedShift.required_employee_count
                        }}</strong>
                    </p>
                    <p>
                        Assigned:
                        <strong>{{ selectedShift.assignments.length }}</strong>
                    </p>
                    <p v-if="selectedShift.role_label">
                        Role: <strong>{{ selectedShift.role_label }}</strong>
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
                        Assigned employees
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
                        No one assigned yet.
                    </p>
                </div>

                <div>
                    <h4
                        class="mb-2 font-heading text-xs font-bold text-on-surface"
                    >
                        Add employee
                    </h4>
                    <div class="flex gap-2">
                        <select
                            v-model.number="assignForm.employee_profile_id"
                            class="flex-1 rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                        >
                            <option :value="0">— select —</option>
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
                            Add
                        </button>
                    </div>
                </div>

                <button
                    @click="removeShift(selectedShift.id)"
                    class="mt-auto inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-4 text-xs font-bold text-rose-700 hover:bg-rose-100"
                >
                    <Trash2 :size="12" />
                    Delete shift
                </button>
            </aside>
        </div>

        <!-- Create shift modal -->
        <div
            v-if="showCreateShift"
            class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4"
            @click.self="showCreateShift = null"
        >
            <form
                @submit.prevent="submitCreate"
                class="w-full max-w-md space-y-3 rounded-2xl border border-outline-glass bg-white p-5 shadow-lg"
            >
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    New shift
                </h3>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                            >Start</label
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
                            >End</label
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
                        >Required count</label
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
                        >Role label (optional)</label
                    >
                    <input
                        v-model="createForm.role_label"
                        type="text"
                        class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                    />
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
        </div>
    </AppLayout>
</template>
