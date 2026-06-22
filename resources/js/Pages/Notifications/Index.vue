<script setup lang="ts">
    import { reactive, computed, watch } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { BellIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Pagination from '@/Components/Pagination.vue';
    import NotificationItem from '@/Components/Notifications/NotificationItem.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useNotificationsStore } from '@/stores/notifications';
    import type { PaginatedData } from '@/types/pagination';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';

    interface Props {
        notifications: PaginatedData<App.Data.Notifications.NotificationListItemData>;
        filters: { unreadOnly?: boolean | null; type?: string | null };
        typeOptions?: Array<{ value: string; label: string }>;
    }

    const props = withDefaults(defineProps<Props>(), {
        typeOptions: () => [],
    });

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);
    const store = useNotificationsStore();

    const filterState = reactive({
        unreadOnly: props.filters.unreadOnly ?? false,
        type: (props.filters.type ?? '') as string,
    });

    let filterTimer: ReturnType<typeof setTimeout> | null = null;

    function applyFilters(): void {
        router.get(
            '/notifications',
            {
                unreadOnly: filterState.unreadOnly || undefined,
                type: filterState.type || undefined,
            },
            {
                preserveState: true,
                replace: true,
                only: ['notifications', 'filters'],
            },
        );
    }

    watch(
        () => [filterState.unreadOnly, filterState.type] as const,
        () => {
            if (filterTimer) clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, 300);
        },
    );

    function handleSelect(n: App.Data.Notifications.NotificationListItemData): void {
        store.markReadLocally(n.id);
        router.post(
            `/notifications/${n.id}/read`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    if (n.url) {
                        router.visit(n.url);
                    }
                },
            },
        );
    }

    function markAllRead(): void {
        router.post(
            '/notifications/read-all',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['notifications'] });
                    void store.fetchBell();
                },
            },
        );
    }

    const meta = computed(() => props.notifications.meta);
    const links = computed(() => props.notifications.links);

    const allTypeOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('notifications.filter.all_types') },
        ...props.typeOptions,
    ]);
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('notifications.title')" :subtitle="t('notifications.subtitle')">
            <template #actions>
                <button
                    v-if="notifications.data.length > 0"
                    type="button"
                    class="btn btn-sm btn-ghost"
                    @click="markAllRead"
                >
                    {{ t('notifications.mark_all_read') }}
                </button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <ToggleInput v-model="filterState.unreadOnly" :label="t('notifications.filter.unread_only')" />
            <div class="w-48">
                <SelectInput
                    v-model="filterState.type"
                    :options="allTypeOptions"
                    :label="t('notifications.filter.type')"
                />
            </div>
        </div>

        <!-- List -->
        <div class="bg-base-100 rounded-lg border border-base-300 shadow-sm">
            <EmptyState
                v-if="!notifications.data.length"
                :title="t('notifications.empty')"
                :description="t('notifications.empty_hint')"
                :icon="BellIcon"
            />
            <ul v-else class="divide-y divide-base-300">
                <NotificationItem
                    v-for="n in notifications.data"
                    :key="n.id"
                    :notification="n"
                    :compact="false"
                    @select="handleSelect"
                />
            </ul>
        </div>

        <Pagination :meta="meta" :links="links" />
    </div>
</template>
