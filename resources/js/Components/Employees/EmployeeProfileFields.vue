<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';

defineProps<{
    isEditing: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <TextInput
                field="email"
                type="email"
                autocomplete="off"
                required
                :disabled="isEditing"
                :label="t('email')"
            />
            <p v-if="!isEditing" class="text-xs text-base-content/60">{{ t('employee_email_hint') }}</p>
        </div>

        <TextInput field="first_name" maxlength="100" :label="t('employee_first_name')" />
        <TextInput field="last_name" maxlength="100" :label="t('employee_last_name')" />
        <TextInput field="phone" type="tel" maxlength="50" :label="t('phone')" />
        <TextInput field="position" maxlength="100" :label="t('position')" />
    </div>
</template>
