<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import NotificationPreferenceRow from '@/Components/Notifications/NotificationPreferenceRow.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        preferences: App.Data.Notifications.NotificationPreferencesData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();

    interface NotificationPreferencesFormData {
        preferences: Record<string, boolean>;
    }

    const form = useForm<NotificationPreferencesFormData>('put', '/settings/notifications', {
        preferences: Object.fromEntries(
            props.preferences.items.filter((item) => item.configurable).map((item) => [item.type, item.mail]),
        ),
    });

    function submit(): void {
        form.submit();
    }
</script>

<template>
    <div class="max-w-2xl mx-auto">
        <div v-if="pageProps.flash.success" class="alert alert-success mb-4">
            <span>{{ pageProps.flash.success }}</span>
        </div>

        <PageHeader
            :title="t('notification_settings.title')"
            :subtitle="t('notification_settings.subtitle')"
        />

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <form novalidate @submit.prevent="submit">
                    <div class="divide-y divide-base-300">
                        <NotificationPreferenceRow
                            v-for="item in preferences.items"
                            :key="item.type"
                            :item="item"
                            :model-value="
                                item.configurable ? (form.preferences[item.type] ?? item.mail) : item.mail
                            "
                            @update:model-value="
                                (val) => {
                                    if (item.configurable) {
                                        form.preferences[item.type] = val;
                                    }
                                }
                            "
                        />
                    </div>

                    <div class="mt-6">
                        <FormActions
                            cancel-href="/dashboard"
                            :cancel-label="t('cancel')"
                            :submit-label="t('save')"
                            :processing="form.processing"
                        />
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
