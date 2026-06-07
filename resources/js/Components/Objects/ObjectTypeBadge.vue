<script setup lang="ts">
    import { computed } from 'vue';
    import {
        BuildingOffice2Icon,
        BuildingOfficeIcon,
        HomeIcon,
        Squares2X2Icon,
    } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        type: App.Enums.ObjectTypeEnum;
    }>();

    const { t } = useTranslate();

    type BadgeConfig = {
        icon: unknown;
        color: string;
    };

    const config = computed<BadgeConfig>(() => {
        const map: Record<App.Enums.ObjectTypeEnum, BadgeConfig> = {
            office: { icon: BuildingOffice2Icon, color: 'badge-primary' },
            apartment: { icon: BuildingOfficeIcon, color: 'badge-secondary' },
            house: { icon: HomeIcon, color: 'badge-accent' },
            common_areas: { icon: Squares2X2Icon, color: 'badge-info' },
        };
        return map[props.type] ?? map['office'];
    });
</script>

<template>
    <span :class="['badge gap-1', config.color]">
        <component :is="config.icon" class="w-3 h-3" />
        {{ t('object_type.' + type) }}
    </span>
</template>
