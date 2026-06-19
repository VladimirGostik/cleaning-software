<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import InvoiceSubjectPicker from './InvoiceSubjectPicker.vue';
    import InvoiceItemsEditor from './InvoiceItemsEditor.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
    import type { SubjectValue } from './InvoiceSubjectPicker.vue';
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

    interface InvoiceFormData {
        client_id: string | null;
        cleaning_object_id: string | null;
        type: App.Enums.InvoiceTypeEnum;
        template: App.Enums.InvoiceTemplateEnum | null;
        issue_date: string;
        delivery_date: string;
        due_date: string;
        period_from: string | null;
        period_to: string | null;
        customer_name: string | null;
        customer_ico: string | null;
        customer_dic: string | null;
        customer_vat_number: string | null;
        customer_street: string | null;
        customer_city: string | null;
        customer_postal_code: string | null;
        customer_country: string | null;
        customer_email: string | null;
        customer_representative: string | null;
        note: string | null;
        items: App.Data.Invoices.InvoiceItemData[];
        deposit: number;
        constant_symbol: string | null;
        specific_symbol: string | null;
        payment_type: App.Enums.PaymentTypeEnum;
        currency: App.Enums.CurrencyEnum;
        rounding_mode: App.Enums.RoundingModeEnum;
        header_text: string | null;
        footer_text: string | null;
        _subject_mode: 'client' | 'object' | 'standalone';
    }

    const props = withDefaults(
        defineProps<{
            invoice?: App.Data.Invoices.InvoiceDetailData | null;
            clients: ClientOption[];
            objects?: ObjectOption[] | null;
            typeOptions: SelectOption[];
            templateOptions: SelectOption[];
            isVatPayer: boolean;
            vatRate?: string | null;
            vatRateOptions?: VatRateOption[];
            paymentTypeOptions?: SelectOption[];
            currencyOptions?: SelectOption[];
            roundingModeOptions?: SelectOption[];
            invoiceDefaults?: InvoiceDefaults | null;
        }>(),
        {
            invoice: null,
            objects: null,
            vatRate: null,
            vatRateOptions: () => [],
            paymentTypeOptions: () => [],
            currencyOptions: () => [],
            roundingModeOptions: () => [],
            invoiceDefaults: null,
        },
    );

    const { t } = useTranslate();

    const isEditing = computed(() => !!props.invoice);

    function resolveInitialMode(): 'client' | 'object' | 'standalone' {
        if (!props.invoice) return 'client';
        if (props.invoice.cleaning_object_id) return 'object';
        if (props.invoice.client_id) return 'client';
        return 'standalone';
    }

    const defaultVatRateValue = computed<number>(() => {
        if (!props.vatRate) return 0;
        const n = parseFloat(props.vatRate);
        return isNaN(n) ? 0 : n;
    });

    const today = new Date().toISOString().slice(0, 10);
    const in14days = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);

    const form = useForm<InvoiceFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/invoices/${props.invoice!.id}` : '/invoices',
        {
            client_id: props.invoice?.client_id ?? null,
            cleaning_object_id: props.invoice?.cleaning_object_id ?? null,
            type: props.invoice?.type ?? 'one_off',
            template: props.invoice?.template ?? null,
            issue_date: props.invoice?.issue_date ?? today,
            delivery_date: props.invoice?.delivery_date ?? today,
            due_date: props.invoice?.due_date ?? in14days,
            period_from: props.invoice?.period_from ?? null,
            period_to: props.invoice?.period_to ?? null,
            customer_name: props.invoice?.customer_name ?? null,
            customer_ico: props.invoice?.customer_ico ?? null,
            customer_dic: props.invoice?.customer_dic ?? null,
            customer_vat_number: props.invoice?.customer_vat_number ?? null,
            customer_street: props.invoice?.customer_street ?? null,
            customer_city: props.invoice?.customer_city ?? null,
            customer_postal_code: props.invoice?.customer_postal_code ?? null,
            customer_country: props.invoice?.customer_country ?? null,
            customer_email: props.invoice?.customer_email ?? null,
            customer_representative: props.invoice?.customer_representative ?? null,
            note: props.invoice?.note ?? null,
            items: props.invoice?.items ?? [
                {
                    id: null,
                    description: '',
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
            deposit: parseFloat(props.invoice?.deposit ?? '0') || 0,
            constant_symbol: props.invoice?.constant_symbol ?? props.invoiceDefaults?.constant_symbol ?? null,
            specific_symbol: props.invoice?.specific_symbol ?? null,
            payment_type: props.invoice?.payment_type ?? props.invoiceDefaults?.payment_type ?? 'transfer',
            currency: props.invoice?.currency ?? props.invoiceDefaults?.currency ?? 'EUR',
            rounding_mode: props.invoice?.rounding_mode ?? props.invoiceDefaults?.rounding_mode ?? 'none',
            header_text: props.invoice?.header_text ?? null,
            footer_text: props.invoice?.footer_text ?? null,
            _subject_mode: resolveInitialMode(),
        },
    );

    const subjectValue = computed<SubjectValue>({
        get() {
            return {
                mode: form._subject_mode,
                client_id: form.client_id,
                cleaning_object_id: form.cleaning_object_id,
                customer_name: form.customer_name,
                customer_ico: form.customer_ico,
                customer_dic: form.customer_dic,
                customer_vat_number: form.customer_vat_number,
                customer_street: form.customer_street,
                customer_city: form.customer_city,
                customer_postal_code: form.customer_postal_code,
                customer_country: form.customer_country,
                customer_email: form.customer_email,
                customer_representative: form.customer_representative,
            };
        },
        set(val: SubjectValue) {
            form._subject_mode = val.mode;
            form.client_id = val.client_id;
            form.cleaning_object_id = val.cleaning_object_id;
            form.customer_name = val.customer_name;
            form.customer_ico = val.customer_ico;
            form.customer_dic = val.customer_dic;
            form.customer_vat_number = val.customer_vat_number;
            form.customer_street = val.customer_street;
            form.customer_city = val.customer_city;
            form.customer_postal_code = val.customer_postal_code;
            form.customer_country = val.customer_country;
            form.customer_email = val.customer_email;
            form.customer_representative = val.customer_representative;
        },
    });

    const showPeriodFields = computed(() => form.type === 'monthly' || form.type === 'special');

    const subjectErrors = computed(() => {
        const keys = [
            'client_id',
            'cleaning_object_id',
            'customer_name',
            'customer_ico',
            'customer_dic',
            'customer_vat_number',
            'customer_street',
            'customer_city',
            'customer_postal_code',
            'customer_country',
            'customer_email',
            'customer_representative',
        ] as const;
        const out: Record<string, string> = {};
        for (const k of keys) {
            const err = (form.errors as Record<string, string>)[k];
            if (err) out[k] = err;
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

    // Preview totals via shared composable
    const itemsRef = computed(() => form.items);
    const isVatPayerRef = computed(() => props.isVatPayer);
    const depositRef = computed(() => form.deposit);
    const { subtotal: previewSubtotal, total: previewTotal, balanceDue: previewBalanceDue } = useInvoiceTotals(
        itemsRef,
        isVatPayerRef,
        depositRef,
    );

    const typeLabel = computed(() => {
        const opt = props.typeOptions.find((o) => o.value === form.type);
        return opt ? opt.label : form.type;
    });

    function onDepositChange(val: number | null): void {
        form.deposit = val ?? 0;
    }

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
                    <!-- Subject -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('invoices.form.subject') }}</h2>
                            <InvoiceSubjectPicker
                                v-model="subjectValue"
                                :clients="clients"
                                :objects="objects"
                                :errors="subjectErrors"
                            />
                        </div>
                    </div>

                    <!-- Dates + type + template + payment fields -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('invoices.form.details') }}</h2>
                            <div class="space-y-4">
                                <!-- Type segmented control -->
                                <FormField :label="t('invoices.form.type')" required>
                                    <div
                                        class="flex flex-wrap gap-2"
                                        role="radiogroup"
                                        :aria-label="t('invoices.form.type')"
                                    >
                                        <button
                                            v-for="opt in typeOptions"
                                            :key="opt.value"
                                            type="button"
                                            role="radio"
                                            :aria-checked="form.type === opt.value"
                                            :class="[
                                                'btn btn-sm',
                                                form.type === opt.value
                                                    ? 'btn-primary'
                                                    : 'btn-ghost border border-base-300',
                                            ]"
                                            @click="form.type = opt.value as App.Enums.InvoiceTypeEnum"
                                        >
                                            {{ opt.label }}
                                        </button>
                                    </div>
                                    <p v-if="form.errors.type" class="text-error text-xs mt-0.5">
                                        {{ form.errors.type }}
                                    </p>
                                </FormField>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <SelectInput
                                        field="template"
                                        :label="t('invoices.form.template')"
                                        :options="templateOptions"
                                    />

                                    <FormField :label="t('invoices.form.issue_date')" required>
                                        <input
                                            v-model="form.issue_date"
                                            type="date"
                                            required
                                            class="input w-full"
                                            :class="{ 'input-error': form.errors.issue_date }"
                                            :aria-required="'true'"
                                            :aria-invalid="form.errors.issue_date ? 'true' : undefined"
                                        />
                                        <p v-if="form.errors.issue_date" class="text-error text-xs mt-0.5">
                                            {{ form.errors.issue_date }}
                                        </p>
                                    </FormField>

                                    <FormField :label="t('invoices.form.delivery_date')" required>
                                        <input
                                            v-model="form.delivery_date"
                                            type="date"
                                            required
                                            class="input w-full"
                                            :class="{ 'input-error': form.errors.delivery_date }"
                                            :aria-required="'true'"
                                            :aria-invalid="form.errors.delivery_date ? 'true' : undefined"
                                        />
                                        <p v-if="form.errors.delivery_date" class="text-error text-xs mt-0.5">
                                            {{ form.errors.delivery_date }}
                                        </p>
                                    </FormField>

                                    <FormField :label="t('invoices.form.due_date')" required>
                                        <input
                                            v-model="form.due_date"
                                            type="date"
                                            required
                                            class="input w-full"
                                            :class="{ 'input-error': form.errors.due_date }"
                                            :aria-required="'true'"
                                            :aria-invalid="form.errors.due_date ? 'true' : undefined"
                                        />
                                        <p v-if="form.errors.due_date" class="text-error text-xs mt-0.5">
                                            {{ form.errors.due_date }}
                                        </p>
                                    </FormField>

                                    <template v-if="showPeriodFields">
                                        <FormField :label="t('invoices.form.period_from')">
                                            <input
                                                v-model="(form as unknown as Record<string, string | null>)['period_from']"
                                                type="date"
                                                class="input w-full"
                                                :class="{ 'input-error': form.errors.period_from }"
                                                :aria-invalid="form.errors.period_from ? 'true' : undefined"
                                            />
                                            <p v-if="form.errors.period_from" class="text-error text-xs mt-0.5">
                                                {{ form.errors.period_from }}
                                            </p>
                                        </FormField>
                                        <FormField :label="t('invoices.form.period_to')">
                                            <input
                                                v-model="(form as unknown as Record<string, string | null>)['period_to']"
                                                type="date"
                                                class="input w-full"
                                                :class="{ 'input-error': form.errors.period_to }"
                                                :aria-invalid="form.errors.period_to ? 'true' : undefined"
                                            />
                                            <p v-if="form.errors.period_to" class="text-error text-xs mt-0.5">
                                                {{ form.errors.period_to }}
                                            </p>
                                        </FormField>
                                    </template>

                                    <!-- Deposit -->
                                    <NumberInput
                                        :model-value="form.deposit"
                                        :label="t('invoices.detail.deposit')"
                                        :min="0"
                                        :step="0.01"
                                        :error="(form.errors as Record<string, string>)['deposit']"
                                        @update:model-value="onDepositChange"
                                    />

                                    <!-- Constant symbol / Specific symbol -->
                                    <TextInput
                                        field="constant_symbol"
                                        :label="t('invoices.detail.constant_symbol')"
                                    />
                                    <TextInput
                                        field="specific_symbol"
                                        :label="t('invoices.detail.specific_symbol')"
                                    />

                                    <!-- Payment type -->
                                    <SelectInput
                                        field="payment_type"
                                        :label="t('invoices.detail.payment_method')"
                                        :options="paymentTypeOptions"
                                    />

                                    <!-- Currency -->
                                    <SelectInput
                                        field="currency"
                                        :label="t('invoices.detail.currency')"
                                        :options="currencyOptions"
                                    />

                                    <!-- Rounding mode -->
                                    <div class="md:col-span-2">
                                        <SelectInput
                                            field="rounding_mode"
                                            :label="t('invoices.detail.rounding')"
                                            :options="roundingModeOptions"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Header text (intro above items) -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <TextareaInput
                                field="header_text"
                                :label="t('invoices.detail.header_text')"
                                :rows="2"
                            />
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('invoices.form.items') }}</h2>
                            <InvoiceItemsEditor
                                v-model="form.items"
                                :is-vat-payer="isVatPayer"
                                :vat-rate-options="vatRateOptions"
                                :default-vat-rate="defaultVatRateValue"
                                :errors="itemErrors"
                            />
                        </div>
                    </div>

                    <!-- Footer text (closing text below totals) -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <TextareaInput
                                field="footer_text"
                                :label="t('invoices.detail.footer_text')"
                                :rows="2"
                            />
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <TextareaInput
                                field="note"
                                :label="t('invoices.form.note')"
                                :rows="3"
                            />
                        </div>
                    </div>

                    <FormActions
                        cancel-href="/invoices"
                        :cancel-label="t('cancel')"
                        :submit-label="isEditing ? t('save') : t('invoices.add')"
                        :processing="form.processing"
                    />
                </div>

                <!-- Right column: sticky preview -->
                <div class="hidden lg:block">
                    <div class="sticky top-4">
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body gap-3">
                                <h2 class="card-title text-sm text-base-content/60 uppercase tracking-wide font-medium">
                                    {{ t('invoices.preview.title') }}
                                </h2>

                                <!-- Number placeholder -->
                                <div>
                                    <p class="text-xs text-base-content/50 mb-0.5">{{ t('invoices.col.number') }}</p>
                                    <p class="font-mono text-sm font-medium text-base-content/40">
                                        {{ t('invoices.draft_number') }}
                                    </p>
                                </div>

                                <!-- Type -->
                                <div>
                                    <p class="text-xs text-base-content/50 mb-0.5">{{ t('invoices.form.type') }}</p>
                                    <span class="badge badge-ghost badge-sm">{{ typeLabel }}</span>
                                </div>

                                <!-- Dates -->
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <p class="text-base-content/50 mb-0.5">{{ t('invoices.form.issue_date') }}</p>
                                        <p class="font-mono">{{ form.issue_date }}</p>
                                    </div>
                                    <div>
                                        <p class="text-base-content/50 mb-0.5">{{ t('invoices.form.due_date') }}</p>
                                        <p class="font-mono">{{ form.due_date }}</p>
                                    </div>
                                </div>

                                <div class="divider my-0" />

                                <!-- Totals -->
                                <dl class="space-y-1 text-sm">
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-base-content/60">{{ t('invoices.detail.subtotal') }}</dt>
                                        <dd class="font-mono">{{ previewSubtotal.toFixed(2) }} {{ form.currency }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2 font-semibold">
                                        <dt>{{ t('invoices.detail.total') }}</dt>
                                        <dd class="font-mono">{{ previewTotal.toFixed(2) }} {{ form.currency }}</dd>
                                    </div>
                                    <template v-if="form.deposit > 0">
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-base-content/60">{{ t('invoices.detail.deposit') }}</dt>
                                            <dd class="font-mono">{{ form.deposit.toFixed(2) }} {{ form.currency }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-2 font-semibold">
                                            <dt>{{ t('invoices.detail.balance_due') }}</dt>
                                            <dd class="font-mono">{{ previewBalanceDue.toFixed(2) }} {{ form.currency }}</dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </FormProvider>
</template>
