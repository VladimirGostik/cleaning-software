<script setup lang="ts">
    import { computed, watch } from 'vue';
    import { router } from '@inertiajs/vue3';
    import FormField from '@/Components/Forms/FormField.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    export interface SubjectValue {
        mode: 'client' | 'object' | 'standalone';
        client_id: string | null;
        cleaning_object_id: string | null;
        customer_name: string | null;
        customer_ico: string | null;
        customer_dic: string | null;
        customer_vat_number: string | null;
        customer_street: string | null;
        customer_city: string | null;
        customer_postal_code: string | null;
        customer_country: string | null;
        customer_email: string | null;
    }

    interface ClientOption {
        id: string;
        name: string;
    }

    interface ObjectOption {
        id: string;
        name: string;
    }

    const props = withDefaults(
        defineProps<{
            modelValue: SubjectValue;
            clients: ClientOption[];
            objects?: ObjectOption[] | null;
            errors?: Record<string, string>;
        }>(),
        {
            objects: null,
            errors: () => ({}),
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: SubjectValue];
    }>();

    const { t } = useTranslate();

    const modes = [
        { value: 'client', label: t('invoices.subject.mode_client') },
        { value: 'object', label: t('invoices.subject.mode_object') },
        { value: 'standalone', label: t('invoices.subject.mode_standalone') },
    ] as const;

    const standaloneFields = {
        customer_name: null,
        customer_ico: null,
        customer_dic: null,
        customer_vat_number: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
        customer_country: null,
        customer_email: null,
    } satisfies Partial<SubjectValue>;

    const currentMode = computed(() => props.modelValue.mode);
    const currentClientId = computed(() => props.modelValue.client_id);

    function setMode(mode: 'client' | 'object' | 'standalone') {
        if (mode === 'standalone') {
            emit('update:modelValue', {
                ...props.modelValue,
                mode,
                client_id: null,
                cleaning_object_id: null,
            });
        } else if (mode === 'client') {
            emit('update:modelValue', {
                ...props.modelValue,
                ...standaloneFields,
                mode,
                cleaning_object_id: null,
            });
        } else {
            // object mode — keep client_id so the object dropdown can filter; clear standalone fields
            emit('update:modelValue', {
                ...props.modelValue,
                ...standaloneFields,
                mode,
                cleaning_object_id: null,
            });
        }
    }

    function setClientId(id: string) {
        emit('update:modelValue', {
            ...props.modelValue,
            client_id: id || null,
            cleaning_object_id: null,
        });
        if (id && currentMode.value === 'object') {
            router.reload({ only: ['objects'], data: { client_id: id } });
        }
    }

    function setObjectId(id: string) {
        emit('update:modelValue', {
            ...props.modelValue,
            cleaning_object_id: id || null,
        });
    }

    function setCustomerField(field: keyof SubjectValue, value: string | null) {
        emit('update:modelValue', {
            ...props.modelValue,
            [field]: value || null,
        });
    }

    // When object mode selected and client_id already set, reload objects list
    watch(currentMode, (mode) => {
        if (mode !== 'object') return;
        if (currentClientId.value) {
            router.reload({ only: ['objects'], data: { client_id: currentClientId.value } });
        }
    });
</script>

<template>
    <div class="space-y-4">
        <!-- Mode selector -->
        <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('invoices.subject.label')">
            <button
                v-for="modeOpt in modes"
                :key="modeOpt.value"
                type="button"
                role="radio"
                :aria-checked="currentMode === modeOpt.value"
                :class="[
                    'btn btn-sm',
                    currentMode === modeOpt.value ? 'btn-primary' : 'btn-ghost border border-base-300',
                ]"
                @click="setMode(modeOpt.value)"
            >
                {{ modeOpt.label }}
            </button>
        </div>

        <!-- Client mode -->
        <template v-if="currentMode === 'client' || currentMode === 'object'">
            <FormField :label="t('invoices.subject.client')" :error="errors['client_id']" required>
                <select
                    :value="modelValue.client_id ?? ''"
                    class="select w-full"
                    :class="{ 'select-error': errors['client_id'] }"
                    :aria-required="'true'"
                    :aria-invalid="errors['client_id'] ? 'true' : undefined"
                    @change="setClientId(($event.target as HTMLSelectElement).value)"
                >
                    <option value="" disabled>{{ t('invoices.subject.client_placeholder') }}</option>
                    <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </FormField>

            <FormField
                v-if="currentMode === 'object'"
                :label="t('invoices.subject.object')"
                :error="errors['cleaning_object_id']"
                required
            >
                <select
                    :value="modelValue.cleaning_object_id ?? ''"
                    :disabled="!modelValue.client_id"
                    class="select w-full"
                    :class="{ 'select-error': errors['cleaning_object_id'] }"
                    :aria-required="'true'"
                    :aria-invalid="errors['cleaning_object_id'] ? 'true' : undefined"
                    @change="setObjectId(($event.target as HTMLSelectElement).value)"
                >
                    <option value="" disabled>{{ t('invoices.subject.object_placeholder') }}</option>
                    <option v-for="o in (objects ?? [])" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </FormField>
        </template>

        <!-- Standalone mode — manual customer fields -->
        <template v-if="currentMode === 'standalone'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <TextInput
                        :model-value="modelValue.customer_name ?? ''"
                        :label="t('invoices.subject.customer_name')"
                        :error="errors['customer_name']"
                        required
                        @update:model-value="setCustomerField('customer_name', $event)"
                    />
                </div>
                <TextInput
                    :model-value="modelValue.customer_ico ?? ''"
                    :label="t('invoices.subject.customer_ico')"
                    :error="errors['customer_ico']"
                    @update:model-value="setCustomerField('customer_ico', $event)"
                />
                <TextInput
                    :model-value="modelValue.customer_dic ?? ''"
                    :label="t('invoices.subject.customer_dic')"
                    :error="errors['customer_dic']"
                    @update:model-value="setCustomerField('customer_dic', $event)"
                />
                <TextInput
                    :model-value="modelValue.customer_vat_number ?? ''"
                    :label="t('invoices.subject.customer_vat_number')"
                    :error="errors['customer_vat_number']"
                    @update:model-value="setCustomerField('customer_vat_number', $event)"
                />
                <TextInput
                    :model-value="modelValue.customer_email ?? ''"
                    type="email"
                    :label="t('invoices.subject.customer_email')"
                    :error="errors['customer_email']"
                    @update:model-value="setCustomerField('customer_email', $event)"
                />
                <div class="md:col-span-2">
                    <TextInput
                        :model-value="modelValue.customer_street ?? ''"
                        :label="t('invoices.subject.customer_street')"
                        :error="errors['customer_street']"
                        @update:model-value="setCustomerField('customer_street', $event)"
                    />
                </div>
                <TextInput
                    :model-value="modelValue.customer_city ?? ''"
                    :label="t('invoices.subject.customer_city')"
                    :error="errors['customer_city']"
                    @update:model-value="setCustomerField('customer_city', $event)"
                />
                <TextInput
                    :model-value="modelValue.customer_postal_code ?? ''"
                    :label="t('invoices.subject.customer_postal_code')"
                    :error="errors['customer_postal_code']"
                    @update:model-value="setCustomerField('customer_postal_code', $event)"
                />
                <TextInput
                    :model-value="modelValue.customer_country ?? ''"
                    :label="t('invoices.subject.customer_country')"
                    :error="errors['customer_country']"
                    @update:model-value="setCustomerField('customer_country', $event)"
                />
            </div>
        </template>
    </div>
</template>
