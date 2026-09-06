<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-outline badge-warning" :title="t('quote_rough_hint')">
        {{ t('quote_rough') }}
    </span>
</template>
