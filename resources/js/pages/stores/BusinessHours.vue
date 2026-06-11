<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();

interface Store {
    id: number;
    name: string;
    timezone: string;
}

interface Hour {
    day_of_week: number;
    opens_at: string | null;
    closes_at: string | null;
    is_closed: boolean;
}

const props = defineProps<{
    store: Store;
    hours: Hour[];
}>();

const dayNames = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const form = useForm({
    hours: props.hours.map((h) => ({ ...h })),
});

function toggleClosed(index: number): void {
    if (form.hours[index].is_closed) {
        form.hours[index].is_closed = false;
    } else {
        form.hours[index].is_closed = true;
        form.hours[index].opens_at = null;
        form.hours[index].closes_at = null;
    }
}

function submit(): void {
    form.post(`/stores/business-hours/update?id=${props.store.id}`);
}
</script>

<template>
    <AppLayout :title="t('stores.title_business_hours')">
        <div class="mb-6">
            <h1 class="font-heading text-2xl font-bold text-on-surface">
                {{ t('stores.title_business_hours') }}
            </h1>
            <p class="mt-1 text-xs text-on-surface-variant">
                {{ store.name }} · {{ store.timezone }}
            </p>
        </div>

        <form
            @submit.prevent="submit"
            class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
        >
            <table class="w-full text-xs">
                <thead class="bg-surface-container-low">
                    <tr
                        class="text-left font-mono text-[10px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                    >
                        <th class="px-4 py-2">Day</th>
                        <th class="px-4 py-2">Closed</th>
                        <th class="px-4 py-2">Opens at</th>
                        <th class="px-4 py-2">Closes at</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(h, idx) in form.hours"
                        :key="h.day_of_week"
                        class="border-t border-outline-glass/30"
                    >
                        <td class="px-4 py-2 font-semibold text-on-surface">
                            {{ dayNames[h.day_of_week - 1] }}
                        </td>
                        <td class="px-4 py-2">
                            <input
                                type="checkbox"
                                :checked="h.is_closed"
                                @change="toggleClosed(idx)"
                                class="h-4 w-4 cursor-pointer rounded border-outline-glass"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="form.hours[idx].opens_at"
                                type="time"
                                :disabled="h.is_closed"
                                class="rounded-lg border border-outline-glass bg-white px-2 py-1 text-xs text-on-surface focus:border-primary focus:outline-none disabled:opacity-50"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="form.hours[idx].closes_at"
                                type="time"
                                :disabled="h.is_closed"
                                class="rounded-lg border border-outline-glass bg-white px-2 py-1 text-xs text-on-surface focus:border-primary focus:outline-none disabled:opacity-50"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                class="flex items-center gap-2 border-t border-outline-glass/30 p-4"
            >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex h-9 cursor-pointer items-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                >
                    {{ t('common.save') }}
                </button>
                <a
                    :href="`/stores/show?id=${store.id}`"
                    class="inline-flex h-9 items-center rounded-xl border border-outline-glass bg-white px-4 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                >
                    {{ t('common.cancel') }}
                </a>
            </div>
        </form>
    </AppLayout>
</template>
