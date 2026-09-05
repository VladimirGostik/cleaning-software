<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import PasswordInput from '@/Components/Forms/PasswordInput.vue';
import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';

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
    <div class="min-h-screen flex items-center justify-center bg-base-200 p-4">
        <div class="card bg-base-100 shadow-xl w-full max-w-md">
            <div class="card-body">
                <h1 class="text-2xl font-bold text-center mb-6">
                    {{ t('login') }}
                </h1>

                <FormProvider :form="form">
                    <form
                        novalidate
                        @submit.prevent="submit"
                    >
                        <TextInput
                            field="email"
                            :label="t('email')"
                            type="email"
                            autocomplete="email"
                            required
                        />

                        <PasswordInput
                            field="password"
                            :label="t('password')"
                            autocomplete="current-password"
                            required
                            class="mt-2"
                        />

                        <div class="mt-4">
                            <CheckboxInput
                                field="remember"
                                :label="t('remember_me')"
                            />
                        </div>

                        <div class="flex items-center justify-between mt-6">
                            <a
                                v-if="canResetPassword"
                                href="/forgot-password"
                                class="link link-primary text-sm"
                            >
                                {{ t('forgot_password') }}
                            </a>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="form.processing"
                            >
                                <span
                                    v-if="form.processing"
                                    class="loading loading-spinner loading-xs"
                                />
                                {{ t('login') }}
                            </button>
                        </div>
                    </form>
                </FormProvider>
            </div>
        </div>
    </div>
</template>
