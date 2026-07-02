<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import TableFilter from '@/Components/TableFilter.vue'

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

const tabs = computed(() => [
    {
        key: 'trainer',
        label: 'Մարզիչների միջնորդավճարներ',
        href: route('reports.commissions', {
            locale: currentLocale.value,
            ...withoutPageParams(filters.value),
            tab: 'trainer',
        }),
    },
    {
        key: 'salesperson',
        label: 'Վաճառողների միջնորդավճարներ',
        href: route('reports.commissions', {
            locale: currentLocale.value,
            ...withoutPageParams(filters.value),
            tab: 'salesperson',
        }),
    },
])

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
        label: 'Աբոնեմենտի տեսակ',
        placeholder: 'Բոլոր աբոնեմենտները',
        options: props.filterOptions.membershipPlans ?? [],
    },
    {
        name: 'trainer_status',
        label: 'Կարգավիճակ',
        placeholder: 'Բոլոր կարգավիճակները',
        options: props.filterOptions.trainerStatuses ?? [],
    },
    {
        name: 'trainer_id',
        label: 'Մարզիչ',
        placeholder: 'Բոլոր մարզիչները',
        options: props.filterOptions.trainers ?? [],
    },
])

const salespersonFilterFields = computed(() => [
    {
        name: 'salesperson_membership_plan_id',
        label: 'Աբոնեմենտի տեսակ',
        placeholder: 'Բոլոր աբոնեմենտները',
        options: props.filterOptions.membershipPlans ?? [],
    },
    {
        name: 'salesperson_status',
        label: 'Կարգավիճակ',
        placeholder: 'Բոլոր կարգավիճակները',
        options: props.filterOptions.salespersonStatuses ?? [],
    },
    {
        name: 'salesperson_id',
        label: 'Վաճառող',
        placeholder: 'Բոլոր վաճառողները',
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

const updateActiveFilters = payload => {
    filters.value = {
        ...clearKeys(filters.value, activeFilterKeys.value),
        ...payload,
    }
}

const applyFilters = payload => {
    router.get(
        route('reports.commissions', { locale: currentLocale.value }),
        {
            ...clearKeys(filters.value, activeFilterKeys.value),
            ...payload,
            tab: activeTab.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const resetFilters = () => {
    const query = {
        ...clearKeys(filters.value, activeFilterKeys.value),
        tab: activeTab.value,
    }

    filters.value = clearKeys(filters.value, activeFilterKeys.value)

    router.get(
        route('reports.commissions', { locale: currentLocale.value }),
        query,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
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
    fixed: 'Ֆիքսված',
    percent: 'Տոկոս',
}[type] ?? type ?? '-')

const statusLabel = status => ({
    pending: 'Սպասման մեջ',
    paid: 'Վճարված',
    cancelled: 'Չեղարկված',
}[status] ?? status ?? '-')

const statusClass = status => ({
    pending: 'bg-label-warning',
    paid: 'bg-label-success',
    cancelled: 'bg-label-danger',
}[status] ?? 'bg-label-secondary')
</script>

<template>
    <Head title="Միջնորդավճարների հաշվետվություն" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">Միջնորդավճարների հաշվետվություն</h2>
                <div class="text-muted">Մարզիչների և վաճառողների միջնորդավճարներ</div>
            </div>
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
                <h5 class="mb-0">Մարզիչների միջնորդավճարներ</h5>
                <span class="badge bg-label-primary">{{ trainerCommissions.total ?? trainerCommissions.data.length }} գրառում</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Մարզիչ</th>
                            <th>Հաճախորդ</th>
                            <th>Աբոնեմենտ</th>
                            <th>Տեսակ</th>
                            <th>Արժեք</th>
                            <th>Գումար</th>
                            <th>Կարգավիճակ</th>
                            <th>Պահված է</th>
                            <th>Վճարվել է</th>
                            <th>Ստեղծվել է</th>
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
                            <td>{{ commission.is_kept ? 'Այո' : 'Ոչ' }}</td>
                            <td>{{ formatDate(commission.paid_at) }}</td>
                            <td>{{ formatDate(commission.created_at) }}</td>
                        </tr>
                        <tr v-if="!trainerCommissions.data.length">
                            <td
                                colspan="11"
                                class="text-center text-muted py-4"
                            >
                                Մարզիչների միջնորդավճարներ չկան։
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
                <h5 class="mb-0">Վաճառողների միջնորդավճարներ</h5>
                <span class="badge bg-label-primary">{{ salespersonCommissions.total ?? salespersonCommissions.data.length }} գրառում</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Վաճառող</th>
                            <th>Հաճախորդ</th>
                            <th>Աբոնեմենտ</th>
                            <th>Տեսակ</th>
                            <th>Արժեք</th>
                            <th>Վաճառքի գումար</th>
                            <th>Միջնորդավճար</th>
                            <th>Կարգավիճակ</th>
                            <th>Վճարվել է</th>
                            <th>Ստեղծվել է</th>
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
                                Վաճառողների միջնորդավճարներ չկան։
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
