<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import RecurringInvoiceForm from '@/Components/RecurringInvoices/RecurringInvoiceForm.vue';
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
        recurring: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
        clients: ClientOption[];
        objects?: ObjectOption[] | null;
        typeOptions: SelectOption[];
        templateOptions: SelectOption[];
        frequencyOptions: SelectOption[];
        isVatPayer: boolean;
    }

    const props = withDefaults(defineProps<Props>(), {
        objects: null,
    });

    const { t } = useTranslate();
</script>

<template>
    <div class="page-container">
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/recurring-invoices">{{ t('recurring_invoices.title') }}</Link>
                </li>
                <li>
                    <Link :href="`/recurring-invoices/${props.recurring.id}`">
                        {{ props.recurring.name }}
                    </Link>
                </li>
                <li>{{ t('recurring_invoices.edit') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('recurring_invoices.edit')" />

        <RecurringInvoiceForm
            :recurring="props.recurring"
            :clients="props.clients"
            :objects="props.objects"
            :type-options="props.typeOptions"
            :template-options="props.templateOptions"
            :frequency-options="props.frequencyOptions"
            :is-vat-payer="props.isVatPayer"
        />
    </div>
</template>
