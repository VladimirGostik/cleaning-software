<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import SelectInput from '@/Components/Forms/SelectInput.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    members: App.Data.Tasks.TaskMemberOptionData[];
}>();

const { t } = useI18n();

const form = useForm<{ assigned_membership_id: string | null }>('post', `/tasks/${props.task.id}/assign`, {
    assigned_membership_id: props.task.assigned_membership_id,
});

watch(
    () => props.task.assigned_membership_id,
    (value) => {
        form.assigned_membership_id = value;
        form.defaults({ assigned_membership_id: value });
    },
);

const options = computed<SelectOption[]>(() => [
    { value: '', label: t('task_unassigned') },
    ...props.members.map((m) => ({ value: m.id, label: m.name })),
]);

function onChange(value: string): void {
    form.assigned_membership_id = value === '' ? null : value;
}

function submit(): void {
    form.submit({ preserveScroll: true });
}
</script>

<template>
    <form novalidate class="flex items-end gap-2" @submit.prevent="submit">
        <div class="flex-1">
            <SelectInput
                :model-value="form.assigned_membership_id ?? ''"
                :label="t('task_assignee')"
                :options="options"
                :error="form.errors.assigned_membership_id"
                @update:model-value="onChange"
            />
        </div>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
            {{ t('task_assign') }}
        </button>
    </form>
</template>
