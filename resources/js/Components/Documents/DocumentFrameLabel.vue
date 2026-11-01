<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    settingsUrl: string | null;
}>();

const { t } = useI18n();
</script>

<template>
    <Link
        v-if="settingsUrl"
        :href="settingsUrl"
        :title="t('document_frame_settings_link')"
        class="document-frame-label"
    >
        {{ t('document_frame_label') }}
    </Link>
    <span v-else class="document-frame-label">{{ t('document_frame_label') }}</span>
</template>
