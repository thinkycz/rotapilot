import type { AgentClarification } from '@/types';

export function clarificationChoicePrompt(
    clarification: AgentClarification,
    option: string,
): string {
    return `For "${clarification.question}", I choose "${option}".`;
}

export function isRecommendedClarificationOption(
    option: string,
    clarification: AgentClarification,
): boolean {
    return clarification.recommended_option === option;
}

export function optionLetter(index: number): string {
    return String.fromCharCode(65 + index);
}
