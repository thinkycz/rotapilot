<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Edit, CalendarCheck2, Trash2, Plus, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

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

interface Store {
    id: number;
    name: string;
}

const props = defineProps<{
    employee: Employee;
    stores: Store[];
}>();

function unassign(storeId: number): void {
    if (confirm('Unassign from store?')) {
        router.post('/employees/stores/destroy', {
            employee_id: props.employee.id,
            store_id: storeId,
        });
    }
}
</script>

<template>
    <AppLayout :title="employee.name">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ employee.name }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ employee.email ?? '—' }} ·
                    {{ employee.role_label ?? '—' }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    href="/employees/edit"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    <Edit :size="14" class="mr-1.5" />
                    {{ t('employees.edit_link') }}
                </Link>
                <Link
                    :href="`/availability?employee_id=${employee.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
                >
                    <CalendarCheck2 :size="14" class="mr-1.5" />
                    {{ t('employees.manage_availability') }}
                </Link>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    Profile
                </h2>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">Phone</dt>
                        <dd class="text-on-surface">
                            {{ employee.phone ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">
                            Max hours / week
                        </dt>
                        <dd class="text-on-surface">
                            {{ employee.max_hours_per_week ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">Login</dt>
                        <dd>
                            <span
                                v-if="employee.has_login"
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                                >Yes</span
                            >
                            <span
                                v-else
                                class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-700"
                                >No</span
                            >
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="mb-3 font-heading text-sm font-bold text-on-surface">
                    {{ t('employees.assigned_stores') }}
                </h2>
                <ul v-if="stores.length > 0" class="space-y-1.5">
                    <li
                        v-for="s in stores"
                        :key="s.id"
                        class="flex items-center justify-between rounded-lg border border-outline-glass/40 bg-white px-3 py-2"
                    >
                        <span class="text-xs font-semibold text-on-surface">{{
                            s.name
                        }}</span>
                        <button
                            @click="unassign(s.id)"
                            class="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 text-[10px] font-bold text-rose-700 hover:bg-rose-100"
                        >
                            <X :size="10" />
                            Unassign
                        </button>
                    </li>
                </ul>
                <p v-else class="text-xs text-on-surface-variant">—</p>
            </section>
        </div>
    </AppLayout>
</template>
