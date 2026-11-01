<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const showHeader = defineModel<boolean>('showHeader', { required: true });
const showFooter = defineModel<boolean>('showFooter', { required: true });

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input v-model="showHeader" type="checkbox" class="toggle toggle-primary toggle-sm" />
            {{ t('document_frame_toggle_header') }}
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input v-model="showFooter" type="checkbox" class="toggle toggle-primary toggle-sm" />
            {{ t('document_frame_toggle_footer') }}
        </label>
    </div>
</template>
