<script setup lang="ts">
import { ref, onMounted, watch, nextTick, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bot, Send, Sparkles, Loader2, Check, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useSharedProps } from '@/composables/useSharedProps';
import { renderMarkdown, renderPlainText } from '@/lib/markdown';
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
const streamStatus = ref<'idle' | 'connecting' | 'working' | 'answering'>(
    'idle',
);
const proposalActionId = ref<number | null>(null);
const chatScrollContainer = ref<HTMLDivElement | null>(null);
const promptTextarea = ref<HTMLTextAreaElement | null>(null);
const streamIdleTimeoutMs = 75000;

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

function renderedMessageContent(message: MessagePayload): string {
    return message.role === 'assistant'
        ? renderMarkdown(message.content)
        : renderPlainText(message.content);
}

const streamingStatusLabel = computed(() => {
    if (streamStatus.value === 'connecting') {
        return t('agent.stream_connecting');
    }

    if (streamStatus.value === 'working') {
        return t('agent.stream_working');
    }

    if (streamStatus.value === 'answering') {
        return t('agent.stream_answering');
    }

    return t('agent.thinking');
});

// Group proposals by the assistant message that created them so the
// conversation can render each proposal inline as a "tool result" bubble
// attached to the matching assistant turn. Proposals without a message id
// (legacy rows, or proposals created by a code path that has not yet been
// linked) fall into `orphanProposals` and render after the message list.
const proposalsByMessageId = computed<Map<string, AgentProposal[]>>(() => {
    const map = new Map<string, AgentProposal[]>();
    const orphans: AgentProposal[] = [];

    for (const proposal of props.proposals) {
        if (proposal.message_id !== null && proposal.message_id !== '') {
            const bucket = map.get(proposal.message_id) ?? [];
            bucket.push(proposal);
            map.set(proposal.message_id, bucket);
        } else {
            orphans.push(proposal);
        }
    }

    if (orphans.length > 0) {
        map.set('__orphan__', orphans);
    }

    return map;
});

function proposalsForMessage(messageId: string): AgentProposal[] {
    return proposalsByMessageId.value.get(messageId) ?? [];
}

