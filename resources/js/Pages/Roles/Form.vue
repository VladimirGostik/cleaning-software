<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import PermissionManager from '@/Components/PermissionManager.vue';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    role?: App.Data.RoleDetailData;
    permissions: App.Data.PermissionGroupData[];
}>();

const isEditing = computed(() => !!props.role);

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('roles'), url: '/roles' },
    { label: isEditing.value ? t('edit') : t('create') },
];

const form = useForm(isEditing.value ? 'put' : 'post', isEditing.value ? `/roles/${props.role!.id}` : '/roles', {
    name: props.role?.name ?? '',
    permissions: (props.role?.permissions ?? []) as string[],
});

function updatePermissions(value: string[]) {
    form.permissions = value;
    form.validate('permissions');
}

function submit() {
    form.submit();
}
</script>

<template>
    <AppLayout>
        <Header
            :title="
                isEditing ? `${t('edit')} ${t('roles').toLowerCase()}` : `${t('create')} ${t('roles').toLowerCase()}`
            "
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <a href="/roles" class="btn btn-ghost btn-sm">{{ t('cancel') }}</a>
            </template>
        </Header>

        <FormProvider :form="form">
            <form novalidate @submit.prevent="submit">
                <div class="card bg-base-100 shadow-sm mb-6">
                    <div class="card-body">
                        <div v-if="isEditing && role?.is_system" class="alert alert-warning mb-4">
                            <span>{{ t('system_role_warning') }}</span>
                        </div>

                        <TextInput
                            field="name"
                            :label="t('name')"
                            required
                            :disabled="isEditing && role?.is_system"
                            class="md:max-w-sm"
                        />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm mb-6">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('permissions') }}</h2>
                        <PermissionManager
                            :groups="permissions"
                            :model-value="form.permissions"
                            @update:model-value="updatePermissions"
                        />
                    </div>
                </div>

                <FormActions
                    cancel-href="/roles"
                    :cancel-label="t('cancel')"
                    :submit-label="t('save')"
                    :processing="form.processing"
                />
            </form>
        </FormProvider>
    </AppLayout>
</template>
