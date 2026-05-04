<script setup lang="ts">
    import { computed } from 'vue';
    import { BuildingOffice2Icon, UserIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        type: App.Enums.ClientType;
    }>();

    const { t } = useTranslate();

    const isCorporate = computed(() => props.type === 'corporate');
</script>

<template>
    <span :class="['badge', isCorporate ? 'badge-primary' : 'badge-secondary']">
        <BuildingOffice2Icon v-if="isCorporate" class="w-3 h-3 mr-1" />
        <UserIcon v-else class="w-3 h-3 mr-1" />
        {{ t(isCorporate ? 'client_type.corporate' : 'client_type.private') }}
    </span>
</template>
