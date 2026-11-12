<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    result: App.Data.Expenses.ExpenseImportResultData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-4" role="status">
            <h2 class="card-title">
                {{ result.created > 0 ? t('expense_import_result_title') : t('expense_import_result_nothing_title') }}
            </h2>

            <dl class="grid w-fit grid-cols-[auto_auto] gap-x-6 gap-y-1 text-sm">
                <dt>{{ t('expense_import_result_created') }}</dt>
                <dd class="text-right tabular-nums">{{ result.created }}</dd>
                <dt>{{ t('expense_import_state_already_imported') }}</dt>
                <dd class="text-right tabular-nums">{{ result.already_imported }}</dd>
                <dt>{{ t('expense_import_result_excluded') }}</dt>
                <dd class="text-right tabular-nums">{{ result.excluded }}</dd>
            </dl>

            <div class="flex flex-wrap gap-2">
                <Link v-if="result.created > 0" href="/expenses?filter[status]=draft" class="btn btn-neutral btn-sm">
                    {{ t('expense_import_view_drafts') }}
                </Link>
                <Link href="/expenses/import" class="btn btn-neutral btn-sm">
                    {{ t('expense_import_another') }}
                </Link>
            </div>
        </div>
    </div>
</template>
