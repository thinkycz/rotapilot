<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useSharedProps } from '@/composables/useSharedProps';

withDefaults(
    defineProps<{
        href?: string;
        class?: string;
    }>(),
    {
        href: '/',
        class: '',
    },
);

const { app } = useSharedProps();
</script>

<template>
    <Link
        :href="href"
        :class="[
            'flex items-center gap-3 font-medium select-none',
            $props.class,
        ]"
    >
        <div
            class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-container font-heading text-lg font-bold text-on-primary-container shadow-[0_2px_8px_rgba(0,131,120,0.15)]"
        >
            L
        </div>
        <div class="text-left">
            <h1
                class="mb-0.5 font-heading text-sm font-bold tracking-tight text-primary leading-none"
            >
                {{ app.name }}
            </h1>
            <p
                class="font-mono text-[9px] font-semibold tracking-wider text-on-surface-variant uppercase opacity-85 leading-none"
            >
                Workspace
            </p>
        </div>
    </Link>
</template>
