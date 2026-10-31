<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import NotificationPreferenceRow from '@/Components/Notifications/NotificationPreferenceRow.vue';
import type { Breadcrumb } from '@/types';

type NotificationPreferenceItemData = App.Data.Notifications.NotificationPreferenceItemData;

interface NotificationPreferenceFormItem {
    type: App.Enums.NotificationTypeEnum;
    mail: boolean;
    push: boolean;
}

interface NotificationPreferencesFormData {
    preferences: NotificationPreferenceFormItem[];
}

const props = defineProps<{
    preferences: App.Data.Notifications.NotificationPreferencesData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('notification_settings') }];

const form = useForm<NotificationPreferencesFormData>('put', '/settings/notifications', {
    preferences: props.preferences.items
        .filter((item) => item.configurable)
        .map(({ type, mail, push }) => ({ type, mail, push })),
});

function mailFor(item: NotificationPreferenceItemData): boolean {
    return form.preferences.find((p) => p.type === item.type)?.mail ?? item.mail;
}

function pushFor(item: NotificationPreferenceItemData): boolean {
    return form.preferences.find((p) => p.type === item.type)?.push ?? item.push;
}

function setChannel(item: NotificationPreferenceItemData, channel: 'mail' | 'push', value: boolean): void {
    if (!item.configurable) return;
    const entry = form.preferences.find((p) => p.type === item.type);
    if (entry) entry[channel] = value;
}

function submit(): void {
    form.submit();
}
</script>

<template>
    <Header :title="t('notification_settings')" :breadcrumbs="breadcrumbs" />

    <p class="mb-6 text-base-content/60">{{ t('notification_settings_subtitle') }}</p>

    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="divide-y divide-base-300">
                        <NotificationPreferenceRow
                            v-for="item in preferences.items"
                            :key="item.type"
                            :item="item"
                            :mail="mailFor(item)"
                            :push="pushFor(item)"
                            @update:mail="setChannel(item, 'mail', $event)"
                            @update:push="setChannel(item, 'push', $event)"
                        />
                    </div>
                    <p v-if="form.errors.preferences" class="text-error text-sm">
                        {{ form.errors.preferences }}
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <FormActions cancel-href="/" :processing="form.processing" />
            </div>
        </form>
    </FormProvider>
</template>
