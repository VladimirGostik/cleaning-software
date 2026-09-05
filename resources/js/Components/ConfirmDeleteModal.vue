<script setup lang="ts">


defineProps<{
    isOpen: boolean;
    title: string;
    description: string;
}>();

const emit = defineEmits<{
    cancel: [];
    confirm: [];
}>();
</script>

<template>
    <dialog
        class="modal"
        :class="{ 'modal-open': isOpen }"
    >
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ title }}</h3>
            <p class="py-4 text-base-content/70">{{ description }}</p>
            <div class="modal-action">
                <button
                    type="button"
                    class="btn btn-ghost"
                    @click="emit('cancel')"
                >
                    {{ $t('cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-error"
                    @click="emit('confirm')"
                >
                    {{ $t('delete') }}
                </button>
            </div>
        </div>
        <form
            method="dialog"
            class="modal-backdrop"
        >
            <button @click="emit('cancel')">close</button>
        </form>
    </dialog>
</template>
