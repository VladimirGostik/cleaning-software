<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';

const { t } = useI18n();
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-4">
            <h4 class="font-semibold text-sm">{{ t('tenant_add_section_basic') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TextInput field="name" :label="t('tenant_add_name')" required />
                <TextInput field="ico" :label="t('tenant_add_ico')" required />
                <TextInput field="dic" :label="t('client_dic')" />
                <TextInput field="vat_number" :label="t('client_vat_number')" />
                <div class="md:col-span-2">
                    <ToggleInput field="is_vat_payer" :label="t('client_is_vat_payer')" />
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <h4 class="font-semibold text-sm">{{ t('tenant_add_section_address') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TextInput field="address_line" :label="t('street')" />
                <TextInput field="city" :label="t('city')" />
                <TextInput field="postal_code" :label="t('postal_code')" />
                <TextInput field="country" :label="t('country')" required />
            </div>
        </div>

        <div class="space-y-4">
            <h4 class="font-semibold text-sm">{{ t('tenant_add_section_contact') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TextInput field="contact_email" type="email" :label="t('invoice_settings_contact_email')" />
                <TextInput field="contact_phone" type="tel" :label="t('invoice_settings_contact_phone')" />
            </div>
        </div>

        <div class="space-y-4">
            <h4 class="font-semibold text-sm">{{ t('invoice_settings_section_bank') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TextInput field="iban" :label="t('invoice_settings_iban')" placeholder="SK0000000000000000000000" />
                <TextInput
                    field="swift_bic"
                    :label="t('invoice_settings_swift_bic')"
                    :placeholder="t('invoice_settings_swift_bic_hint')"
                />
            </div>
        </div>
    </div>
</template>
