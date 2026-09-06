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
        <TextInput field="customer_email" type="email" :label="t('email')" />
        <TextInput field="customer_street" :label="t('street')" />
        <TextInput field="customer_city" :label="t('city')" />
        <TextInput field="customer_postal_code" :label="t('postal_code')" maxlength="16" />
    </div>
</template>
