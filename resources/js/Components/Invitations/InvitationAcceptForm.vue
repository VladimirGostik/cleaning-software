<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { UserIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import AuthTextField from '@/Components/Auth/AuthTextField.vue';
import AuthPasswordField from '@/Components/Auth/AuthPasswordField.vue';
import AuthSubmitButton from '@/Components/Auth/AuthSubmitButton.vue';

interface AcceptInvitationFormData {
    password: string;
    name: string | null;
}

const props = defineProps<{ invitation: App.Data.Invitations.InvitationAcceptPageData }>();

const { t } = useI18n();

const form = useForm<AcceptInvitationFormData>('post', `/invitations/${props.invitation.token}`, {
    password: '',
    name: props.invitation.state === 'new_user' ? '' : null,
});

function submit() {
    form.submit({ onFinish: () => form.reset('password') });
}
</script>

<template>
    <div>
        <p v-if="invitation.tenant_name" class="text-sm font-medium text-slate-700">
            {{ invitation.tenant_name }}
            <template v-if="invitation.role_name">
                · {{ t('invitation_join_as', { role: invitation.role_name }) }}
            </template>
        </p>

        <div class="mt-4 flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('invitation_email_label') }}</span>
            <p class="flex items-center gap-2 text-sm text-slate-500">
                <EnvelopeIcon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                {{ invitation.email }}
            </p>
        </div>

        <FormProvider :form="form">
            <form class="mt-4 flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                <AuthTextField
                    v-if="invitation.state === 'new_user'"
                    field="name"
                    :label="t('invitation_name_label')"
                    autocomplete="name"
                    required
                    :icon="UserIcon"
                />
                <AuthPasswordField
                    field="password"
                    :label="t('invitation_password_label')"
                    :autocomplete="invitation.state === 'new_user' ? 'new-password' : 'current-password'"
                    required
                />
                <AuthSubmitButton :processing="form.processing">{{ t('invitation_accept_cta') }}</AuthSubmitButton>
            </form>
        </FormProvider>
    </div>
</template>
