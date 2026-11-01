<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import { DOCUMENT_TOKEN_GROUPS, documentTokenGroupKey } from '@/utils/enums';
import { normalizeSearchText } from '@/utils/text';

type DocumentTokenDef = App.Data.DocumentTemplates.DocumentTokenData;

const props = defineProps<{
    tokens: readonly DocumentTokenDef[];
}>();

const emit = defineEmits<{
    pick: [token: DocumentTokenDef];
}>();

const { t } = useI18n();

const query = ref('');

const needle = computed(() => normalizeSearchText(query.value));

const groups = computed(() => {
    const matching = props.tokens.filter(
        (token) => needle.value === '' || normalizeSearchText(`${token.label} ${token.key}`).includes(needle.value),
    );

    return DOCUMENT_TOKEN_GROUPS.map((group) => ({
        group,
        title: t(documentTokenGroupKey(group)),
        tokens: matching.filter((token) => token.group === group),
    })).filter((entry) => entry.tokens.length > 0);
});
</script>

<template>
    <div class="space-y-3">
        <TextInput
            v-model="query"
            type="search"
            :label="t('document_picker_search')"
            :placeholder="t('document_picker_search')"
            autocomplete="off"
            @keydown.enter.prevent
        />

        <p class="text-xs text-base-content/60">{{ t('document_picker_hint') }}</p>

        <p v-if="groups.length === 0" class="text-sm text-base-content/60">
            {{ t('document_picker_no_match') }}
        </p>

        <section v-for="entry in groups" :key="entry.group">
            <h3 class="mb-1 text-sm font-semibold">{{ entry.title }}</h3>
            <ul class="space-y-0.5">
                <li v-for="token in entry.tokens" :key="token.key">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm h-auto w-full flex-col items-start justify-start gap-0 py-1 text-left font-normal"
                        :title="token.key"
                        @click="emit('pick', token)"
                    >
                        <span>{{ token.label }}</span>
                        <span class="font-mono text-xs text-base-content/60">{{ token.key }}</span>
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
