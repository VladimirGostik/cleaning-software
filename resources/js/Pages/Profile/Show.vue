<script setup lang="ts">
import { computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import PasswordInput from '@/Components/Forms/PasswordInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import type { Breadcrumb } from '@/types';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

const { t } = useI18n();
const page = usePage();

const user = page.props.auth.user;
const languages = page.props.languages;

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('profile') },
];

const languageOptions = computed<SelectOption[]>(() =>
    languages.map((l: { value: string; label: string }) => ({ value: l.value, label: l.label })),
);

const profileForm = useForm('put', '/profile', {
    name: user?.name ?? '',
    email: user?.email ?? '',
    locale: page.props.locale,
});

const passwordForm = useForm('put', '/profile/password', {
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitProfile() {
    profileForm.submit();
}

function submitPassword() {
    passwordForm.submit({
        onSuccess: () => {
            passwordForm.reset();
        },
    });
}
</script>

<template>
    <AppLayout>
        <Header
            :title="t('profile')"
            :breadcrumbs="breadcrumbs"
        />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Profile form -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-lg">
                        {{ t('update_profile') }}
                    </h2>

                    <FormProvider :form="profileForm">
                        <form
                            class="space-y-2"
                            novalidate
                            @submit.prevent="submitProfile"
                        >
                            <TextInput
                                field="name"
                                :label="t('name')"
                                autocomplete="name"
                            />

                            <TextInput
                                field="email"
                                :label="t('email')"
                                type="email"
                                autocomplete="email"
                            />

                            <SelectInput
                                field="locale"
                                :label="t('locale')"
                                :options="languageOptions"
                            />

                            <div class="flex justify-end mt-4">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    :disabled="profileForm.processing"
                                >
                                    <span
                                        v-if="profileForm.processing"
                                        class="loading loading-spinner loading-xs"
                                    />
                                    {{ t('save') }}
                                </button>
                            </div>
                        </form>
                    </FormProvider>
                </div>
            </div>

            <!-- Change password form -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-lg">
                        {{ t('change_password') }}
                    </h2>

                    <FormErrorsAlert :errors="passwordForm.errors" />

                    <FormProvider :form="passwordForm">
                        <form
                            class="space-y-2"
                            novalidate
                            @submit.prevent="submitPassword"
                        >
                            <PasswordInput
                                field="current_password"
                                :label="t('current_password')"
                                autocomplete="current-password"
                            />

                            <PasswordInput
                                field="password"
                                :label="t('new_password')"
                                autocomplete="new-password"
                            />

                            <PasswordInput
                                field="password_confirmation"
                                :label="t('confirm_password')"
                                autocomplete="new-password"
                            />

                            <div class="flex justify-end mt-4">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    :disabled="passwordForm.processing"
                                >
                                    <span
                                        v-if="passwordForm.processing"
                                        class="loading loading-spinner loading-xs"
                                    />
                                    {{ t('change_password') }}
                                </button>
                            </div>
                        </form>
                    </FormProvider>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
