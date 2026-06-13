<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    CalendarCheck2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Lock,
    Plus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatDate, formatDateRange } from '@/lib/date';

const { t, tm } = useI18n();

useBoundLocale();

interface AvailabilityEntry {
    id: number;
    date: string;
    type: string;
    start_time: string | null;
    end_time: string | null;
    note: string | null;
    source: string;
    store_name: string | null;
    can_edit: boolean;
}

const props = defineProps<{
    has_profile: boolean;
    month: string;
    days: string[];
    entries: AvailabilityEntry[];
}>();

const weekdays = computed(() => tm('common.weekdays') as string[]);
const currentView = ref<'calendar' | 'list'>('calendar');
const showModal = ref(false);
const editingEntry = ref<AvailabilityEntry | null>(null);
const availabilityDeleted = ref(false);
const showAvailabilityModalFlash = ref(false);

const form = useForm({
    date: props.days[0] || '',
    start_time: '09:00',
    end_time: '17:00',
    type: 'available',
    note: '',
});

const byDay = computed(() => {
    const map: Record<string, AvailabilityEntry[]> = {};
    for (const entry of props.entries) {
        if (!map[entry.date]) map[entry.date] = [];
        map[entry.date].push(entry);
    }
    return map;
});

const monthRange = computed(() =>
    props.days.length > 0
        ? formatDateRange(props.days[0], props.days[props.days.length - 1])
        : '',
);

const prevMonth = computed(() => {
    const [year, month] = props.month.split('-').map(Number);
    const date = new Date(year, month - 2, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
});

const nextMonth = computed(() => {
    const [year, month] = props.month.split('-').map(Number);
    const date = new Date(year, month, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
});

interface CalendarDay {
    date: string;
    dayOfMonth: string;
    inMonth: boolean;
    entries: AvailabilityEntry[];
}

const calendarWeeks = computed(() => {
    if (props.days.length === 0) return [];
    const [year, month] = props.month.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const startDay = firstDay.getDay();
    const startOffset = startDay === 0 ? -6 : 1 - startDay;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() + startOffset);

    const lastDay = new Date(year, month, 0);
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
        const date = `${y}-${m}-${d}`;

        currentWeek.push({
            date,
            dayOfMonth: iter.getDate().toString(),
            inMonth: iter.getMonth() === month - 1,
            entries: byDay.value[date] ?? [],
        });

        if (currentWeek.length === 7) {
            weeks.push(currentWeek);
            currentWeek = [];
        }

        iter.setDate(iter.getDate() + 1);
    }

    return weeks;
});

const typeColor: Record<string, string> = {
    available: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    unavailable: 'border-rose-200 bg-rose-50 text-rose-700',
    backup: 'border-blue-200 bg-blue-50 text-blue-700',
};

function openAdd(date: string): void {
    editingEntry.value = null;
    availabilityDeleted.value = false;
    showAvailabilityModalFlash.value = false;
    showModal.value = true;
    form.date = date;
    form.type = 'available';
    form.start_time = '09:00';
    form.end_time = '17:00';
    form.note = '';
    form.clearErrors();
}

function openEdit(entry: AvailabilityEntry): void {
    if (!entry.can_edit) return;
    editingEntry.value = entry;
    availabilityDeleted.value = false;
    showAvailabilityModalFlash.value = false;
    showModal.value = true;
    form.date = entry.date;
    form.type = entry.type;
    form.start_time = entry.start_time ? entry.start_time.substring(0, 5) : '';
    form.end_time = entry.end_time ? entry.end_time.substring(0, 5) : '';
    form.note = entry.note ?? '';
    form.clearErrors();
}

function closeModal(): void {
    showModal.value = false;
    editingEntry.value = null;
    availabilityDeleted.value = false;
    showAvailabilityModalFlash.value = false;
}

function submit(): void {
    showAvailabilityModalFlash.value = true;
    if (editingEntry.value) {
        form.post(`/my-availabilities/update?id=${editingEntry.value.id}`);
    } else {
        form.post('/my-availabilities/store', {
            onSuccess: () =>
                form.reset('note', 'type', 'start_time', 'end_time'),
        });
    }
}

function destroyEntry(): void {
    if (!editingEntry.value || !confirm(t('common.confirm_title'))) return;

    showAvailabilityModalFlash.value = true;
    router.post(
        `/my-availabilities/destroy?id=${editingEntry.value.id}`,
        {},
        {
            onSuccess: () => {
                availabilityDeleted.value = true;
            },
        },
    );
}

function formatTimeRange(start: string | null, end: string | null): string {
    if (!start || !end) return '—';
    return `${start.substring(0, 5)}-${end.substring(0, 5)}`;
}

function entryLabel(entry: AvailabilityEntry): string {
    if (entry.type === 'unavailable') return t('availability.type_unavailable');
    return `${t(`availability.type_${entry.type}`)} · ${formatTimeRange(entry.start_time, entry.end_time)}`;
}
</script>

