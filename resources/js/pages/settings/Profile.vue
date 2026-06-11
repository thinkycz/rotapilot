<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

type ProfileFields = {
    email: string;
    locale: string;
};

const { user, app } = useSharedProps();
const { t, te } = useI18n();

useBoundLocale();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);
</script>

<template>
    <AppLayout :title="t('settings.profile.title')">
        <section
            class="max-w-xl rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <Form
                v-slot="{ errors, processing }"
                action="/settings/profile"
                method="post"
                class="space-y-5"
            >
                <div class="space-y-2">
                    <Label for="email">{{ t('fields.email') }}</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        :default-value="user?.email ?? ''"
                        required
                    />
                    <FieldError
                        :message="
                            (
                                errors as ProfileFields extends object
                                    ? ProfileFields
                                    : never
                            )['email']
                        "
                    />
                </div>

                <div class="space-y-2">
                    <Label for="locale">{{ t('fields.locale') }}</Label>
                    <Select
                        id="locale"
                        name="locale"
                        :options="localeOptions"
                        :default-value="user?.locale ?? app.locale"
                        required
                    />
                    <FieldError
                        :message="
                            (
                                errors as ProfileFields extends object
                                    ? ProfileFields
                                    : never
                            )['locale']
                        "
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="processing">{{
                        t('settings.profile.submit')
                    }}</Button>
                    <Link
                        href="/settings/password"
                        class="text-xs font-bold text-primary hover:text-primary-container"
                        >{{ t('settings.profile.change_password') }}</Link
                    >
                </div>
            </Form>
        </section>
    </AppLayout>
</template>
