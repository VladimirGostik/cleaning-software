<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('invoice_settings_section_supplier') }}</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <TextInput field="name" :label="t('name')" required />
                <TextInput field="ico" :label="t('client_ico')" />
                <TextInput field="dic" :label="t('client_dic')" />
                <TextInput field="vat_number" :label="t('client_vat_number')" />
                <div class="md:col-span-2">
                    <ToggleInput field="is_vat_payer" :label="t('client_is_vat_payer')" />
                </div>
                <TextInput field="address_line" :label="t('street')" />
                <TextInput field="city" :label="t('city')" />
                <TextInput field="postal_code" :label="t('postal_code')" />
                <TextInput field="country" :label="t('country')" required />
                <TextInput field="contact_email" type="email" :label="t('invoice_settings_contact_email')" />
                <TextInput field="contact_phone" type="tel" :label="t('invoice_settings_contact_phone')" />
            </div>

            <div class="alert alert-info text-sm">
                <span>{{ t('invoice_settings_supplier_hint') }}</span>
            </div>
        </div>
    </div>
</template>
