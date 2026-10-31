<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import { notificationPreferenceDescKey } from '@/utils/enums';

defineProps<{
    item: App.Data.Notifications.NotificationPreferenceItemData;
    mail: boolean;
    push: boolean;
}>();

const emit = defineEmits<{
    'update:mail': [value: boolean];
    'update:push': [value: boolean];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex items-start justify-between gap-4 py-3">
        <div>
            <p class="text-sm font-medium">{{ item.label }}</p>
            <p class="text-xs text-base-content/60">{{ t(notificationPreferenceDescKey(item.type)) }}</p>
            <p v-if="!item.configurable" class="text-xs text-base-content/50 italic">
                {{ t('notification_settings_not_configurable') }}
            </p>
        </div>
        <div class="flex items-center gap-4">
            <ToggleInput
                :model-value="mail"
                :label="t('notification_settings_mail_label')"
                :disabled="!item.configurable"
                @update:model-value="emit('update:mail', $event)"
            />
            <ToggleInput
                v-if="item.configurable"
                :model-value="push"
                :label="t('notification_settings_push_label')"
                @update:model-value="emit('update:push', $event)"
            />
        </div>
    </div>
</template>
