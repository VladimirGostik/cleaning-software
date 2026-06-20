<script setup lang="ts">
    import { computed } from 'vue';

    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';
    import ContractBodyEditor from '@/Components/Contracts/ContractBodyEditor.vue';
    import PlaceholderTokenList from '@/Components/Contracts/PlaceholderTokenList.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        template?: App.Data.ContractTemplates.ContractTemplateDetailData | null;
        categoryOptions: SelectOption[];
        clientContractTokens: { token: string; label: string }[];
        employmentContractTokens: { token: string; label: string }[];
    }>();

    const { t } = useTranslate();

    interface ContractTemplateFormData {
        name: string;
        category: App.Enums.ContractCategoryEnum;
        body: string;
        is_active: boolean;
    }

    const isEditing = computed(() => !!props.template);

    const form = useForm<ContractTemplateFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/contract-templates/${props.template!.id}` : '/contract-templates',
        {
            name: props.template?.name ?? '',
            category: props.template?.category ?? 'service_agreement',
            body: props.template?.body ?? '',
            is_active: props.template?.is_active ?? true,
        },
    );

    const activeTokens = computed(() =>
        form.category === 'employment' ? props.employmentContractTokens : props.clientContractTokens,
    );

    // eslint-disable-next-line no-restricted-syntax -- component ref for cursor-position token insert
    const bodyEditorRef = ref<InstanceType<typeof ContractBodyEditor> | null>(null);

    function onInsertToken(token: string): void {
        bodyEditorRef.value?.insertAtCursor(token);
    }

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
                            <div class="md:col-span-2">
                                <TextInput field="name" :label="t('contract_templates.form.name')" required />
                            </div>

                            <SelectInput
                                field="category"
                                :options="categoryOptions"
                                :label="t('contract_templates.form.category')"
                                required
                            />

                            <ToggleInput field="is_active" :label="t('contract_templates.form.is_active')" />
                        </div>

                        <div class="mt-4">
                            <ContractBodyEditor
                                ref="bodyEditorRef"
                                field="body"
                                :label="t('contract_templates.form.body')"
                                :placeholder="t('contract_templates.form.body_placeholder')"
                                :rows="24"
                                required
                            />
                        </div>

                        <div class="mt-6">
                            <FormActions
                                :processing="form.processing"
                                :submit-label="t('common.save')"
                                :cancel-label="t('common.cancel')"
                                cancel-href="/contract-templates"
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
