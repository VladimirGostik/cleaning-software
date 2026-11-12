<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';

defineProps<{
    emailField: 'email' | 'contact_email';
    emailIsLogin: boolean;
    emailLocked: boolean;
    namesRequired: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <TextInput
                :field="emailField"
                type="email"
                autocomplete="off"
                :required="emailIsLogin && !emailLocked"
                :disabled="emailLocked"
                :label="emailIsLogin ? t('employee_login_email') : t('employee_contact_email')"
            />
            <p v-if="emailIsLogin && !emailLocked" class="text-xs text-muted">
                {{ t('employee_email_hint') }}
            </p>
        </div>

        <TextInput field="first_name" maxlength="100" :required="namesRequired" :label="t('employee_first_name')" />
        <TextInput field="last_name" maxlength="100" :required="namesRequired" :label="t('employee_last_name')" />
        <TextInput field="phone" type="tel" maxlength="50" :label="t('phone')" />
        <TextInput field="position" maxlength="100" :label="t('position')" />
    </div>
</template>
