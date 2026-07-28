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
    commissions: {
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
            membershipPlans: [],
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
        route('reports.trainer-commissions', { locale: currentLocale.value }),
        cleanQuery(withoutPageParams(query)),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const exportHref = computed(() => route('reports.trainer-commissions.export', {
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
        name: 'membership_plan_id',
        label: 'Աբոնեմենտի տեսակ',
        placeholder: 'Բոլոր աբոնեմենտները',
        options: props.filterOptions.membershipPlans ?? [],
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
    membership_plan_id: filters.value.membership_plan_id ?? '',
    status: filters.value.status ?? '',
}))

const summaryCards = computed(() => [
    { label: 'Գրանցումների քանակ', value: props.summary.commissions_count ?? 0, icon: 'tabler-list-numbers', class: 'bg-label-primary text-primary' },
    { label: 'Միջնորդավճար', value: formatAmount(props.summary.total_commission_amount), icon: 'tabler-cash', class: 'bg-label-success text-success' },
    { label: 'Վճարված միջնորդավճար', value: formatAmount(props.summary.paid_commission_amount), icon: 'tabler-check', class: 'bg-label-info text-info' },
    { label: 'Սպասող միջնորդավճար', value: formatAmount(props.summary.pending_commission_amount), icon: 'tabler-clock', class: 'bg-label-warning text-warning' },
    { label: 'Վերադարձված', value: formatAmount(props.summary.refunded_commission_amount), icon: 'tabler-arrow-back-up', class: 'bg-label-danger text-danger' },
    { label: 'Փոխանցված մուտք', value: formatAmount(props.summary.transferred_in_amount), icon: 'tabler-arrow-down-left', class: 'bg-label-success text-success' },
    { label: 'Փոխանցված ելք', value: formatAmount(props.summary.transferred_out_amount), icon: 'tabler-arrow-up-right', class: 'bg-label-secondary text-secondary' },
    { label: 'Պահված միջնորդավճարներ', value: props.summary.kept_commissions_count ?? 0, icon: 'tabler-lock', class: 'bg-label-secondary text-secondary' },
])

const updateFilters = payload => {
    filters.value = {
        ...filters.value,
        trainer_id: payload.trainer_id ?? '',
        membership_plan_id: payload.membership_plan_id ?? '',
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
        membership_plan_id: '',
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

const salaryTypeLabel = type => ({
    fixed: 'Ֆիքսված',
    percent: 'Տոկոս',
}[type] ?? type ?? '-')

const statusLabel = status => ({
    pending: 'Սպասման մեջ',
    partial: 'Մասնակի վճարված',
    paid: 'Վճարված',
    transferred: 'Փոխանցված',
}[status] ?? status ?? '-')

const statusClass = status => ({
    pending: 'bg-label-warning',
    partial: 'bg-label-info',
    paid: 'bg-label-success',
    transferred: 'bg-label-secondary',
}[status] ?? 'bg-label-secondary')
</script>

<template>
    <Head title="Մարզիչների միջնորդավճարներ" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">Մարզիչների միջնորդավճարներ</h2>
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
                <h5 class="mb-0">Մարզիչների միջնորդավճարներ</h5>
                <span class="badge bg-label-primary">{{ commissions.total ?? commissions.data.length }} գրառում</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Մարզիչ</th>
                            <th>Աբոնեմենտ</th>
                            <th>Հաճախորդ</th>
                            <th>Միջնորդավճարի տեսակ</th>
                            <th>Միջնորդավճարի արժեք</th>
                            <th>Ընդհանուր վերագրված</th>
                            <th>Զուտ վճարված</th>
                            <th>Չվճարված մնացորդ</th>
                            <th>Վերադարձված</th>
                            <th>Փոխանցված մուտք</th>
                            <th>Փոխանցված ելք</th>
                            <th>Կարգավիճակ</th>
                            <th>Պահված է</th>
                            <th>Ստեղծվել է</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="commission in commissions.data"
                            :key="commission.id"
                        >
                            <td>{{ commission.trainer }}</td>
                            <td>{{ commission.membership_plan }}</td>
                            <td>{{ commission.customer }}</td>
                            <td>{{ salaryTypeLabel(commission.salary_type) }}</td>
                            <td>{{ formatAmount(commission.salary_value) }}</td>
                            <td>{{ formatAmount(commission.salary_amount) }}</td>
                            <td>{{ formatAmount(commission.net_paid_amount) }}</td>
                            <td>{{ formatAmount(commission.outstanding_amount) }}</td>
                            <td>{{ formatAmount(commission.refunded_amount) }}</td>
                            <td>{{ formatAmount(commission.transferred_in_amount) }}</td>
                            <td>{{ formatAmount(commission.transferred_out_amount) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="statusClass(commission.status)"
                                >
                                    {{ statusLabel(commission.status) }}
                                </span>
                            </td>
                            <td>{{ commission.is_kept ? 'Այո' : 'Ոչ' }}</td>
                            <td>{{ formatDate(commission.created_at) }}</td>
                        </tr>
                        <tr v-if="!commissions.data.length">
                            <td
                                colspan="14"
                                class="text-center text-muted py-4"
                            >
                                Տվյալներ չկան։
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="commissions.links?.length"
                class="card-footer"
            >
                <Pagination :links="commissions.links" />
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
