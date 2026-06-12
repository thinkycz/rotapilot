<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Eye } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

const { t } = useI18n();
const { auth } = useSharedProps();

useBoundLocale();

interface Employee {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role_label: string | null;
    max_hours_per_week: number | null;
    is_active: boolean;
    has_login: boolean;
}

defineProps<{
    employees: Employee[];
}>();

const isStoreManager = computed(
    () => auth.value.user?.role === 'store_manager',
);
</script>

<template>
    <AppLayout :title="t('employees.title_index')">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-heading text-2xl font-bold text-on-surface">
                {{ t('employees.title_index') }}
            </h1>
            <Link
                v-if="isStoreManager"
                href="/employees/create"
                class="inline-flex h-9 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
            >
                <Plus :size="14" class="mr-1.5" />
                {{ t('employees.create_cta') }}
            </Link>
        </div>

        <div
            v-if="employees.length === 0"
            class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
        >
            <p class="text-sm text-on-surface-variant">
                {{ t('employees.empty') }}
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
                        <th class="px-4 py-2">{{ t('employees.name') }}</th>
                        <th class="px-4 py-2">{{ t('employees.email') }}</th>
                        <th class="px-4 py-2">
                            {{ t('employees.role_label') }}
                        </th>
                        <th class="px-4 py-2">
                            {{ t('employees.max_hours_per_week') }}
                        </th>
                        <th class="px-4 py-2">{{ t('employees.login') }}</th>
                        <th class="px-4 py-2 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in employees"
                        :key="e.id"
                        class="border-t border-outline-glass/30 hover:bg-surface-container-low"
                    >
                        <td class="px-4 py-2 font-semibold text-on-surface">
                            <Link
                                :href="`/employees/show?id=${e.id}`"
                                class="hover:text-primary"
                            >
                                {{ e.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-on-surface-variant">
                            {{ e.email ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-on-surface-variant">
                            {{ e.role_label ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-on-surface-variant">
                            {{ e.max_hours_per_week ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-if="e.has_login"
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                                >{{ t('common.yes') }}</span
                            >
                            <span
                                v-else
                                class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-700"
                                >{{ t('common.no') }}</span
                            >
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="inline-flex gap-1">
                                <Link
                                    :href="`/employees/show?id=${e.id}`"
                                    class="inline-flex h-7 items-center gap-1 rounded-lg border border-outline-glass bg-white px-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                                >
                                    <Eye :size="12" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
