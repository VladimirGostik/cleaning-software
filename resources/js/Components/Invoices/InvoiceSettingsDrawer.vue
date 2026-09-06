<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import InvoiceSettingsForm from './InvoiceSettingsForm.vue';
import type { InvoiceSettingsDrawerStatus } from '@/Composables/useInvoiceSettingsDrawer';

defineProps<{
    open: boolean;
    status: InvoiceSettingsDrawerStatus;
    settings: App.Data.Invoices.InvoiceSettingsData | null;
}>();

const emit = defineEmits<{
    close: [];
    retry: [];
    saved: [];
}>();

const { t } = useI18n();
</script>

<template>
    <SideDrawer :open="open" :title="t('invoicing_settings')" @close="emit('close')">
        <div v-if="status === 'loading'" class="flex items-center justify-center gap-3 p-10">
            <span class="loading loading-spinner loading-md" />
            <span class="text-sm text-base-content/60">{{ t('invoice_settings_drawer_loading') }}</span>
        </div>

        <div v-else-if="status === 'error'" class="p-6">
            <div role="alert" class="alert alert-error">
                <span>{{ t('invoice_settings_drawer_error') }}</span>
            </div>
            <button type="button" class="btn btn-sm mt-4" @click="emit('retry')">
                {{ t('invoice_settings_drawer_retry') }}
            </button>
        </div>

        <InvoiceSettingsForm
            v-else-if="settings"
            class="p-6"
            :settings="settings"
            compact
            @saved="emit('saved')"
            @cancel="emit('close')"
        />
    </SideDrawer>
</template>
