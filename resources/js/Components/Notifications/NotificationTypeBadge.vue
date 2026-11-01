<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { notificationTypeKey } from '@/utils/enums';
import { NOTIFICATION_TYPE_STYLE } from './notificationTypeStyle';

const props = defineProps<{
    type: App.Enums.NotificationTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip
        :tone="NOTIFICATION_TYPE_STYLE[props.type].tone"
        :label="t(notificationTypeKey(props.type))"
        size="sm"
    />
</template>
