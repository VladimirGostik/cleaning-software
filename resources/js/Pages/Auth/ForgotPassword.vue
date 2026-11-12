<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import EnvelopeIcon from '~icons/material-symbols/mail-outline-rounded';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import AuthShell from '@/Components/Auth/AuthShell.vue';
import AuthTextField from '@/Components/Auth/AuthTextField.vue';
import AuthSubmitButton from '@/Components/Auth/AuthSubmitButton.vue';

const { t } = useI18n();

defineProps<{
    status?: string | null;
}>();

const form = useForm('post', '/forgot-password', {
    email: '',
});

function submit() {
    form.submit();
}
</script>

<template>
    <AuthShell :title="t('forgot_password')" :subtitle="t('forgot_password_description')">
        <div v-if="status" class="alert alert-success text-sm mb-4" role="status">
            {{ status }}
        </div>
        <FormProvider :form="form">
            <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <AuthTextField
                    field="email"
                    :label="t('email')"
                    type="email"
                    autocomplete="email"
                    required
                    :icon="EnvelopeIcon"
                />
                <AuthSubmitButton :processing="form.processing">{{ t('send_reset_link') }}</AuthSubmitButton>
            </form>
        </FormProvider>
        <template #after>
            <a
                href="/login"
                class="inline-flex min-h-11 items-center justify-center text-xs font-medium hover:underline auth-link"
            >
                {{ t('back_to_login') }}
            </a>
        </template>
    </AuthShell>
</template>
