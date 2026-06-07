<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
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

    const form = useForm<AddTenantFormData>({
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
        form.post('/tenants', {
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
                    <form
                        id="add-tenant-form"
                        class="p-6 space-y-4 overflow-y-auto"
                        novalidate
                        @submit.prevent="submit"
                    >
                        <!-- Name -->
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">
                                {{ t('tenant.add.name') }} <span class="text-error">*</span>
                            </legend>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.name }"
                                autofocus
                            />
                            <span v-if="form.errors.name" class="fieldset-label text-error">
                                {{ form.errors.name }}
                            </span>
                        </fieldset>

                        <!-- IČO -->
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">
                                {{ t('tenant.add.ico') }} <span class="text-error">*</span>
                            </legend>
                            <input
                                v-model="form.ico"
                                type="text"
                                inputmode="numeric"
                                maxlength="8"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.ico }"
                            />
                            <span v-if="form.errors.ico" class="fieldset-label text-error">
                                {{ form.errors.ico }}
                            </span>
                        </fieldset>

                        <!-- Color swatch picker -->
                        <ColorSwatchPicker
                            v-model="form.color"
                            :colors="colors"
                            :label="t('tenant.add.color')"
                            :error="form.errors.color"
                        />

                        <!-- Copy settings toggle -->
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input v-model="form.copy_settings" type="checkbox" class="toggle toggle-primary" />
                            <span class="text-sm">{{ t('tenant.add.copy_settings') }}</span>
                        </label>

                        <!-- Leader email -->
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">{{ t('tenant.add.leader_email') }}</legend>
                            <input
                                v-model="form.leader_email"
                                type="email"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.leader_email }"
                            />
                            <span v-if="form.errors.leader_email" class="fieldset-label text-error">
                                {{ form.errors.leader_email }}
                            </span>
                        </fieldset>
                    </form>

                    <!-- Footer -->
                    <footer class="flex justify-end gap-2 px-6 py-4 border-t border-base-300">
                        <button type="button" class="btn btn-ghost" @click="close">
                            {{ t('cancel') }}
                        </button>
                        <button
                            type="submit"
                            form="add-tenant-form"
                            class="btn btn-primary"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                            {{ t('tenant.add.submit') }}
                        </button>
                    </footer>
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
