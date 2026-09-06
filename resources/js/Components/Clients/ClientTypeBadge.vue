<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { BuildingOffice2Icon, UserIcon } from '@heroicons/vue/24/outline';
import { clientTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.ClientTypeEnum;
}>();

const { t } = useI18n();

const badgeClass = computed(() => (props.type === 'corporate' ? 'badge-primary' : 'badge-secondary'));
const icon = computed(() => (props.type === 'corporate' ? BuildingOffice2Icon : UserIcon));
</script>

<template>
    <span class="badge badge-sm gap-1" :class="badgeClass">
        <component :is="icon" class="size-3" />
        {{ t(clientTypeKey(type)) }}
    </span>
</template>
