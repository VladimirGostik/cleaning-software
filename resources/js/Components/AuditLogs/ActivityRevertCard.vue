<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import UndoIcon from '~icons/material-symbols/undo-outline-rounded';
import { useAuthorization } from '@/Composables/useAuthorization';

const props = defineProps<{
    revert: App.Data.ActivityRevertData;
    changes: App.Data.ActivityRevertChangeData[];
}>();

const emit = defineEmits<{ revert: [] }>();

const { t } = useI18n();
const { allows } = useAuthorization();

const isAvailable = computed(() => props.revert.status === 'available');
const canRevert = computed(() => isAvailable.value && allows('revert audit logs'));
const reasonText = computed(() => (props.revert.reason ? t(`audit_revert_reason_${props.revert.reason}`) : ''));
const conflictLabels = computed(() => props.changes.filter((change) => change.conflict).map((change) => change.label));
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ t('audit_revert_action') }}</h2>

            <p v-if="revert.batch_size > 1" class="text-sm text-muted">
                {{ t('audit_revert_batch_hint', { count: revert.batch_size - 1 }, revert.batch_size - 1) }}
            </p>

            <div v-if="!isAvailable" class="space-y-2">
                <p id="audit-revert-reason" class="text-sm text-muted">{{ reasonText }}</p>

                <div v-if="revert.reason === 'conflict' && conflictLabels.length > 0">
                    <p class="text-sm font-medium">{{ t('audit_revert_conflicts_title') }}</p>
                    <ul class="list-disc pl-5 text-sm">
                        <li v-for="label in conflictLabels" :key="label">{{ label }}</li>
                    </ul>
                </div>

                <Link
                    v-if="revert.reason === 'batch_member_blocked' && revert.blocking_activity_id !== null"
                    :href="`/audit-logs/${revert.blocking_activity_id}`"
                    class="link text-sm"
                >
                    {{ t('audit_revert_blocking_link', { id: revert.blocking_activity_id }) }}
                </Link>
            </div>

            <div v-if="canRevert || !isAvailable" class="card-actions">
                <button
                    type="button"
                    class="btn btn-neutral btn-sm"
                    :disabled="!canRevert"
                    :aria-describedby="isAvailable ? undefined : 'audit-revert-reason'"
                    @click="emit('revert')"
                >
                    <UndoIcon aria-hidden="true" />
                    {{ t('audit_revert_action') }}
                </button>
            </div>
        </div>
    </div>
</template>
