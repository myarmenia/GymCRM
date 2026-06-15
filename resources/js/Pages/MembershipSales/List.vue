<script setup>
import { computed, ref, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import TableFilter from '@/Components/TableFilter.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipSales: Object,
    membershipPlans: {
        type: Array,
        default: () => [],
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    discounts: {
        type: Array,
        default: () => [],
    },
})

const salesList = ref(props.membershipSales.data)
const pagination = ref(props.membershipSales)
const queryParams = new URLSearchParams(window.location.search)
const queryValue = name => {
    const values = []

    queryParams.forEach((value, key) => {
        if (key === name || key === `${name}[]` || key.startsWith(`${name}[`)) {
            values.push(value)
        }
    })

    return values[0] ?? ''
}
const filters = ref({
    date_field: queryParams.get('date_field') ?? 'sold_at',
    trainer_id: queryParams.get('trainer_id') ?? '',
    membership_plan_id: queryParams.get('membership_plan_id') ?? '',
    membership_discount_ids: queryValue('membership_discount_ids'),
    manual_discount: queryParams.get('manual_discount') ?? '',
    payment_status: queryParams.get('payment_status') ?? '',
    date_from: queryParams.get('date_from') ?? '',
    date_to: queryParams.get('date_to') ?? '',
})

const optionName = item => {
    return item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
        ?? item?.name
        ?? (item?.id ? `#${item.id}` : '-')
}

const trainerOptionName = trainer => {
    const fullName = `${trainer.name ?? ''} ${trainer.surname ?? ''}`.trim()

    return fullName || trainer.email || `#${trainer.id}`
}

const saleFilterSelectFields = computed(() => [
    {
        name: 'trainer_id',
        label: 'Մարզիչ',
        placeholder: 'Բոլոր մարզիչները',
        options: props.trainers.map(trainer => ({
            value: trainer.id,
            label: trainerOptionName(trainer),
        })),
    },
    {
        name: 'membership_plan_id',
        label: 'Աբոնեմենտ',
        placeholder: 'Բոլոր աբոնեմենտները',
        options: props.membershipPlans.map(plan => ({
            value: plan.id,
            label: optionName(plan),
        })),
    },
    {
        name: 'membership_discount_ids',
        label: 'Աբոնեմենտի զեղչեր',
        placeholder: 'Բոլոր զեղչերը',
        options: props.discounts.map(discount => ({
            value: discount.id,
            label: optionName(discount),
        })),
    },
    {
        name: 'manual_discount',
        label: 'Ձեռքով զեղչ',
        placeholder: 'Բոլորը',
        options: [
            { value: 'with', label: 'Ձեռքով զեղչով' },
            { value: 'without', label: 'Առանց ձեռքով զեղչի' },
        ],
    },
    {
        name: 'payment_status',
        label: 'Վճարման վիճակ',
        placeholder: 'Բոլորը',
        options: [
            { value: 'paid', label: 'Լրիվ վճարում' },
            { value: 'partial', label: 'Մասնակի վճարում' },
        ],
    },
])

const saleFilterDateFields = [
    { value: 'sold_at', label: 'Վաճառքի օր' },
]

watch(
    () => props.membershipSales,
    (membershipSales) => {
        salesList.value = membershipSales.data
        pagination.value = membershipSales
    },
)

const personName = sale => {
    const person = sale.person
    return person ? `${person.name ?? ''} ${person.surname ?? ''}`.trim() : '-'
}

const planName = plan => {
    return plan?.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan?.name
        ?? (plan?.id ? `#${plan.id}` : '-')
}

const trainerName = sale => {
    const trainer = sale.person_memberships?.[0]?.trainer
    return trainer ? `${trainer.name ?? ''} ${trainer.surname ?? ''}`.trim() : '-'
}

const paymentStatusLabel = status => ({
    unpaid: 'Չվճարված',
    partial: 'Մասնակի',
    paid: 'Վճարված',
    refunded: 'Վերադարձված',
    cancelled: 'Չեղարկված',
}[status] ?? status ?? '-')

const statusClass = status => ({
    unpaid: 'bg-label-warning',
    partial: 'bg-label-info',
    paid: 'bg-label-success',
    refunded: 'bg-label-secondary',
    cancelled: 'bg-label-danger',
}[status] ?? 'bg-label-secondary')

const formatDate = value => value ? String(value).slice(0, 10) : '-'

const paidAmount = sale => (sale.payments ?? []).reduce((total, payment) => total + Number(payment.amount || 0), 0)
const debtAmount = sale => Math.max(Number(sale.final_price || 0) - paidAmount(sale), 0)
const membershipDiscountAmount = sale => {
    return (sale.discounts ?? []).reduce((total, discount) => total + Number(discount.discount_amount || 0), 0)
}
const formattedAmount = value => Number(value || 0).toFixed(2)

const applyFilters = (payload) => {
    router.get(
        route('membership_sale.list', { locale: currentLocale.value }),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const resetFilters = () => {
    filters.value = {
        date_field: 'sold_at',
        membership_discount_ids: '',
    }

    router.get(
        route('membership_sale.list', { locale: currentLocale.value }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}
</script>

<template>
    <Head title="Աբոնեմենտների վաճառքներ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Աբոնեմենտների վաճառքներ
            </h2>
        </template>

        <TableFilter
            v-model="filters"
            :text-fields="[]"
            :select-fields="saleFilterSelectFields"
            :date-fields="saleFilterDateFields"
            default-date-field="sold_at"
            @filter="applyFilters"
            @reset="resetFilters"
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Վաճառքների ցանկ
                </h5>

            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Հաճախորդ</th>
                                <th>Աբոնեմենտ</th>
                                <th>Մարզիչ</th>
                                <th>Գին</th>
                                <th>Զեղչեր</th>
                                <th>Վերջնական գին</th>
                                <th>Վճարում</th>
                                <th>Պարտք</th>
                                <th>Վաճառքի օր</th>
                                <th>Գործողություններ</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="sale in salesList"
                                :key="sale.id"
                            >
                                <td>{{ personName(sale) }}</td>
                                <td>{{ planName(sale.membership_plan) }}</td>
                                <td>{{ trainerName(sale) }}</td>
                                <td>{{ sale.total_price }}</td>
                                <td>
                                    <div>
                                        <span class="text-muted">Ձեռքով զեղչ՝</span>
                                        {{ formattedAmount(sale.discount_amount) }}
                                    </div>
                                    <div>
                                        <span class="text-muted">Աբոնեմենտի զեղչ՝</span>
                                        {{ formattedAmount(membershipDiscountAmount(sale)) }}
                                    </div>
                                </td>
                                <td>{{ sale.final_price }}</td>
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="statusClass(sale.payment_status)"
                                    >
                                        {{ paymentStatusLabel(sale.payment_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span
                                        v-if="debtAmount(sale) > 0"
                                        class="text-danger fw-semibold"
                                    >
                                        {{ formattedAmount(debtAmount(sale)) }}
                                    </span>
                                    <span v-else>0</span>
                                </td>
                                <td>{{ formatDate(sale.sold_at) }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button
                                            type="button"
                                            class="btn p-0 dropdown-toggle hide-arrow"
                                            data-bs-toggle="dropdown"
                                        >
                                            <i class="icon-base ti tabler-dots-vertical"></i>
                                        </button>

                                        <div class="dropdown-menu">
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="route('membership_sale.edit', { locale: currentLocale, id: sale.id })"
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Խմբագրել
                                            </Link>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!salesList.length">
                                <td
                                    colspan="10"
                                    class="text-center text-muted"
                                >
                                    Վաճառքներ չկան
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>
