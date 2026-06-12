<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatDateRange, parseIsoDate } from '@/lib/date';

const { t, tm } = useI18n();

useBoundLocale();

interface DayEntry {
    id: number;
    type: string;
    start_time: string | null;
    end_time: string | null;
    note: string | null;
}

interface EmployeeRow {
    employee: { id: number; name: string };
    days: Record<string, DayEntry | null>;
}

interface StoreOption {
    id: number;
    name: string;
}

const props = defineProps<{
    month: string;
    days: string[];
    employees: EmployeeRow[];
    stores: StoreOption[];
    filter_store_id: number;
    filter_employee_id: number;
}>();

const form = useForm({
    employee_profile_id:
        props.filter_employee_id || props.employees[0]?.employee.id || 0,
    store_id: props.filter_store_id || 0,
    date: props.days[0] || '',
    start_time: '09:00',
    end_time: '17:00',
    type: 'available',
    note: '',
});

const showAdd = ref<string | null>(null);
const editingId = ref<number | null>(null);
const weekdays = computed(() => tm('common.weekdays') as string[]);
const monthRange = computed(() =>
    formatDateRange(props.days[0], props.days[props.days.length - 1]),
);

function openAdd(employeeId: number, date: string): void {
    editingId.value = null;
    showAdd.value = `${employeeId}-${date}`;
    form.employee_profile_id = employeeId;
    form.store_id = props.filter_store_id || 0;
    form.date = date;
    form.type = 'available';
    form.start_time = '09:00';
    form.end_time = '17:00';
    form.note = '';
    form.clearErrors();
}

function openEdit(entry: DayEntry, employeeId: number, date: string): void {
    editingId.value = entry.id;
    showAdd.value = `${employeeId}-${date}`;
    form.employee_profile_id = employeeId;
    form.store_id = props.filter_store_id || 0;
    form.date = date;
    form.type = entry.type;
    form.start_time = entry.start_time ? entry.start_time.substring(0, 5) : '';
    form.end_time = entry.end_time ? entry.end_time.substring(0, 5) : '';
    form.note = entry.note || '';
    form.clearErrors();
}

function closeAdd(): void {
    showAdd.value = null;
    editingId.value = null;
}

function submitAdd(): void {
    if (editingId.value !== null) {
        form.post(`/availability/update?id=${editingId.value}`, {
            onSuccess: () => {
                closeAdd();
                form.reset('note', 'type', 'start_time', 'end_time');
            },
        });
    } else {
        form.post('/availability/store', {
            onSuccess: () => {
                closeAdd();
                form.reset('note', 'type', 'start_time', 'end_time');
            },
        });
    }
}

function destroy(id: number): void {
    if (confirm(t('common.confirm_title'))) {
        router.post(
            `/availability/destroy?id=${id}`,
            {},
            {
                onSuccess: () => {
                    closeAdd();
                },
            },
        );
    }
}

const typeColor: Record<string, string> = {
    available: 'bg-emerald-100 text-emerald-700',
    unavailable: 'bg-rose-100 text-rose-700',
    preferred: 'bg-blue-100 text-blue-700',
};

function formatTimeRange(start: string | null, end: string | null): string {
    if (!start || !end) return '—';
    const fmt = (t: string) => {
        const parts = t.split(':');
        if (parts.length < 2) return t;
        const h = parts[0];
        const m = parts[1];
        return m === '00'
            ? parseInt(h, 10).toString()
            : `${parseInt(h, 10)}:${m}`;
    };
    return `${fmt(start)}-${fmt(end)}`;
}

const prevMonth = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
});

const nextMonth = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
});

function dayLabel(date: string): string {
    return parseIsoDate(date)?.getDate().toString() ?? '';
}

function weekdayLabel(date: string): string {
    const d = parseIsoDate(date);
    if (!d) return '';

    return (
        weekdays.value[d.getDay() === 0 ? 6 : d.getDay() - 1]?.slice(0, 2) ?? ''
    );
}
</script>

