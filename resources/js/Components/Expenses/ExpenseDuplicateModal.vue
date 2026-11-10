<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    open: boolean;
    messages: string[];
    processing: boolean;
}>();

const emit = defineEmits<{
    keepBoth: [];
    replace: [];
    cancel: [];
}>();

const { t } = useI18n();
const firstAction = ref<HTMLElement | null>(null);

watch(
    () => props.open,
    async (open) => {
        if (!open) return;
        await nextTick();
        firstAction.value?.focus();
    },
);
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': open }" aria-labelledby="expense-duplicate-title">
        <div class="modal-box" @keydown.esc.prevent="emit('cancel')">
            <h3 id="expense-duplicate-title" class="text-lg font-bold">{{ t('expense_payslip_duplicate_title') }}</h3>
            <p class="pt-4 text-base-content/70">{{ t('expense_payslip_duplicate_intro') }}</p>
            <ul class="list-disc space-y-1 py-3 ps-5 text-sm">
                <li v-for="message in messages" :key="message">{{ message }}</li>
            </ul>
            <p class="text-sm text-base-content/70">{{ t('expense_payslip_duplicate_question') }}</p>
            <div class="modal-action flex-col sm:flex-row sm:justify-end">
                <button type="button" class="btn btn-ghost" :disabled="processing" @click="emit('cancel')">
                    {{ t('cancel') }}
                </button>
                <button type="button" class="btn btn-error" :disabled="processing" @click="emit('replace')">
                    {{ t('expense_payslip_duplicate_replace') }}
                </button>
                <button
                    ref="firstAction"
                    type="button"
                    class="btn btn-primary"
                    :disabled="processing"
                    @click="emit('keepBoth')"
                >
                    {{ t('expense_payslip_duplicate_keep') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('cancel')">close</button>
        </form>
    </dialog>
</template>
