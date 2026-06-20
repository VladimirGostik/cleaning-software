<script setup lang="ts">
    import { computed, watch } from 'vue';

    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import RadioGroup from '@/Components/Forms/RadioGroup.vue';
    import ContractBodyEditor from './ContractBodyEditor.vue';
    import EmploymentContractFields, {
        type ContractFormEmploymentData,
    } from './EmploymentContractFields.vue';
    import PlaceholderTokenList from './PlaceholderTokenList.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        contract?: App.Data.Contracts.ContractDetailData | null;
        templates: App.Data.ContractTemplates.ContractTemplateListItemData[];
        objects: App.Data.Objects.ObjectOptionData[];
        memberships: App.Data.Contracts.MembershipOptionData[];
        categoryOptions: SelectOption[];
        termTypeOptions: SelectOption[];
        employmentTypeOptions: SelectOption[];
        clientContractTokens: { token: string; label: string }[];
        employmentContractTokens: { token: string; label: string }[];
    }>();

    const { t } = useTranslate();

    interface ContractFormData {
        title: string;
        reference_number: string | null;
        category: App.Enums.ContractCategoryEnum;
        term_type: App.Enums.ContractTermTypeEnum;
        contractable_type: string;
        contractable_id: string;
        contract_template_id: string | null;
        body: string;
        valid_from: string;
        end_date: string | null;
        notes: string | null;
        employment: ContractFormEmploymentData | null;
        _contractable_type: 'cleaning_object' | 'tenant_membership';
    }

    const defaultEmployment: ContractFormEmploymentData = {
        employment_type: 'dpp',
        position: null,
        hourly_rate: null,
        monthly_salary: null,
        weekly_hours: null,
        probation_end_date: null,
    };

    const today = new Date().toISOString().slice(0, 10);

    const isEditing = computed(() => !!props.contract);

    const initialContractableType = (props.contract?.contractable_type ?? 'cleaning_object') as
        | 'cleaning_object'
        | 'tenant_membership';

    const form = useForm<ContractFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/contracts/${props.contract!.id}` : '/contracts',
        {
            title: props.contract?.title ?? '',
            reference_number: props.contract?.reference_number ?? null,
            category: props.contract?.category ?? 'service_agreement',
            term_type: props.contract?.term_type ?? 'fixed',
            contractable_type: initialContractableType,
            contractable_id: props.contract?.contractable_id ?? '',
            contract_template_id: props.contract?.contract_template_id ?? null,
            body: props.contract?.body ?? '',
            valid_from: props.contract?.valid_from ?? today,
            end_date: props.contract?.end_date ?? null,
            notes: props.contract?.notes ?? null,
            employment:
                props.contract?.employment != null
                    ? {
                          employment_type: props.contract.employment.employment_type,
                          position: props.contract.employment.position,
                          hourly_rate:
                              props.contract.employment.hourly_rate != null
                                  ? Number(props.contract.employment.hourly_rate)
                                  : null,
                          monthly_salary:
                              props.contract.employment.monthly_salary != null
                                  ? Number(props.contract.employment.monthly_salary)
                                  : null,
                          weekly_hours:
                              props.contract.employment.weekly_hours != null
                                  ? Number(props.contract.employment.weekly_hours)
                                  : null,
                          probation_end_date: props.contract.employment.probation_end_date,
                      }
                    : null,
            _contractable_type: initialContractableType,
        },
    );

    const isEmployment = computed(() => form.category === 'employment');
    const showEndDate = computed(() => form.term_type === 'fixed');

    const activeTokens = computed(() =>
        isEmployment.value ? props.employmentContractTokens : props.clientContractTokens,
    );

    const objectOptions = computed<SelectOption[]>(() =>
        props.objects.map((o) => ({ value: o.id, label: o.name })),
    );

    const membershipOptions = computed<SelectOption[]>(() =>
        props.memberships.map((m) => ({ value: m.id, label: m.user_name })),
    );

    const templateOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('contracts.form.no_template') },
        ...props.templates.map((tmpl) => ({ value: tmpl.id, label: tmpl.name })),
    ]);

    const contractableTypeOptions = computed(() => [
        { value: 'cleaning_object', label: t('contracts.form.type_object') },
        { value: 'tenant_membership', label: t('contracts.form.type_membership') },
    ]);

    // eslint-disable-next-line no-restricted-syntax -- component ref for cursor-position token insert
    const bodyEditorRef = ref<InstanceType<typeof ContractBodyEditor> | null>(null);

    function onInsertToken(token: string): void {
        bodyEditorRef.value?.insertAtCursor(token);
    }

    watch(
        () => form.contract_template_id,
        (id) => {
            if (!id || form.body) {
                return;
            }
            const tmpl = props.templates.find((tpl) => tpl.id === id);
            if (tmpl?.body) {
                (form as unknown as Record<string, unknown>).body = tmpl.body;
            }
        },
    );

    watch(
        () => form._contractable_type,
        (type) => {
            (form as unknown as Record<string, unknown>).contractable_type = type;
            (form as unknown as Record<string, unknown>).contractable_id = '';
        },
    );

    watch(
        () => form.category,
        (cat) => {
            if (cat === 'employment' && !form.employment) {
                (form as unknown as Record<string, unknown>).employment = { ...defaultEmployment };
            } else if (cat !== 'employment') {
                (form as unknown as Record<string, unknown>).employment = null;
            }
        },
    );

    function submit(): void {
        form.submit();
    }
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
        <!-- Main form card -->
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <FormProvider :form="form">
                    <form novalidate @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Title -->
                            <div class="md:col-span-2">
                                <TextInput field="title" :label="t('contracts.form.title')" required />
                            </div>

                            <!-- Reference number -->
                            <TextInput
                                field="reference_number"
                                :label="t('contracts.form.reference_number')"
                            />

                            <!-- Category -->
                            <SelectInput
                                field="category"
                                :options="categoryOptions"
                                :label="t('contracts.form.category')"
                                required
                            />

                            <!-- Term type -->
                            <SelectInput
                                field="term_type"
                                :options="termTypeOptions"
                                :label="t('contracts.form.term_type')"
                                required
                            />

                            <!-- Template -->
                            <SelectInput
                                field="contract_template_id"
                                :options="templateOptions"
                                :label="t('contracts.form.template')"
                                :placeholder="t('contracts.form.no_template')"
                            />

                            <!-- Contractable type toggle -->
                            <div class="md:col-span-2">
                                <RadioGroup
                                    field="_contractable_type"
                                    :options="contractableTypeOptions"
                                    :label="t('contracts.form.contractable_type')"
                                />
                            </div>

                            <!-- Contractable selector -->
                            <div class="md:col-span-2">
                                <SelectInput
                                    v-if="form._contractable_type === 'cleaning_object'"
                                    field="contractable_id"
                                    :options="objectOptions"
                                    :label="t('contracts.form.contractable_id')"
                                    :placeholder="t('contracts.form.pick_object')"
                                    required
                                />
                                <SelectInput
                                    v-else
                                    field="contractable_id"
                                    :options="membershipOptions"
                                    :label="t('contracts.form.contractable_id')"
                                    :placeholder="t('contracts.form.pick_membership')"
                                    required
                                />
                            </div>

                            <!-- Valid from -->
                            <FormField
                                :label="t('contracts.form.valid_from')"
                                :error="form.errors.valid_from"
                                required
                            >
                                <input
                                    :value="form.valid_from"
                                    type="date"
                                    required
                                    class="input input-bordered w-full"
                                    :class="{ 'input-error': form.errors.valid_from }"
                                    aria-required="true"
                                    :aria-invalid="form.errors.valid_from ? 'true' : undefined"
                                    @input="
                                        (form as unknown as Record<string, unknown>).valid_from = (
                                            $event.target as HTMLInputElement
                                        ).value
                                    "
                                />
                            </FormField>

                            <!-- End date (only for fixed term) -->
                            <FormField
                                v-if="showEndDate"
                                :label="t('contracts.form.end_date')"
                                :error="form.errors.end_date"
                                required
                            >
                                <input
                                    :value="form.end_date ?? ''"
                                    type="date"
                                    required
                                    class="input input-bordered w-full"
                                    :class="{ 'input-error': form.errors.end_date }"
                                    aria-required="true"
                                    :aria-invalid="form.errors.end_date ? 'true' : undefined"
                                    @input="
                                        (form as unknown as Record<string, unknown>).end_date =
                                            ($event.target as HTMLInputElement).value || null
                                    "
                                />
                            </FormField>
                        </div>

                        <!-- Body editor -->
                        <div class="mt-4">
                            <ContractBodyEditor
                                ref="bodyEditorRef"
                                field="body"
                                :label="t('contracts.form.body')"
                                :placeholder="t('contracts.form.body_placeholder')"
                                :rows="20"
                                required
                            />
                        </div>

                        <!-- Employment fields (only for employment category) -->
                        <div v-if="isEmployment && form.employment" class="mt-4">
                            <p class="font-semibold text-sm mb-3">
                                {{ t('contracts.form.employment_section') }}
                            </p>
                            <EmploymentContractFields
                                :employment="form.employment"
                                :errors="form.errors as unknown as Record<string, string>"
                                :employment-type-options="employmentTypeOptions"
                                @update:employment="
                                    (form as unknown as Record<string, unknown>).employment = $event
                                "
                            />
                        </div>

                        <!-- Notes -->
                        <div class="mt-4">
                            <TextareaInput
                                v-model="(form as unknown as Record<string, string | null>).notes as string"
                                :error="form.errors.notes"
                                :label="t('contracts.form.notes')"
                                :rows="3"
                            />
                        </div>

                        <div class="mt-6">
                            <FormActions
                                :processing="form.processing"
                                :submit-label="t('common.save')"
                                :cancel-label="t('common.cancel')"
                                cancel-href="/contracts"
                            />
                        </div>
                    </form>
                </FormProvider>
            </div>
        </div>

        <!-- Token list sidebar -->
        <div class="sticky top-6 self-start">
            <PlaceholderTokenList :tokens="activeTokens" @insert="onInsertToken" />
        </div>
    </div>
</template>
