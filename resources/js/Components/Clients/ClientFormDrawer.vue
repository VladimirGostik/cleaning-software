<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';
    import RadioGroup from '@/Components/Forms/RadioGroup.vue';
    import type { RadioOption } from '@/Components/Forms/RadioGroup.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import ContactsListField from '@/Components/Clients/ContactsListField.vue';

    const props = defineProps<{
        mode: 'create' | 'edit';
        client?: App.Data.Clients.ClientDetailData | null;
    }>();

    const emit = defineEmits<{
        (e: 'close'): void;
        (e: 'saved'): void;
    }>();

    const { t } = useTranslate();

    const isEdit = computed(() => props.mode === 'edit' && !!props.client);

    const typeOptions = computed<RadioOption[]>(() => [
        { value: 'corporate', label: t('client_type.corporate') },
        { value: 'private', label: t('client_type.private') },
    ]);

    const form = useForm(
        isEdit.value ? 'put' : 'post',
        isEdit.value && props.client ? `/clients/${props.client.id}` : '/clients',
        {
            type: (props.client?.type ?? 'corporate') as App.Enums.ClientTypeEnum,
            name: props.client?.name ?? '',
            ico: props.client?.ico ?? '',
            dic: props.client?.dic ?? '',
            vat_number: props.client?.vat_number ?? '',
            is_vat_payer: props.client?.is_vat_payer ?? false,
            street: props.client?.street ?? '',
            city: props.client?.city ?? '',
            postal_code: props.client?.postal_code ?? '',
            country: props.client?.country ?? 'SK',
            note: props.client?.note ?? '',
            contacts: (props.client?.contacts ?? []).map((c) => ({ ...c })),
        },
    );

    function submit() {
        form.submit({
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    }
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        <aside
            class="fixed top-0 right-0 z-50 h-full w-full md:w-[640px] bg-base-100 shadow-xl overflow-y-auto flex flex-col"
        >
            <header
                class="sticky top-0 bg-base-100 border-b border-base-300 px-6 py-4 flex justify-between items-center"
            >
                <h2 class="text-lg font-semibold">
                    {{ t(isEdit ? 'clients.form.edit_title' : 'clients.form.create_title') }}
                </h2>
                <button class="btn btn-sm btn-ghost btn-circle" type="button" @click="emit('close')">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </header>

            <FormProvider :form="form">
                <form class="p-6 space-y-6 pb-24 flex-1" novalidate @submit.prevent="submit">
                    <!-- Typ klienta -->
                    <section>
                        <RadioGroup
                            field="type"
                            :label="t('clients.form.section.type')"
                            :options="typeOptions"
                        />
                    </section>

                    <!-- Zakladne udaje -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('clients.form.section.basic') }}</p>
                        <div class="space-y-3">
                            <TextInput field="name" :label="t('clients.form.name')" required />
                            <TextInput
                                v-if="form.type === 'corporate'"
                                field="ico"
                                :label="t('clients.form.ico')"
                                required
                            />
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <TextInput field="dic" :label="t('clients.form.dic')" />
                                <TextInput field="vat_number" :label="t('clients.form.ic_dph')" />
                            </div>
                            <ToggleInput field="is_vat_payer" :label="t('clients.form.is_vat_payer')" />
                        </div>
                    </section>

                    <!-- Adresa -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('clients.form.section.address') }}</p>
                        <div class="space-y-3">
                            <TextInput field="street" :label="t('clients.form.street')" />
                            <div class="grid grid-cols-2 gap-3">
                                <TextInput field="city" :label="t('clients.form.city')" />
                                <TextInput field="postal_code" :label="t('clients.form.postal_code')" />
                            </div>
                            <TextInput field="country" :label="t('clients.form.country')" />
                        </div>
                    </section>

                    <!-- Kontaktne osoby -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('clients.form.section.contacts') }}</p>
                        <ContactsListField v-model="form.contacts" :errors="form.errors" />
                    </section>

                    <!-- Poznamka -->
                    <section>
                        <TextareaInput field="note" :label="t('clients.form.section.note')" :rows="4" />
                    </section>

                    <footer class="sticky bottom-0 bg-base-100 border-t border-base-300 px-6 py-3">
                        <FormActions
                            :processing="form.processing"
                            :cancel-label="t('clients.form.cancel')"
                            :submit-label="t('clients.form.save')"
                            @cancel="emit('close')"
                        />
                    </footer>
                </form>
            </FormProvider>
        </aside>
    </Teleport>
</template>
