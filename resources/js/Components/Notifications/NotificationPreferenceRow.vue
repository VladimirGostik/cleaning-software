<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        item: App.Data.Notifications.NotificationPreferenceItemData;
        modelValue: boolean;
    }>();

    const emit = defineEmits<{
        'update:modelValue': [value: boolean];
    }>();

    const { t } = useTranslate();

    function onChange(event: Event): void {
        emit('update:modelValue', (event.target as HTMLInputElement).checked);
    }
</script>

<template>
    <div class="flex items-start justify-between gap-4 py-3">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-base-content">{{ item.label }}</p>
            <p class="text-xs text-base-content/60 mt-0.5">
                {{ t('notification_settings.desc.' + item.type) }}
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 pt-0.5">
            <span class="text-xs text-base-content/50">{{ t('notification_settings.mail_label') }}</span>
            <input
                type="checkbox"
                :checked="modelValue"
                :disabled="!item.configurable"
                class="toggle toggle-primary toggle-sm"
                :class="{ 'opacity-50 cursor-not-allowed': !item.configurable }"
                @change="onChange"
            />
        </div>
    </div>
</template>
