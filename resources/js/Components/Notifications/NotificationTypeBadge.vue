<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { notificationTypeKey } from '@/utils/enums';
import { NOTIFICATION_TYPE_STYLE } from './notificationTypeStyle';

const props = defineProps<{
    type: App.Enums.NotificationTypeEnum;
}>();

const { t } = useI18n();

const badgeClass = computed(() => NOTIFICATION_TYPE_STYLE[props.type].badge);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(notificationTypeKey(type)) }}</span>
</template>
