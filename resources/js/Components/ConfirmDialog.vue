<script setup lang="ts">
    withDefaults(
        defineProps<{
            open: boolean;
            title: string;
            body: string;
            confirmLabel: string;
            cancelLabel?: string;
            confirmVariant?: 'error' | 'warning' | 'primary';
        }>(),
        { cancelLabel: undefined, confirmVariant: undefined },
    );

    const emit = defineEmits<{
        (e: 'confirm'): void;
        (e: 'cancel'): void;
    }>();
</script>

<template>
    <dialog class="modal" :open="open">
        <div class="modal-box">
            <h3 class="font-bold text-lg">{{ title }}</h3>
            <p class="py-4">{{ body }}</p>
            <div class="modal-action">
                <button v-if="cancelLabel" type="button" class="btn btn-ghost" @click="emit('cancel')">
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    :class="['btn', `btn-${confirmVariant ?? 'error'}`]"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
        <div class="modal-backdrop" @click="emit('cancel')" />
    </dialog>
</template>
