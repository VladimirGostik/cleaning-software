<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ArrowLeftIcon from '~icons/material-symbols/arrow-back-outline-rounded';
import AuthShell from '@/Components/Auth/AuthShell.vue';
import InvitationAcceptForm from '@/Components/Invitations/InvitationAcceptForm.vue';
import InvitationBlockedNotice from '@/Components/Invitations/InvitationBlockedNotice.vue';

const props = defineProps<{ invitation: App.Data.Invitations.InvitationAcceptPageData }>();

const { t } = useI18n();

const isFormState = computed(() => props.invitation.state === 'existing_user' || props.invitation.state === 'new_user');

const subtitle = computed(() => {
    if (props.invitation.state === 'existing_user') return t('invitation_existing_user_hint');
    if (props.invitation.state === 'new_user') return t('invitation_new_user_hint');
    return t('invitation_accept_subtitle');
});

const blockedState = computed<'expired' | 'wrong_user'>(() =>
    props.invitation.state === 'expired' ? 'expired' : 'wrong_user',
);
</script>

<template>
    <Head :title="t('invitation_accept_title')" />

    <AuthShell :title="t('invitation_accept_title')" :subtitle="subtitle">
        <InvitationAcceptForm v-if="isFormState" :invitation="invitation" />
        <InvitationBlockedNotice v-else :state="blockedState" :invited-email="invitation.invited_email" />

        <template v-if="!isFormState" #after>
            <a href="/login" class="inline-flex items-center gap-1.5 text-sm font-medium hover:underline auth-link">
                <ArrowLeftIcon />
                {{ t('invitation_back_to_login') }}
            </a>
        </template>
    </AuthShell>
</template>
