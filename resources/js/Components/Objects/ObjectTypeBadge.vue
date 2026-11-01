<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import BuildingOffice2Icon from '~icons/material-symbols/apartment-outline-rounded';
import BuildingOfficeIcon from '~icons/material-symbols/corporate-fare-outline-rounded';
import HomeIcon from '~icons/material-symbols/dashboard-outline-rounded';
import Squares2X2Icon from '~icons/material-symbols/grid-view-outline-rounded';
import { objectTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.ObjectTypeEnum;
}>();

const { t } = useI18n();

const ICON: Record<App.Enums.ObjectTypeEnum, object> = {
    office: BuildingOffice2Icon,
    apartment: BuildingOfficeIcon,
    house: HomeIcon,
    common_areas: Squares2X2Icon,
};

const icon = computed(() => ICON[props.type]);
</script>

<template>
    <StatusChip tone="neutral" :icon="icon" :label="t(objectTypeKey(props.type))" size="sm" />
</template>
