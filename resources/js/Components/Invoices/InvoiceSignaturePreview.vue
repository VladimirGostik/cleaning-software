<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/utils/bytes';
import { formatDate } from '@/utils/date';

withDefaults(
    defineProps<{
        signature: App.Data.Tenants.TenantSignatureData;
        muted?: boolean;
    }>(),
    { muted: false },
);

const { t } = useI18n();
</script>

<template>
    <div class="flex items-center gap-3 rounded-box border border-base-300 bg-base-200 p-3">
        <img
            :key="signature.uploaded_at"
            :src="`${signature.url}?v=${encodeURIComponent(signature.uploaded_at)}`"
            :alt="t('invoice_settings_signature_preview_alt')"
            class="max-h-24 max-w-56 object-contain"
            :class="{ 'opacity-40 grayscale': muted }"
        />
        <div class="min-w-0">
            <p class="truncate font-medium">{{ signature.file_name }}</p>
            <p class="text-xs text-base-content/60">
                {{ formatBytes(signature.size) }} · {{ t('invoice_settings_signature_uploaded_at') }}
                {{ formatDate(signature.uploaded_at) }}
            </p>
        </div>
    </div>
</template>
