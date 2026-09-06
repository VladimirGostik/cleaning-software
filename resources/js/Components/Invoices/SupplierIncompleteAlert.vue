<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Can from '@/Components/Can.vue';

const SUPPLIER_FIELD_LABEL_KEYS: Record<string, string> = {
    name: 'name',
    address_line: 'street',
    city: 'city',
    postal_code: 'postal_code',
    ico: 'client_ico',
    dic: 'client_dic',
    vat_number: 'client_vat_number',
};

const props = defineProps<{
    missingFields: string[];
}>();

const emit = defineEmits<{
    'open-settings': [];
}>();

const { t } = useI18n();

function fieldLabel(field: string): string {
    const key = SUPPLIER_FIELD_LABEL_KEYS[field];
    return key ? t(key) : field;
}
</script>

<template>
    <div v-if="props.missingFields.length > 0" role="alert" class="alert alert-warning mb-4 items-start">
        <div class="flex-1">
            <p class="font-semibold">{{ t('invoice_settings_incomplete_title') }}</p>
            <p class="text-sm">{{ t('invoice_supplier_incomplete') }}</p>
            <p class="mt-1 text-sm">
                <span class="font-medium">{{ t('invoice_settings_incomplete_missing_label') }}</span>
                {{ ' ' }}{{ props.missingFields.map(fieldLabel).join(', ') }}
            </p>
        </div>

        <Can permission="manage billing settings">
            <button type="button" class="btn btn-warning btn-sm" @click="emit('open-settings')">
                {{ t('invoice_settings_incomplete_cta') }}
            </button>
            <template #fallback>
                <p class="text-sm text-base-content/70">{{ t('invoice_settings_incomplete_no_permission') }}</p>
            </template>
        </Can>
    </div>
</template>
