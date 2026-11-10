<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BankStatementAccountAlert from '@/Components/BankStatement/BankStatementAccountAlert.vue';
import Can from '@/Components/Can.vue';
import { formatDate } from '@/utils/date';

defineProps<{
    preview: App.Data.Invoices.PaymentImportPreviewData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-3 text-sm">
        <ul class="space-y-3">
            <li v-for="file in preview.files" :key="file.index" class="space-y-2">
                <p>
                    <span class="font-medium">{{ file.name }}</span>
                    <span class="tabular-nums text-muted"> · {{ file.iban }}</span>
                    <span v-if="file.period_from && file.period_to" class="text-muted">
                        ·
                        {{
                            t('invoice_payment_import_file_period', {
                                from: formatDate(file.period_from),
                                to: formatDate(file.period_to),
                            })
                        }}
                    </span>
                    <span class="text-muted">
                        · {{ t('invoice_payment_import_file_credit_rows') }}: {{ file.credit_rows }}
                    </span>
                    <span v-if="file.account.status !== 'matched'" class="font-medium text-warning">
                        · {{ t('invoice_payment_import_file_left_out') }}
                    </span>
                </p>
                <BankStatementAccountAlert
                    v-if="file.account.status !== 'matched'"
                    :check="file.account"
                    return-url="/invoices/payment-import"
                />
            </li>
        </ul>

        <ul class="flex flex-wrap gap-x-4 gap-y-1">
            <li v-if="preview.ignored_debit_rows > 0">
                {{ t('invoice_payment_import_ignored_debits') }}: {{ preview.ignored_debit_rows }}
                <Can permission="manage expenses">
                    <Link href="/expenses/import" class="link ml-1">{{ t('invoice_payment_import_go_expenses') }}</Link>
                </Can>
            </li>
            <li v-if="preview.unreadable_credit_rows > 0">
                {{ t('invoice_payment_import_unreadable_credits') }}: {{ preview.unreadable_credit_rows }}
            </li>
            <li v-if="preview.overlapping_rows > 0">
                {{ t('invoice_payment_import_overlapping') }}: {{ preview.overlapping_rows }}
            </li>
        </ul>
    </div>
</template>
