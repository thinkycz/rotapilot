<script setup lang="ts">
import { ref, onMounted, watch, nextTick, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bot, Send, Sparkles, Loader2, Check, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useSharedProps } from '@/composables/useSharedProps';
import { parseTextDeltaSseChunk } from '@/lib/sse';
import type { AgentProposal } from '@/types';

interface MessagePayload {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at?: string;
}

const props = defineProps<{
    conversationId: string | null;
    messages: MessagePayload[];
    proposals: AgentProposal[];
}>();

const { t } = useI18n();
const { conversations } = useSharedProps();

const localMessages = ref<MessagePayload[]>([]);
const promptInput = ref('');
const isStreaming = ref(false);
const proposalActionId = ref<number | null>(null);
const chatScrollContainer = ref<HTMLDivElement | null>(null);
const promptTextarea = ref<HTMLTextAreaElement | null>(null);

// Sync local messages with props
watch(
    () => props.messages,
    (newMessages) => {
        localMessages.value = [...newMessages];
        scrollToBottom();
    },
    { immediate: true, deep: true },
);

// Scroll to bottom helper
function scrollToBottom(): void {
    nextTick(() => {
        if (chatScrollContainer.value) {
            chatScrollContainer.value.scrollTop =
                chatScrollContainer.value.scrollHeight;
        }
    });
}

const suggestions = computed(() => [
    t('agent.suggestion_stores'),
    t('agent.suggestion_shifts'),
    t('agent.suggestion_availability'),
]);

function useSuggestion(text: string): void {
    promptInput.value = text;
    resizePromptTextarea();
    sendMessage();
}

function resizePromptTextarea(): void {
    nextTick(() => {
        if (!promptTextarea.value) {
            return;
        }

        promptTextarea.value.style.height = 'auto';
        promptTextarea.value.style.height = `${promptTextarea.value.scrollHeight}px`;
    });
}

function handlePromptKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    event.preventDefault();
    void sendMessage();
}

function applyProposal(proposal: AgentProposal): void {
    proposalActionId.value = proposal.id;
    router.post(
        '/agent/proposals/apply',
        { proposal_id: proposal.id },
        {
            preserveScroll: true,
            onFinish: () => {
                proposalActionId.value = null;
            },
        },
    );
}

function rejectProposal(proposal: AgentProposal): void {
    proposalActionId.value = proposal.id;
    router.post(
        '/agent/proposals/reject',
        { proposal_id: proposal.id },
        {
            preserveScroll: true,
            onFinish: () => {
                proposalActionId.value = null;
            },
        },
    );
}

function proposalStatusLabel(status: AgentProposal['status']): string {
    return t(`agent.proposal_status_${status}`);
}

function proposalConflictCount(proposal: AgentProposal): number {
    const conflicts = proposal.result?.conflicts;
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

// Extract CSRF token from cookie
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Send Message stream handler
async function sendMessage(): Promise<void> {
    const prompt = promptInput.value.trim();
    if (!prompt || isStreaming.value) return;

    promptInput.value = '';
    resizePromptTextarea();
    isStreaming.value = true;

    // Optimistically push User message
    const userMsgId = 'user-temp-' + Date.now();
    localMessages.value.push({
        id: userMsgId,
        role: 'user',
        content: prompt,
    });
    scrollToBottom();

    // Setup placeholder for assistant message
    const assistantMsgId = 'assistant-temp-' + Date.now();
    localMessages.value.push({
        id: assistantMsgId,
        role: 'assistant',
        content: '',
    });
    scrollToBottom();

    try {
        const response = await fetch('/agent/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                prompt: prompt,
                conversation_id: props.conversationId,
            }),
        });

        if (!response.ok) {
            throw new Error('Streaming failed');
        }

        const reader = response.body?.getReader();
        const decoder = new TextDecoder('utf-8');

        if (!reader) {
            throw new Error('Unable to read stream');
        }

        let assistantContent = '';
        let sseBuffer = '';
        let streamDone = false;

        while (!streamDone) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value, { stream: true });
            const parsed = parseTextDeltaSseChunk(chunk, sseBuffer);
            sseBuffer = parsed.buffer;
            streamDone = parsed.done;

            for (const delta of parsed.deltas) {
                assistantContent += delta;
                const idx = localMessages.value.findIndex(
                    (m) => m.id === assistantMsgId,
                );
                if (idx !== -1) {
                    localMessages.value[idx].content = assistantContent;
                    scrollToBottom();
                }
            }
        }
    } catch (error) {
        const idx = localMessages.value.findIndex(
            (m) => m.id === assistantMsgId,
        );
        if (idx !== -1) {
            localMessages.value[idx].content = t('agent.connection_error');
        }
    } finally {
        isStreaming.value = false;
        scrollToBottom();

        // Reload to sync database conversation list and message history
        router.reload({
            onSuccess: () => {
                if (!props.conversationId && conversations.value.length > 0) {
                    const latestChat = conversations.value[0];
                    router.visit(`/agent?conversation=${latestChat.id}`, {
                        replace: true,
                        preserveScroll: true,
                    });
                }
            },
        });
    }
}

