<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X, Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

interface DayEntry {
    id: number;
    type: string;
    start_time: string | null;
    end_time: string | null;
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

const aiRows = ref<
    | {
          date: string;
          type: string;
          start_time: string | null;
          end_time: string | null;
          note: string | null;
      }[]
    | null
>(null);

function openAdd(employeeId: number, date: string): void {
    showAdd.value = `${employeeId}-${date}`;
    form.employee_profile_id = employeeId;
    form.date = date;
}

function closeAdd(): void {
    showAdd.value = null;
}

function submitAdd(): void {
    form.post('/availability/store');
}

function destroy(id: number): void {
    if (confirm('Remove availability?')) {
        router.post('/availability/destroy', { id });
    }
}

const parseForm = useForm({
    employee_profile_id: props.employees[0]?.employee.id || 0,
    text: '',
});

async function parseAi(): Promise<void> {
    parseForm.post('/availability/parse-ai', {
        onSuccess: (page) => {
            const data = page.props as Record<string, unknown>;
            if (data.availability && Array.isArray(data.availability)) {
                aiRows.value = data.availability as typeof aiRows.value;
            }
        },
    });
}

function saveParsed(row: {
    date: string;
    type: string;
    start_time: string | null;
    end_time: string | null;
    note: string | null;
}): void {
    form.employee_profile_id = parseForm.employee_profile_id;
    form.date = row.date;
    form.start_time = row.start_time ?? '';
    form.end_time = row.end_time ?? '';
    form.type = row.type;
    form.note = row.note ?? '';
    form.post('/availability/store');
}

const typeColor: Record<string, string> = {
    available: 'bg-emerald-100 text-emerald-700',
    unavailable: 'bg-rose-100 text-rose-700',
    preferred: 'bg-blue-100 text-blue-700',
};

function dayLabel(date: string): string {
    const d = new Date(date);
    return d.getDate().toString();
}

function weekdayLabel(date: string): string {
    const d = new Date(date);
    return ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'][
        d.getDay() === 0 ? 6 : d.getDay() - 1
    ];
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
                    {{ month }}
                </p>
            </div>
            <div class="flex gap-2">
                <a
                    :href="`/availability?month=${month}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    Today
                </a>
            </div>
        </div>

        <section
            class="mb-6 rounded-2xl border border-outline-glass bg-gradient-to-br from-primary-container/10 via-surface-container-lowest to-secondary-cyan/5 p-4 shadow-sm"
        >
            <div class="mb-2 flex items-center gap-2">
                <Sparkles :size="14" class="text-primary" />
                <span
                    class="font-mono text-[10px] font-extrabold tracking-wider text-primary uppercase"
                >
                    AI parser
                </span>
            </div>
            <div class="grid gap-2 md:grid-cols-3">
                <select
                    v-model.number="parseForm.employee_profile_id"
                    class="rounded-lg border border-outline-glass bg-white px-2 py-1.5 text-xs text-on-surface"
                >
                    <option
                        v-for="e in employees"
                        :key="e.employee.id"
                        :value="e.employee.id"
                    >
                        {{ e.employee.name }}
                    </option>
                </select>
                <input
                    v-model="parseForm.text"
                    type="text"
                    :placeholder="t('availability.parse_ai_placeholder')"
                    class="md:col-span-2 rounded-lg border border-outline-glass bg-white px-3 py-1.5 text-xs text-on-surface"
                />
            </div>
            <button
                type="button"
                @click="parseAi"
                class="mt-2 inline-flex h-7 cursor-pointer items-center rounded-lg border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-3 text-[11px] font-semibold text-white shadow-sm hover:brightness-105"
            >
                {{ t('availability.parse_ai_cta') }}
            </button>
            <div v-if="aiRows" class="mt-3 space-y-1">
                <p class="text-xs text-on-surface">
                    Parsed {{ aiRows.length }} entries.
                </p>
                <ul class="space-y-1">
                    <li
                        v-for="(row, idx) in aiRows"
                        :key="idx"
                        class="flex items-center justify-between rounded-lg border border-outline-glass/40 bg-white px-3 py-1.5 text-xs"
                    >
                        <span>
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 text-[10px] font-bold uppercase',
                                    typeColor[row.type] ?? '',
                                ]"
                            >
                                {{ row.type }}
                            </span>
                            <span class="ml-2 font-mono">{{ row.date }}</span>
                            <span v-if="row.start_time" class="ml-2"
                                >{{ row.start_time }} – {{ row.end_time }}</span
                            >
                        </span>
                        <button
                            @click="saveParsed(row)"
                            class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                        >
                            Save
                        </button>
                    </li>
                </ul>
            </div>
        </section>

        <div
            class="overflow-x-auto rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        >
            <table class="min-w-full text-xs">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th
                            class="sticky left-0 z-10 bg-surface-container-low px-3 py-2 text-left font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                        >
                            Employee
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
                                class="flex items-center justify-center gap-0.5"
                            >
                                <span
                                    :class="[
                                        'rounded px-1.5 py-0.5 text-[10px] font-bold uppercase',
                                        typeColor[row.days[d]!.type] ?? '',
                                    ]"
                                >
                                    {{ row.days[d]!.type.slice(0, 3) }}
                                </span>
                                <button
                                    @click="destroy(row.days[d]!.id)"
                                    class="rounded p-0.5 text-rose-500 hover:bg-rose-50"
                                >
                                    <X :size="10" />
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

        <div
            v-if="showAdd"
            class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4"
            @click.self="closeAdd"
        >
            <form
                @submit.prevent="submitAdd"
                class="w-full max-w-md space-y-3 rounded-2xl border border-outline-glass bg-white p-5 shadow-lg"
            >
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('availability.create_cta') }}
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
                <div class="flex items-center gap-2 pt-2">
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
            </form>
        </div>
    </AppLayout>
</template>
