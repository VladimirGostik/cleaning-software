<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { EnvelopeIcon } from '@heroicons/vue/24/outline';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import AuthShell from '@/Components/Auth/AuthShell.vue';
import AuthTextField from '@/Components/Auth/AuthTextField.vue';
import AuthPasswordField from '@/Components/Auth/AuthPasswordField.vue';
import AuthSubmitButton from '@/Components/Auth/AuthSubmitButton.vue';

const { t } = useI18n();

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm('post', '/reset-password', {
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.submit({
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
}
</script>

<template>
    <AuthShell :title="t('reset_password')">
        <FormProvider :form="form">
            <form class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                <AuthTextField
                    field="email"
                    :label="t('email')"
                    type="email"
                    autocomplete="email"
                    :icon="EnvelopeIcon"
                />
                <AuthPasswordField field="password" :label="t('new_password')" autocomplete="new-password" required />
                <AuthPasswordField
                    field="password_confirmation"
                    :label="t('confirm_password')"
                    autocomplete="new-password"
                    required
                />
                <AuthSubmitButton :processing="form.processing">{{ t('reset_password') }}</AuthSubmitButton>
            </form>
        </FormProvider>
    </AuthShell>
</template>
