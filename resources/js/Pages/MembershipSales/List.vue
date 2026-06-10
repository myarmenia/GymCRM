<script setup>
import { computed, ref, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipSales: Object,
})

const salesList = ref(props.membershipSales.data)
const pagination = ref(props.membershipSales)

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

const destroy = sale => {
    if (!window.confirm('Ջնջե՞լ այս վաճառքը։')) {
        return
    }

    router.delete(
        route('membership_sale.destroy', {
            locale: currentLocale.value,
            id: sale.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                salesList.value = salesList.value.filter(item => item.id !== sale.id)
            },
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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Վաճառքների ցանկ
                </h5>

                <Link
                    class="btn create-new btn-primary"
                    :href="route('membership_sale.create', { locale: currentLocale })"
                >
                    <span class="d-flex align-items-center gap-2">
                        <i class="icon-base ti tabler-plus icon-sm"></i>
                        <span class="d-none d-sm-inline-block">
                            Նոր վաճառք
                        </span>
                    </span>
                </Link>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Հաճախորդ</th>
                                <th>Աբոնեմենտ</th>
                                <th>Մարզասրահ</th>
                                <th>Մարզիչ</th>
                                <th>Գին</th>
                                <th>Զեղչ</th>
                                <th>Վերջնական գին</th>
                                <th>Վճարում</th>
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
                                <td>{{ sale.gym?.name ?? '-' }}</td>
                                <td>{{ trainerName(sale) }}</td>
                                <td>{{ sale.total_price }}</td>
                                <td>{{ sale.discount_amount }}</td>
                                <td>{{ sale.final_price }}</td>
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="statusClass(sale.payment_status)"
                                    >
                                        {{ paymentStatusLabel(sale.payment_status) }}
                                    </span>
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

                                            <button
                                                type="button"
                                                class="dropdown-item waves-effect"
                                                @click="destroy(sale)"
                                            >
                                                <i class="icon-base ti tabler-trash me-1"></i>
                                                Ջնջել
                                            </button>
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
