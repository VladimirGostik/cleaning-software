<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { useForm, router } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import FileDropInput from '@/Components/Forms/FileDropInput.vue';
    import QuoteSubjectPicker, { type SubjectValue } from './QuoteSubjectPicker.vue';
    import QuoteItemsEditor from './QuoteItemsEditor.vue';
    import QuoteKindSelector from './QuoteKindSelector.vue';
    import QuoteTotalsPreview from './QuoteTotalsPreview.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
    import { DOCUMENT_ALLOWED_MIMES, DOCUMENT_MAX_SIZE_KB } from '@/lib/documentUpload';

    interface VatRateOption {
        value: number;
        label: string;
    }

    interface QuoteFormData {
        kind: App.Enums.QuoteKindEnum;
        client_id: string | null;
        cleaning_object_id: string | null;
        subject: string | null;
        number: string | null;
        issue_date: string;
        valid_until: string;
        note: string | null;
        currency: App.Enums.CurrencyEnum;
        items: App.Data.Quotes.QuoteItemData[];
        customer_name: string | null;
        customer_email: string | null;
        customer_street: string | null;
        customer_city: string | null;
        customer_postal_code: string | null;
    }

    const props = withDefaults(
        defineProps<{
            quote?: App.Data.Quotes.QuoteDetailData | null;
            clients: App.Data.Clients.ClientOptionData[];
            objects?: App.Data.Objects.ObjectOptionData[] | null;
            currencyOptions: SelectOption[];
            kindOptions: SelectOption[];
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

    function emptyItemRow(): App.Data.Quotes.QuoteItemData {
        return {
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
        };
    }

    // Snapshot fields only belong to the form when the quote is clientless —
    // `customer_name` on QuoteDetailData always falls back to the client's
    // name, so it must not be echoed back when `client_id` is set (would trip
    // the BE `prohibits` guard on update).
    const isClientless = props.quote?.client_id === null;

    const form = useForm<QuoteFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/quotes/${props.quote!.id}` : '/quotes',
        {
            kind: props.quote?.kind ?? 'itemized',
            client_id: props.quote?.client_id ?? null,
            cleaning_object_id: props.quote?.cleaning_object_id ?? null,
            subject: props.quote?.subject ?? null,
            number: props.quote?.number ?? null,
            issue_date: props.quote?.issue_date ?? today,
            valid_until: props.quote?.valid_until ?? in30days,
            note: props.quote?.note ?? null,
            currency: props.quote?.currency ?? 'EUR',
            items: props.quote?.items ?? (props.quote?.kind === 'document' ? [] : [emptyItemRow()]),
            customer_name: isClientless ? (props.quote?.customer_name ?? null) : null,
            customer_email: isClientless ? (props.quote?.customer_email ?? null) : null,
            customer_street: isClientless ? (props.quote?.customer_street ?? null) : null,
            customer_city: isClientless ? (props.quote?.customer_city ?? null) : null,
            customer_postal_code: isClientless ? (props.quote?.customer_postal_code ?? null) : null,
        },
    );

    const subjectValue = computed<SubjectValue>({
        get() {
            return {
                client_id: form.client_id,
                cleaning_object_id: form.cleaning_object_id,
                customer_name: form.customer_name,
                customer_email: form.customer_email,
                customer_street: form.customer_street,
                customer_city: form.customer_city,
                customer_postal_code: form.customer_postal_code,
            };
        },
        set(val: SubjectValue) {
            form.client_id = val.client_id;
            form.cleaning_object_id = val.cleaning_object_id;
            form.customer_name = val.customer_name;
            form.customer_email = val.customer_email;
            form.customer_street = val.customer_street;
            form.customer_city = val.customer_city;
            form.customer_postal_code = val.customer_postal_code;
        },
    });

    const subjectErrors = computed(() => {
        const out: Record<string, string> = {};
        for (const key of [
            'client_id',
            'cleaning_object_id',
            'customer_name',
            'customer_email',
            'customer_street',
            'customer_city',
            'customer_postal_code',
        ] as const) {
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

    const isDocument = computed(() => form.kind === 'document');

    const kindBadgeLabel = computed(() => t(`quote_kind.${form.kind}`));

    function setKind(kind: App.Enums.QuoteKindEnum): void {
        form.kind = kind;
        form.items = kind === 'document' ? [] : [emptyItemRow()];
    }

    const doc = reactive<{ file: File | null; clientError: string | null }>({
        file: null,
        clientError: null,
    });

    function onDocFileUpdate(file: File | null): void {
        doc.file = file;
        doc.clientError = null;
    }

    function onInvalid(reason: 'mime' | 'size'): void {
        doc.clientError = t(reason === 'mime' ? 'quotes.document.error_mime' : 'quotes.document.error_size');
    }

    function submit(): void {
        if (!isEditing.value && isDocument.value && doc.file) {
            const file = doc.file;
            form.submit({
                onSuccess: (page) => {
                    const id = (page?.props as { quote?: { id: string } } | undefined)?.quote?.id;
                    if (!id) return; // Show's "missing document" state handles the rest.
                    router.post(
                        `/quotes/${id}/document`,
                        { document: file },
                        { forceFormData: true, preserveScroll: true },
                    );
                },
            });
            return;
        }
        form.submit();
    }
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                <!-- Left column: form cards -->
                <div class="space-y-6">
                    <!-- Kind: radio on create, static badge on edit -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <QuoteKindSelector
                                v-if="!isEditing"
                                :model-value="form.kind"
                                :options="kindOptions"
                                :disabled="form.processing"
                                @update:model-value="setKind"
                            />
                            <template v-else>
                                <h2 class="card-title text-base">{{ t('quotes.form.kind') }}</h2>
                                <span class="badge badge-outline w-fit">{{ kindBadgeLabel }}</span>
                                <p class="text-xs text-base-content/60">{{ t('quotes.form.kind_locked') }}</p>
                            </template>
                        </div>
                    </div>

                    <!-- Subject: client, object, or manual recipient -->
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

                    <!-- Details: number, subject text, dates, currency -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('quotes.form.details') }}</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <TextInput field="number" :label="t('quotes.form.number')" />
                                    <p class="text-xs text-base-content/50 mt-0.5">
                                        {{ t('quotes.form.number_hint') }}
                                    </p>
                                </div>

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

                    <!-- Items (itemized only) -->
                    <div v-if="!isDocument" class="card bg-base-100 shadow-sm">
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

                    <!-- Document (document only) -->
                    <div v-else class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('quotes.section.document') }}</h2>

                            <FileDropInput
                                v-if="!isEditing"
                                :model-value="doc.file"
                                :accept="DOCUMENT_ALLOWED_MIMES"
                                :max-size-kb="DOCUMENT_MAX_SIZE_KB"
                                :hint="t('quotes.document.drop_hint')"
                                :choose-label="t('quotes.document.choose_file')"
                                :remove-label="t('quotes.document.remove_file')"
                                :error="doc.clientError ?? undefined"
                                required
                                @update:model-value="onDocFileUpdate"
                                @invalid="onInvalid"
                            />
                            <div v-else class="text-sm text-base-content/70">
                                <p v-if="quote?.document">{{ quote.document.file_name }}</p>
                                <p class="text-xs text-base-content/50 mt-1">
                                    {{ t('quotes.document.replace_on_detail') }}
                                </p>
                            </div>
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
                        :disabled="isDocument && !isEditing && doc.file === null"
                    />
                </div>

                <!-- Right column: sticky preview (itemized only) -->
                <div v-if="!isDocument" class="hidden lg:block">
                    <QuoteTotalsPreview
                        :number="form.number"
                        :issue-date="form.issue_date"
                        :valid-until="form.valid_until"
                        :currency="form.currency"
                        :subtotal="previewSubtotal"
                        :total="previewTotal"
                    />
                </div>
            </div>
        </form>
    </FormProvider>
</template>
