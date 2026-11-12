<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import KeyIcon from '~icons/material-symbols/key-outline-rounded';

defineProps<{
    object: App.Data.Objects.ObjectDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <div class="flex items-center gap-2">
                <KeyIcon class="text-warning" />
                <h2 class="card-title">{{ t('object_access') }}</h2>
                <span class="kg-chip kg-chip--warning kg-chip--sm">{{ t('sensitive') }}</span>
            </div>

            <p class="text-xs text-muted">{{ t('object_access_sensitive_hint') }}</p>

            <dl class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('object_access_code') }}</dt>
                    <dd class="tabular-nums">{{ object.access_code ?? t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('object_key_box_code') }}</dt>
                    <dd class="tabular-nums">{{ object.key_box_code ?? t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('object_key_count') }}</dt>
                    <dd>{{ object.key_count !== null ? object.key_count : t('empty_dash') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
