<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
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
    trainerCommissions: {
        type: Object,
        required: true,
    },
    salespersonCommissions: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Object,
        default: () => ({
            membershipPlans: [],
            trainers: [],
            salespeople: [],
            trainerStatuses: [],
            salespersonStatuses: [],
        }),
    },
})

const filters = ref({ ...props.filters })

const activeTab = computed(() => {
    const query = page.url?.split('?')[1] ?? window.location.search.replace(/^\?/, '')
    const tab = new URLSearchParams(query).get('tab')

    return tab === 'salesperson' ? 'salesperson' : 'trainer'
})

const withoutPageParams = params => {
    const query = { ...params }

    delete query.trainer_page
    delete query.salesperson_page

    return query
}

const cleanQuery = query => Object.fromEntries(
    Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''),
)

const tabs = computed(() => [
    {
        key: 'trainer',
        label: t('sidebar.trainer_commissions'),
        href: route('reports.commissions', {
            locale: currentLocale.value,
            ...withoutPageParams(filters.value),
            tab: 'trainer',
        }),
    },
    {
        key: 'salesperson',
        label: t('sidebar.salesperson_commissions'),
        href: route('reports.commissions', {
            locale: currentLocale.value,
            ...withoutPageParams(filters.value),
            tab: 'salesperson',
        }),
    },
])

const exportHref = computed(() => route('reports.commissions.export', {
    locale: currentLocale.value,
    ...cleanQuery({
        ...withoutPageParams(filters.value),
        tab: activeTab.value,
    }),
}))

const trainerFilterKeys = [
    'trainer_membership_plan_id',
    'trainer_status',
    'trainer_id',
]

const salespersonFilterKeys = [
    'salesperson_membership_plan_id',
    'salesperson_status',
    'salesperson_id',
]

const activeFilterKeys = computed(() => activeTab.value === 'salesperson'
    ? salespersonFilterKeys
    : trainerFilterKeys)

const trainerFilterFields = computed(() => [
    {
        name: 'trainer_membership_plan_id',
        label: t('membership.plan_type'),
        placeholder: t('sales.all_memberships'),
        options: props.filterOptions.membershipPlans ?? [],
    },
    {
        name: 'trainer_status',
        label: t('status.status'),
        placeholder: t('staff_reports.all_statuses'),
        options: props.filterOptions.trainerStatuses ?? [],
    },
    {
        name: 'trainer_id',
        label: t('roles.trainer'),
        placeholder: t('sales.all_trainers'),
        options: props.filterOptions.trainers ?? [],
    },
])

const salespersonFilterFields = computed(() => [
    {
        name: 'salesperson_membership_plan_id',
        label: t('membership.plan_type'),
        placeholder: t('sales.all_memberships'),
        options: props.filterOptions.membershipPlans ?? [],
    },
    {
        name: 'salesperson_status',
        label: t('status.status'),
        placeholder: t('staff_reports.all_statuses'),
        options: props.filterOptions.salespersonStatuses ?? [],
    },
    {
        name: 'salesperson_id',
        label: t('staff_reports.salesperson'),
        placeholder: t('staff_reports.all_salespeople'),
        options: props.filterOptions.salespeople ?? [],
    },
])

const activeFilterFields = computed(() => activeTab.value === 'salesperson'
    ? salespersonFilterFields.value
    : trainerFilterFields.value)

const activeFilterValues = computed(() => {
    return Object.fromEntries(
        activeFilterKeys.value.map(key => [key, filters.value[key] ?? ''])
    )
})

const clearKeys = (params, keys) => {
    const query = { ...params }

    keys.forEach(key => delete query[key])
    delete query.trainer_page
    delete query.salesperson_page

    return query
}

