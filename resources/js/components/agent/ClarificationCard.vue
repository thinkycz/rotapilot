<script setup lang="ts">
import { Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import {
    isRecommendedClarificationOption,
    optionLetter,
} from '@/lib/clarification';
import type { AgentClarification } from '@/types';

const props = defineProps<{
    clarification: AgentClarification;
    disabled: boolean;
    selectedOption: string | null;
    showQuestion: boolean;
}>();

const emit = defineEmits<{
    select: [option: string];
}>();

const { t } = useI18n();

function selectOption(option: string): void {
    if (props.disabled || props.selectedOption !== null) {
        return;
    }

    emit('select', option);
}
</script>

<template>
    <div class="mt-3 space-y-3 border-t border-outline-glass pt-3">
        <p
            v-if="showQuestion"
            class="text-[10px] font-semibold tracking-wider text-primary uppercase"
        >
            {{ clarification.question }}
        </p>

        <div class="flex flex-col gap-2">
            <button
                v-for="(option, idx) in clarification.options"
                :key="option"
                type="button"
                :disabled="disabled || selectedOption !== null"
                class="group flex items-center justify-between rounded-xl border p-3 text-left transition-all"
                :class="
                    selectedOption === option
                        ? 'border-primary/60 bg-primary/10'
                        : 'border-outline-glass bg-surface-container-low/40 hover:border-primary/40 hover:bg-primary/5 disabled:hover:border-outline-glass disabled:hover:bg-surface-container-low/40'
                "
                @click="selectOption(option)"
            >
                <div class="flex min-w-0 items-start gap-2.5">
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold transition-colors"
                        :class="
                            selectedOption === option
                                ? 'bg-primary text-white'
                                : 'bg-surface-container-high text-on-surface-variant group-hover:bg-primary/10 group-hover:text-primary'
                        "
                    >
                        <Check v-if="selectedOption === option" :size="12" />
                        <span v-else>{{ optionLetter(idx) }}</span>
                    </span>
                    <span
                        class="text-xs leading-tight font-medium whitespace-normal break-words transition-colors"
                        :class="
                            selectedOption === option
                                ? 'text-primary'
                                : 'text-on-surface group-hover:text-primary'
                        "
                    >
                        {{ option }}
                    </span>
                </div>
                <span
                    v-if="
                        selectedOption === null &&
                        isRecommendedClarificationOption(option, clarification)
                    "
                    class="shrink-0 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[8px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                >
                    {{ t('agent.recommended') }}
                </span>
            </button>
        </div>

        <p
            v-if="!disabled && selectedOption === null"
            class="mt-1.5 text-[10px] text-on-surface-variant italic"
        >
            {{ t('agent.clarification_hint') }}
        </p>
    </div>
</template>
