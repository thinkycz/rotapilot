import { describe, expect, test } from 'vitest';
import {
    clarificationChoicePrompt,
    isRecommendedClarificationOption,
    optionLetter,
} from '@/lib/clarification';
import type { AgentClarification } from '@/types';

const clarification: AgentClarification = {
    question: 'Which store?',
    options: ['Zizkov', 'Karlin'],
    recommended_option: 'Zizkov',
};

describe('clarification helpers', () => {
    test('matches recommended option exactly', () => {
        expect(isRecommendedClarificationOption('Zizkov', clarification)).toBe(
            true,
        );
        expect(isRecommendedClarificationOption('Karlin', clarification)).toBe(
            false,
        );
    });

    test('builds contextual prompt for selected choices', () => {
        expect(clarificationChoicePrompt(clarification, 'Zizkov')).toBe(
            'For "Which store?", I choose "Zizkov".',
        );
    });

    test('renders stable option letters', () => {
        expect(optionLetter(0)).toBe('A');
        expect(optionLetter(1)).toBe('B');
    });
});
