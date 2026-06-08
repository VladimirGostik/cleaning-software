<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import ColorSwatchPicker from '@/Components/Forms/ColorSwatchPicker.vue';
    import type { TenantColorOption } from '@/types';

    interface AddTenantFormData {
        name: string;
        ico: string;
        color: App.Enums.TenantColorEnum | null;
        copy_settings: boolean;
        leader_email: string | null;
    }

    defineProps<{
        open: boolean;
        colors: TenantColorOption[];
    }>();

    const emit = defineEmits<{
        (e: 'update:open', value: boolean): void;
        (e: 'close'): void;
    }>();

    const { t } = useTranslate();

    const form = useForm<AddTenantFormData>('post', '/tenants', {
        name: '',
        ico: '',
        color: null,
        copy_settings: false,
        leader_email: null,
    });

    function close() {
        emit('update:open', false);
        emit('close');
    }

    function submit() {
        form.submit({
            onSuccess: () => {
                close();
            },
        });
    }
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-40 bg-black/40"
                aria-hidden="true"
                @click="close"
            />
        </Transition>

        <Transition name="slide-up">
            <div
                v-if="open"
                role="dialog"
                aria-modal="true"
                aria-labelledby="add-tenant-title"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @keydown.esc="close"
            >
                <div class="w-full max-w-md bg-base-100 rounded-xl shadow-xl flex flex-col overflow-hidden">
                    <!-- Header -->
                    <header class="flex items-center justify-between px-6 py-4 border-b border-base-300">
                        <h2 id="add-tenant-title" class="text-lg font-semibold">
                            {{ t('tenant.add.title') }}
                        </h2>
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost btn-circle"
                            :aria-label="t('cancel')"
                            @click="close"
                        >
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </header>

                    <!-- Body -->
                    <FormProvider :form="form">
                        <form
                            id="add-tenant-form"
                            class="p-6 space-y-4 overflow-y-auto"
                            novalidate
                            @submit.prevent="submit"
                        >
                            <TextInput field="name" :label="t('tenant.add.name')" required />

                            <TextInput field="ico" :label="t('tenant.add.ico')" required />

                            <ColorSwatchPicker
                                v-model="form.color"
                                :colors="colors"
                                :label="t('tenant.add.color')"
                                :error="form.errors.color"
                            />

                            <ToggleInput field="copy_settings" :label="t('tenant.add.copy_settings')" />

                            <TextInput
                                field="leader_email"
                                type="email"
                                :label="t('tenant.add.leader_email')"
                            />

                            <!-- Footer inside form so submit button works -->
                            <div class="flex justify-end gap-2 pt-2 border-t border-base-300">
                                <FormActions
                                    :processing="form.processing"
                                    :cancel-label="t('cancel')"
                                    :submit-label="t('tenant.add.submit')"
                                    @cancel="close"
                                />
                            </div>
                        </form>
                    </FormProvider>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 0.2s ease;
    }

    .fade-enter-from,
    .fade-leave-to {
        opacity: 0;
    }

    .slide-up-enter-active,
    .slide-up-leave-active {
        transition:
            opacity 0.2s ease,
            transform 0.2s ease;
    }

    .slide-up-enter-from,
    .slide-up-leave-to {
        opacity: 0;
        transform: translateY(8px);
    }
</style>
