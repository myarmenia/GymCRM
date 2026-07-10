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
        label: 'Որոնում',
        placeholder: 'Անուն, հեռախոս, email, entry code, MAC',
        col: 'col-md-4',
    },
]

const filterFields = computed(() => [
    {
        name: 'owner_type',
        label: 'Տեսակ',
        placeholder: 'Բոլորը',
        options: props.filterOptions.ownerTypes ?? [],
    },
    {
        name: 'person_type',
        label: 'Հաճախորդ / հյուր',
        placeholder: 'Բոլորը',
        options: props.filterOptions.personTypes ?? [],
    },
    ...(props.filterOptions.canSelectClient ? [{
        name: 'client_id',
        label: 'Մասնաճյուղ',
        placeholder: 'Բոլոր մասնաճյուղերը',
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
    { label: 'Ընդհանուր մուտքեր', value: props.summary.entry_count ?? 0, icon: 'tabler-door-enter', class: 'bg-label-primary text-primary' },
    { label: 'Ընդհանուր ելքեր', value: props.summary.exit_count ?? 0, icon: 'tabler-door-exit', class: 'bg-label-info text-info' },
    { label: 'Եզակի հաճախորդներ', value: props.summary.unique_customers_count ?? 0, icon: 'tabler-users', class: 'bg-label-success text-success' },
    { label: 'Այս պահին ներսում', value: props.summary.currently_inside_count ?? 0, icon: 'tabler-map-pin', class: 'bg-label-warning text-warning' },
    { label: 'Ներսում հյուրեր', value: props.summary.currently_inside_guests_count ?? 0, icon: 'tabler-user-star', class: 'bg-label-secondary text-secondary' },
    { label: 'Այցելություններ', value: props.summary.total_visits_count ?? 0, icon: 'tabler-calendar-check', class: 'bg-label-dark text-dark' },
    { label: 'Նոր այցելություններ', value: props.summary.new_customer_visits_count ?? 0, icon: 'tabler-user-plus', class: 'bg-label-success text-success' },
    { label: 'Կրկնակի այցելություններ', value: props.summary.repeat_visits_count ?? 0, icon: 'tabler-repeat', class: 'bg-label-info text-info' },
    { label: 'Այսօր', value: props.summary.today_visits_count ?? 0, icon: 'tabler-calendar', class: 'bg-label-primary text-primary' },
    { label: 'Այս շաբաթ', value: props.summary.week_visits_count ?? 0, icon: 'tabler-calendar-week', class: 'bg-label-warning text-warning' },
    { label: 'Այս ամիս', value: props.summary.month_visits_count ?? 0, icon: 'tabler-calendar-month', class: 'bg-label-secondary text-secondary' },
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
    <Head title="Մուտք / Ելք" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">Մուտք / Ելք</h2>
                <div class="text-muted">
                    {{ filters.start_date }} - {{ filters.end_date }}
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
                        <div class="text-muted small">Ամենածանրաբեռնված օրերը</div>
                        <div class="h6 mb-0">{{ busiestDaysText }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Ամենածանրաբեռնված ժամերը</div>
                        <div class="h6 mb-0">{{ busiestHoursText }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h5 class="mb-0">Մուտք / Ելք պատմություն</h5>
                <span class="badge bg-label-primary">{{ visits.total ?? visits.data.length }} գրառում</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Հաճախորդ</th>
                            <th>Հյուր</th>
                            <th>Entry Code</th>
                            <th>Մուտքի ժամանակ</th>
                            <th>Ելքի ժամանակ</th>
                            <th>Այցի տևողություն</th>
                            <th>Կարգավիճակ</th>
                            <th>Ստեղծման ամսաթիվ</th>
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
                                Տվյալներ չկան։
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
