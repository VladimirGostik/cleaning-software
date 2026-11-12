<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/utils/bytes';
import { formatDate } from '@/utils/date';

export type SettingsImage = Pick<App.Data.Tenants.TenantSignatureData, 'url' | 'file_name' | 'size' | 'uploaded_at'>;

withDefaults(
    defineProps<{
        image: SettingsImage;
        alt: string;
        muted?: boolean;
    }>(),
    { muted: false },
);

const { t } = useI18n();
</script>

<template>
    <div class="flex items-center gap-3 rounded-card-nested bg-base-200 p-3">
        <img
            :key="image.uploaded_at"
            :src="`${image.url}?v=${encodeURIComponent(image.uploaded_at)}`"
            :alt="alt"
            class="max-h-24 max-w-56 object-contain"
            :class="{ 'opacity-40 grayscale': muted }"
        />
        <div class="min-w-0">
            <p class="truncate font-medium">{{ image.file_name }}</p>
            <p class="text-xs text-muted">
                {{ formatBytes(image.size) }} · {{ t('settings_image_uploaded_at') }}
                {{ formatDate(image.uploaded_at) }}
            </p>
        </div>
    </div>
</template>
