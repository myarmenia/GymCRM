<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import TableFilter from '@/Components/TableFilter.vue'
import PeriodDateRangeFilter from '@/Components/Reports/PeriodDateRangeFilter.vue'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    sales: {
        type: Object,
        required: true,
    },
    totals: {
        type: Object,
        required: true,
    },
})

const form = reactive({
    period: props.filters.period,
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    report_filter: props.filters.report_filter ?? '',
})

const reportFilters = ref({
    report_filter: props.filters.report_filter ?? '',
})

watch(
    () => props.filters,
    filters => {
        form.period = filters.period
        form.start_date = filters.start_date
        form.end_date = filters.end_date
        form.report_filter = filters.report_filter ?? ''
        reportFilters.value = {
            report_filter: filters.report_filter ?? '',
        }
    },
)

const reportFilterFields = [
    {
        name: 'report_filter',
        label: t('staff_reports.filter'),
        placeholder: t('sales.all_memberships'),
        options: [
            { value: 'discounted', label: t('staff_reports.discounted_memberships') },
            { value: 'manual_discount', label: t('staff_reports.manual_discount_only') },
            { value: 'membership_plan_discount', label: t('sales.membership_discount') },
            { value: 'fully_paid', label: t('staff_reports.fully_paid_memberships') },
            { value: 'with_debt', label: t('staff_reports.memberships_with_debt') },
            { value: 'refund_due', label: t('staff_reports.memberships_with_refund_amounts') },
        ],
    },
]

const summaryCards = computed(() => [
    { label: t('staff_reports.memberships_sold'), value: props.summary.sold_memberships_count, icon: 'tabler-id', class: 'bg-label-primary text-primary' },
    { label: t('staff_reports.initial_amount'), value: formatAmount(props.summary.total_amount), icon: 'tabler-cash', class: 'bg-label-info text-info' },
    { label: t('staff_reports.final_amount'), value: formatAmount(props.summary.final_amount), icon: 'tabler-receipt', class: 'bg-label-dark text-dark' },
    { label: t('sales.paid_amount'), value: formatAmount(props.summary.paid_amount), icon: 'tabler-credit-card', class: 'bg-label-success text-success' },
    { label: t('sales.debt'), value: formatAmount(props.summary.debt), icon: 'tabler-alert-circle', class: 'bg-label-danger text-danger' },
    { label: t('sales.manual_discount'), value: formatAmount(props.summary.manual_discount_amount), icon: 'tabler-discount', class: 'bg-label-warning text-warning' },
    { label: t('sales.membership_discount'), value: formatAmount(props.summary.membership_discount_amount), icon: 'tabler-percentage', class: 'bg-label-secondary text-secondary' },
])

const cleanQuery = query => Object.fromEntries(
    Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''),
)

const exportHref = computed(() => route('reports.membership-sales.export', {
    locale: currentLocale.value,
    ...cleanQuery({
        period: form.period,
        start_date: form.start_date,
        end_date: form.end_date,
        report_filter: form.report_filter,
    }),
}))

