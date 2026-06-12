<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Eye, CalendarRange } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { formatDateRange } from '@/lib/date';

const { t } = useI18n();
const { auth } = useSharedProps();

useBoundLocale();

interface ScheduleRow {
    id: number;
    name: string;
    store_id: number;
    status: string;
    period_start: string;
    period_end: string;
    shift_count: number;
}

interface StoreOption {
    id: number;
    name: string;
}

const props = defineProps<{
    schedules: ScheduleRow[];
    stores: StoreOption[];
}>();

const isManager = computed(() => auth.value.user?.role === 'store_manager');

const storeName = (id: number): string => {
    return props.stores.find((x) => x.id === id)?.name ?? '—';
};

const statusVariant = (s: string): string => {
    if (s === 'published') return 'bg-emerald-50 text-emerald-700';
    if (s === 'draft') return 'bg-amber-50 text-amber-700';
    return 'bg-zinc-100 text-zinc-700';
};
</script>

<template>
    <AppLayout :title="t('schedules.title_index')">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold text-on-surface">
                {{ t('schedules.title_index') }}
            </h1>
            <Link
                v-if="isManager"
                href="/schedules/create"
                class="inline-flex h-9 items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
            >
                <Plus :size="14" class="mr-1.5" />
                {{ t('schedules.create_cta') }}
            </Link>
        </div>

        <div
            v-if="schedules.length === 0"
            class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
        >
            <CalendarRange
                :size="32"
                class="mx-auto mb-3 text-on-surface-variant opacity-40"
            />
            <p class="text-sm text-on-surface-variant">
                {{ t('schedules.empty') }}
            </p>
        </div>

        <div
            v-else
            class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        >
            <table class="w-full text-xs">
                <thead class="bg-surface-container-low">
                    <tr
                        class="text-left font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                    >
                        <th class="px-4 py-2">{{ t('schedules.name') }}</th>
                        <th class="px-4 py-2">{{ t('schedules.store') }}</th>
                        <th class="px-4 py-2">{{ t('schedules.period') }}</th>
                        <th class="px-4 py-2">
                            {{ t('schedules.shift_count') }}
                        </th>
                        <th class="px-4 py-2">{{ t('schedules.status') }}</th>
                        <th class="px-4 py-2 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="s in schedules"
                        :key="s.id"
                        class="border-t border-outline-glass/30 hover:bg-surface-container-low"
                    >
                        <td class="px-4 py-2 font-semibold text-on-surface">
                            <Link
                                :href="`/schedules/show?id=${s.id}`"
                                class="hover:text-primary"
                            >
                                {{ s.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-on-surface-variant">
                            {{ storeName(s.store_id) }}
                        </td>
                        <td class="px-4 py-2 text-on-surface-variant">
                            {{ formatDateRange(s.period_start, s.period_end) }}
                        </td>
                        <td class="px-4 py-2 text-on-surface-variant">
                            {{ s.shift_count }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    statusVariant(s.status),
                                ]"
                            >
                                {{ t('schedules.status_' + s.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link
                                :href="`/schedules/show?id=${s.id}`"
                                class="inline-flex h-7 items-center gap-1 rounded-lg border border-outline-glass bg-white px-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                            >
                                <Eye :size="12" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
