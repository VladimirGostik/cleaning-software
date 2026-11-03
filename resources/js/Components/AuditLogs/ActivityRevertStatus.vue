<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{ revert: App.Data.ActivityRevertData }>();

const { t } = useI18n();

const revertedTitle = computed(() =>
    t('audit_revert_badge_reverted_by', {
        name: props.revert.reverted_by_name ?? '-',
        date: formatDatetime(props.revert.reverted_at),
    }),
);

const unavailableTitle = computed(() => (props.revert.reason ? t(`audit_revert_reason_${props.revert.reason}`) : ''));
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <component
            :is="revert.reverted_by_activity_id !== null ? Link : 'span'"
            v-if="revert.status === 'reverted'"
            v-bind="
                revert.reverted_by_activity_id !== null ? { href: `/audit-logs/${revert.reverted_by_activity_id}` } : {}
            "
            :title="revertedTitle"
        >
            <StatusChip tone="done" size="sm" :label="t('audit_revert_badge_reverted')" />
        </component>

        <Link v-if="revert.reverts_activity_id !== null" :href="`/audit-logs/${revert.reverts_activity_id}`">
            <StatusChip
                tone="neutral"
                size="sm"
                :label="t('audit_revert_badge_reverts', { id: revert.reverts_activity_id })"
            />
        </Link>

        <span v-if="revert.status === 'unavailable'" :title="unavailableTitle">
            <StatusChip tone="neutral" size="sm" :label="t('audit_revert_badge_unavailable')" />
        </span>
    </div>
</template>
