<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import QuoteSubjectPicker, { type SubjectValue } from './QuoteSubjectPicker.vue';
    import QuoteItemsEditor from './QuoteItemsEditor.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';

    interface VatRateOption {
        value: number;
        label: string;
    }

    interface QuoteFormData {
        client_id: string | null;
        cleaning_object_id: string | null;
        subject: string | null;
        issue_date: string;
        valid_until: string;
        note: string | null;
        currency: App.Enums.CurrencyEnum;
        items: App.Data.Quotes.QuoteItemData[];
    }

    const props = withDefaults(
        defineProps<{
            quote?: App.Data.Quotes.QuoteDetailData | null;
            clients: App.Data.Clients.ClientOptionData[];
            objects?: App.Data.Objects.ObjectOptionData[] | null;
            currencyOptions: SelectOption[];
            isVatPayer: boolean;
            vatRate?: string | null;
            vatRateOptions?: VatRateOption[];
        }>(),
        {
            quote: null,
            objects: null,
            vatRate: null,
            vatRateOptions: () => [],
        },
    );

    const { t } = useTranslate();

    const isEditing = computed(() => !!props.quote);

    const defaultVatRateValue = computed<number>(() => {
        if (!props.vatRate) return 0;
        const n = parseFloat(props.vatRate);
        return isNaN(n) ? 0 : n;
    });

    const today = new Date().toISOString().slice(0, 10);
    const in30days = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);

    const form = useForm<QuoteFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/quotes/${props.quote!.id}` : '/quotes',
        {
            client_id: props.quote?.client_id ?? null,
            cleaning_object_id: props.quote?.cleaning_object_id ?? null,
            subject: props.quote?.subject ?? null,
            issue_date: props.quote?.issue_date ?? today,
            valid_until: props.quote?.valid_until ?? in30days,
            note: props.quote?.note ?? null,
            currency: props.quote?.currency ?? 'EUR',
            items: props.quote?.items ?? [
                {
                    id: null,
                    name: '',
                    description: null,
                    frequency: null,
                    quantity: 1,
                    unit: null,
                    unit_price: 0,
                    discount_percent: 0,
                    vat_rate: defaultVatRateValue.value,
                    line_base: null,
                    line_vat: null,
                    line_total: null,
                },
            ],
        },
    );

    const subjectValue = computed<SubjectValue>({
        get() {
            return {
                client_id: form.client_id,
                cleaning_object_id: form.cleaning_object_id,
            };
        },
        set(val: SubjectValue) {
            form.client_id = val.client_id;
            form.cleaning_object_id = val.cleaning_object_id;
        },
    });

    const subjectErrors = computed(() => {
        const out: Record<string, string> = {};
        for (const key of ['client_id', 'cleaning_object_id'] as const) {
            const err = (form.errors as Record<string, string>)[key];
            if (err) out[key] = err;
        }
        return out;
    });

    const itemErrors = computed(() => {
        const out: Record<string, string> = {};
        for (const [k, v] of Object.entries(form.errors as Record<string, string>)) {
            if (k.startsWith('items')) out[k] = v;
        }
        return out;
    });

    const itemsRef = computed(() => form.items);
    const isVatPayerRef = computed(() => props.isVatPayer);
    const zeroDeposit = computed<number>(() => 0);

    const { subtotal: previewSubtotal, total: previewTotal } = useInvoiceTotals(
        itemsRef,
        isVatPayerRef,
        zeroDeposit,
    );

    function submit() {
        form.submit();
    }
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                <!-- Left column: form cards -->
                <div class="space-y-6">
                    <!-- Subject: client + object -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('quotes.section.customer') }}</h2>
                            <QuoteSubjectPicker
                                v-model="subjectValue"
                                :clients="clients"
                                :objects="objects"
                                :errors="subjectErrors"
                            />
                        </div>
                    </div>

                    <!-- Details: subject text, dates, currency -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('quotes.form.details') }}</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <TextInput field="subject" :label="t('quotes.form.subject')" />
                                </div>

                                <FormField :label="t('quotes.form.issue_date')" required>
                                    <input
                                        v-model="form.issue_date"
                                        type="date"
                                        required
                                        class="input w-full"
                                        :class="{ 'input-error': form.errors.issue_date }"
                                        aria-required="true"
                                        :aria-invalid="form.errors.issue_date ? 'true' : undefined"
                                    />
                                    <p v-if="form.errors.issue_date" class="text-error text-xs mt-0.5">
                                        {{ form.errors.issue_date }}
                                    </p>
                                </FormField>

                                <FormField :label="t('quotes.form.valid_until')" required>
                                    <input
                                        v-model="form.valid_until"
                                        type="date"
                                        required
                                        class="input w-full"
                                        :class="{ 'input-error': form.errors.valid_until }"
                                        aria-required="true"
                                        :aria-invalid="form.errors.valid_until ? 'true' : undefined"
                                    />
                                    <p v-if="form.errors.valid_until" class="text-error text-xs mt-0.5">
                                        {{ form.errors.valid_until }}
                                    </p>
                                </FormField>

                                <SelectInput
                                    field="currency"
                                    :label="t('quotes.form.currency')"
                                    :options="currencyOptions"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('quotes.form.items') }}</h2>
                            <QuoteItemsEditor
                                v-model="form.items"
                                :is-vat-payer="isVatPayer"
                                :vat-rate-options="vatRateOptions"
                                :default-vat-rate="defaultVatRateValue"
                                :errors="itemErrors"
                            />
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <TextareaInput field="note" :label="t('quotes.form.note')" :rows="3" />
                        </div>
                    </div>

                    <FormActions
                        cancel-href="/quotes"
                        :cancel-label="t('cancel')"
                        :submit-label="isEditing ? t('save') : t('quotes.add')"
                        :processing="form.processing"
                    />
                </div>

                <!-- Right column: sticky preview -->
                <div class="hidden lg:block">
                    <div class="sticky top-4">
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body gap-3">
                                <h2
                                    class="card-title text-sm text-base-content/60 uppercase tracking-wide font-medium"
                                >
                                    {{ t('quotes.preview.title') }}
                                </h2>

                                <div>
                                    <p class="text-xs text-base-content/50 mb-0.5">
                                        {{ t('quotes.col.number') }}
                                    </p>
                                    <p class="font-mono text-sm font-medium text-base-content/40">
                                        {{ t('quotes.draft_number') }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <p class="text-base-content/50 mb-0.5">
                                            {{ t('quotes.form.issue_date') }}
                                        </p>
                                        <p class="font-mono">{{ form.issue_date }}</p>
                                    </div>
                                    <div>
                                        <p class="text-base-content/50 mb-0.5">
                                            {{ t('quotes.form.valid_until') }}
                                        </p>
                                        <p class="font-mono">{{ form.valid_until }}</p>
                                    </div>
                                </div>

                                <div class="divider my-0" />

                                <dl class="space-y-1 text-sm">
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-base-content/60">{{ t('quotes.items.subtotal') }}</dt>
                                        <dd class="font-mono">
                                            {{ previewSubtotal.toFixed(2) }} {{ form.currency }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-2 font-semibold">
                                        <dt>{{ t('quotes.items.total') }}</dt>
                                        <dd class="font-mono">
                                            {{ previewTotal.toFixed(2) }} {{ form.currency }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </FormProvider>
</template>
