import { computed, toValue, type Component, type ComputedRef, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import { BanknotesIcon, CalendarDaysIcon, DocumentTextIcon, UsersIcon } from '@heroicons/vue/24/outline';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

export type MetricTone = 'neutral' | 'primary' | 'error';

export interface MetricStatVm {
    key: string;
    label: string;
    value: string;
    caption: string | null;
    secondary: string[];
    tone: MetricTone;
    href: string;
}

export interface MetricGroupVm {
    key: 'invoices' | 'schedule' | 'contracts' | 'people';
    title: string;
    icon: Component;
    hint: string | null;
    stats: MetricStatVm[];
}

type InvoiceCountField = 'invoiced_month_count' | 'unpaid_count' | 'overdue_count';
type InvoiceSumField = 'invoiced_month_sum' | 'unpaid_sum' | 'overdue_sum';

/** Maps the Dashboard DTO into per-group, per-stat view models (labels, formatted values, deep-link targets). */
export function useCompanyMetricGroups(
    company: MaybeRefOrGetter<App.Data.Dashboard.CompanyOverviewData>,
): ComputedRef<MetricGroupVm[]> {
    const { t } = useI18n();
    const { money } = useMoneyFormat();

    function invoiceStat(
        key: string,
        label: string,
        def: App.Data.Dashboard.InvoiceCurrencyMetricsData,
        byCurrency: App.Data.Dashboard.InvoiceCurrencyMetricsData[],
        countField: InvoiceCountField,
        sumField: InvoiceSumField,
        href: string,
        tone: MetricTone,
    ): MetricStatVm {
        return {
            key,
            label,
            value: money(def[sumField], def.currency),
            caption: t('invoice_stat_count', { count: def[countField] }),
            secondary: byCurrency
                .filter((row) => row.currency !== def.currency && row[countField] > 0)
                .map(
                    (row) =>
                        `${money(row[sumField], row.currency)} · ${t('invoice_stat_count', { count: row[countField] })}`,
                ),
            tone,
            href,
        };
    }

    return computed<MetricGroupVm[]>(() => {
        const c = toValue(company);
        const groups: MetricGroupVm[] = [];

        if (c.invoices) {
            const invoices = c.invoices;
            const byCurrency = invoices.by_currency;
            const def = byCurrency.find((row) => row.currency === invoices.default_currency) ??
                byCurrency[0] ?? {
                    currency: invoices.default_currency,
                    invoiced_month_count: 0,
                    invoiced_month_sum: '0',
                    unpaid_count: 0,
                    unpaid_sum: '0',
                    overdue_count: 0,
                    overdue_sum: '0',
                };

            groups.push({
                key: 'invoices',
                title: t('dashboard_group_invoices'),
                icon: BanknotesIcon,
                hint: null,
                stats: [
                    invoiceStat(
                        'invoices.invoiced_month',
                        t('dashboard_stat_invoiced_month'),
                        def,
                        byCurrency,
                        'invoiced_month_count',
                        'invoiced_month_sum',
                        '/invoices',
                        'primary',
                    ),
                    invoiceStat(
                        'invoices.unpaid',
                        t('dashboard_stat_unpaid'),
                        def,
                        byCurrency,
                        'unpaid_count',
                        'unpaid_sum',
                        '/invoices?filter[status]=issued',
                        'neutral',
                    ),
                    invoiceStat(
                        'invoices.overdue',
                        t('dashboard_stat_overdue'),
                        def,
                        byCurrency,
                        'overdue_count',
                        'overdue_sum',
                        '/invoices?filter[status]=overdue',
                        def.overdue_count > 0 ? 'error' : 'neutral',
                    ),
                ],
            });
        }

        if (c.schedule) {
            const schedule = c.schedule;
            const stats: MetricStatVm[] = [
                {
                    key: 'schedule.today',
                    label: t('dashboard_stat_today'),
                    value: String(schedule.today),
                    caption: null,
                    secondary: [],
                    tone: 'primary',
                    href: '/jobs',
                },
                {
                    key: 'schedule.this_week',
                    label: t('dashboard_stat_this_week'),
                    value: String(schedule.this_week),
                    caption: null,
                    secondary: [],
                    tone: 'neutral',
                    href: '/jobs',
                },
            ];

            if (schedule.unassigned_next_7_days !== null) {
                stats.push({
                    key: 'schedule.unassigned',
                    label: t('dashboard_stat_unassigned_7d'),
                    value: String(schedule.unassigned_next_7_days),
                    caption: null,
                    secondary: [],
                    tone: schedule.unassigned_next_7_days > 0 ? 'error' : 'neutral',
                    href: '/jobs?filter[status]=unassigned',
                });
            }

            groups.push({
                key: 'schedule',
                title: t('dashboard_group_schedule'),
                icon: CalendarDaysIcon,
                hint: schedule.own_only ? t('dashboard_schedule_own_only') : null,
                stats,
            });
        }

        if (c.contracts) {
            const contracts = c.contracts;
            const stats: MetricStatVm[] = [];

            if (contracts.active !== null) {
                stats.push({
                    key: 'contracts.active',
                    label: t('dashboard_stat_contracts_active'),
                    value: String(contracts.active),
                    caption: null,
                    secondary: [],
                    tone: 'primary',
                    href: '/contracts?filter[status]=active',
                });
            }

            if (contracts.expiring_30d !== null) {
                stats.push({
                    key: 'contracts.expiring_30d',
                    label: t('dashboard_stat_contracts_expiring'),
                    value: String(contracts.expiring_30d),
                    caption: null,
                    secondary: [],
                    tone: contracts.expiring_30d > 0 ? 'error' : 'neutral',
                    href: '/contracts?filter[status]=active&filter[term_type]=fixed&sort=end_date',
                });
            }

            if (contracts.quotes_awaiting !== null) {
                stats.push({
                    key: 'contracts.quotes_awaiting',
                    label: t('dashboard_stat_quotes_awaiting'),
                    value: String(contracts.quotes_awaiting),
                    caption: null,
                    secondary: [],
                    tone: 'neutral',
                    href: '/quotes?filter[status]=sent',
                });
            }

            groups.push({
                key: 'contracts',
                title: t('dashboard_group_contracts'),
                icon: DocumentTextIcon,
                hint: null,
                stats,
            });
        }

        if (c.people) {
            const people = c.people;
            const stats: MetricStatVm[] = [];

            if (people.employees !== null) {
                stats.push({
                    key: 'people.employees',
                    label: t('dashboard_stat_employees'),
                    value: String(people.employees),
                    caption: null,
                    secondary: [],
                    tone: 'neutral',
                    href: '/employees',
                });
            }

            if (people.clients !== null) {
                stats.push({
                    key: 'people.clients',
                    label: t('dashboard_stat_clients'),
                    value: String(people.clients),
                    caption: null,
                    secondary: [],
                    tone: 'neutral',
                    href: '/clients',
                });
            }

            if (people.objects !== null) {
                stats.push({
                    key: 'people.objects',
                    label: t('dashboard_stat_objects'),
                    value: String(people.objects),
                    caption: null,
                    secondary: [],
                    tone: 'neutral',
                    href: '/objects',
                });
            }

            groups.push({
                key: 'people',
                title: t('dashboard_group_people'),
                icon: UsersIcon,
                hint: null,
                stats,
            });
        }

        return groups;
    });
}
