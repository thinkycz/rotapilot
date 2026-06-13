<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    CalendarRange,
    Calendar,
    CalendarCheck2,
    Coffee,
    UserRound,
    Settings as SettingsIcon,
    LogOut,
    Bot,
    Trash2,
    MessageSquare,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

defineProps<{
    title: string;
    fullBleed?: boolean;
}>();

const { auth, conversations } = useSharedProps();
const { t } = useI18n();
const { confirm } = useConfirmDialog();

useBoundLocale();

const activeUrl = computed(() => usePage().url);

const isEmployee = computed(() => auth.value.user?.role === 'employee');

const navItems = computed(() => {
    if (isEmployee.value) {
        return [
            { href: '/dashboard', key: 'dashboard', icon: Activity },
            { href: '/my-calendar', key: 'my_calendar', icon: Calendar },
            {
                href: '/my-availabilities',
                key: 'my_availabilities',
                icon: CalendarCheck2,
            },
        ];
    }
    return [
        { href: '/dashboard', key: 'dashboard', icon: Activity },
        { href: '/stores/index', key: 'stores', icon: Coffee },
        { href: '/employees/index', key: 'employees', icon: UserRound },
        { href: '/schedules/index', key: 'schedules', icon: CalendarRange },
        { href: '/availability', key: 'availability', icon: CalendarCheck2 },
        { href: '/agent', key: 'agent', icon: Bot },
    ];
});

const settingsActive = computed(() => activeUrl.value.startsWith('/settings'));

const isActive = (href: string): boolean => activeUrl.value.startsWith(href);

const userInitials = computed(() => {
    const email = auth.value.user?.email ?? '';
    if (!email) return 'DU';
    return email.substring(0, 2).toUpperCase();
});

