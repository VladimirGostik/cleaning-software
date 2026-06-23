<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';

    const props = defineProps<{
        jobId: string;
        currentMembershipId: string | null;
        membershipOptions: App.Data.Contracts.MembershipOptionData[];
    }>();

    const { t } = useTranslate();

    const state = reactive({
        assigned_membership_id: props.currentMembershipId ?? '',
        processing: false,
    });

    const selectOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('schedule.assign_panel.unassign') },
        ...props.membershipOptions.map((m) => ({ value: m.id, label: m.user_name })),
    ]);

    const selectedValue = computed({
        get: () => state.assigned_membership_id,
        set: (v: string | number) => {
            state.assigned_membership_id = String(v);
        },
    });

    function submit(): void {
        state.processing = true;
        router.post(
            `/jobs/${props.jobId}/assign`,
            { assigned_membership_id: state.assigned_membership_id || null },
            {
                preserveScroll: true,
                onFinish: () => {
                    state.processing = false;
                },
            },
        );
    }
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="card-title text-sm">{{ t('schedule.assign_panel.title') }}</h2>

            <SelectInput
                v-model="selectedValue"
                :options="selectOptions"
                :aria-label="t('schedule.assign_panel.title')"
            />

            <button
                type="button"
                class="btn btn-primary btn-sm w-full"
                :disabled="state.processing"
                @click="submit"
            >
                {{ t('schedule.assign_panel.submit') }}
            </button>
        </div>
    </div>
</template>
