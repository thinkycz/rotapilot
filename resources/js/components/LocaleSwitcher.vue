<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { isSupportedLocale, SUPPORTED_LOCALES } from '@/i18n';
import { useSharedProps } from '@/composables/useSharedProps';

const { user } = useSharedProps();
const { t, locale, te } = useI18n();

const options = computed(() =>
    SUPPORTED_LOCALES.map((value) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);

const form = useForm({
    email: user.value?.email ?? '',
    locale: locale.value,
});

const currentLocale = computed(() =>
    isSupportedLocale(locale.value) ? locale.value : 'en',
);

function switchLocale(next: string): void {
    if (!isSupportedLocale(next) || next === currentLocale.value) {
        return;
    }

    form.locale = next;

    router.post('/settings/profile', form.data(), {
        preserveScroll: true,
        only: ['app', 'auth'],
    });
}
</script>

<template>
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <span class="sr-only">{{ t('locale.switcher_label') }}</span>
        <select
            :value="currentLocale"
            :aria-label="t('locale.switcher_label')"
            class="h-8 rounded-lg border border-outline-glass bg-white px-2 text-xs text-on-surface outline-none transition focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 cursor-pointer"
            @change="
                (event) =>
                    switchLocale((event.target as HTMLSelectElement).value)
            "
        >
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
    </label>
</template>
