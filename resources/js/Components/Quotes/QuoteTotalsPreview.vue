<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        number: string | null;
        issueDate: string;
        validUntil: string;
        currency: string;
        subtotal: number;
        total: number;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="sticky top-4">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="card-title text-sm text-base-content/60 uppercase tracking-wide font-medium">
                    {{ t('quotes.preview.title') }}
                </h2>

                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">{{ t('quotes.col.number') }}</p>
                    <p class="font-mono text-sm font-medium text-base-content/40">
                        {{ number || t('quotes.no_number') }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <p class="text-base-content/50 mb-0.5">{{ t('quotes.form.issue_date') }}</p>
                        <p class="font-mono">{{ issueDate }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 mb-0.5">{{ t('quotes.form.valid_until') }}</p>
                        <p class="font-mono">{{ validUntil }}</p>
                    </div>
                </div>

                <div class="divider my-0" />

                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-base-content/60">{{ t('quotes.items.subtotal') }}</dt>
                        <dd class="font-mono">{{ subtotal.toFixed(2) }} {{ currency }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 font-semibold">
                        <dt>{{ t('quotes.items.total') }}</dt>
                        <dd class="font-mono">{{ total.toFixed(2) }} {{ currency }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>
