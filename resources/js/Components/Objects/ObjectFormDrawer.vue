<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';

    const props = defineProps<{
        mode: 'create' | 'edit';
        object?: App.Data.Objects.ObjectDetailData | null;
        clients: Array<{ id: string; name: string }>;
    }>();

    const emit = defineEmits<{
        (e: 'close'): void;
        (e: 'saved'): void;
    }>();

    const { t } = useTranslate();

    const isEdit = computed(() => props.mode === 'edit' && !!props.object);

    const clientOptions = computed<SelectOption[]>(() =>
        props.clients.map((c) => ({ value: c.id, label: c.name })),
    );

    const typeOptions = computed<SelectOption[]>(() => [
        { value: 'office', label: t('object_type.office') },
        { value: 'apartment', label: t('object_type.apartment') },
        { value: 'house', label: t('object_type.house') },
        { value: 'common_areas', label: t('object_type.common_areas') },
    ]);

    const form = useForm(
        isEdit.value ? 'put' : 'post',
        isEdit.value && props.object ? `/objects/${props.object.id}` : '/objects',
        {
            client_id: props.object?.client_id ?? props.clients[0]?.id ?? '',
            type: (props.object?.type ?? 'office') as App.Enums.ObjectTypeEnum,
            name: props.object?.name ?? '',
            street: props.object?.street ?? '',
            city: props.object?.city ?? '',
            postal_code: props.object?.postal_code ?? '',
            country: props.object?.country ?? 'SK',
            access_code: props.object?.access_code ?? '',
            key_box_code: props.object?.key_box_code ?? '',
            key_count: props.object?.key_count ?? null,
            area_sqm: props.object?.area_sqm ?? '',
            floor: props.object?.floor ?? null,
            special_instructions: props.object?.special_instructions ?? '',
            is_active: props.object?.is_active ?? true,
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
                    {{ t(isEdit ? 'objects.form.edit_title' : 'objects.form.create_title') }}
                </h2>
                <button class="btn btn-sm btn-ghost btn-circle" type="button" @click="emit('close')">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </header>

            <FormProvider :form="form">
                <form class="p-6 space-y-6 pb-24 flex-1" novalidate @submit.prevent="submit">
                    <!-- Klient -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('objects.form.section.client') }}</p>
                        <SelectInput
                            field="client_id"
                            :label="t('objects.form.client')"
                            :options="clientOptions"
                            required
                        />
                    </section>

                    <!-- Typ objektu -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('objects.form.section.type') }}</p>
                        <SelectInput
                            field="type"
                            :label="t('objects.col.type')"
                            :options="typeOptions"
                            required
                        />
                    </section>

                    <!-- Zakladne udaje -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('objects.form.section.basic') }}</p>
                        <div class="space-y-3">
                            <TextInput field="name" :label="t('objects.form.name')" required />
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <TextInput field="area_sqm" :label="t('objects.form.area_sqm')" />
                                <NumberInput field="floor" :label="t('objects.form.floor')" />
                            </div>
                        </div>
                    </section>

                    <!-- Adresa -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('objects.form.section.address') }}</p>
                        <div class="space-y-3">
                            <TextInput field="street" :label="t('objects.form.street')" />
                            <div class="grid grid-cols-2 gap-3">
                                <TextInput field="city" :label="t('objects.form.city')" />
                                <TextInput field="postal_code" :label="t('objects.form.postal_code')" />
                            </div>
                            <TextInput field="country" :label="t('objects.form.country')" />
                        </div>
                    </section>

                    <!-- Pristup -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('objects.form.section.access') }}</p>
                        <div class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <TextInput field="access_code" :label="t('objects.form.access_code')" />
                                <TextInput field="key_box_code" :label="t('objects.form.key_box_code')" />
                            </div>
                            <NumberInput field="key_count" :label="t('objects.form.key_count')" />
                        </div>
                    </section>

                    <!-- Pokyny -->
                    <section>
                        <p class="text-sm font-medium mb-3">{{ t('objects.form.section.instructions') }}</p>
                        <TextareaInput
                            field="special_instructions"
                            :label="t('objects.form.special_instructions')"
                            :rows="4"
                        />
                    </section>

                    <!-- Aktivny -->
                    <section>
                        <ToggleInput field="is_active" :label="t('objects.form.is_active')" />
                    </section>

                    <footer class="sticky bottom-0 bg-base-100 border-t border-base-300 px-6 py-3">
                        <FormActions
                            :processing="form.processing"
                            :cancel-label="t('objects.form.cancel')"
                            :submit-label="t('objects.form.save')"
                            @cancel="emit('close')"
                        />
                    </footer>
                </form>
            </FormProvider>
        </aside>
    </Teleport>
</template>
