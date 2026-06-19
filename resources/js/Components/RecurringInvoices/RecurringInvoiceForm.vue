<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';
    import InvoiceSubjectPicker from '@/Components/Invoices/InvoiceSubjectPicker.vue';
    import RecurringInvoiceItemsEditor from './RecurringInvoiceItemsEditor.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SubjectValue } from '@/Components/Invoices/InvoiceSubjectPicker.vue';
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

    type EndMode = 'forever' | 'until_date' | 'count';

    interface RecurringInvoiceFormData {
        name: string;
        client_id: string | null;
        cleaning_object_id: string | null;
        type: App.Enums.InvoiceTypeEnum;
        template: App.Enums.InvoiceTemplateEnum | null;
        frequency: App.Enums.RecurringFrequencyEnum;
        day_of_month: number;
        auto_issue: boolean;
        start_date: string;
        end_date: string | null;
        occurrences_limit: number | null;
        due_days: number;
        period_from: string | null;
        period_to: string | null;
        customer_name: string | null;
        customer_representative: string | null;
        customer_ico: string | null;
        customer_dic: string | null;
        customer_vat_number: string | null;
        customer_street: string | null;
        customer_city: string | null;
        customer_postal_code: string | null;
        customer_country: string | null;
        customer_email: string | null;
        note: string | null;
        items: App.Data.RecurringInvoices.RecurringInvoiceItemData[];
        deposit: number;
        constant_symbol: string | null;
        payment_type: App.Enums.PaymentTypeEnum;
        currency: App.Enums.CurrencyEnum;
        rounding_mode: App.Enums.RoundingModeEnum;
        header_text: string | null;
        footer_text: string | null;
        _subject_mode: 'client' | 'object' | 'standalone';
        _end_mode: EndMode;
    }

    const props = withDefaults(
        defineProps<{
            recurring?: App.Data.RecurringInvoices.RecurringInvoiceDetailData | null;
            clients: ClientOption[];
            objects?: ObjectOption[] | null;
            typeOptions: SelectOption[];
            templateOptions: SelectOption[];
            frequencyOptions: SelectOption[];
            isVatPayer: boolean;
            defaultAutoIssue?: boolean;
            vatRateOptions?: VatRateOption[];
            paymentTypeOptions?: SelectOption[];
            currencyOptions?: SelectOption[];
            roundingModeOptions?: SelectOption[];
            invoiceDefaults?: InvoiceDefaults | null;
        }>(),
        {
            recurring: null,
            objects: null,
            defaultAutoIssue: false,
            vatRateOptions: () => [],
            paymentTypeOptions: () => [],
            currencyOptions: () => [],
            roundingModeOptions: () => [],
            invoiceDefaults: null,
        },
    );

    const { t } = useTranslate();

    const isEditing = computed(() => !!props.recurring);

    function resolveInitialSubjectMode(): 'client' | 'object' | 'standalone' {
        if (!props.recurring) return 'client';
        if (props.recurring.cleaning_object_id) return 'object';
        if (props.recurring.client_id) return 'client';
        return 'standalone';
    }

    function resolveEndMode(): EndMode {
        if (!props.recurring) return 'forever';
        if (props.recurring.end_date) return 'until_date';
        if (props.recurring.occurrences_limit !== null) return 'count';
        return 'forever';
    }

    const today = new Date().toISOString().slice(0, 10);

    const form = useForm<RecurringInvoiceFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/recurring-invoices/${props.recurring!.id}` : '/recurring-invoices',
        {
            name: props.recurring?.name ?? '',
            client_id: props.recurring?.client_id ?? null,
            cleaning_object_id: props.recurring?.cleaning_object_id ?? null,
            type: props.recurring?.type ?? 'monthly',
            template: props.recurring?.template ?? null,
            frequency: props.recurring?.frequency ?? 'monthly',
            day_of_month: props.recurring?.day_of_month ?? 1,
            auto_issue: props.recurring?.auto_issue ?? props.defaultAutoIssue,
            start_date: props.recurring?.start_date ?? today,
            end_date: props.recurring?.end_date ?? null,
            occurrences_limit: props.recurring?.occurrences_limit ?? null,
            due_days: props.recurring?.due_days ?? 14,
            period_from: props.recurring?.period_from ?? null,
            period_to: props.recurring?.period_to ?? null,
            customer_name: props.recurring?.customer_name ?? null,
            customer_representative: props.recurring?.customer_representative ?? null,
            customer_ico: props.recurring?.customer_ico ?? null,
            customer_dic: props.recurring?.customer_dic ?? null,
            customer_vat_number: props.recurring?.customer_vat_number ?? null,
            customer_street: props.recurring?.customer_street ?? null,
            customer_city: props.recurring?.customer_city ?? null,
            customer_postal_code: props.recurring?.customer_postal_code ?? null,
            customer_country: props.recurring?.customer_country ?? null,
            customer_email: props.recurring?.customer_email ?? null,
            note: props.recurring?.note ?? null,
            items: props.recurring?.items ?? [
                { description: '', quantity: 1, unit: null, unit_price: 0, discount_percent: 0, vat_rate: 0 },
            ],
            deposit: parseFloat(props.recurring?.deposit ?? '0') || 0,
            constant_symbol: props.recurring?.constant_symbol ?? props.invoiceDefaults?.constant_symbol ?? null,
            payment_type: props.recurring?.payment_type ?? props.invoiceDefaults?.payment_type ?? 'transfer',
            currency: props.recurring?.currency ?? props.invoiceDefaults?.currency ?? 'EUR',
            rounding_mode: props.recurring?.rounding_mode ?? props.invoiceDefaults?.rounding_mode ?? 'none',
            header_text: props.recurring?.header_text ?? null,
            footer_text: props.recurring?.footer_text ?? null,
            _subject_mode: resolveInitialSubjectMode(),
            _end_mode: resolveEndMode(),
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

    function setEndMode(mode: EndMode): void {
        form._end_mode = mode;
        if (mode === 'forever') {
            form.end_date = null;
            form.occurrences_limit = null;
        } else if (mode === 'until_date') {
            form.occurrences_limit = null;
        } else {
            form.end_date = null;
        }
    }

    function onDepositChange(val: number | null): void {
        form.deposit = val ?? 0;
    }

    function submit(): void {
        form.submit();
    }
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <div class="space-y-6">
                <!-- Name -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('recurring_invoices.form.name') }}</h2>
                        <TextInput
                            field="name"
                            :label="t('recurring_invoices.form.name')"
                            required
                        />
                    </div>
                </div>

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

                <!-- Schedule -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('recurring_invoices.form.schedule') }}</h2>
                        <div class="space-y-4">
                            <!-- Type -->
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
                                    field="frequency"
                                    :label="t('recurring_invoices.form.frequency')"
                                    :options="frequencyOptions"
                                />

                                <NumberInput
                                    field="day_of_month"
                                    :label="t('recurring_invoices.form.day_of_month')"
                                    :min="1"
                                    :max="28"
                                    required
                                />

                                <SelectInput
                                    field="template"
                                    :label="t('invoices.form.template')"
                                    :options="templateOptions"
                                />

                                <FormField :label="t('recurring_invoices.form.start_date')" required>
                                    <input
                                        v-model="(form as unknown as Record<string, string>)['start_date']"
                                        type="date"
                                        required
                                        class="input w-full"
                                        :class="{ 'input-error': form.errors.start_date }"
                                        aria-required="true"
                                        :aria-invalid="form.errors.start_date ? 'true' : undefined"
                                    />
                                    <p v-if="form.errors.start_date" class="text-error text-xs mt-0.5">
                                        {{ form.errors.start_date }}
                                    </p>
                                </FormField>

                                <!-- Period fields (conditional) -->
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
                            </div>

                            <!-- Termination mode -->
                            <FormField :label="t('recurring_invoices.form.termination')">
                                <div
                                    class="flex flex-wrap gap-2 mb-3"
                                    role="radiogroup"
                                    :aria-label="t('recurring_invoices.form.termination')"
                                >
                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="form._end_mode === 'forever'"
                                        :class="[
                                            'btn btn-sm',
                                            form._end_mode === 'forever'
                                                ? 'btn-primary'
                                                : 'btn-ghost border border-base-300',
                                        ]"
                                        @click="setEndMode('forever')"
                                    >
                                        {{ t('recurring_invoices.form.termination_forever') }}
                                    </button>
                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="form._end_mode === 'until_date'"
                                        :class="[
                                            'btn btn-sm',
                                            form._end_mode === 'until_date'
                                                ? 'btn-primary'
                                                : 'btn-ghost border border-base-300',
                                        ]"
                                        @click="setEndMode('until_date')"
                                    >
                                        {{ t('recurring_invoices.form.termination_until_date') }}
                                    </button>
                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="form._end_mode === 'count'"
                                        :class="[
                                            'btn btn-sm',
                                            form._end_mode === 'count'
                                                ? 'btn-primary'
                                                : 'btn-ghost border border-base-300',
                                        ]"
                                        @click="setEndMode('count')"
                                    >
                                        {{ t('recurring_invoices.form.termination_count') }}
                                    </button>
                                </div>

                                <div v-if="form._end_mode === 'until_date'">
                                    <FormField :label="t('recurring_invoices.form.end_date')">
                                        <input
                                            v-model="(form as unknown as Record<string, string | null>)['end_date']"
                                            type="date"
                                            class="input w-full"
                                            :class="{ 'input-error': form.errors.end_date }"
                                            :aria-invalid="form.errors.end_date ? 'true' : undefined"
                                        />
                                        <p v-if="form.errors.end_date" class="text-error text-xs mt-0.5">
                                            {{ form.errors.end_date }}
                                        </p>
                                    </FormField>
                                </div>

                                <div v-if="form._end_mode === 'count'">
                                    <NumberInput
                                        v-model="form.occurrences_limit"
                                        :label="t('recurring_invoices.form.occurrences_limit')"
                                        :min="1"
                                        :error="(form.errors as Record<string, string>)['occurrences_limit']"
                                    />
                                </div>
                            </FormField>

                            <!-- Due days -->
                            <NumberInput
                                field="due_days"
                                :label="t('recurring_invoices.form.due_days')"
                                :min="0"
                            />

                            <!-- Auto issue -->
                            <ToggleInput
                                field="auto_issue"
                                :label="t('recurring_invoices.form.auto_issue')"
                            />
                        </div>
                    </div>
                </div>

                <!-- Payment fields -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.detail.payment_method') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <TextInput
                                field="constant_symbol"
                                :label="t('invoices.detail.constant_symbol')"
                            />
                            <SelectInput
                                field="payment_type"
                                :label="t('invoices.detail.payment_method')"
                                :options="paymentTypeOptions"
                            />
                            <SelectInput
                                field="currency"
                                :label="t('invoices.detail.currency')"
                                :options="currencyOptions"
                            />
                            <SelectInput
                                field="rounding_mode"
                                :label="t('invoices.detail.rounding')"
                                :options="roundingModeOptions"
                            />
                        </div>
                    </div>
                </div>

                <!-- Header text -->
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
                        <RecurringInvoiceItemsEditor
                            v-model="form.items"
                            :is-vat-payer="isVatPayer"
                            :vat-rate-options="vatRateOptions"
                            :errors="itemErrors"
                        />
                    </div>
                </div>

                <!-- Footer text -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <TextareaInput
                            field="footer_text"
                            :label="t('invoices.detail.footer_text')"
                            :rows="2"
                        />
                    </div>
                </div>

                <!-- Deposit -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <NumberInput
                            :model-value="form.deposit"
                            :label="t('invoices.detail.deposit')"
                            :min="0"
                            :step="0.01"
                            :error="(form.errors as Record<string, string>)['deposit']"
                            @update:model-value="onDepositChange"
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
                    cancel-href="/recurring-invoices"
                    :cancel-label="t('cancel')"
                    :submit-label="isEditing ? t('save') : t('recurring_invoices.add')"
                    :processing="form.processing"
                />
            </div>
        </form>
    </FormProvider>
</template>
