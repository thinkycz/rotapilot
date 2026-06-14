<script setup lang="ts">
import { Check, Loader2, Sparkles, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import type { AgentProposal } from '@/types';

const props = defineProps<{
    proposal: AgentProposal;
    selected: Record<number, boolean>;
    isStreaming: boolean;
    isSubmittingThis: boolean;
    isAnySubmitting: boolean;
}>();

const emit = defineEmits<{
    toggle: [index: number];
    apply: [];
    reject: [];
}>();

const { t } = useI18n();

function isActionApplied(index: number): boolean {
    if (props.proposal.status !== 'applied') {
        return false;
    }

    const applied = props.proposal.result?.applied_actions;
    if (!Array.isArray(applied)) {
        return true;
    }

    return applied.some(
        (a: { action_index?: unknown }) => a.action_index === index,
    );
}

function getActionTypeName(type: string): string {
    if (type.endsWith('.create')) return '+';
    if (type === 'shift.assign') return 'assign';
    if (type === 'shift.unassign') return 'remove';
    if (type.endsWith('.delete')) return 'delete';
    if (type.endsWith('.update')) return 'edit';

    return type.split('.')[1] || type;
}

function actionDiffClass(action: { type: string }, index: number): string {
    const type = action.type;
    const base =
        'rounded-lg border-l-2 px-3 py-2 text-[11px] flex items-center justify-between gap-3 transition-all duration-150 text-on-surface ';

    const isPending = props.proposal.status === 'pending';
    const isApplied = props.proposal.status === 'applied';
    const isSelectedOrApplied = isPending
        ? (props.selected[index] ?? true)
        : isActionApplied(index);

    if (isPending) {
        const opacity = !isSelectedOrApplied ? 'opacity-40 ' : '';
        if (type.endsWith('.create') || type === 'shift.assign') {
            return (
                base +
                opacity +
                'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20'
            );
        } else if (type.endsWith('.delete') || type === 'shift.unassign') {
            const lineThrough = isSelectedOrApplied ? 'line-through ' : '';
            return (
                base +
                opacity +
                lineThrough +
                'border-rose-500 bg-rose-50/50 dark:bg-rose-950/20'
            );
        } else if (type.endsWith('.update')) {
            return (
                base +
                opacity +
                'border-amber-500 bg-amber-50/50 dark:bg-amber-950/20'
            );
        }

        return (
            base + opacity + 'border-sky-500 bg-sky-50/50 dark:bg-sky-950/20'
        );
    }

    if (isApplied) {
        if (!isSelectedOrApplied) {
            return (
                base +
                'opacity-30 line-through border-outline-glass bg-surface-container-lowest text-on-surface-variant'
            );
        }

        if (type.endsWith('.create') || type === 'shift.assign') {
            return base + 'border-emerald-500 bg-emerald-500/5';
        } else if (type.endsWith('.delete') || type === 'shift.unassign') {
            return base + 'line-through border-rose-500 bg-rose-500/5';
        } else if (type.endsWith('.update')) {
            return base + 'border-amber-500 bg-amber-500/5';
        }

        return base + 'border-sky-500 bg-sky-500/5';
    }

    return (
        base +
        'opacity-50 border-outline-glass bg-surface-container-lowest text-on-surface-variant'
    );
}

function proposalStatusLabel(status: AgentProposal['status']): string {
    return t(`agent.proposal_status_${status}`);
}

function proposalConflictCount(): number {
    const conflicts = props.proposal.result?.conflicts;
    if (!Array.isArray(conflicts)) {
        return 0;
    }

    return conflicts.reduce((count, row) => {
        if (
            typeof row === 'object' &&
            row !== null &&
            Array.isArray((row as { conflicts?: unknown }).conflicts)
        ) {
            return count + (row as { conflicts: unknown[] }).conflicts.length;
        }

        return count;
    }, 0);
}
</script>

<template>
    <article
        class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-4 py-3 text-xs leading-relaxed shadow-sm transition-all"
    >
        <div
            class="mb-2 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-primary"
        >
            <Sparkles :size="13" />
            <span>{{ t('agent.proposal_title') }}</span>
            <span
                class="rounded-full bg-surface-container px-2 py-0.5 text-[9px] font-semibold normal-case tracking-normal text-on-surface-variant"
            >
                {{ proposalStatusLabel(proposal.status) }}
            </span>
        </div>

        <h2 class="text-sm font-semibold text-on-surface">
            {{ proposal.summary }}
        </h2>

        <ul class="mt-3 space-y-2">
            <li
                v-for="(action, idx) in proposal.actions"
                :key="`${proposal.id}-${idx}`"
                :class="actionDiffClass(action, idx)"
            >
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <input
                        v-if="proposal.status === 'pending'"
                        type="checkbox"
                        :checked="selected[idx] ?? true"
                        class="h-4 w-4 shrink-0 rounded border-outline-glass bg-surface-container-low text-primary focus:ring-primary/20 accent-primary cursor-pointer"
                        @change="emit('toggle', idx)"
                    />
                    <span class="truncate font-medium leading-5">
                        {{ action.label }}
                    </span>
                </div>
                <span
                    class="shrink-0 text-[9px] font-bold uppercase tracking-wider opacity-60"
                >
                    {{ getActionTypeName(action.type) }}
                </span>
            </li>
        </ul>

        <p
            v-if="proposalConflictCount() > 0"
            class="mt-3 text-[11px] font-medium text-amber-700"
        >
            {{
                t('agent.proposal_conflicts', {
                    count: proposalConflictCount(),
                })
            }}
        </p>

        <div
            v-if="proposal.status === 'failed' && proposal.result?.error"
            class="mt-3 rounded-lg border border-red-200 bg-red-50/50 px-3 py-2 text-[11px] text-red-800"
        >
            {{ proposal.result.error }}
        </div>

        <div
            v-if="proposal.status === 'pending'"
            class="mt-3 flex shrink-0 items-center gap-2"
        >
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-lg border border-outline-glass bg-surface-container-lowest/40 px-3 text-[11px] font-semibold text-on-surface-variant transition hover:bg-surface-container disabled:opacity-50"
                :disabled="isStreaming || isAnySubmitting"
                @click="emit('reject')"
            >
                <X :size="13" />
                {{ t('agent.proposal_reject') }}
            </button>
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-lg bg-primary px-3 text-[11px] font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="
                    isStreaming ||
                    isAnySubmitting ||
                    !Object.values(selected).some(Boolean)
                "
                @click="emit('apply')"
            >
                <Loader2
                    v-if="isSubmittingThis"
                    class="h-3.5 w-3.5 animate-spin"
                />
                <Check v-else :size="13" />
                {{ t('agent.proposal_apply') }}
            </button>
        </div>
    </article>
</template>
