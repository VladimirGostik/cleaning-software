<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import PlaceholderTokenList from '@/Components/Contracts/PlaceholderTokenList.vue';

withDefaults(
    defineProps<{
        tokens: readonly App.Data.Contracts.PlaceholderTokenData[];
        subjectError?: string | null;
        bodyError?: string | null;
    }>(),
    { subjectError: undefined, bodyError: undefined },
);

const subject = defineModel<string | null>('subject', { required: true });
const body = defineModel<string | null>('body', { required: true });

const { t } = useI18n();

const bodyRef = ref<InstanceType<typeof TextareaInput> | null>(null);
const subjectEl = ref<HTMLInputElement | null>(null);
const lastFocused = ref<'subject' | 'body'>('body');

function onSubjectFocus(event: Event): void {
    subjectEl.value = event.target as HTMLInputElement;
    lastFocused.value = 'subject';
}

function insertIntoSubject(text: string): void {
    const el = subjectEl.value;
    const current = subject.value ?? '';
    const start = el?.selectionStart ?? current.length;
    const end = el?.selectionEnd ?? start;

    subject.value = current.slice(0, start) + text + current.slice(end);

    if (!el) return;
    const pos = start + text.length;
    void nextTick(() => {
        el.focus();
        el.setSelectionRange(pos, pos);
    });
}

function onInsert(token: string): void {
    const text = `{{${token}}}`;

    if (lastFocused.value === 'subject') {
        insertIntoSubject(text);
    } else {
        bodyRef.value?.insertAtCursor(text);
    }
}
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('invoice_mail_card_title') }}</h2>
            <p class="text-sm text-base-content/60">{{ t('invoice_mail_card_hint') }}</p>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_260px]">
                <div class="space-y-4">
                    <TextInput
                        :model-value="subject ?? ''"
                        :label="t('invoice_mail_subject_label')"
                        :error="subjectError"
                        @focus="onSubjectFocus"
                        @update:model-value="subject = $event || null"
                    />

                    <TextareaInput
                        ref="bodyRef"
                        :model-value="body ?? ''"
                        :label="t('invoice_mail_body_label')"
                        :rows="6"
                        :error="bodyError"
                        @focusin="lastFocused = 'body'"
                        @update:model-value="body = $event || null"
                    />
                </div>

                <PlaceholderTokenList class="self-start lg:sticky lg:top-4" :tokens="tokens" @insert="onInsert" />
            </div>
        </div>
    </div>
</template>