<template>
    <AppLayout :title="t('availability.title')">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ t('availability.title') }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ monthRange }}
                </p>
            </div>
            <div class="flex gap-2">
                <a
                    :href="`/availability?month=${prevMonth}`"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-outline-glass bg-white text-on-surface hover:bg-surface-container-low"
                >
                    <ChevronLeft :size="16" />
                </a>
                <a
                    href="/availability"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.today') }}
                </a>
                <a
                    :href="`/availability?month=${nextMonth}`"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-outline-glass bg-white text-on-surface hover:bg-surface-container-low"
                >
                    <ChevronRight :size="16" />
                </a>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        >
            <table class="min-w-full text-xs">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th
                            class="sticky left-0 z-10 bg-surface-container-low px-3 py-2 text-left font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                        >
                            {{ t('availability.employee') }}
                        </th>
                        <th
                            v-for="d in days"
                            :key="d"
                            class="px-1 py-2 text-center font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                        >
                            <div>{{ weekdayLabel(d) }}</div>
                            <div>{{ dayLabel(d) }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in employees"
                        :key="row.employee.id"
                        class="border-t border-outline-glass/30"
                    >
                        <td
                            class="sticky left-0 z-10 bg-surface-container-lowest px-3 py-1.5 font-semibold text-on-surface"
                        >
                            {{ row.employee.name }}
                        </td>
                        <td
                            v-for="d in days"
                            :key="d"
                            class="border-l border-outline-glass/30 p-0.5 text-center"
                        >
                            <div
                                v-if="row.days[d]"
                                class="flex items-center justify-center"
                            >
                                <button
                                    type="button"
                                    @click="
                                        openEdit(
                                            row.days[d]!,
                                            row.employee.id,
                                            d,
                                        )
                                    "
                                    :class="[
                                        'rounded px-1.5 py-0.5 text-[10px] font-bold uppercase w-full text-center hover:opacity-85 cursor-pointer',
                                        typeColor[row.days[d]!.type] ?? '',
                                    ]"
                                >
                                    {{
                                        row.days[d]!.type === 'unavailable'
                                            ? 'UNA'
                                            : formatTimeRange(
                                                  row.days[d]!.start_time,
                                                  row.days[d]!.end_time,
                                              )
                                    }}
                                </button>
                            </div>
                            <button
                                v-else
                                @click="openAdd(row.employee.id, d)"
                                class="flex h-6 w-full items-center justify-center rounded text-on-surface-variant opacity-30 hover:opacity-100"
                            >
                                <Plus :size="10" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ModalOverlay :open="showAdd !== null" @close="closeAdd">
            <form @submit.prevent="submitAdd" class="space-y-3">
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{
                        editingId
                            ? t('common.edit') +
                              ' ' +
                              t('availability.title').toLowerCase()
                            : t('availability.create_cta')
                    }}
                </h3>
                <div>
                    <label
                        class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                    >
                        {{ t('availability.type') }}
                    </label>
                    <select
                        v-model="form.type"
                        class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                    >
                        <option value="available">
                            {{ t('availability.type_available') }}
                        </option>
                        <option value="unavailable">
                            {{ t('availability.type_unavailable') }}
                        </option>
                        <option value="preferred">
                            {{ t('availability.type_preferred') }}
                        </option>
                    </select>
                </div>
                <div
                    v-if="form.type !== 'unavailable'"
                    class="grid grid-cols-2 gap-2"
                >
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                        >
                            {{ t('availability.start_time') }}
                        </label>
                        <input
                            v-model="form.start_time"
                            type="time"
                            class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                        >
                            {{ t('availability.end_time') }}
                        </label>
                        <input
                            v-model="form.end_time"
                            type="time"
                            class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs"
                        />
                    </div>
                </div>
                <div>
                    <label
                        class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                    >
                        {{ t('availability.note') }}
                    </label>
                    <input
                        v-model="form.note"
                        type="text"
                        class="w-full rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs text-on-surface"
                    />
                </div>
                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-8 cursor-pointer items-center rounded-lg border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-3 text-xs font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                        >
                            {{ t('common.save') }}
                        </button>
                        <button
                            type="button"
                            @click="closeAdd"
                            class="inline-flex h-8 items-center rounded-lg border border-outline-glass bg-white px-3 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                        >
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                    <button
                        v-if="editingId"
                        type="button"
                        @click="destroy(editingId)"
                        class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-600 hover:bg-rose-100"
                    >
                        {{ t('common.delete') }}
                    </button>
                </div>
            </form>
        </ModalOverlay>
    </AppLayout>
</template>
