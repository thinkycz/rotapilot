<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import EmployeeScheduleViewer from '@/components/EmployeeScheduleViewer.vue';
import Brand from '@/components/ui/Brand.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

interface Employee {
    id: number;
    name: string;
}

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
    employee: Employee;
    token: string;
    stores: StoreOption[];
    selected_store_id: number | null;
    schedules: ScheduleOption[];
    selected_schedule: ScheduleOption | null;
    days: Record<string, { shifts: Shift[] }>;
}>();
</script>

<template>
    <Head :title="t('public_schedules.title')" />

    <div class="min-h-screen bg-surface-bg font-sans text-on-surface">
        <header
            class="border-b border-outline-glass bg-surface-container-lowest/90 px-4 py-4 shadow-sm"
        >
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <Brand href="/" />
                <div class="text-left sm:text-right">
                    <h1 class="font-heading text-xl font-bold">
                        {{ t('public_schedules.title') }}
                    </h1>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        {{ employee.name }}
                    </p>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-4 py-6">
            <EmployeeScheduleViewer
                :stores="stores"
                :selected-store-id="selected_store_id"
                :schedules="schedules"
                :selected-schedule="selected_schedule"
                :days="days"
                route-base="/public/employee-schedules"
                :token="token"
            />
        </main>
    </div>
</template>
