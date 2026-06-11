<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

interface Schedule {
    id: number;
    name: string;
    store_id: number;
    period_start: string;
    period_end: string;
    status: string;
}

const props = defineProps<{
    schedule: Schedule | null;
    stores: { id: number; name: string }[];
    default_store_id?: number;
}>();

const isEdit = !!props.schedule;

const form = useForm({
    name: props.schedule?.name ?? '',
    store_id: props.schedule?.store_id ?? props.default_store_id ?? 0,
    period_start: props.schedule?.period_start ?? '',
    period_end: props.schedule?.period_end ?? '',
});

function submit(): void {
    if (isEdit) {
        form.post(`/schedules/update?id=${props.schedule!.id}`);
    } else {
        form.post('/schedules/store');
    }
}
</script>

<template>
    <AppLayout
        :title="
            isEdit ? t('schedules.title_edit') : t('schedules.title_create')
        "
    >
        <h1 class="mb-6 font-heading text-2xl font-bold text-on-surface">
            {{
                isEdit ? t('schedules.title_edit') : t('schedules.title_create')
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
                    {{ t('schedules.name') }}
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
                    {{ t('schedules.store') }}
                </label>
                <select
                    v-model.number="form.store_id"
                    required
                    :disabled="isEdit"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none disabled:opacity-50"
                >
                    <option v-for="s in stores" :key="s.id" :value="s.id">
                        {{ s.name }}
                    </option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                    >
                        {{ t('schedules.period') }} start
                    </label>
                    <input
                        v-model="form.period_start"
                        type="date"
                        required
                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                    />
                </div>
                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                    >
                        {{ t('schedules.period') }} end
                    </label>
                    <input
                        v-model="form.period_end"
                        type="date"
                        required
                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                    />
                </div>
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
                    href="/schedules/index"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.cancel') }}
                </a>
            </div>
        </form>
    </AppLayout>
</template>
