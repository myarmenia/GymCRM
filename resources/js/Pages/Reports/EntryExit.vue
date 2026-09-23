<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import TableFilter from '@/Components/TableFilter.vue'
import PeriodDateRangeFilter from '@/Components/Reports/PeriodDateRangeFilter.vue'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    visits: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        required: true,
    },
    filterOptions: {
        type: Object,
        default: () => ({
            ownerTypes: [],
            personTypes: [],
            clients: [],
            canSelectClient: false,
        }),
    },
})

const filters = ref({ ...props.filters })

const cleanQuery = query => Object.fromEntries(
    Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''),
)

const withoutPageParams = params => {
    const query = { ...params }

    delete query.page

    return query
}

const routeWithFilters = query => {
    router.get(
        route('reports.entry-exit', { locale: currentLocale.value }),
        cleanQuery(withoutPageParams(query)),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const exportHref = computed(() => route('reports.entry-exit.export', {
    locale: currentLocale.value,
    ...cleanQuery(withoutPageParams(filters.value)),
}))

const textFields = [
    {
        name: 'search',
        label: t('inventory.search_2'),
        placeholder: t('staff_reports.name_phone_email_entry_code_mac'),
        col: 'col-md-4',
    },
]

const filterFields = computed(() => [
    {
        name: 'owner_type',
        label: t('people.type'),
        placeholder: t('common.all'),
        options: props.filterOptions.ownerTypes ?? [],
    },
    {
        name: 'person_type',
        label: t('staff_reports.customer_guest'),
        placeholder: t('common.all'),
        options: props.filterOptions.personTypes ?? [],
    },
    ...(props.filterOptions.canSelectClient ? [{
        name: 'client_id',
        label: t('staff_reports.branch'),
        placeholder: t('staff_reports.all_branches'),
        options: (props.filterOptions.clients ?? []).map(client => ({
            value: client.id,
            label: client.name,
        })),
    }] : []),
])

const filterValues = computed(() => ({
    search: filters.value.search ?? '',
    owner_type: filters.value.owner_type ?? '',
    person_type: filters.value.person_type ?? '',
    client_id: filters.value.client_id ?? '',
}))

const summaryCards = computed(() => [
    { label: t('staff_reports.total_entries'), value: props.summary.entry_count ?? 0, icon: 'tabler-door-enter', class: 'bg-label-primary text-primary' },
    { label: t('staff_reports.total_exits'), value: props.summary.exit_count ?? 0, icon: 'tabler-door-exit', class: 'bg-label-info text-info' },
    { label: t('staff_reports.unique_customers'), value: props.summary.unique_customers_count ?? 0, icon: 'tabler-users', class: 'bg-label-success text-success' },
    { label: t('staff_reports.currently_inside'), value: props.summary.currently_inside_count ?? 0, icon: 'tabler-map-pin', class: 'bg-label-warning text-warning' },
    { label: t('staff_reports.guests_inside'), value: props.summary.currently_inside_guests_count ?? 0, icon: 'tabler-user-star', class: 'bg-label-secondary text-secondary' },
    { label: t('people.visits'), value: props.summary.total_visits_count ?? 0, icon: 'tabler-calendar-check', class: 'bg-label-dark text-dark' },
    { label: t('staff_reports.new_visits'), value: props.summary.new_customer_visits_count ?? 0, icon: 'tabler-user-plus', class: 'bg-label-success text-success' },
    { label: t('staff_reports.repeat_visits'), value: props.summary.repeat_visits_count ?? 0, icon: 'tabler-repeat', class: 'bg-label-info text-info' },
    { label: t('staff_reports.today'), value: props.summary.today_visits_count ?? 0, icon: 'tabler-calendar', class: 'bg-label-primary text-primary' },
    { label: t('staff_reports.this_week'), value: props.summary.week_visits_count ?? 0, icon: 'tabler-calendar-week', class: 'bg-label-warning text-warning' },
    { label: t('people.this_month'), value: props.summary.month_visits_count ?? 0, icon: 'tabler-calendar-month', class: 'bg-label-secondary text-secondary' },
])

const busiestDaysText = computed(() => formatStats(props.summary.busiest_days))
const busiestHoursText = computed(() => formatStats(props.summary.busiest_hours))

const updateFilters = payload => {
    filters.value = {
        ...filters.value,
        search: payload.search ?? '',
        owner_type: payload.owner_type ?? '',
        person_type: payload.person_type ?? '',
        client_id: payload.client_id ?? '',
    }
}

const applyFilters = payload => {
    updateFilters(payload)
    routeWithFilters(filters.value)
}

const resetFilters = () => {
    filters.value = {
        ...filters.value,
        search: '',
        owner_type: '',
        person_type: '',
        client_id: '',
    }

    routeWithFilters(filters.value)
}

const changePeriod = period => {
    const query = {
        ...filters.value,
        period,
    }

    delete query.start_date
    delete query.end_date

    filters.value = query
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

const formatDateTime = value => value ? String(value).slice(0, 16) : '-'

const formatStats = value => {
    const items = Array.isArray(value) ? value : []

    if (!items.length) {
        return '-'
    }

    return items.map(item => `${item.label}: ${item.value}`).join(', ')
}

const statusClass = status => ({
    inside: 'bg-label-warning',
    exited: 'bg-label-success',
}[status] ?? 'bg-label-secondary')
</script>

<template>
    <Head :title="t('sidebar.report_entry_exit')" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">{{ t('sidebar.report_entry_exit') }}</h2>
                <div class="text-muted">
                    {{ filters.start_date }} - {{ filters.end_date }}
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
            v-model:selected-period="filters.period"
            v-model:start-date="filters.start_date"
            v-model:end-date="filters.end_date"
            @period-change="changePeriod"
            @apply="applyPeriodFilters"
        />

        <TableFilter
            :model-value="filterValues"
            :text-fields="textFields"
            :select-fields="filterFields"
            :date-fields="[]"
            default-date-field=""
            @update:model-value="updateFilters"
            @filter="applyFilters"
            @reset="resetFilters"
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
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ t('staff_reports.busiest_days') }}</div>
                        <div class="h6 mb-0">{{ busiestDaysText }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ t('staff_reports.busiest_hours') }}</div>
                        <div class="h6 mb-0">{{ busiestHoursText }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h5 class="mb-0">{{ t('staff_reports.entry_exit_history') }}</h5>
                <span class="badge bg-label-primary">{{ t('staff_reports.records_count', { count: visits.total ?? visits.data.length }) }}</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ t('sales.client') }}</th>
                            <th>{{ t('people.guest') }}</th>
                            <th>{{ t('people.entry_code') }}</th>
                            <th>{{ t('staff_reports.entry_time') }}</th>
                            <th>{{ t('staff_reports.exit_time') }}</th>
                            <th>{{ t('staff_reports.visit_duration') }}</th>
                            <th>{{ t('status.status') }}</th>
                            <th>{{ t('filter.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="visit in visits.data"
                            :key="visit.id"
                        >
                            <td>{{ visit.customer }}</td>
                            <td>{{ visit.guest }}</td>
                            <td>{{ visit.entry_code ?? '-' }}</td>
                            <td>{{ formatDateTime(visit.entry_at) }}</td>
                            <td>{{ formatDateTime(visit.exit_at) }}</td>
                            <td>{{ visit.duration }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="statusClass(visit.visit_status)"
                                >
                                    {{ visit.visit_status_label }}
                                </span>
                            </td>
                            <td>{{ formatDateTime(visit.created_at) }}</td>
                        </tr>
                        <tr v-if="!visits.data.length">
                            <td
                                colspan="8"
                                class="text-center text-muted py-4"
                            >
                                {{ t('staff_reports.no_data_2') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="visits.links?.length"
                class="card-footer"
            >
                <Pagination :links="visits.links" />
            </div>
        </div>
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
