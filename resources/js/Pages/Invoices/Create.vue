<script setup lang="ts">
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
    }

    interface Props {
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
    <div class="max-w-4xl mx-auto">
        <PageHeader :title="t('invoices.add')" />

        <InvoiceForm
            :clients="clients"
            :objects="objects"
            :type-options="typeOptions"
            :template-options="templateOptions"
            :is-vat-payer="isVatPayer"
            :vat-rate="vatRate"
        />
    </div>
</template>
