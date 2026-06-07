<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

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

    const form = useForm({
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
    });

    function submit() {
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        };
        if (isEdit.value && props.object) {
            form.put(`/objects/${props.object.id}`, opts);
        } else {
            form.post('/objects', opts);
        }
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

            <form id="object-form" class="p-6 space-y-6 pb-24 flex-1" @submit.prevent="submit">
                <!-- Klient -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('objects.form.section.client') }}</p>
                    <fieldset class="fieldset w-full">
                        <legend class="fieldset-legend">
                            {{ t('objects.form.client') }} <span class="text-error">*</span>
                        </legend>
                        <select
                            v-model="form.client_id"
                            class="select w-full"
                            :class="{ 'select-error': form.errors.client_id }"
                        >
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <span v-if="form.errors.client_id" class="fieldset-label text-error">
                            {{ form.errors.client_id }}
                        </span>
                    </fieldset>
                </section>

                <!-- Typ objektu -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('objects.form.section.type') }}</p>
                    <fieldset class="fieldset w-full">
                        <legend class="fieldset-legend">
                            {{ t('objects.col.type') }} <span class="text-error">*</span>
                        </legend>
                        <select
                            v-model="form.type"
                            class="select w-full"
                            :class="{ 'select-error': form.errors.type }"
                        >
                            <option value="office">{{ t('object_type.office') }}</option>
                            <option value="apartment">{{ t('object_type.apartment') }}</option>
                            <option value="house">{{ t('object_type.house') }}</option>
                            <option value="common_areas">{{ t('object_type.common_areas') }}</option>
                        </select>
                        <span v-if="form.errors.type" class="fieldset-label text-error">
                            {{ form.errors.type }}
                        </span>
                    </fieldset>
                </section>

                <!-- Zakladne udaje -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('objects.form.section.basic') }}</p>
                    <div class="space-y-3">
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">
                                {{ t('objects.form.name') }} <span class="text-error">*</span>
                            </legend>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.name }"
                            />
                            <span v-if="form.errors.name" class="fieldset-label text-error">
                                {{ form.errors.name }}
                            </span>
                        </fieldset>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('objects.form.area_sqm') }}</legend>
                                <input
                                    v-model="form.area_sqm"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.area_sqm }"
                                />
                                <span v-if="form.errors.area_sqm" class="fieldset-label text-error">
                                    {{ form.errors.area_sqm }}
                                </span>
                            </fieldset>

                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('objects.form.floor') }}</legend>
                                <input
                                    v-model.number="form.floor"
                                    type="number"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.floor }"
                                />
                                <span v-if="form.errors.floor" class="fieldset-label text-error">
                                    {{ form.errors.floor }}
                                </span>
                            </fieldset>
                        </div>
                    </div>
                </section>

                <!-- Adresa -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('objects.form.section.address') }}</p>
                    <div class="space-y-3">
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">{{ t('objects.form.street') }}</legend>
                            <input
                                v-model="form.street"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.street }"
                            />
                            <span v-if="form.errors.street" class="fieldset-label text-error">
                                {{ form.errors.street }}
                            </span>
                        </fieldset>

                        <div class="grid grid-cols-2 gap-3">
                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('objects.form.city') }}</legend>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.city }"
                                />
                                <span v-if="form.errors.city" class="fieldset-label text-error">
                                    {{ form.errors.city }}
                                </span>
                            </fieldset>

                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('objects.form.postal_code') }}</legend>
                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.postal_code }"
                                />
                                <span v-if="form.errors.postal_code" class="fieldset-label text-error">
                                    {{ form.errors.postal_code }}
                                </span>
                            </fieldset>
                        </div>

                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">{{ t('objects.form.country') }}</legend>
                            <input
                                v-model="form.country"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.country }"
                            />
                            <span v-if="form.errors.country" class="fieldset-label text-error">
                                {{ form.errors.country }}
                            </span>
                        </fieldset>
                    </div>
                </section>

                <!-- Pristup -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('objects.form.section.access') }}</p>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('objects.form.access_code') }}</legend>
                                <input
                                    v-model="form.access_code"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.access_code }"
                                />
                                <span v-if="form.errors.access_code" class="fieldset-label text-error">
                                    {{ form.errors.access_code }}
                                </span>
                            </fieldset>

                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('objects.form.key_box_code') }}</legend>
                                <input
                                    v-model="form.key_box_code"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.key_box_code }"
                                />
                                <span v-if="form.errors.key_box_code" class="fieldset-label text-error">
                                    {{ form.errors.key_box_code }}
                                </span>
                            </fieldset>
                        </div>

                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">{{ t('objects.form.key_count') }}</legend>
                            <input
                                v-model.number="form.key_count"
                                type="number"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.key_count }"
                            />
                            <span v-if="form.errors.key_count" class="fieldset-label text-error">
                                {{ form.errors.key_count }}
                            </span>
                        </fieldset>
                    </div>
                </section>

                <!-- Pokyny -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('objects.form.section.instructions') }}</p>
                    <fieldset class="fieldset w-full">
                        <legend class="fieldset-legend">{{ t('objects.form.special_instructions') }}</legend>
                        <textarea
                            v-model="form.special_instructions"
                            rows="4"
                            class="textarea w-full"
                            :class="{ 'textarea-error': form.errors.special_instructions }"
                        />
                        <span v-if="form.errors.special_instructions" class="fieldset-label text-error">
                            {{ form.errors.special_instructions }}
                        </span>
                    </fieldset>
                </section>

                <!-- Aktivny -->
                <section>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.is_active" type="checkbox" class="toggle toggle-primary" />
                        <span class="text-sm">{{ t('objects.form.is_active') }}</span>
                    </label>
                </section>
            </form>

            <footer
                class="sticky bottom-0 bg-base-100 border-t border-base-300 px-6 py-3 flex justify-end gap-2"
            >
                <button type="button" class="btn btn-ghost" @click="emit('close')">
                    {{ t('objects.form.cancel') }}
                </button>
                <button type="submit" form="object-form" class="btn btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                    {{ t('objects.form.save') }}
                </button>
            </footer>
        </aside>
    </Teleport>
</template>
