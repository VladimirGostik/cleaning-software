<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <TextInput field="customer_name" :label="t('quote_customer_name')" required />
        </div>
        <div class="md:col-span-2">
            <TextInput field="customer_represented_by" :label="t('quote_customer_represented_by')" />
            <p class="mt-1 text-xs text-base-content/60">{{ t('quote_customer_represented_by_hint') }}</p>
        </div>
        <TextInput field="customer_street" :label="t('street')" />
        <TextInput field="customer_city" :label="t('city')" />
        <TextInput field="customer_postal_code" :label="t('postal_code')" maxlength="16" />
        <TextInput field="customer_email" type="email" :label="t('email')" />
        <TextInput field="customer_ico" :label="t('client_ico')" maxlength="20" />
        <TextInput field="customer_dic" :label="t('client_dic')" maxlength="20" />
    </div>
</template>
