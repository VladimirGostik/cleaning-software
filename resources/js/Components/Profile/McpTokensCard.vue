<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { formatDatetime } from '@/utils/date';

defineProps<{
    tokens: App.Data.Profile.McpTokenData[];
    url: string;
}>();

const { t } = useI18n();
const page = usePage();

const form = useForm('post', '/profile/mcp-tokens', { name: '' });

const plainToken = ref<string | null>(null);
const copied = ref(false);

const flashed = computed(() => (page.flash as { mcpToken?: App.Data.Profile.McpTokenCreatedData }).mcpToken ?? null);

watch(
    flashed,
    (value) => {
        if (value) {
            plainToken.value = value.plain_text_token;
            copied.value = false;
        }
    },
    { immediate: true },
);

function dismiss(): void {
    plainToken.value = null;
    copied.value = false;
}

onBeforeUnmount(dismiss);

async function copy(): Promise<void> {
    const clipboard = window.navigator.clipboard;
    if (!plainToken.value || !clipboard) return;
    await clipboard.writeText(plainToken.value);
    copied.value = true;
}

function submit(): void {
    form.submit({ preserveScroll: true, onSuccess: () => form.reset() });
}

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Profile.McpTokenData>({
        resolveUrl: (token) => `/profile/mcp-tokens/${token.id}`,
        getTitle: () => t('mcp_token_revoke'),
        getDescription: (token) => t('mcp_token_revoke_confirm', { name: token.name }),
    });
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-lg">{{ t('mcp_tokens_title') }}</h2>
            <p class="text-sm text-base-content/70">{{ t('mcp_tokens_intro') }}</p>

            <div v-if="plainToken" class="space-y-2 rounded-box border border-warning bg-warning/10 p-4" role="alert">
                <p class="text-sm font-medium">{{ t('mcp_token_once_warning') }}</p>
                <code class="block break-all rounded bg-base-100 p-2 text-xs">{{ plainToken }}</code>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" @click="copy">
                        {{ copied ? t('mcp_token_copied') : t('mcp_token_copy') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" @click="dismiss">
                        {{ t('mcp_token_dismiss') }}
                    </button>
                </div>
            </div>

            <FormProvider :form="form">
                <form class="flex flex-col gap-2 sm:flex-row sm:items-end" novalidate @submit.prevent="submit">
                    <div class="flex-1">
                        <TextInput field="name" :label="t('mcp_token_name')" />
                    </div>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing || form.name === ''">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                        {{ t('mcp_token_create') }}
                    </button>
                </form>
            </FormProvider>

            <p v-if="tokens.length === 0" class="text-sm text-base-content/60">{{ t('mcp_tokens_empty') }}</p>
            <ul v-else class="divide-y divide-base-300">
                <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ token.name }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ t('mcp_token_last_used') }}:
                            {{ token.last_used_at ? formatDatetime(token.last_used_at) : t('mcp_token_never_used') }}
                        </p>
                        <p v-if="token.expires_at" class="text-xs text-base-content/60">
                            {{ t('mcp_token_expires') }}: {{ formatDatetime(token.expires_at) }}
                        </p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs text-error" @click="openModal(token)">
                        {{ t('mcp_token_revoke') }}
                    </button>
                </li>
            </ul>

            <div class="space-y-1 border-t border-base-300 pt-3 text-xs text-base-content/70">
                <p>
                    {{ t('mcp_help_url') }}:
                    <code class="break-all">{{ url }}</code>
                </p>
                <p>{{ t('mcp_help_local') }}</p>
                <p>{{ t('mcp_help_web_connectors') }}</p>
            </div>
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        :confirm-label="t('mcp_token_revoke')"
        @cancel="closeModal"
        @confirm="confirmDelete"
    />
</template>
