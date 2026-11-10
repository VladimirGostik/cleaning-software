<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { formatMoney } from '@/utils/money';

defineProps<{
    result: App.Data.Invoices.PaymentImportResultData;
}>();

const { t, locale } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4" role="status">
            <h2 class="text-lg font-semibold">
                {{
                    result.recorded_count > 0
                        ? t('invoice_payment_import_result_title')
                        : t('invoice_payment_import_result_nothing_title')
                }}
            </h2>

            <dl class="grid w-fit grid-cols-[auto_auto] gap-x-6 gap-y-1 text-sm">
                <dt>{{ t('invoice_payment_import_result_recorded') }}</dt>
                <dd class="text-right tabular-nums">
                    {{ result.recorded_count }} · {{ formatMoney(result.recorded_total, 'EUR', locale) }}
                </dd>
                <dt>{{ t('invoice_payment_import_result_already_imported') }}</dt>
                <dd class="text-right tabular-nums">{{ result.already_imported }}</dd>
                <dt>{{ t('invoice_payment_import_result_excluded') }}</dt>
                <dd class="text-right tabular-nums">{{ result.excluded }}</dd>
            </dl>

            <section v-if="result.settled_invoices.length > 0" class="text-sm">
                <h3 class="font-medium">{{ t('invoice_payment_import_result_settled') }}</h3>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="invoice in result.settled_invoices" :key="invoice.id">
                        <Link :href="`/invoices/${invoice.id}`" class="link tabular-nums">{{ invoice.number }}</Link>
                    </li>
                </ul>
            </section>

            <section v-if="result.partly_paid_invoices.length > 0" class="text-sm">
                <h3 class="font-medium">{{ t('invoice_payment_import_result_partly') }}</h3>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="invoice in result.partly_paid_invoices" :key="invoice.id">
                        <Link :href="`/invoices/${invoice.id}`" class="link tabular-nums">{{ invoice.number }}</Link>
                        <span class="tabular-nums text-muted">
                            · {{ t('invoice_payment_import_balance') }}:
                            {{ formatMoney(invoice.balance_due, 'EUR', locale) }}
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="result.overpayments.length > 0" class="text-sm">
                <h3 class="font-medium">{{ t('invoice_payment_import_result_overpayments') }}</h3>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="item in result.overpayments" :key="item.reference">
                        <Link :href="`/invoices/${item.invoice_id}`" class="link tabular-nums">{{
                            item.invoice_number
                        }}</Link>
                        <span class="text-muted">
                            · {{ item.payer_name ?? t('empty_dash') }} ·
                            <span class="tabular-nums">{{
                                t('invoice_payment_import_excess', { amount: formatMoney(item.excess, 'EUR', locale) })
                            }}</span>
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="result.skipped.length > 0" class="text-sm">
                <h3 class="font-medium">{{ t('invoice_payment_import_result_skipped') }}</h3>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="item in result.skipped" :key="item.reference">
                        <span class="tabular-nums">{{ item.reference }}</span>
                        <span class="text-muted">
                            · {{ item.invoice_number ?? t('empty_dash') }} ·
                            {{ t(`payment_import_row_state_${item.state}`) }}
                        </span>
                    </li>
                </ul>
            </section>

            <div class="flex flex-wrap gap-2">
                <Link href="/invoices?filter[open]=1" class="btn btn-neutral btn-sm">
                    {{ t('invoice_payment_import_view_unpaid') }}
                </Link>
                <Link href="/invoices/payment-import" class="btn btn-neutral btn-sm">
                    {{ t('invoice_payment_import_another') }}
                </Link>
            </div>
        </div>
    </div>
</template>
