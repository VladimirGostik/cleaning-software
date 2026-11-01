<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import {
    EMPLOYMENT_CONTRACT_TYPES,
    TEMPLATABLE_DOCUMENT_TYPES,
    documentTypeKey,
    employmentTypeKey,
    enumOptions,
} from '@/utils/enums';

const props = defineProps<{
    showEmploymentType: boolean;
    readonly: boolean;
    typeLocked: boolean;
}>();

const { t } = useI18n();

const gridCols = computed(() =>
    props.showEmploymentType
        ? 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1.2fr)_auto]'
        : 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]',
);

const typeOptions = computed(() => enumOptions(TEMPLATABLE_DOCUMENT_TYPES, documentTypeKey, t));
const employmentOptions = computed(() => [
    { value: '', label: t('document_template_employment_all') },
    ...enumOptions(EMPLOYMENT_CONTRACT_TYPES, employmentTypeKey, t),
]);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2" :class="gridCols">
        <TextInput field="name" :label="t('name')" :disabled="readonly" required />
        <SelectInput
            field="type"
            :label="t('type')"
            :options="typeOptions"
            :disabled="readonly || typeLocked"
            required
        />
        <SelectInput
            v-if="showEmploymentType"
            field="employment_type"
            :label="t('document_template_employment_type')"
            :options="employmentOptions"
            :disabled="readonly || typeLocked"
        />
        <div class="self-start [&_.toggle]:mt-2">
            <ToggleInput field="is_active" :label="t('document_template_is_active')" :disabled="readonly" />
        </div>
    </div>
</template>
