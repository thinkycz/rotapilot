<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Sparkles, AlertCircle, Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();
useBoundLocale();

interface StoreOption {
    id: number;
    name: string;
}

interface PreviewShift {
    date: string;
    start_time: string;
    end_time: string;
    required_employee_count: number;
    role_label: string | null;
    note: string | null;
}

interface Preview {
    intent?: string;
    understanding: string;
    warnings: string[];
    shift_requirements: PreviewShift[];
}

interface Context {
    store: { id: number; name: string } | null;
    store_id: number;
    period_start: string;
    period_end: string;
    name: string;
}

const props = defineProps<{
    context: Context;
    stores: StoreOption[];
    has_ai: boolean;
    preview: Preview | null;
}>();

const form = useForm({
    store_id: props.context.store_id ?? 0,
    period_start: props.context.period_start ?? '',
    period_end: props.context.period_end ?? '',
    name: props.context.name ?? 'AI Schedule',
    prompt: 'Create a schedule for next week. Weekdays 1 person 10:00-18:00. Weekends 2 people 11:00-20:00.',
});

const preview = ref<Preview | null>(props.preview);
const loading = ref(false);

function generate(): void {
    loading.value = true;
    form.post('/ai-planner/message', {
        onSuccess: (page) => {
            const data = page.props as { preview?: Preview };
            preview.value = data.preview ?? null;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

const applyForm = useForm({
    store_id: form.store_id,
    period_start: form.period_start,
    period_end: form.period_end,
    name: form.name,
    shift_requirements: [] as PreviewShift[],
});

function apply(): void {
    if (!preview.value) return;
    applyForm.store_id = form.store_id;
    applyForm.period_start = form.period_start;
    applyForm.period_end = form.period_end;
    applyForm.name = form.name;
    applyForm.shift_requirements = preview.value.shift_requirements;
    applyForm.post('/ai-planner/apply-preview');
}
</script>

<template>
    <AppLayout :title="t('ai.title')">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ t('ai.title') }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ context.store?.name ?? '—' }}
                </p>
            </div>
            <span
                v-if="!has_ai"
                class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 uppercase tracking-wider"
            >
                Fake mode
            </span>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <section
                class="space-y-4 rounded-2xl border border-outline-glass bg-gradient-to-br from-primary-container/10 via-surface-container-lowest to-secondary-cyan/5 p-5 shadow-sm"
            >
                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                    >
                        {{ t('schedules.store') }}
                    </label>
                    <select
                        v-model.number="form.store_id"
                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                    >
                        <option :value="0">— select —</option>
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

                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                    >
                        {{ t('schedules.name') }}
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-on-surface-variant"
                    >
                        {{ t('ai.prompt_label') }}
                    </label>
                    <textarea
                        v-model="form.prompt"
                        rows="5"
                        :placeholder="t('ai.prompt_placeholder')"
                        class="w-full rounded-xl border border-outline-glass bg-white px-3 py-2 font-mono text-xs text-on-surface focus:border-primary focus:outline-none"
                    ></textarea>
                </div>

                <button
                    type="button"
                    :disabled="
                        loading ||
                        !form.store_id ||
                        !form.period_start ||
                        !form.period_end
                    "
                    @click="generate"
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-primary/20 bg-gradient-to-b from-primary-container to-primary text-sm font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                >
                    <Sparkles :size="14" />
                    {{ loading ? '…' : t('ai.send_cta') }}
                </button>
            </section>

            <section
                class="space-y-4 rounded-2xl border border-outline-glass bg-surface-container-lowest p-5 shadow-sm"
            >
                <h2 class="font-heading text-sm font-bold text-on-surface">
                    {{ t('ai.understanding') }}
                </h2>

                <div
                    v-if="!preview"
                    class="rounded-xl border border-dashed border-outline-glass bg-white/40 p-6 text-center text-xs text-on-surface-variant italic"
                >
                    <Sparkles :size="20" class="mx-auto mb-2 opacity-40" />
                    {{ t('ai.no_preview') }}
                </div>

                <div v-else class="space-y-3">
                    <p
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700"
                    >
                        {{ preview.understanding }}
                    </p>

                    <div
                        v-if="preview.warnings.length > 0"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3"
                    >
                        <p
                            class="mb-1 font-mono text-[10px] font-bold uppercase tracking-wider text-amber-700"
                        >
                            {{ t('ai.warnings') }}
                        </p>
                        <ul class="space-y-1 text-xs text-amber-700">
                            <li
                                v-for="(w, idx) in preview.warnings"
                                :key="idx"
                                class="flex items-start gap-1.5"
                            >
                                <AlertCircle
                                    :size="12"
                                    class="mt-0.5 shrink-0"
                                />
                                <span>{{ w }}</span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <p
                            class="mb-2 font-mono text-[10px] font-bold uppercase tracking-wider text-on-surface-variant"
                        >
                            {{ t('ai.shift_requirements') }}
                        </p>
                        <ul class="max-h-64 space-y-1 overflow-y-auto">
                            <li
                                v-for="(s, idx) in preview.shift_requirements"
                                :key="idx"
                                class="rounded-lg border border-outline-glass/40 bg-white px-3 py-1.5 text-xs"
                            >
                                <span
                                    class="font-mono font-bold text-on-surface"
                                    >{{ s.date }}</span
                                >
                                <span class="ml-2 text-on-surface"
                                    >{{ s.start_time }} – {{ s.end_time }}</span
                                >
                                <span class="ml-2 text-on-surface-variant"
                                    >×{{ s.required_employee_count }}</span
                                >
                                <span
                                    v-if="s.role_label"
                                    class="ml-2 text-on-surface-variant"
                                    >{{ s.role_label }}</span
                                >
                            </li>
                        </ul>
                    </div>

                    <button
                        type="button"
                        @click="apply"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"
                    >
                        <Check :size="14" />
                        {{ t('ai.apply_cta') }}
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