const routeWithFilters = query => {
    router.get(
        route('reports.commissions', { locale: currentLocale.value }),
        cleanQuery({
            ...withoutPageParams(query),
            tab: activeTab.value,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const updateActiveFilters = payload => {
    filters.value = {
        ...clearKeys(filters.value, activeFilterKeys.value),
        ...payload,
    }
}

const applyFilters = payload => {
    routeWithFilters({
        ...clearKeys(filters.value, activeFilterKeys.value),
        ...payload,
    })
}

const resetFilters = () => {
    const query = clearKeys(filters.value, activeFilterKeys.value)

    filters.value = clearKeys(filters.value, activeFilterKeys.value)

    routeWithFilters(query)
}

const changePeriod = period => {
    const query = {
        ...filters.value,
        period,
    }

    delete query.start_date
    delete query.end_date

    filters.value = {
        ...filters.value,
        period,
    }

    routeWithFilters(query)
}

const applyPeriodFilters = () => {
    routeWithFilters(filters.value)
}

watch(
    () => props.filters,
    value => {
        filters.value = { ...(value ?? {}) }
    },
    { deep: true },
)

const formatAmount = value => {
    return Number(value || 0).toLocaleString('hy-AM', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })
}

const formatDate = value => value ? String(value).slice(0, 16).replace('T', ' ') : '-'

const salaryTypeLabel = type => ({
    fixed: t('staff_reports.fixed'),
    percent: t('staff_reports.percent'),
}[type] ?? type ?? '-')

const statusLabel = status => ({
    pending: t('status.pending'),
    paid: t('people.paid'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')

const statusClass = status => ({
    pending: 'bg-label-warning',
    paid: 'bg-label-success',
    cancelled: 'bg-label-danger',
}[status] ?? 'bg-label-secondary')
</script>

<template>
    <Head :title="t('staff_reports.commission_report')" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">{{ t('staff_reports.commission_report') }}</h2>
                <div class="text-muted">{{ t('staff_reports.trainer_and_salesperson_commissions') }}</div>
            </div>
            <a
                :href="exportHref"
                class="btn btn-outline-success"
            >
                <i class="icon-base ti tabler-file-export me-1"></i>
                {{ t('staff_reports.export_to_excel') }}
            </a>
        </div>

        <div class="d-flex gap-2 flex-wrap mb-4">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                class="btn"
                :class="activeTab === tab.key ? 'btn-primary' : 'btn-outline-primary'"
                :href="tab.href"
                preserve-scroll
            >
                {{ tab.label }}
            </Link>
        </div>

        <PeriodDateRangeFilter
            v-model:selected-period="filters.period"
            v-model:start-date="filters.start_date"
            v-model:end-date="filters.end_date"
            @period-change="changePeriod"
            @apply="applyPeriodFilters"
        />

        <TableFilter
            :key="activeTab"
            :model-value="activeFilterValues"
            :text-fields="[]"
            :select-fields="activeFilterFields"
            :date-fields="[]"
            default-date-field=""
            @update:model-value="updateActiveFilters"
            @filter="applyFilters"
            @reset="resetFilters"
        />

        <div
            v-if="activeTab === 'trainer'"
            class="card"
        >
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h5 class="mb-0">{{ t('sidebar.trainer_commissions') }}</h5>
                <span class="badge bg-label-primary">{{ t('staff_reports.records_count', { count: trainerCommissions.total ?? trainerCommissions.data.length }) }}</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ t('roles.trainer') }}</th>
                            <th>{{ t('sales.client') }}</th>
                            <th>{{ t('people.membership') }}</th>
                            <th>{{ t('people.type') }}</th>
                            <th>{{ t('membership.cost') }}</th>
                            <th>{{ t('people.amount') }}</th>
                            <th>{{ t('status.status') }}</th>
                            <th>{{ t('staff_reports.saved') }}</th>
                            <th>{{ t('staff_reports.paid') }}</th>
                            <th>{{ t('inventory.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="commission in trainerCommissions.data"
                            :key="commission.id"
                        >
                            <td>#{{ commission.id }}</td>
                            <td>{{ commission.trainer }}</td>
                            <td>{{ commission.customer }}</td>
                            <td>{{ commission.membership_plan ?? '-' }}</td>
                            <td>{{ salaryTypeLabel(commission.salary_type) }}</td>
                            <td>{{ formatAmount(commission.salary_value) }}</td>
                            <td>{{ formatAmount(commission.salary_amount) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="statusClass(commission.status)"
                                >
                                    {{ statusLabel(commission.status) }}
                                </span>
                            </td>
                            <td>{{ commission.is_kept ? t('membership.yes') : t('people.no') }}</td>
                            <td>{{ formatDate(commission.paid_at) }}</td>
                            <td>{{ formatDate(commission.created_at) }}</td>
                        </tr>
                        <tr v-if="!trainerCommissions.data.length">
                            <td
                                colspan="11"
                                class="text-center text-muted py-4"
                            >
                                {{ t('staff_reports.there_are_no_trainer_commissions') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="trainerCommissions.links?.length"
                class="card-footer"
            >
                <Pagination :links="trainerCommissions.links" />
            </div>
        </div>

        <div
            v-if="activeTab === 'salesperson'"
            class="card"
        >
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h5 class="mb-0">{{ t('sidebar.salesperson_commissions') }}</h5>
                <span class="badge bg-label-primary">{{ t('staff_reports.records_count', { count: salespersonCommissions.total ?? salespersonCommissions.data.length }) }}</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ t('staff_reports.salesperson') }}</th>
                            <th>{{ t('sales.client') }}</th>
                            <th>{{ t('people.membership') }}</th>
                            <th>{{ t('people.type') }}</th>
                            <th>{{ t('membership.cost') }}</th>
                            <th>{{ t('staff_reports.sale_amount') }}</th>
                            <th>{{ t('staff_reports.commission') }}</th>
                            <th>{{ t('status.status') }}</th>
                            <th>{{ t('staff_reports.paid') }}</th>
                            <th>{{ t('inventory.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="commission in salespersonCommissions.data"
                            :key="commission.id"
                        >
                            <td>#{{ commission.id }}</td>
                            <td>{{ commission.salesperson }}</td>
                            <td>{{ commission.customer }}</td>
                            <td>{{ commission.membership_plan ?? '-' }}</td>
                            <td>{{ salaryTypeLabel(commission.salary_type) }}</td>
                            <td>{{ formatAmount(commission.salary_value) }}</td>
                            <td>{{ formatAmount(commission.sale_amount) }}</td>
                            <td>{{ formatAmount(commission.salary_amount) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="statusClass(commission.status)"
                                >
                                    {{ statusLabel(commission.status) }}
                                </span>
                            </td>
                            <td>{{ formatDate(commission.paid_at) }}</td>
                            <td>{{ formatDate(commission.created_at) }}</td>
                        </tr>
                        <tr v-if="!salespersonCommissions.data.length">
                            <td
                                colspan="11"
                                class="text-center text-muted py-4"
                            >
                                {{ t('staff_reports.there_are_no_salesperson_commissions') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="salespersonCommissions.links?.length"
                class="card-footer"
            >
                <Pagination :links="salespersonCommissions.links" />
            </div>
        </div>
    </Index>
</template>
