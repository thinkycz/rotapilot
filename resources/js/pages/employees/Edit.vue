<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
}

const props = defineProps<{
    employee: Employee | null;
    stores: { id: number; name: string }[];
}>();

const isEdit = !!props.employee;

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    phone: props.employee?.phone ?? '',
    role_label: props.employee?.role_label ?? '',
    max_hours_per_week:
        props.employee?.max_hours_per_week ?? (null as number | null),
    is_active: props.employee?.is_active ?? true,
});

function submit(): void {
    if (isEdit) {
        form.post(`/employees/update?id=${props.employee!.id}`);
    } else {
        form.post('/employees/store');
    }
}
</script>

<template>
    <AppLayout
        :title="
            isEdit ? t('employees.title_edit') : t('employees.title_create')
        "
    >
        <h1 class="mb-6 font-heading text-2xl font-bold text-on-surface">
            {{
                isEdit ? t('employees.title_edit') : t('employees.title_create')
            }}
        </h1>

        <form
            @submit.prevent="submit"
            class="max-w-2xl space-y-4 rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('employees.name') }}
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('employees.email') }}
                </label>
                <input
                    v-model="form.email"
                    type="email"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('employees.phone') }}
                </label>
                <input
                    v-model="form.phone"
                    type="tel"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('employees.role_label') }}
                </label>
                <input
                    v-model="form.role_label"
                    type="text"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('employees.max_hours_per_week') }}
                </label>
                <input
                    v-model.number="form.max_hours_per_week"
                    type="number"
                    min="1"
                    max="168"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-outline-glass"
                />
                <label
                    for="is_active"
                    class="text-xs font-semibold text-on-surface-variant"
                >
                    Active
                </label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                >
                    {{ t('common.save') }}
                </button>
                <a
                    href="/employees/index"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.cancel') }}
                </a>
            </div>
        </form>
    </AppLayout>
</template>
