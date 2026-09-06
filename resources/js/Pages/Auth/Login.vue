<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { EnvelopeIcon } from '@heroicons/vue/24/outline';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import AuthShell from '@/Components/Auth/AuthShell.vue';
import AuthTextField from '@/Components/Auth/AuthTextField.vue';
import AuthPasswordField from '@/Components/Auth/AuthPasswordField.vue';
import AuthCheckboxField from '@/Components/Auth/AuthCheckboxField.vue';
import AuthSubmitButton from '@/Components/Auth/AuthSubmitButton.vue';

const { t } = useI18n();

const { canResetPassword } = defineProps<{ canResetPassword: boolean }>();

const form = useForm('post', '/login', {
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.submit({
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <AuthShell :title="t('login')" :subtitle="t('auth_welcome_back')">
        <FormProvider :form="form">
            <form class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                <AuthTextField
                    field="email"
                    :label="t('email')"
                    type="email"
                    autocomplete="email"
                    required
                    :icon="EnvelopeIcon"
                />
                <AuthPasswordField field="password" :label="t('password')" autocomplete="current-password" required />

                <div class="flex items-center justify-between">
                    <AuthCheckboxField field="remember" :label="t('remember_me')" />
                    <a
                        v-if="canResetPassword"
                        href="/forgot-password"
                        class="text-[13px] font-medium hover:underline auth-link"
                    >
                        {{ t('forgot_password') }}
                    </a>
                </div>

                <AuthSubmitButton :processing="form.processing">{{ t('login') }}</AuthSubmitButton>
            </form>
        </FormProvider>
    </AuthShell>
</template>
