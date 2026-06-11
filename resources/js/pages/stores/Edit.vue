<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

interface Store {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    timezone: string;
    is_active: boolean;
}

const props = defineProps<{
    store: Store | null;
    timezones: string[];
}>();

const isEdit = computed(() => props.store !== null);

const form = useForm({
    name: props.store?.name ?? '',
    address: props.store?.address ?? '',
    city: props.store?.city ?? '',
    timezone: props.store?.timezone ?? 'Europe/Prague',
    is_active: props.store?.is_active ?? true,
});

function submit(): void {
    if (isEdit.value) {
        form.post(`/stores/update?id=${props.store!.id}`);
    } else {
        form.post('/stores/store');
    }
}
</script>

<template>
    <AppLayout
        :title="isEdit ? t('stores.title_edit') : t('stores.title_create')"
    >
        <h1 class="mb-6 font-heading text-2xl font-bold text-on-surface">
            {{ isEdit ? t('stores.title_edit') : t('stores.title_create') }}
        </h1>

        <form
            @submit.prevent="submit"
            class="max-w-2xl space-y-4 rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('stores.name') }}
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
                    {{ t('stores.address') }}
                </label>
                <input
                    v-model="form.address"
                    type="text"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('stores.city') }}
                </label>
                <input
                    v-model="form.city"
                    type="text"
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-on-surface-variant"
                >
                    {{ t('stores.timezone') }}
                </label>
                <select
                    v-model="form.timezone"
                    required
                    class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                >
                    <option v-for="tz in timezones" :key="tz" :value="tz">
                        {{ tz }}
                    </option>
                </select>
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
                    {{ t('stores.is_active') }}
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
                    href="/stores/index"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.cancel') }}
                </a>
            </div>
        </form>
    </AppLayout>
</template>