async function deleteConversation(id: string): Promise<void> {
    const ok = await confirm(t('agent.confirm_delete'), {
        title: t('agent.delete_title'),
        confirmLabel: t('common.delete'),
        cancelLabel: t('common.cancel'),
        variant: 'danger',
    });

    if (ok) {
        router.post(
            '/agent/conversations/destroy',
            {
                conversation_id: id,
            },
            {
                preserveScroll: true,
            },
        );
    }
}

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <Head :title="title" />

    <div
        class="flex min-h-screen flex-col bg-surface-bg font-sans antialiased md:flex-row"
    >
        <!-- Desktop Persistent Sidebar -->
        <aside
            class="sticky top-0 z-20 hidden h-screen w-64 flex-col border-r border-outline-glass bg-surface-container px-4 py-6 text-left md:flex"
        >
            <!-- Brand App Header -->
            <div
                class="mb-8 flex cursor-default items-center gap-3 px-2 transition-all select-none"
            >
                <Brand href="/dashboard" />
            </div>

            <!-- Nav Links -->
            <nav class="space-y-1.5">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="[
                        'flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold transition-all',
                        isActive(item.href)
                            ? 'border-r-2 border-primary bg-surface-container-low font-bold text-primary shadow-[inset_0_1px_0_rgba(255,255,255,0.3)]'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                >
                    <component :is="item.icon" :size="16" />
                    {{ t(`nav.${item.key}`) }}
                </Link>
            </nav>

            <!-- Conversations Section (Manager Only) -->
            <div
                v-if="!isEmployee"
                class="mt-6 flex flex-1 flex-col min-h-0 border-t border-outline-glass pt-4"
            >
                <div class="mb-2 px-2">
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant opacity-75"
                    >
                        {{ t('nav.agent') }}
                    </span>
                </div>

                <div class="flex-1 overflow-y-auto pr-1 custom-scrollbar">
                    <TransitionGroup
                        name="conversation-list"
                        tag="div"
                        class="relative space-y-1"
                    >
                        <div
                            v-for="chat in conversations"
                            :key="chat.id"
                            class="group relative flex items-center justify-between rounded-xl px-3 py-2 text-xs font-medium transition-all hover:bg-surface-container-low"
                            :class="[
                                activeUrl.includes(`conversation=${chat.id}`)
                                    ? 'bg-surface-container-low font-bold text-primary border-r-2 border-primary shadow-[inset_0_1px_0_rgba(255,255,255,0.3)]'
                                    : 'text-on-surface-variant hover:text-on-surface',
                            ]"
                        >
                            <Link
                                :href="`/agent?conversation=${chat.id}`"
                                class="flex flex-1 items-center gap-2 truncate pr-6"
                            >
                                <MessageSquare :size="12" class="shrink-0" />
                                <span class="truncate">{{ chat.title }}</span>
                            </Link>

                            <button
                                @click.stop.prevent="
                                    deleteConversation(chat.id)
                                "
                                class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer rounded-md p-1 text-on-surface-variant/50 hover:bg-rose-50/50 hover:text-error-red opacity-0 group-hover:opacity-100 transition-all duration-200"
                                :title="t('agent.delete_tooltip')"
                                :aria-label="t('agent.delete_tooltip')"
                            >
                                <Trash2 :size="12" />
                            </button>
                        </div>
                    </TransitionGroup>
                    <div
                        v-if="conversations.length === 0"
                        class="px-3 py-2 text-[11px] text-on-surface-variant/60 italic"
                    >
                        {{ t('agent.no_conversations') }}
                    </div>
                </div>
            </div>

            <!-- Spacer if employee (to push footer down) -->
            <div v-else class="flex-1"></div>

            <!-- Footer: User Identity + Quick Actions -->
            <div
                class="flex items-center justify-between gap-2 border-t border-outline-glass pt-4 px-2"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-outline-glass bg-surface-container-low font-heading text-xs font-bold text-primary"
                    >
                        {{ userInitials }}
                    </div>
                    <div class="min-w-0 overflow-hidden">
                        <p
                            class="truncate text-xs font-semibold text-on-surface"
                        >
                            {{
                                auth.user
                                    ? auth.user.email.split('@')[0]
                                    : 'User'
                            }}
                        </p>
                        <p
                            class="truncate text-[9px] text-on-surface-variant opacity-85 font-medium"
                        >
                            {{ auth.user ? auth.user.email : '' }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        v-if="auth.user"
                        href="/settings"
                        :class="[
                            'rounded-lg p-1.5 transition-colors',
                            settingsActive
                                ? 'bg-surface-container-lowest text-primary'
                                : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary',
                        ]"
                        :title="t('nav.settings')"
                        :aria-label="t('nav.settings')"
                    >
                        <SettingsIcon :size="14" />
                    </Link>
                    <button
                        @click="logout"
                        class="cursor-pointer rounded-lg p-1.5 text-on-surface-variant transition-all hover:bg-rose-50/50 hover:text-error-red"
                        :title="t('nav.logout')"
                        :aria-label="t('nav.logout')"
                    >
                        <LogOut :size="14" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Mobile Top Navigation Header -->
        <header
            class="glass-panel sticky top-0 z-30 flex h-15 w-full items-center justify-between border-b border-outline-glass px-4 shadow-sm md:hidden"
        >
            <div class="flex items-center gap-2">
                <Brand href="/dashboard" />
            </div>

            <div class="flex items-center gap-1.5">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="[
                        'rounded-lg p-2 transition-all',
                        isActive(item.href)
                            ? 'font-bold text-primary bg-surface-container-low'
                            : 'text-on-surface-variant',
                    ]"
                    :title="t(`nav.${item.key}`)"
                >
                    <component :is="item.icon" :size="16" />
                </Link>
                <Link
                    v-if="auth.user"
                    href="/settings"
                    :class="[
                        'rounded-lg p-2 transition-all',
                        settingsActive
                            ? 'bg-surface-container-lowest text-primary'
                            : 'text-on-surface-variant',
                    ]"
                    :title="t('nav.settings')"
                    :aria-label="t('nav.settings')"
                >
                    <SettingsIcon :size="16" />
                </Link>
                <button
                    @click="logout"
                    class="rounded-lg p-2 text-on-surface-variant transition-all hover:text-error-red"
                >
                    <LogOut :size="16" />
                </button>
            </div>
        </header>

        <!-- Main Workspace -->
        <main
            :class="[
                'flex flex-1 flex-col overflow-x-hidden',
                fullBleed ? 'h-screen min-h-0' : 'min-h-screen',
            ]"
        >
            <!-- Full-bleed mode: no padding, no max-width (e.g. AI agent page) -->
            <template v-if="fullBleed">
                <FlashAlerts display="toast" />
                <div class="flex flex-1 flex-col min-h-0">
                    <slot />
                </div>
            </template>

            <!-- Default padded layout -->
            <template v-else>
                <div class="relative flex flex-1 flex-col p-4 md:p-8">
                    <!-- Ambient Decorator -->
                    <div
                        class="pointer-events-none absolute top-1/2 left-1/2 h-[70vw] w-[70vw] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-[100px]"
                    ></div>

                    <div
                        class="z-10 flex flex-1 flex-col max-w-6xl w-full mx-auto"
                    >
                        <FlashAlerts display="toast" />

                        <div class="flex-1">
                            <slot />
                        </div>
                    </div>
                </div>
            </template>
        </main>
        <ConfirmDialog />
    </div>
</template>

<style scoped>
.conversation-list-move,
.conversation-list-enter-active,
.conversation-list-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.conversation-list-enter-from,
.conversation-list-leave-to {
    opacity: 0;
    transform: translateX(-0.5rem);
}

.conversation-list-leave-active {
    position: absolute;
    width: calc(100% - 0.25rem);
}
</style>
