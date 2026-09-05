<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import PasswordInput from '@/Components/Forms/PasswordInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import CheckboxGroup from '@/Components/Forms/CheckboxGroup.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import type { Breadcrumb } from '@/types';
import type { CheckboxOption } from '@/Components/Forms/CheckboxGroup.vue';

const { t } = useI18n();

const props = defineProps<{
    user?: App.Data.UserListItemData;
    roles: App.Data.RoleListItemData[];
}>();

const isEditing = computed(() => !!props.user);

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('users'), url: '/users' },
    { label: isEditing.value ? t('edit') : t('create') },
];

const roleOptions = computed<CheckboxOption[]>(() =>
    props.roles.map((r) => ({ value: r.name, label: r.name })),
);

const form = useForm(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/users/${props.user!.id}` : '/users',
    {
        name: props.user?.name ?? '',
        email: props.user?.email ?? '',
        password: '',
        password_confirmation: '',
        is_active: props.user?.is_active ?? true,
        roles: [...(props.user?.roles ?? [])] as string[],
    },
);

function submit() {
    form.submit();
}
</script>

<template>
    <AppLayout>
        <Header
            :title="
                isEditing
                    ? `${t('edit')} ${t('users').toLowerCase()}`
                    : `${t('create')} ${t('users').toLowerCase()}`
            "
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <a
                    href="/users"
                    class="btn btn-ghost btn-sm"
                >{{ t('cancel') }}</a>
            </template>
        </Header>

        <FormProvider :form="form">
            <form
                novalidate
                @submit.prevent="submit"
            >
                <div class="card bg-base-100 shadow-sm mb-6">
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <TextInput
                                field="name"
                                :label="t('name')"
                                autocomplete="name"
                                required
                            />

                            <TextInput
                                field="email"
                                :label="t('email')"
                                type="email"
                                autocomplete="email"
                                required
                            />

                            <template v-if="!isEditing">
                                <PasswordInput
                                    field="password"
                                    :label="t('password')"
                                    autocomplete="new-password"
                                    required
                                />

                                <PasswordInput
                                    field="password_confirmation"
                                    :label="t('confirm_password')"
                                    autocomplete="new-password"
                                />
                            </template>

                            <div class="md:col-span-2">
                                <ToggleInput
                                    field="is_active"
                                    :label="t('is_active')"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm mb-6">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('assign_roles') }}</h2>
                        <CheckboxGroup
                            field="roles"
                            :options="roleOptions"
                        />
                    </div>
                </div>

                <FormActions
                    cancel-href="/users"
                    :cancel-label="t('cancel')"
                    :submit-label="t('save')"
                    :processing="form.processing"
                />
            </form>
        </FormProvider>
    </AppLayout>
</template>
