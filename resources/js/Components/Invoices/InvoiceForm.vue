<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import InvoiceSubjectPicker from './InvoiceSubjectPicker.vue';
    import InvoiceItemsEditor from './InvoiceItemsEditor.vue';
    import type { SubjectValue } from './InvoiceSubjectPicker.vue';
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
        note: string | null;
        items: App.Data.Invoices.InvoiceItemData[];
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
        }>(),
        {
            invoice: null,
            objects: null,
            vatRate: null,
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
            note: props.invoice?.note ?? null,
            items: props.invoice?.items ?? [{ id: null, description: '', quantity: 1, unit: null, unit_price: 0, total: null }],
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

    function submit() {
        form.submit();
    }
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
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

                <!-- Dates + type + template -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.form.details') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <SelectInput
                                field="type"
                                :label="t('invoices.form.type')"
                                :options="typeOptions"
                                required
                            />
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
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.form.items') }}</h2>
                        <InvoiceItemsEditor
                            v-model="form.items"
                            :is-vat-payer="isVatPayer"
                            :vat-rate="vatRate"
                            :errors="itemErrors"
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
        </form>
    </FormProvider>
</template>
