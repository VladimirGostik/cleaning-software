<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        title: string;
        description: string;
        confirmLabel?: string;
        confirmVariant?: 'error' | 'warning' | 'success' | 'primary';
    }>(),
    { confirmVariant: 'error' },
);

const emit = defineEmits<{
    cancel: [];
    confirm: [];
}>();

const { t } = useI18n();

const CONFIRM_VARIANT_CLASS: Record<'error' | 'warning' | 'success' | 'primary', string> = {
    error: 'btn-error',
    warning: 'btn-warning',
    success: 'btn-success',
    primary: 'btn-primary',
};

const confirmButtonClass = computed(() => CONFIRM_VARIANT_CLASS[props.confirmVariant]);
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': isOpen }">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ title }}</h3>
            <p class="py-4 text-base-content/70">{{ description }}</p>
            <div class="modal-action">
                <button type="button" class="btn btn-ghost" @click="emit('cancel')">
                    {{ t('cancel') }}
                </button>
                <button type="button" class="btn" :class="confirmButtonClass" @click="emit('confirm')">
                    {{ confirmLabel ?? t('delete') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('cancel')">close</button>
        </form>
    </dialog>
</template>