<template>
    <AppLayout :title="t('my_availabilities.title')">
        <div
            class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ t('my_availabilities.title') }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ monthRange }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
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
                <a
                    :href="`/my-availabilities?month=${prevMonth}`"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-outline-glass bg-white text-on-surface hover:bg-surface-container-low"
                >
                    <ChevronLeft :size="16" />
                </a>
                <a
                    href="/my-availabilities"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.today') }}
                </a>
                <a
                    :href="`/my-availabilities?month=${nextMonth}`"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-outline-glass bg-white text-on-surface hover:bg-surface-container-low"
                >
                    <ChevronRight :size="16" />
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
                {{ t('my_availabilities.no_profile') }}
            </p>
        </div>

        <div v-else-if="currentView === 'list'" class="space-y-4">
            <div
                v-for="date in days"
                :key="date"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b border-outline-glass/50 bg-surface-container-low px-5 py-3"
                >
                    <h3 class="font-heading text-sm font-bold text-on-surface">
                        {{ formatDate(date) }}
                    </h3>
                    <button
                        type="button"
                        @click="openAdd(date)"
                        class="inline-flex h-7 items-center gap-1.5 rounded-lg border border-outline-glass bg-white px-2.5 text-[11px] font-semibold text-on-surface hover:bg-surface-container"
                    >
                        <Plus :size="11" />
                        {{ t('availability.create_cta') }}
                    </button>
                </div>
                <div
                    v-if="(byDay[date] ?? []).length === 0"
                    class="flex items-center justify-center py-8 text-xs text-on-surface-variant/60"
                >
                    <Clock :size="16" class="mr-2 text-on-surface-variant/30" />
                    {{ t('availability.type_missing') }}
                </div>
                <div v-else class="divide-y divide-outline-glass/30">
                    <button
                        v-for="entry in byDay[date]"
                        :key="entry.id"
                        type="button"
                        @click="openEdit(entry)"
                        :class="[
                            'flex w-full items-center justify-between gap-3 px-5 py-3 text-left',
                            entry.can_edit
                                ? 'hover:bg-surface-container-lowest/60'
                                : 'cursor-default',
                        ]"
                    >
                        <span
                            :class="[
                                'rounded-lg border px-2 py-1 text-xs font-bold',
                                typeColor[entry.type] ?? '',
                            ]"
                        >
                            {{ entryLabel(entry) }}
                        </span>
                        <span
                            class="min-w-0 flex-1 text-xs text-on-surface-variant"
                        >
                            {{ entry.note }}
                            <span v-if="entry.store_name">
                                · {{ entry.store_name }}
                            </span>
                            <span v-if="entry.source !== 'employee'">
                                · {{ t('my_availabilities.manager_entry') }}
                            </span>
                        </span>
                        <Lock
                            v-if="!entry.can_edit"
                            :size="13"
                            class="shrink-0 text-on-surface-variant/50"
                        />
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else
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
                        class="grid min-h-[132px] grid-cols-7 divide-x divide-outline-glass/20"
                    >
                        <div
                            v-for="day in week"
                            :key="day.date"
                            :class="[
                                'relative flex flex-col gap-1.5 p-2',
                                day.inMonth
                                    ? 'bg-white'
                                    : 'bg-surface-container-low/30',
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    :class="[
                                        'flex h-5 w-5 items-center justify-center rounded-full font-mono text-[11px] font-bold',
                                        day.inMonth
                                            ? 'text-on-surface'
                                            : 'text-on-surface-variant/25',
                                    ]"
                                >
                                    {{ day.dayOfMonth }}
                                </span>
                                <button
                                    v-if="day.inMonth"
                                    type="button"
                                    @click="openAdd(day.date)"
                                    class="flex h-5 w-5 items-center justify-center rounded-md text-primary hover:bg-primary/10"
                                >
                                    <Plus :size="11" />
                                </button>
                            </div>
                            <button
                                v-for="entry in day.entries"
                                :key="entry.id"
                                type="button"
                                @click="openEdit(entry)"
                                :class="[
                                    'flex items-start gap-1 rounded-lg border px-1.5 py-1 text-left text-[9px] font-bold leading-tight',
                                    typeColor[entry.type] ?? '',
                                    entry.can_edit
                                        ? ''
                                        : 'cursor-default opacity-75',
                                ]"
                            >
                                <Lock
                                    v-if="!entry.can_edit"
                                    :size="9"
                                    class="mt-0.5 shrink-0"
                                />
                                <span class="min-w-0 truncate">
                                    {{ entryLabel(entry) }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ModalOverlay :open="showModal" @close="closeModal">
            <div v-if="availabilityDeleted" class="space-y-3">
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('common.delete') }}
                </h3>
                <FlashAlerts
                    v-if="showAvailabilityModalFlash"
                    success-key="availability_modal_success"
                    error-key="availability_modal_error"
                />
                <button
                    type="button"
                    @click="closeModal"
                    class="inline-flex h-8 items-center rounded-lg border border-outline-glass bg-white px-3 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.close') }}
                </button>
            </div>

            <form v-else class="space-y-3" @submit.prevent="submit">
                <h3 class="font-heading text-sm font-bold text-on-surface">
                    {{
                        editingEntry
                            ? t('common.edit') +
                              ' ' +
                              t('availability.title').toLowerCase()
                            : t('availability.create_cta')
                    }}
                </h3>
                <p class="text-xs font-semibold text-on-surface-variant">
                    {{ formatDate(form.date) }}
                </p>
                <FlashAlerts
                    v-if="showAvailabilityModalFlash"
                    success-key="availability_modal_success"
                    error-key="availability_modal_error"
                />
                <input v-model="form.date" type="hidden" />
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
                        <option value="backup">
                            {{ t('availability.type_backup') }}
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
                            @click="closeModal"
                            class="inline-flex h-8 items-center rounded-lg border border-outline-glass bg-white px-3 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                        >
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                    <button
                        v-if="editingEntry"
                        type="button"
                        @click="destroyEntry"
                        class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-600 hover:bg-rose-100"
                    >
                        {{ t('common.delete') }}
                    </button>
                </div>
            </form>
        </ModalOverlay>
    </AppLayout>
</template>
