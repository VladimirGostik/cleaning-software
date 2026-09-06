<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import ClientForm from './ClientForm.vue';

const props = defineProps<{
    open: boolean;
    client?: App.Data.Clients.ClientDetailData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const title = computed(() => (props.client ? t('client_edit') : t('client_add')));
</script>

<template>
    <SideDrawer :open="open" :title="title" @close="emit('close')">
        <ClientForm :client="client" @saved="emit('close')" @cancel="emit('close')" />
    </SideDrawer>
</template>
