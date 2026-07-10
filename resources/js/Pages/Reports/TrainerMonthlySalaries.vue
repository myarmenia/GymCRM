<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import TableFilter from '@/Components/TableFilter.vue'
import PeriodDateRangeFilter from '@/Components/Reports/PeriodDateRangeFilter.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    salaries: {
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
            trainers: [],
            statuses: [],
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
        route('reports.trainer-monthly-salaries', { locale: currentLocale.value }),
        cleanQuery(withoutPageParams(query)),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const exportHref = computed(() => route('reports.trainer-monthly-salaries.export', {
    locale: currentLocale.value,
    ...cleanQuery(withoutPageParams(filters.value)),
}))

const filterFields = computed(() => [
    {
        name: 'trainer_id',
        label: 'Մարզիչ',
        placeholder: 'Բոլոր մարզիչները',
        options: props.filterOptions.trainers ?? [],
    },
    {
        name: 'status',
        label: 'Կարգավիճակ',
        placeholder: 'Բոլոր կարգավիճակները',
        options: props.filterOptions.statuses ?? [],
    },
])

const filterValues = computed(() => ({
    trainer_id: filters.value.trainer_id ?? '',
    status: filters.value.status ?? '',
}))

const summaryCards = computed(() => [
    { label: 'Գրանցումների քանակ', value: props.summary.salaries_count ?? 0, icon: 'tabler-list-numbers', class: 'bg-label-primary text-primary' },
    { label: 'Ընդհանուր գումար', value: formatAmount(props.summary.total_price), icon: 'tabler-cash', class: 'bg-label-info text-info' },
    { label: 'Վճարված գումար', value: formatAmount(props.summary.paid_price), icon: 'tabler-check', class: 'bg-label-success text-success' },
    { label: 'Սպասող գումար', value: formatAmount(props.summary.pending_price), icon: 'tabler-clock', class: 'bg-label-warning text-warning' },
])

const updateFilters = payload => {
    filters.value = {
        ...filters.value,
        trainer_id: payload.trainer_id ?? '',
        status: payload.status ?? '',
    }
}

const applyFilters = payload => {
    updateFilters(payload)
    routeWithFilters(filters.value)
}

const resetFilters = () => {
    filters.value = {
        ...filters.value,
        trainer_id: '',
        status: '',
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

const formatAmount = value => Number(value || 0).toLocaleString('hy-AM', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
})

const formatDate = value => value ? String(value).slice(0, 10) : '-'

const statusLabel = status => ({
    pending: 'Սպասման մեջ',
    paid: 'Վճարված',
    transfer: 'Փոխանցում',
    cancel: 'Չեղարկված',
    reject: 'Մերժված',
}[status] ?? status ?? '-')

const statusClass = status => ({
    pending: 'bg-label-warning',
    paid: 'bg-label-success',
    transfer: 'bg-label-info',
    cancel: 'bg-label-danger',
    reject: 'bg-label-secondary',
}[status] ?? 'bg-label-secondary')
</script>

<template>
    <Head title="Մարզիչների աշխատավարձեր" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">Մարզիչների աշխատավարձեր</h2>
                <div class="text-muted">
                    {{ formatDate(filters.start_date) }} - {{ formatDate(filters.end_date) }}
                </div>
            </div>
            <a
                :href="exportHref"
                class="btn btn-outline-success"
            >
                <i class="icon-base ti tabler-file-export me-1"></i>
                Արտահանել Excel
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
            :text-fields="[]"
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
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h5 class="mb-0">Մարզիչների աշխատավարձեր</h5>
                <span class="badge bg-label-primary">{{ salaries.total ?? salaries.data.length }} գրառում</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Մարզիչ</th>
                            <th>Աբոնեմենտ / հաճախորդ</th>
                            <th>Աշխատավարձի ամիս</th>
                            <th>Գումար</th>
                            <th>Կարգավիճակ</th>
                            <th>Ստեղծվել է</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="salary in salaries.data"
                            :key="salary.id"
                        >
                            <td>{{ salary.trainer }}</td>
                            <td>{{ salary.membership_customer }}</td>
                            <td>{{ formatDate(salary.salary_month) }}</td>
                            <td>{{ formatAmount(salary.price) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="statusClass(salary.status)"
                                >
                                    {{ statusLabel(salary.status) }}
                                </span>
                            </td>
                            <td>{{ formatDate(salary.created_at) }}</td>
                        </tr>
                        <tr v-if="!salaries.data.length">
                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >
                                Տվյալներ չկան։
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="salaries.links?.length"
                class="card-footer"
            >
                <Pagination :links="salaries.links" />
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