function orphanProposals(): AgentProposal[] {
    return proposalsByMessageId.value.get('__orphan__') ?? [];
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
    streamStatus.value = 'connecting';

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

    const abortController = new AbortController();
    let streamTimedOut = false;
    let idleTimer: number | null = null;
    const resetIdleTimer = (): void => {
        if (idleTimer !== null) {
            window.clearTimeout(idleTimer);
        }

        idleTimer = window.setTimeout(() => {
            streamTimedOut = true;
            abortController.abort();
        }, streamIdleTimeoutMs);
    };

    try {
        resetIdleTimer();

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
            signal: abortController.signal,
        });

        if (!response.ok) {
            throw new Error('Streaming failed');
        }

        streamStatus.value = 'working';
        resetIdleTimer();

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

            resetIdleTimer();
            const chunk = decoder.decode(value, { stream: true });
            const parsed = parseTextDeltaSseChunk(chunk, sseBuffer);
            sseBuffer = parsed.buffer;
            streamDone = parsed.done;

            if (parsed.eventTypes.length > 0 && assistantContent === '') {
                streamStatus.value = 'working';
            }

            for (const delta of parsed.deltas) {
                streamStatus.value = 'answering';
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

        if (idleTimer !== null) {
            window.clearTimeout(idleTimer);
        }
    } catch (error) {
        const idx = localMessages.value.findIndex(
            (m) => m.id === assistantMsgId,
        );
        if (idx !== -1) {
            localMessages.value[idx].content = streamTimedOut
                ? t('agent.stream_timeout')
                : t('agent.connection_error');
        }
    } finally {
        if (idleTimer !== null) {
            window.clearTimeout(idleTimer);
        }

        isStreaming.value = false;
        streamStatus.value = 'idle';
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
                    <!-- Empty state: only shown when no conversation is selected -->
                    <div
                        v-if="
                            localMessages.length === 0 && !props.conversationId
                        "
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

                    <!-- Messages list: shown when there are messages OR a conversation is selected -->
                    <div v-else class="space-y-6 max-w-3xl mx-auto">
                        <template v-for="msg in localMessages" :key="msg.id">
                            <div
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

                                    <!-- Text bubble (hidden when assistant has no text but has a tool result attached) -->
                                    <div
                                        v-if="
                                            msg.role === 'user' ||
                                            msg.content ||
                                            (msg.role === 'assistant' &&
                                                proposalsForMessage(msg.id)
                                                    .length === 0)
                                        "
                                        class="rounded-2xl px-4 py-3 text-xs leading-relaxed shadow-sm transition-all"
                                        :class="[
                                            msg.role === 'user'
                                                ? 'bg-gradient-to-r from-primary to-primary/80 text-white font-medium rounded-tr-none'
                                                : 'bg-surface-container-low/60 border border-outline-glass text-on-surface-variant rounded-tl-none',
                                        ]"
                                    >
                                        <!-- Message body -->
                                        <div
                                            class="max-w-none break-words text-inherit leading-relaxed"
                                            :class="[
                                                msg.role === 'assistant'
                                                    ? 'markdown-message'
                                                    : 'whitespace-pre-wrap',
                                            ]"
                                            v-html="renderedMessageContent(msg)"
                                        ></div>

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
                                                >{{
                                                    streamingStatusLabel
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tool-result bubbles (proposals) attached to this assistant turn -->
                            <div
                                v-if="
                                    msg.role === 'assistant' &&
                                    proposalsForMessage(msg.id).length > 0
                                "
                                class="flex w-full justify-start"
                            >
                                <div class="flex items-start gap-3 max-w-[85%]">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-outline-glass bg-surface-container-low text-primary"
                                    >
                                        <Bot :size="16" />
                                    </div>

                                    <div
                                        class="flex min-w-0 flex-1 flex-col gap-3"
                                    >
                                        <article
                                            v-for="proposal in proposalsForMessage(
                                                msg.id,
                                            )"
                                            :key="proposal.id"
                                            class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-4 py-3 text-xs leading-relaxed shadow-sm transition-all"
                                        >
                                            <div
                                                class="mb-2 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-primary"
                                            >
                                                <Sparkles :size="13" />
                                                <span>{{
                                                    t('agent.proposal_title')
                                                }}</span>
                                                <span
                                                    class="rounded-full bg-surface-container px-2 py-0.5 text-[9px] font-semibold normal-case tracking-normal text-on-surface-variant"
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
                                                v-if="
                                                    proposalConflictCount(
                                                        proposal,
                                                    ) > 0
                                                "
                                                class="mt-3 text-[11px] font-medium text-amber-700"
                                            >
                                                {{
                                                    t(
                                                        'agent.proposal_conflicts',
                                                        {
                                                            count: proposalConflictCount(
                                                                proposal,
                                                            ),
                                                        },
                                                    )
                                                }}
                                            </p>

                                            <div
                                                v-if="
                                                    proposal.status ===
                                                        'failed' &&
                                                    proposal.result?.error
                                                "
                                                class="mt-3 rounded-lg border border-red-200 bg-red-50/50 px-3 py-2 text-[11px] text-red-800"
                                            >
                                                {{ proposal.result.error }}
                                            </div>

                                            <div
                                                v-if="
                                                    proposal.status ===
                                                    'pending'
                                                "
                                                class="mt-3 flex shrink-0 items-center gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-outline-glass bg-surface-container-lowest/40 px-3 text-[11px] font-semibold text-on-surface-variant transition hover:bg-surface-container disabled:opacity-50"
                                                    :disabled="
                                                        isStreaming ||
                                                        proposalActionId !==
                                                            null
                                                    "
                                                    @click="
                                                        rejectProposal(proposal)
                                                    "
                                                >
                                                    <X :size="13" />
                                                    {{
                                                        t(
                                                            'agent.proposal_reject',
                                                        )
                                                    }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 items-center gap-1 rounded-lg bg-primary px-3 text-[11px] font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50"
                                                    :disabled="
                                                        isStreaming ||
                                                        proposalActionId !==
                                                            null
                                                    "
                                                    @click="
                                                        applyProposal(proposal)
                                                    "
                                                >
                                                    <Loader2
                                                        v-if="
                                                            proposalActionId ===
                                                            proposal.id
                                                        "
                                                        class="h-3.5 w-3.5 animate-spin"
                                                    />
                                                    <Check v-else :size="13" />
                                                    {{
                                                        t(
                                                            'agent.proposal_apply',
                                                        )
                                                    }}
                                                </button>
                                            </div>
                                        </article>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Orphan proposals (no message_id): render after the last turn -->
                        <div
                            v-if="orphanProposals().length > 0"
                            class="flex w-full justify-start"
                        >
                            <div class="flex items-start gap-3 max-w-[85%]">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-outline-glass bg-surface-container-low text-primary"
                                >
                                    <Bot :size="16" />
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col gap-3">
                                    <article
                                        v-for="proposal in orphanProposals()"
                                        :key="proposal.id"
                                        class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-4 py-3 text-xs leading-relaxed shadow-sm transition-all"
                                    >
                                        <div
                                            class="mb-2 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-primary"
                                        >
                                            <Sparkles :size="13" />
                                            <span>{{
                                                t('agent.proposal_title')
                                            }}</span>
                                            <span
                                                class="rounded-full bg-surface-container px-2 py-0.5 text-[9px] font-semibold normal-case tracking-normal text-on-surface-variant"
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
                                            v-if="
                                                proposalConflictCount(
                                                    proposal,
                                                ) > 0
                                            "
                                            class="mt-3 text-[11px] font-medium text-amber-700"
                                        >
                                            {{
                                                t('agent.proposal_conflicts', {
                                                    count: proposalConflictCount(
                                                        proposal,
                                                    ),
                                                })
                                            }}
                                        </p>

                                        <div
                                            v-if="
                                                proposal.status === 'failed' &&
                                                proposal.result?.error
                                            "
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
                                                :disabled="
                                                    isStreaming ||
                                                    proposalActionId !== null
                                                "
                                                @click="
                                                    rejectProposal(proposal)
                                                "
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
                                                        proposalActionId ===
                                                        proposal.id
                                                    "
                                                    class="h-3.5 w-3.5 animate-spin"
                                                />
                                                <Check v-else :size="13" />
                                                {{ t('agent.proposal_apply') }}
                                            </button>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
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

.markdown-message :deep(p + p),
.markdown-message :deep(p + ul),
.markdown-message :deep(p + ol),
.markdown-message :deep(p + pre),
.markdown-message :deep(p + .markdown-table-wrap),
.markdown-message :deep(ul + p),
.markdown-message :deep(ol + p),
.markdown-message :deep(pre + p),
.markdown-message :deep(.markdown-table-wrap + p) {
    margin-top: 0.75rem;
}

.markdown-message :deep(strong) {
    color: rgb(var(--on-surface-rgb));
    font-weight: 700;
}

.markdown-message :deep(em) {
    font-style: italic;
}

.markdown-message :deep(ul),
.markdown-message :deep(ol) {
    margin-left: 1rem;
    margin-top: 0.5rem;
}

.markdown-message :deep(ul) {
    list-style: disc;
}

.markdown-message :deep(ol) {
    list-style: decimal;
}

.markdown-message :deep(li + li) {
    margin-top: 0.25rem;
}

.markdown-message :deep(code) {
    border-radius: 0.375rem;
    background: rgba(var(--on-surface-rgb), 0.08);
    padding: 0.1rem 0.3rem;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        'Liberation Mono', 'Courier New', monospace;
    font-size: 0.72rem;
}

.markdown-message :deep(pre) {
    max-width: 100%;
    overflow-x: auto;
    border-radius: 0.75rem;
    border: 1px solid rgba(var(--on-surface-rgb), 0.08);
    background: rgba(var(--on-surface-rgb), 0.06);
    padding: 0.75rem;
}

.markdown-message :deep(pre code) {
    display: block;
    min-width: max-content;
    background: transparent;
    padding: 0;
    white-space: pre;
}

.markdown-message :deep(a) {
    color: rgb(var(--primary-rgb));
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.markdown-message :deep(.markdown-table-wrap) {
    max-width: 100%;
    overflow-x: auto;
}

.markdown-message :deep(table) {
    min-width: 100%;
    border-collapse: collapse;
    font-size: 0.72rem;
}

.markdown-message :deep(th),
.markdown-message :deep(td) {
    border: 1px solid rgba(var(--on-surface-rgb), 0.1);
    padding: 0.4rem 0.5rem;
    text-align: left;
}

.markdown-message :deep(th) {
    background: rgba(var(--on-surface-rgb), 0.06);
    color: rgb(var(--on-surface-rgb));
    font-weight: 700;
}
</style>
