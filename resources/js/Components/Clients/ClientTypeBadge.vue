<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import BuildingOffice2Icon from '~icons/material-symbols/apartment-outline-rounded';
import UserIcon from '~icons/material-symbols/person-outline-rounded';
import { clientTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.ClientTypeEnum;
}>();

const { t } = useI18n();

const icon = computed(() => (props.type === 'corporate' ? BuildingOffice2Icon : UserIcon));
</script>

<template>
    <StatusChip tone="neutral" :icon="icon" :label="t(clientTypeKey(props.type))" size="sm" />
</template>
