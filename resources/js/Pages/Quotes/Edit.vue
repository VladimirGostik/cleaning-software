<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import QuoteForm from '@/Components/Quotes/QuoteForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    interface VatRateOption {
        value: number;
        label: string;
    }

    interface Props {
        quote: App.Data.Quotes.QuoteDetailData;
        clients: App.Data.Clients.ClientOptionData[];
        objects?: App.Data.Objects.ObjectOptionData[] | null;
        currencyOptions: SelectOption[];
        kindOptions: SelectOption[];
        isVatPayer: boolean;
        vatRate?: string | null;
        vatRateOptions?: VatRateOption[];
    }

    const props = withDefaults(defineProps<Props>(), {
        objects: null,
        vatRate: null,
        vatRateOptions: () => [],
    });

    const { t } = useTranslate();
</script>

<template>
    <div class="page-container">
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/quotes">{{ t('quotes.title') }}</Link>
                </li>
                <li>
                    <Link :href="`/quotes/${props.quote.id}`">
                        {{ props.quote.number ?? t('quotes.no_number') }}
                    </Link>
                </li>
                <li>{{ t('quotes.action.edit') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('quotes.action.edit')" />

        <QuoteForm
            :quote="props.quote"
            :clients="props.clients"
            :objects="props.objects"
            :currency-options="props.currencyOptions"
            :kind-options="props.kindOptions"
            :is-vat-payer="props.isVatPayer"
            :vat-rate="props.vatRate"
            :vat-rate-options="props.vatRateOptions"
        />
    </div>
</template>
