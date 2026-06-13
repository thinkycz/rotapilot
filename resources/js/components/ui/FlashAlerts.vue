<script setup lang="ts">
import { computed } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import { useSharedProps } from '@/composables/useSharedProps';

const props = withDefaults(
    defineProps<{
        successKey?: string;
        errorKey?: string;
        display?: 'inline' | 'toast';
    }>(),
    {
        successKey: 'success',
        errorKey: 'error',
        display: 'inline',
    },
);

const { flash } = useSharedProps();

const successMessage = computed<string | null>(() => {
    const value = flash.value[props.successKey as keyof typeof flash.value];
    return typeof value === 'string' ? value : null;
});

const errorMessage = computed<string | null>(() => {
    const value = flash.value[props.errorKey as keyof typeof flash.value];
    return typeof value === 'string' ? value : null;
});
</script>

<template>
    <TransitionGroup
        v-if="display === 'toast'"
        name="flash-toast"
        tag="div"
        class="pointer-events-none fixed top-4 right-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2"
    >
        <Alert
            v-if="successMessage"
            key="success"
            variant="success"
            class="pointer-events-auto shadow-lg shadow-emerald-950/10"
        >
            {{ successMessage }}
        </Alert>
        <Alert
            v-if="errorMessage"
            key="error"
            variant="error"
            class="pointer-events-auto shadow-lg shadow-rose-950/10"
        >
            {{ errorMessage }}
        </Alert>
    </TransitionGroup>

    <div v-else>
        <TransitionGroup name="flash-inline" tag="div">
            <div v-if="successMessage" key="success" class="mb-4">
                <Alert variant="success">
                    {{ successMessage }}
                </Alert>
            </div>
            <div v-if="errorMessage" key="error" class="mb-4">
                <Alert variant="error">
                    {{ errorMessage }}
                </Alert>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.flash-toast-enter-active,
.flash-toast-leave-active,
.flash-inline-enter-active,
.flash-inline-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.flash-toast-enter-from,
.flash-toast-leave-to {
    opacity: 0;
    transform: translateY(-0.5rem);
}

.flash-inline-enter-from,
.flash-inline-leave-to {
    opacity: 0;
    transform: translateY(-0.25rem);
}
</style>
