<script setup lang="ts">
    import { ref, computed } from 'vue';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import InvoiceTemplateThumbnail from '@/Components/Invoices/InvoiceTemplateThumbnail.vue';

    interface SelectOption {
        value: string;
        label: string;
    }

    withDefaults(
        defineProps<{
            modelValue: App.Enums.InvoiceTemplateEnum;
            options: SelectOption[];
            error?: string | null;
        }>(),
        { error: null },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: App.Enums.InvoiceTemplateEnum];
    }>();

    const { t } = useTranslate();

    // eslint-disable-next-line no-restricted-syntax -- imperative iframe preview lifecycle
    const previewOpen = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- imperative iframe preview lifecycle
    const previewTemplate = ref<App.Enums.InvoiceTemplateEnum | null>(null);

    const iframeSrc = computed(() =>
        previewTemplate.value
            ? route('settings.invoicing.preview', { template: previewTemplate.value })
            : '',
    );

    function selectTemplate(val: App.Enums.InvoiceTemplateEnum): void {
        emit('update:modelValue', val);
        previewTemplate.value = val;
        previewOpen.value = true;
    }

    function closePreview(): void {
        previewOpen.value = false;
        previewTemplate.value = null;
    }
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex flex-wrap gap-2"
            role="radiogroup"
            :aria-label="t('invoice_settings.template')"
        >
            <button
                v-for="opt in options"
                :key="opt.value"
                type="button"
                role="radio"
                :aria-checked="modelValue === opt.value"
                :class="[
                    'flex flex-col items-center w-24 border-2 rounded-lg p-2 transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary',
                    modelValue === opt.value
                        ? 'border-primary bg-primary/5'
                        : 'border-base-300 hover:border-base-content/30',
                ]"
                @click="selectTemplate(opt.value as App.Enums.InvoiceTemplateEnum)"
                @keydown.enter.prevent="selectTemplate(opt.value as App.Enums.InvoiceTemplateEnum)"
                @keydown.space.prevent="selectTemplate(opt.value as App.Enums.InvoiceTemplateEnum)"
            >
                <InvoiceTemplateThumbnail
                    :template="opt.value as App.Enums.InvoiceTemplateEnum"
                    class="h-16 w-12"
                />
                <p class="text-xs font-medium text-center mt-1 leading-tight">{{ opt.label }}</p>
                <p
                    v-if="modelValue === opt.value"
                    class="text-xs text-primary text-center mt-0.5"
                >
                    {{ t('invoice_settings.template_selected') }}
                </p>
            </button>
        </div>

        <p v-if="error" class="text-error text-sm">{{ error }}</p>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="previewOpen"
                class="shadow-md rounded-lg border border-base-300 bg-base-100 overflow-hidden"
            >
                <div class="flex justify-between items-center px-3 py-2 border-b border-base-300">
                    <span class="text-sm font-medium">{{ t('invoice_settings.template_preview') }}</span>
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost btn-circle"
                        :aria-label="t('invoice_settings.close_preview')"
                        @click="closePreview"
                    >
                        <XMarkIcon class="w-4 h-4" />
                    </button>
                </div>
                <iframe
                    :src="iframeSrc"
                    :title="t('invoice_settings.template_preview')"
                    sandbox="allow-same-origin"
                    class="w-full h-[450px] border-0 rounded-b"
                />
            </div>
        </Transition>
    </div>
</template>
