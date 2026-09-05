<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import PasswordInput from '@/Components/Forms/PasswordInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';

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
    <div class="min-h-screen flex items-center justify-center bg-base-200 p-4">
        <div class="card bg-base-100 shadow-xl w-full max-w-md">
            <div class="card-body">
                <h1 class="text-2xl font-bold text-center mb-6">
                    {{ t('reset_password') }}
                </h1>

                <FormProvider :form="form">
                    <form
                        class="space-y-2"
                        novalidate
                        @submit.prevent="submit"
                    >
                        <TextInput
                            field="email"
                            :label="t('email')"
                            type="email"
                            autocomplete="email"
                        />

                        <PasswordInput
                            field="password"
                            :label="t('new_password')"
                            autocomplete="new-password"
                            required
                        />

                        <PasswordInput
                            field="password_confirmation"
                            :label="t('confirm_password')"
                            autocomplete="new-password"
                        />

                        <div class="flex justify-end mt-6">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="form.processing"
                            >
                                <span
                                    v-if="form.processing"
                                    class="loading loading-spinner loading-xs"
                                />
                                {{ t('reset_password') }}
                            </button>
                        </div>
                    </form>
                </FormProvider>
            </div>
        </div>
    </div>
</template>
