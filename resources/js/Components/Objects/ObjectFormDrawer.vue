<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import ObjectForm from './ObjectForm.vue';

const props = defineProps<{
    open: boolean;
    object?: App.Data.Objects.ObjectDetailData | null;
    clients: App.Data.Clients.ClientOptionData[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const title = computed(() => (props.object ? t('object_edit') : t('object_add')));
</script>

<template>
    <SideDrawer :open="open" :title="title" @close="emit('close')">
        <ObjectForm :object="object" :clients="clients" @saved="emit('close')" @cancel="emit('close')" />
    </SideDrawer>
</template>