// Conversation Deletion — handled from the main sidebar

onMounted(() => {
    scrollToBottom();
});
</script>

<template>
    <AppLayout :title="t('nav.agent')" :full-bleed="true">
        <div
            class="flex flex-1 min-h-0 w-full overflow-hidden border-t border-outline-glass bg-surface-container/60 backdrop-blur-xl"
        >
            <!-- Chat workspace -->
            <section
                class="flex flex-1 flex-col min-h-0 bg-surface-container-lowest/20"
            >
                <!-- Chat messages scroll container -->
                <div
                    ref="chatScrollContainer"
                    class="flex-1 overflow-y-auto px-4 py-6 md:px-8 custom-scrollbar"
                >
                    <!-- Empty state -->
                    <div
                        v-if="localMessages.length === 0"
                        class="flex h-full flex-col items-center justify-center text-center max-w-lg mx-auto"
                    >
                        <div
                            class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-[0_4px_20px_rgba(var(--primary-rgb),0.15)] animate-pulse"
                        >
                            <Bot :size="32" />
                        </div>
                        <h1
                            class="font-heading text-lg font-bold text-on-surface mb-2"
                        >
                            {{ t('agent.welcome_title') }}
                        </h1>
                        <p
                            class="text-xs text-on-surface-variant mb-8 max-w-sm leading-relaxed"
                        >
                            {{ t('agent.welcome_desc') }}
                        </p>

                        <!-- Suggestion Cards -->
                        <div
                            class="grid w-full gap-3 grid-cols-1 sm:grid-cols-2"
                        >
                            <button
                                v-for="suggestion in suggestions"
                                :key="suggestion"
                                @click="useSuggestion(suggestion)"
                                class="flex items-start gap-2.5 rounded-xl border border-outline-glass bg-surface-container-low/40 p-3 text-left text-xs text-on-surface hover:bg-surface-container hover:border-primary/30 transition-all duration-200"
                            >
                                <Sparkles
                                    :size="14"
                                    class="text-primary shrink-0 mt-0.5"
                                />
                                <span>{{ suggestion }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Messages list -->
                    <div v-else class="space-y-6 max-w-3xl mx-auto">
                        <div
                            v-for="msg in localMessages"
                            :key="msg.id"
                            class="flex w-full"
                            :class="[
                                msg.role === 'user'
                                    ? 'justify-end'
                                    : 'justify-start',
                            ]"
                        >
                            <div class="flex items-start gap-3 max-w-[85%]">
                                <!-- Bot avatar for assistant -->
                                <div
                                    v-if="msg.role === 'assistant'"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-outline-glass bg-surface-container-low text-primary"
                                >
                                    <Bot :size="16" />
                                </div>

                                <div
                                    class="rounded-2xl px-4 py-3 text-xs leading-relaxed shadow-sm transition-all"
                                    :class="[
                                        msg.role === 'user'
                                            ? 'bg-gradient-to-r from-primary to-primary/80 text-white font-medium rounded-tr-none'
                                            : 'bg-surface-container-low/60 border border-outline-glass text-on-surface-variant rounded-tl-none',
                                    ]"
                                >
                                    <!-- Message body -->
                                    <div
                                        class="max-w-none whitespace-pre-wrap break-words text-inherit leading-relaxed"
                                    >
                                        {{ msg.content }}
                                    </div>

                                    <!-- Streaming typing indicator inside the last empty message -->
                                    <div
                                        v-if="
                                            msg.role === 'assistant' &&
                                            !msg.content &&
                                            isStreaming
                                        "
                                        class="flex items-center gap-1.5 py-1"
                                    >
                                        <Loader2
                                            class="h-3.5 w-3.5 animate-spin text-primary"
                                        />
                                        <span
                                            class="text-[10px] text-on-surface-variant italic"
                                            >{{ t('agent.thinking') }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="props.proposals.length > 0"
                        class="mt-6 max-w-3xl mx-auto space-y-3"
                    >
                        <article
                            v-for="proposal in props.proposals"
                            :key="proposal.id"
                            class="rounded-xl border border-outline-glass bg-surface-container-low/70 p-4 shadow-sm"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-primary"
                                    >
                                        <Sparkles :size="13" />
                                        <span>{{
                                            t('agent.proposal_title')
                                        }}</span>
                                        <span
                                            class="rounded-full bg-surface-container px-2 py-0.5 text-[9px] text-on-surface-variant"
                                        >
                                            {{
                                                proposalStatusLabel(
                                                    proposal.status,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <h2
                                        class="text-sm font-semibold text-on-surface"
                                    >
                                        {{ proposal.summary }}
                                    </h2>
                                </div>

                                <div
                                    v-if="proposal.status === 'pending'"
                                    class="flex shrink-0 items-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-outline-glass px-3 text-[11px] font-semibold text-on-surface-variant transition hover:bg-surface-container disabled:opacity-50"
                                        :disabled="
                                            isStreaming ||
                                            proposalActionId !== null
                                        "
                                        @click="rejectProposal(proposal)"
                                    >
                                        <X :size="13" />
                                        {{ t('agent.proposal_reject') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-lg bg-primary px-3 text-[11px] font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50"
                                        :disabled="
                                            isStreaming ||
                                            proposalActionId !== null
                                        "
                                        @click="applyProposal(proposal)"
                                    >
                                        <Loader2
                                            v-if="
                                                proposalActionId === proposal.id
                                            "
                                            class="h-3.5 w-3.5 animate-spin"
                                        />
                                        <Check v-else :size="13" />
                                        {{ t('agent.proposal_apply') }}
                                    </button>
                                </div>
                            </div>

                            <ul class="mt-3 space-y-1.5">
                                <li
                                    v-for="action in proposal.actions"
                                    :key="`${proposal.id}-${action.label}`"
                                    class="rounded-lg bg-surface-container-lowest/60 px-3 py-2 text-[11px] text-on-surface-variant"
                                >
                                    {{ action.label }}
                                </li>
                            </ul>

                            <p
                                v-if="proposalConflictCount(proposal) > 0"
                                class="mt-3 text-[11px] font-medium text-amber-700"
                            >
                                {{
                                    t('agent.proposal_conflicts', {
                                        count: proposalConflictCount(proposal),
                                    })
                                }}
                            </p>
                        </article>
                    </div>
                </div>

                <!-- Input fixed bar at bottom -->
                <div
                    class="border-t border-outline-glass bg-surface-container-lowest/30 p-4 md:px-8"
                >
                    <form
                        @submit.prevent="sendMessage"
                        class="flex items-end gap-3 max-w-3xl mx-auto"
                    >
                        <textarea
                            ref="promptTextarea"
                            v-model="promptInput"
                            :placeholder="t('agent.input_placeholder')"
                            rows="1"
                            class="max-h-32 min-h-9 flex-1 resize-none overflow-y-auto rounded-xl border border-outline-glass bg-surface-container-low/60 px-4 py-2.5 text-xs leading-4 text-on-surface placeholder-on-surface-variant/50 focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/30 transition-all"
                            :disabled="isStreaming"
                            @input="resizePromptTextarea"
                            @keydown="handlePromptKeydown"
                        ></textarea>
                        <button
                            type="submit"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-md transition-all hover:bg-primary-hover disabled:opacity-50 disabled:hover:bg-primary disabled:cursor-not-allowed cursor-pointer"
                            :disabled="isStreaming || !promptInput.trim()"
                        >
                            <Send :size="14" />
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(var(--on-surface-rgb), 0.1);
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--on-surface-rgb), 0.2);
}
</style>
