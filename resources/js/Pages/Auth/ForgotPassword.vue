<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';

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
    <div class="min-h-screen flex items-center justify-center bg-base-200 p-4">
        <div class="card bg-base-100 shadow-xl w-full max-w-md">
            <div class="card-body">
                <h1 class="text-2xl font-bold text-center mb-2">
                    {{ t('forgot_password') }}
                </h1>
                <p class="text-sm text-base-content/60 text-center mb-6">
                    {{ t('forgot_password_description') }}
                </p>

                <div
                    v-if="status"
                    class="alert alert-success mb-4"
                >
                    {{ status }}
                </div>

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

                        <div class="flex items-center justify-between mt-6">
                            <a
                                href="/login"
                                class="link link-primary text-sm"
                            >
                                {{ t('back_to_login') }}
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
                                {{ t('send_reset_link') }}
                            </button>
                        </div>
                    </form>
                </FormProvider>
            </div>
        </div>
    </div>
</template>
