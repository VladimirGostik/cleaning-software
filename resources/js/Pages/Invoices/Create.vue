<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import InvoiceForm from '@/Components/Invoices/InvoiceForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    interface ClientOption {
        id: string;
        name: string;
    }

    interface ObjectOption {
        id: string;
        name: string;
        client_id: string;
    }

    interface VatRateOption {
        value: number;
        label: string;
    }

    interface InvoiceDefaults {
        constant_symbol?: string | null;
        payment_type?: App.Enums.PaymentTypeEnum;
        currency?: App.Enums.CurrencyEnum;
        rounding_mode?: App.Enums.RoundingModeEnum;
    }

    interface Props {
        clients: ClientOption[];
        objects?: ObjectOption[] | null;
        typeOptions: SelectOption[];
        templateOptions: SelectOption[];
        statusOptions: SelectOption[];
        isVatPayer: boolean;
        vatRate?: string | null;
        vatRateOptions?: VatRateOption[];
        paymentTypeOptions?: SelectOption[];
        currencyOptions?: SelectOption[];
        roundingModeOptions?: SelectOption[];
        invoiceDefaults?: InvoiceDefaults | null;
    }

    const props = withDefaults(defineProps<Props>(), {
        objects: null,
        vatRate: null,
        vatRateOptions: () => [],
        paymentTypeOptions: () => [],
        currencyOptions: () => [],
        roundingModeOptions: () => [],
        invoiceDefaults: null,
    });

    const { t } = useTranslate();
</script>

<template>
    <div class="page-container">
        <!-- Breadcrumb -->
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/invoices">{{ t('invoices.title') }}</Link>
                </li>
                <li>{{ t('invoices.add') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('invoices.add')" />

        <InvoiceForm
            :clients="props.clients"
            :objects="props.objects"
            :type-options="props.typeOptions"
            :template-options="props.templateOptions"
            :is-vat-payer="props.isVatPayer"
            :vat-rate="props.vatRate"
            :vat-rate-options="props.vatRateOptions"
            :payment-type-options="props.paymentTypeOptions"
            :currency-options="props.currencyOptions"
            :rounding-mode-options="props.roundingModeOptions"
            :invoice-defaults="props.invoiceDefaults"
        />
    </div>
</template>