const applyFilters = () => {
    router.get(route('reports.membership-sales', { locale: currentLocale.value }), cleanQuery({
        period: form.period,
        start_date: form.start_date,
        end_date: form.end_date,
        report_filter: form.report_filter,
    }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const changePeriod = period => {
    form.period = period
    router.get(route('reports.membership-sales', { locale: currentLocale.value }), cleanQuery({
        period,
        report_filter: form.report_filter,
    }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const updateReportFilters = payload => {
    form.report_filter = payload.report_filter ?? ''
    reportFilters.value = { report_filter: form.report_filter }
}

const applyReportFilters = payload => {
    updateReportFilters(payload)
    applyFilters()
}

const resetReportFilters = () => {
    updateReportFilters({})
    applyFilters()
}

const formatAmount = value => {
    return Number(value || 0).toLocaleString('hy-AM', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })
}

const formatDate = value => value ? String(value).slice(0, 10) : '-'

const statusLabel = status => ({
    unpaid: t('people.unpaid'),
    partial: t('sales.partial'),
    paid: t('people.paid'),
    refunded: t('sales.refunded'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')

const statusClass = status => ({
    unpaid: 'bg-label-warning',
    partial: 'bg-label-info',
    paid: 'bg-label-success',
    refunded: 'bg-label-secondary',
    cancelled: 'bg-label-danger',
}[status] ?? 'bg-label-secondary')
</script>

<template>
    <Head :title="t('staff_reports.membership_report')" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">{{ t('staff_reports.membership_report') }}</h2>
                <div class="text-muted">
                    {{ formatDate(filters.start_date) }} - {{ formatDate(filters.end_date) }}
                </div>
            </div>
            <a
                :href="exportHref"
                class="btn btn-outline-success"
            >
                <i class="icon-base ti tabler-file-export me-1"></i>
                {{ t('staff_reports.export_to_excel') }}
            </a>
        </div>

        <PeriodDateRangeFilter
            v-model:selected-period="form.period"
            v-model:start-date="form.start_date"
            v-model:end-date="form.end_date"
            @period-change="changePeriod"
            @apply="applyFilters"
        />

        <TableFilter
            v-model="reportFilters"
            :text-fields="[]"
            :select-fields="reportFilterFields"
            :date-fields="[]"
            default-date-field=""
            :submit-label="t('staff_reports.apply')"
            :reset-label="t('filter.reset')"
            @update:model-value="updateReportFilters"
            @filter="applyReportFilters"
            @reset="resetReportFilters"
        />

        <div class="row g-4 mb-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="col-sm-6 col-xl-3"
            >
                <div class="card h-100">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div
                            class="report-icon"
                            :class="card.class"
                        >
                            <i :class="['icon-base ti', card.icon]"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ card.label }}</div>
                            <div class="h5 mb-0">{{ card.value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h5 class="mb-0">{{ t('staff_reports.memberships_sold') }}</h5>
                <span class="badge bg-label-primary">{{ t('staff_reports.records_count', { count: sales.total ?? sales.data.length }) }}</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ t('sales.client') }}</th>
                            <th>{{ t('people.membership') }}</th>
                            <th>{{ t('roles.trainer') }}</th>
                            <th>{{ t('sales.term') }}</th>
                            <th>{{ t('membership.price') }}</th>
                            <th>{{ t('sales.discount') }}</th>
                            <th>{{ t('staff_reports.final') }}</th>
                            <th>{{ t('people.paid') }}</th>
                            <th>{{ t('sales.debt') }}</th>
                            <th>{{ t('staff_reports.refund') }}</th>
                            <th>{{ t('status.status') }}</th>
                            <th>{{ t('inventory.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                        >
                            <td>
                                <Link
                                    v-if="sale.person_id"
                                    :href="route('person.profile', { locale: currentLocale, id: sale.person_id })"
                                    class="text-primary fw-semibold"
                                >
                                    {{ sale.customer }}
                                </Link>
                                <span v-else>{{ sale.customer }}</span>
                            </td>
                            <td>{{ sale.membership_plan }}</td>
                            <td>{{ sale.trainer }}</td>
                            <td>
                                <div>{{ formatDate(sale.start_date) }}</div>
                                <div class="text-muted small">{{ formatDate(sale.end_date) }}</div>
                            </td>
                            <td>{{ formatAmount(sale.total_price) }}</td>
                            <td>
                                <div>{{ t('staff_reports.manual_amount', { amount: formatAmount(sale.manual_discount_amount) }) }}</div>
                                <div class="text-muted small">{{ t('staff_reports.membership_amount', { amount: formatAmount(sale.membership_discount_amount) }) }}</div>
                            </td>
                            <td>{{ formatAmount(sale.final_price) }}</td>
                            <td>{{ formatAmount(sale.paid_amount) }}</td>
                            <td>
                                <span :class="Number(sale.debt || 0) > 0 ? 'text-danger fw-semibold' : ''">
                                    {{ formatAmount(sale.debt) }}
                                </span>
                            </td>
                            <td>
                                <span :class="Number(sale.refund_due_amount || 0) > 0 ? 'text-warning fw-semibold' : ''">
                                    {{ formatAmount(sale.refund_due_amount) }}
                                </span>
                            </td>
                            <td>
                                <span
                                    class="badge"
                                    :class="statusClass(sale.status)"
                                >
                                    {{ statusLabel(sale.status) }}
                                </span>
                            </td>
                            <td>{{ formatDate(sale.created_at) }}</td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td
                                colspan="12"
                                class="text-center text-muted py-4"
                            >
                                {{ t('staff_reports.no_data_2') }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="sales.data.length">
                        <tr class="fw-semibold">
                            <td colspan="4">{{ t('staff_reports.this_page_summary') }}</td>
                            <td>{{ formatAmount(totals.total_amount) }}</td>
                            <td>
                                <div>{{ t('staff_reports.manual_amount', { amount: formatAmount(totals.manual_discount_amount) }) }}</div>
                                <div class="text-muted small">{{ t('staff_reports.membership_amount', { amount: formatAmount(totals.membership_discount_amount) }) }}</div>
                            </td>
                            <td>{{ formatAmount(totals.final_amount) }}</td>
                            <td>{{ formatAmount(totals.paid_amount) }}</td>
                            <td>{{ formatAmount(totals.debt) }}</td>
                            <td>{{ formatAmount(totals.refund_due_amount) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <Pagination
            v-if="sales.links?.length"
            :links="sales.links"
        />
    </Index>
</template>

<style scoped>
.report-icon {
    align-items: center;
    border-radius: 0.5rem;
    display: inline-flex;
    flex: 0 0 2.75rem;
    height: 2.75rem;
    justify-content: center;
    width: 2.75rem;
}
</style>
