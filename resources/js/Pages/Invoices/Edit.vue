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

    interface Props {
        invoice: App.Data.Invoices.InvoiceDetailData;
        clients: ClientOption[];
        objects?: ObjectOption[] | null;
        typeOptions: SelectOption[];
        templateOptions: SelectOption[];
        statusOptions: SelectOption[];
        isVatPayer: boolean;
        vatRate?: string | null;
    }

    withDefaults(defineProps<Props>(), {
        objects: null,
        vatRate: null,
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
                <li>
                    <Link :href="`/invoices/${invoice.id}`">
                        {{ invoice.number ?? t('invoices.draft_number') }}
                    </Link>
                </li>
                <li>{{ t('invoices.edit') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('invoices.edit')" />

        <InvoiceForm
            :invoice="invoice"
            :clients="clients"
            :objects="objects"
            :type-options="typeOptions"
            :template-options="templateOptions"
            :is-vat-payer="isVatPayer"
            :vat-rate="vatRate"
        />
    </div>
</template>
