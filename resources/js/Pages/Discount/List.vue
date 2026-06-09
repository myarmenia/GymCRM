<script setup>
import { computed, ref, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useTrans } from '/resources/js/trans'
import ToggleStatus from '@/Components/ToggleStatus.vue'
import DeleteButton from '@/Components/DeleteButton.vue'
import Pagination from '@/Components/Pagination.vue'
import TableFilter from '@/Components/TableFilter.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    discounts: Object,
    membershipPlans: {
        type: Array,
        default: () => [],
    },
})

const discountsList = ref(props.discounts.data)
const pagination = ref(props.discounts)
const filters = ref({
    date_field: 'start_date',
    ...Object.fromEntries(new URLSearchParams(window.location.search)),
})

const discountName = discount => {
    return discount.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? discount.name
        ?? '-'
}

const planNames = discount => {
    if (!discount.membership_plans?.length) {
        return '-'
    }

    return discount.membership_plans
        .map(plan => plan.translations?.find(item => item.locale === currentLocale.value)?.name ?? plan.name ?? `#${plan.id}`)
        .join(', ')
}

const discountTypeLabel = type => {
    return type === 'percent' ? 'Տոկոս' : 'Ֆիքսված'
}
const discountFilterSelectFields = computed(() => [
    {
        name: 'type',
        label: 'Զեղչի տեսակ',
        placeholder: 'Բոլոր տեսակները',
        options: [
            { value: 'percent', label: 'Տոկոս' },
            { value: 'fixed', label: 'Ֆիքսված' },
        ],
    },
    {
        name: 'membership_plan_id',
        label: 'Անդամակցության պլան',
        placeholder: 'Բոլոր պլանները',
        options: props.membershipPlans.map(plan => ({
            value: plan.id,
            label: plan.translations?.find(item => item.locale === currentLocale.value)?.name
                ?? plan.name
                ?? `#${plan.id}`,
        })),
    },
])

const discountFilterDateFields = [
    { value: 'start_date', label: 'Սկսման ամսաթիվ' },
    { value: 'end_date', label: 'Ավարտի ամսաթիվ' },
]

watch(
    () => props.discounts,
    (discounts) => {
        discountsList.value = discounts.data
        pagination.value = discounts
    },
)

const applyFilters = (payload) => {
    router.get(
        route('discount.list', { locale: currentLocale.value }),
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
        date_field: 'start_date',
    }

    router.get(
        route('discount.list', { locale: currentLocale.value }),
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
    <Head title="Զեղչեր" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Զեղչեր
            </h2>
        </template>

        <TableFilter
            v-model="filters"
            :text-fields="[]"
            :select-fields="discountFilterSelectFields"
            :date-fields="discountFilterDateFields"
            default-date-field="start_date"
            @filter="applyFilters"
            @reset="resetFilters"
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Զեղչերի ցանկ
                </h5>

                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    type="button"
                    :href="route('discount.create', { locale: currentLocale })"
                >
                    <span class="d-flex align-items-center gap-2">
                        <i class="icon-base ti tabler-plus icon-sm"></i>
                        <span class="d-none d-sm-inline-block">
                            Ստեղծել նոր զեղչ
                        </span>
                    </span>
                </Link>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Անվանում</th>
                                <th>Տեսակ</th>
                                <th>Արժեք</th>
                                <th>Սկիզբ</th>
                                <th>Ավարտ</th>
                                <th>Անդամակցության պլաններ</th>
                                <th>Կարգավիճակ</th>
                                <th>Գործողություններ</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="discount in discountsList"
                                :key="discount.id"
                            >
                                <td>{{ discount.id }}</td>
                                <td>{{ discountName(discount) }}</td>
                                <td>{{ discountTypeLabel(discount.type) }}</td>
                                <td>{{ discount.type === 'percent' ? `${discount.value}%` : discount.value }}</td>
                                <td>{{ discount.start_date ?? '-' }}</td>
                                <td>{{ discount.end_date ?? '-' }}</td>
                                <td>{{ planNames(discount) }}</td>
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="discount.status ? 'bg-label-success' : 'bg-label-danger'"
                                    >
                                        {{
                                            discount.status
                                                ? useTrans('app.status.active')
                                                : useTrans('app.status.inactive')
                                        }}
                                    </span>
                                </td>
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
                                            <a
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <ToggleStatus
                                                    :model="'discounts'"
                                                    :model-id="discount.id"
                                                    :active="Boolean(discount.status)"
                                                    :prefix="'discount'"
                                                    :locale="currentLocale"
                                                    :column="'status'"
                                                    :label="useTrans('app.status.status')"
                                                    @update="discount.status = $event"
                                                />
                                            </a>

                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="route('discount.edit', { locale: currentLocale, id: discount.id })"
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Խմբագրել
                                            </Link>

                                            <a
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <DeleteButton
                                                    :model="'discounts'"
                                                    :model-id="discount.id"
                                                    :prefix="'discount'"
                                                    :locale="currentLocale"
                                                    @deleted="discountsList = discountsList.filter(item => item.id !== $event)"
                                                />
                                            </a>
                                        </div>
                                    </div>
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
