<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    interface BreakdownLine {
        rate: number;
        base: number;
        vat: number;
        total: number;
    }

    defineProps<{
        breakdown: BreakdownLine[];
    }>();

    const { t } = useTranslate();

    function fmt(n: number): string {
        return n.toFixed(2);
    }
</script>

<template>
    <table class="table table-xs w-full text-xs">
        <thead>
            <tr>
                <th>{{ t('invoices.vat_recap.rate') }}</th>
                <th class="text-right">{{ t('invoices.vat_recap.base') }}</th>
                <th class="text-right">{{ t('invoices.vat_recap.vat') }}</th>
                <th class="text-right">{{ t('invoices.vat_recap.total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="line in breakdown" :key="line.rate">
                <td>{{ line.rate }}%</td>
                <td class="font-mono text-right">{{ fmt(line.base) }}</td>
                <td class="font-mono text-right">{{ fmt(line.vat) }}</td>
                <td class="font-mono text-right">{{ fmt(line.total) }}</td>
            </tr>
        </tbody>
    </table>
</template>
