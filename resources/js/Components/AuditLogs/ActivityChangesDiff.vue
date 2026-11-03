<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { formatAuditValue } from '@/utils/auditLog';

const props = defineProps<{
    changes: App.Data.ActivityRevertChangeData[];
    event: string | null;
}>();

const { t } = useI18n();

const showBefore = computed(() => props.event !== 'created');
const showAfter = computed(() => props.event !== 'deleted');
const showCurrent = computed(() => props.changes.some((change) => change.conflict));

function display(change: App.Data.ActivityRevertChangeData, value: unknown): string {
    return change.masked ? t('audit_diff_masked') : formatAuditValue(value, t);
}
</script>

<template>
    <p v-if="changes.length === 0" class="text-sm text-base-content/60">{{ t('audit_diff_empty') }}</p>

    <div v-else class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>{{ t('audit_diff_field') }}</th>
                    <th v-if="showBefore">{{ t('audit_diff_before') }}</th>
                    <th v-if="showAfter">{{ t('audit_diff_after') }}</th>
                    <th v-if="showCurrent">{{ t('audit_diff_current') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="change in changes" :key="change.field" :class="{ 'bg-warning/10': change.conflict }">
                    <td class="font-medium">
                        {{ change.label }}
                        <StatusChip v-if="change.conflict" tone="warning" size="sm" :label="t('audit_diff_conflict')" />
                    </td>
                    <td v-if="showBefore" class="break-words">{{ display(change, change.old) }}</td>
                    <td v-if="showAfter" class="break-words">{{ display(change, change.new) }}</td>
                    <td v-if="showCurrent" class="break-words">
                        {{ change.conflict ? display(change, change.current) : '' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
