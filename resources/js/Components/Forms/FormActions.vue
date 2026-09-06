<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    cancelHref: string;
    cancelLabel?: string;
    submitLabel?: string;
    processing?: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex justify-end gap-2">
        <a :href="cancelHref" class="btn btn-ghost">{{ cancelLabel ?? t('cancel') }}</a>
        <slot name="extra" />
        <button type="submit" class="btn btn-primary" :disabled="processing">
            <span v-if="processing" class="loading loading-spinner loading-xs" />
            {{ submitLabel ?? t('save') }}
        </button>
    </div>
</template>
