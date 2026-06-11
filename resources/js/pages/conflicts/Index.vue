<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { AlertCircle, Sparkles, X, Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();
useBoundLocale();

interface ConflictRow {
    id: number;
    type: string;
    severity: string;
    message: string;
    suggested_fix: string | null;
    shift_requirement_id: number | null;
    employee_profile_id: number | null;
}

interface ScheduleRef {
    id: number;
    name: string;
}

defineProps<{
    conflicts: ConflictRow[];
    by_type: Record<string, ConflictRow[]>;
    schedules: ScheduleRef[];
    schedule_id: number;
}>();

const explaining = ref<number | null>(null);
const explanations = ref<
    Record<
        number,
        { explanation: string; suggested_fix: string; severity: string }
    >
>({});

async function askAi(id: number): Promise<void> {
    explaining.value = id;
    await fetch('/conflicts/ask-ai?id=' + id, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN':
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement | null
                )?.content ?? '',
        },
    })
        .then((r) => r.json())
        .then(
            (data: {
                explanation: string;
                suggested_fix: string;
                severity: string;
            }) => {
                explanations.value = { ...explanations.value, [id]: data };
            },
        )
        .finally(() => {
            explaining.value = null;
        });
}

const resolveForm = useForm({});
function resolve(id: number): void {
    resolveForm.post('/conflicts/resolve?id=' + id);
}

function changeSchedule(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    router.get('/conflicts', value === '0' ? {} : { schedule_id: value });
}

function severityClass(severity: string): string {
    if (severity === 'critical') return 'border-red-300 bg-red-50 text-red-800';
    if (severity === 'high')
        return 'border-orange-300 bg-orange-50 text-orange-800';
    if (severity === 'medium')
        return 'border-amber-300 bg-amber-50 text-amber-800';
    return 'border-blue-200 bg-blue-50 text-blue-800';
}

function typeLabel(type: string): string {
    const map: Record<string, string> = {
        overlap: 'Overlap',
        understaffed: 'Understaffed',
        outside_business_hours: 'Outside hours',
        outside_availability: 'Outside availability',
        weekly_hours_exceeded: 'Hours exceeded',
        unavailable_day: 'Unavailable day',
    };
    return map[type] ?? type;
}
</script>

<template>
    <AppLayout :title="t('conflicts.title')">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-on-surface">
                    {{ t('conflicts.title') }}
                </h1>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ t('conflicts.subtitle') }}
                </p>
            </div>

            <div>
                <label
                    class="mr-2 text-xs font-semibold text-on-surface-variant"
                    >Schedule</label
                >
                <select
                    :value="schedule_id.toString()"
                    @change="changeSchedule"
                    class="rounded-xl border border-outline-glass bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                >
                    <option value="0">All schedules</option>
                    <option
                        v-for="s in schedules"
                        :key="s.id"
                        :value="s.id.toString()"
                    >
                        {{ s.name }}
                    </option>
                </select>
            </div>
        </div>

        <div
            v-if="conflicts.length === 0"
            class="rounded-2xl border border-dashed border-outline-glass bg-white/40 p-12 text-center"
        >
            <Check :size="32" class="mx-auto mb-3 text-emerald-500" />
            <p class="font-heading text-sm font-semibold text-on-surface">
                {{ t('conflicts.empty') }}
            </p>
            <p class="mt-1 text-xs text-on-surface-variant">
                {{ t('conflicts.empty_subtitle') }}
            </p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="c in conflicts"
                :key="c.id"
                class="space-y-2 rounded-2xl border bg-white p-4 shadow-sm"
                :class="severityClass(c.severity)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="font-mono text-[10px] font-bold uppercase tracking-wider opacity-80"
                        >
                            {{ typeLabel(c.type) }} · {{ c.severity }}
                        </p>
                        <p class="mt-1 text-sm">{{ c.message }}</p>
                        <p
                            v-if="c.suggested_fix"
                            class="mt-1 text-xs italic opacity-80"
                        >
                            {{ c.suggested_fix }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <button
                            type="button"
                            @click="askAi(c.id)"
                            :disabled="explaining === c.id"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-current/30 bg-white/60 px-2.5 text-xs font-semibold hover:bg-white/80 disabled:opacity-50"
                        >
                            <Sparkles :size="12" />
                            {{ explaining === c.id ? '…' : 'Ask AI' }}
                        </button>
                        <button
                            type="button"
                            @click="resolve(c.id)"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-current/30 bg-white/60 px-2.5 text-xs font-semibold hover:bg-white/80"
                        >
                            <X :size="12" />
                            {{ t('conflicts.resolve') }}
                        </button>
                    </div>
                </div>
                <div
                    v-if="explanations[c.id]"
                    class="rounded-xl border border-current/20 bg-white/60 p-3"
                >
                    <p
                        class="mb-1 font-mono text-[10px] font-bold uppercase tracking-wider opacity-80"
                    >
                        AI explanation
                    </p>
                    <p class="text-xs">{{ explanations[c.id]?.explanation }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
