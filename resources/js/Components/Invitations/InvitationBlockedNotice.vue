<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ state: 'expired' | 'wrong_user'; invitedEmail: string | null }>();

const { t } = useI18n();
const loggingOut = ref(false);

function logout() {
    router.post(
        '/logout',
        {},
        {
            onStart: () => (loggingOut.value = true),
            onFinish: () => (loggingOut.value = false),
        },
    );
}
</script>

<template>
    <div>
        <p v-if="props.state === 'expired'" class="text-sm text-slate-600">
            {{ t('invitation_expired') }}
        </p>
        <template v-else>
            <p class="text-sm text-slate-600">
                {{ t('invitation_wrong_user_block') }}
                <span v-if="props.invitedEmail" class="font-medium text-slate-900">{{ props.invitedEmail }}</span>
            </p>
            <button
                type="button"
                class="mt-4 flex w-full items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-70"
                :disabled="loggingOut"
                @click="logout"
            >
                {{ t('invitation_logout_to_accept') }}
            </button>
        </template>
    </div>
</template>
