<script setup lang="ts">
import { ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import XMarkIcon from '~icons/material-symbols/close-outline-rounded';

import InvoicePreviewFrame from './InvoicePreviewFrame.vue';

const { t } = useI18n();

const titleId = useId();
const open = ref(false);
</script>

<template>
    <section class="card bg-base-100" :aria-labelledby="titleId">
        <div class="card-body space-y-3">
            <h2 :id="titleId" class="card-title">{{ t('invoice_settings_template_preview') }}</h2>

            <div class="flex flex-wrap items-center gap-3">
                <button v-if="!open" type="button" class="btn btn-sm" @click="open = true">
                    {{ t('preview') }}
                </button>
                <a href="/settings/invoicing/preview" target="_blank" rel="noopener" class="link link-hover text-sm">
                    {{ t('invoice_settings_open_preview') }}
                </a>
            </div>

            <div v-if="open" class="overflow-hidden rounded-card-nested bg-base-200">
                <div class="flex items-center justify-between px-3 py-2">
                    <span class="text-sm font-medium">{{ t('invoice_settings_template_preview') }}</span>
                    <button
                        type="button"
                        class="btn btn-square btn-ghost"
                        :aria-label="t('invoice_settings_close_preview')"
                        :title="t('invoice_settings_close_preview')"
                        @click="open = false"
                    >
                        <XMarkIcon />
                    </button>
                </div>
                <InvoicePreviewFrame />
            </div>
        </div>
    </section>
</template>
