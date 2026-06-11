<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus, Eye, Edit, Clock, MapPin, Coffee } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

const { t } = useI18n();
const { auth } = useSharedProps();

useBoundLocale();

interface StoreRow {
    id: number;
    name: string;
    city: string | null;
    address: string | null;
    is_active: boolean;
    today_hours: string;
}

defineProps<{
    stores: StoreRow[];
}>();

const isAdmin = computed(() => auth.value.user?.role === 'admin');

function destroy(id: number): void {
    if (confirm(t('common.confirm'))) {
        router.post('/stores/destroy', { id });
    }
}
</script>

<template>
    <AppLayout :title="t('stores.title_index')">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ t('stores.title_index') }}
                </h1>
            </div>
            <Link
                v-if="isAdmin"
                href="/stores/create"
                class="inline-flex h-9 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary px-4 text-xs font-semibold text-white shadow-sm hover:brightness-105"
            >
                <Plus :size="14" class="mr-1.5" />
                {{ t('stores.create_cta') }}
            </Link>
        </div>

        <div
            v-if="stores.length === 0"
            class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-12 text-center shadow-sm"
        >
            <Coffee
                :size="32"
                class="mx-auto mb-3 text-on-surface-variant opacity-40"
            />
            <p class="text-sm text-on-surface-variant">
                {{ t('stores.empty') }}
            </p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="s in stores"
                :key="s.id"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm transition-all hover:shadow-md"
            >
                <div class="mb-3 flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <h3
                            class="font-heading text-base font-bold text-on-surface truncate"
                        >
                            {{ s.name }}
                        </h3>
                        <p
                            v-if="s.city || s.address"
                            class="mt-1 flex items-center gap-1 text-xs text-on-surface-variant"
                        >
                            <MapPin :size="11" />
                            <span class="truncate">{{
                                s.city ?? s.address
                            }}</span>
                        </p>
                    </div>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                            s.is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-rose-50 text-rose-700',
                        ]"
                    >
                        {{ s.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <div
                    class="mb-4 flex items-center gap-1.5 text-xs text-on-surface-variant"
                >
                    <Clock :size="12" />
                    <span>{{ s.today_hours }}</span>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <Link
                        :href="`/stores/show?id=${s.id}`"
                        class="inline-flex h-7 items-center gap-1 rounded-lg border border-outline-glass bg-white px-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                    >
                        <Eye :size="12" />
                        {{ t('stores.view') }}
                    </Link>
                    <Link
                        v-if="isAdmin"
                        :href="`/stores/edit?id=${s.id}`"
                        class="inline-flex h-7 items-center gap-1 rounded-lg border border-outline-glass bg-white px-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                    >
                        <Edit :size="12" />
                        {{ t('stores.edit_link') }}
                    </Link>
                    <Link
                        :href="`/stores/business-hours?id=${s.id}`"
                        class="inline-flex h-7 items-center gap-1 rounded-lg border border-outline-glass bg-white px-2 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
                    >
                        <Clock :size="12" />
                        {{ t('stores.business_hours_link') }}
                    </Link>
                    <button
                        v-if="isAdmin"
                        @click="destroy(s.id)"
                        class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                    >
                        {{ t('common.delete') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
