<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    cancelHref?: string;
    cancelLabel?: string;
    submitLabel?: string;
    processing?: boolean;
}>();

const emit = defineEmits<{
    cancel: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex justify-end gap-2">
        <a v-if="cancelHref" :href="cancelHref" class="btn btn-ghost">{{ cancelLabel ?? t('cancel') }}</a>
        <button v-else type="button" class="btn btn-ghost" @click="emit('cancel')">
            {{ cancelLabel ?? t('cancel') }}
        </button>
        <slot name="extra" />
        <button type="submit" class="btn btn-primary" :disabled="processing">
            <span v-if="processing" class="loading loading-spinner loading-xs" />
            {{ submitLabel ?? t('save') }}
        </button>
    </div>
</template>
