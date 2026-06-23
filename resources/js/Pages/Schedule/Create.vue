<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import JobForm from '@/Components/Schedule/JobForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    const props = defineProps<{
        typeOptions: SelectOption[];
        objectOptions: App.Data.Objects.ObjectOptionData[];
        membershipOptions: App.Data.Contracts.MembershipOptionData[];
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="page-container">
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/jobs">{{ t('schedule.jobs_title') }}</Link>
                </li>
                <li>{{ t('schedule.add') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('schedule.add')" />

        <JobForm
            :type-options="props.typeOptions"
            :object-options="props.objectOptions"
            :membership-options="props.membershipOptions"
        />
    </div>
</template>
