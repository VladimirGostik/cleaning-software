<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    tokens: readonly App.Data.Contracts.PlaceholderTokenData[];
}>();

const emit = defineEmits<{
    insert: [token: string];
}>();

const { t } = useI18n();

const GROUP_ORDER = ['tenant', 'contract', 'client', 'object', 'employee', 'quote'] as const;

// BE ships PlaceholderTokenData.token already brace-wrapped (`{{tenant.name}}`) — strip once here,
// the bare key is what every downstream consumer (grouping, insert, display) needs.
function normalizeToken(token: string): string {
    return token.replace(/^\{\{\s*|\s*\}\}$/g, '');
}

interface DisplayToken {
    raw: string;
    label: string;
}

interface TokenGroup {
    group: string;
    items: DisplayToken[];
}

const groups = computed<TokenGroup[]>(() => {
    const byGroup = new Map<string, DisplayToken[]>();

    for (const token of props.tokens) {
        const raw = normalizeToken(token.token);
        const prefix = raw.split('.')[0];
        const group = (GROUP_ORDER as readonly string[]).includes(prefix) ? prefix : 'other';
        const items = byGroup.get(group) ?? [];
        items.push({ raw, label: token.label });
        byGroup.set(group, items);
    }

    const order = [...GROUP_ORDER, 'other'];

    return order.filter((group) => byGroup.has(group)).map((group) => ({ group, items: byGroup.get(group)! }));
});

function tokenDisplay(raw: string): string {
    return '{{' + raw + '}}';
}
</script>

<template>
    <div class="card bg-base-200/40">
        <div class="card-body space-y-3 p-4">
            <h3 class="text-sm font-semibold">{{ t('contract_tokens_title') }}</h3>
            <p class="text-xs text-base-content/60">{{ t('contract_tokens_hint') }}</p>

            <p v-if="tokens.length === 0" class="text-xs text-base-content/50">{{ t('contract_tokens_empty') }}</p>

            <div v-for="tokenGroup in groups" :key="tokenGroup.group" class="space-y-1">
                <h4 class="text-xs font-semibold uppercase text-base-content/50">
                    {{ t(`contract_token_group_${tokenGroup.group}`) }}
                </h4>
                <ul class="space-y-0.5">
                    <li v-for="item in tokenGroup.items" :key="item.raw">
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-base-300/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :aria-label="t('contract_token_insert_aria', { token: item.raw })"
                            @click="emit('insert', item.raw)"
                        >
                            <span class="w-full text-sm break-words whitespace-normal">{{ item.label }}</span>
                            <code class="text-xs opacity-60">{{ tokenDisplay(item.raw) }}</code>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
