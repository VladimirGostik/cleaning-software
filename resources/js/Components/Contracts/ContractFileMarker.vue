<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AttachFileIcon from '~icons/material-symbols/attach-file-rounded';
import StatusChip from '@/Components/StatusChip.vue';

defineProps<{
    source: App.Enums.ContractSourceEnum;
    isSigned: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <span v-if="source === 'file'" class="inline-flex items-center gap-1.5 align-middle">
        <AttachFileIcon
            class="size-4 text-base-content/60"
            role="img"
            :aria-label="t('contract_from_file')"
            :title="t('contract_from_file')"
        />
        <StatusChip v-if="!isSigned" tone="warning" size="sm" :label="t('contract_unsigned_draft')" />
    </span>
</template>
