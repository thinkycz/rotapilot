<script setup lang="ts">
import { CalendarCheck2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import EmployeeScheduleViewer from '@/components/EmployeeScheduleViewer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

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

defineProps<{
    has_profile: boolean;
    stores: StoreOption[];
    selected_store_id: number | null;
    schedules: ScheduleOption[];
    selected_schedule: ScheduleOption | null;
    days: Record<string, { shifts: Shift[] }>;
}>();
</script>

<template>
    <AppLayout :title="t('my_calendar.title')">
        <div class="mb-6">
            <h1 class="font-heading text-2xl font-bold text-on-surface">
                {{ t('my_calendar.title') }}
            </h1>
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
                {{ t('my_calendar.no_profile') }}
            </p>
        </div>

        <EmployeeScheduleViewer
            v-else
            :stores="stores"
            :selected-store-id="selected_store_id"
            :schedules="schedules"
            :selected-schedule="selected_schedule"
            :days="days"
            route-base="/my-calendar"
        />
    </AppLayout>
</template>
