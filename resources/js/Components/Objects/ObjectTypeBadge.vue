<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { BuildingOffice2Icon, BuildingOfficeIcon, HomeIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import { objectTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.ObjectTypeEnum;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.ObjectTypeEnum, string> = {
    office: 'badge-primary',
    apartment: 'badge-secondary',
    house: 'badge-accent',
    common_areas: 'badge-info',
};

const ICON: Record<App.Enums.ObjectTypeEnum, object> = {
    office: BuildingOffice2Icon,
    apartment: BuildingOfficeIcon,
    house: HomeIcon,
    common_areas: Squares2X2Icon,
};

const badgeClass = computed(() => BADGE_CLASS[props.type]);
const icon = computed(() => ICON[props.type]);
</script>

<template>
    <span class="badge badge-sm gap-1" :class="badgeClass">
        <component :is="icon" class="size-3" />
        {{ t(objectTypeKey(type)) }}
    </span>
</template>
