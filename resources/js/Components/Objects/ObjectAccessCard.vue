<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { KeyIcon } from '@heroicons/vue/24/outline';

defineProps<{
    object: App.Data.Objects.ObjectDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card border border-warning/40 bg-base-100 shadow-sm">
        <div class="card-body">
            <div class="flex items-center gap-2">
                <KeyIcon class="size-5 text-warning" />
                <h2 class="card-title text-base">{{ t('object_access') }}</h2>
                <span class="badge badge-warning badge-sm">{{ t('sensitive') }}</span>
            </div>

            <p class="text-xs text-base-content/60">{{ t('object_access_sensitive_hint') }}</p>

            <dl class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('object_access_code') }}</dt>
                    <dd class="font-mono">{{ object.access_code ?? t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('object_key_box_code') }}</dt>
                    <dd class="font-mono">{{ object.key_box_code ?? t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('object_key_count') }}</dt>
                    <dd>{{ object.key_count !== null ? object.key_count : t('empty_dash') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
