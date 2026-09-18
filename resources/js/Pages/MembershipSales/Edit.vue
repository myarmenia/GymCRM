<script setup>
import { translate } from '/resources/js/trans'
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const t = (key, replacements = {}) => translate(page.props.translations, `app.${key}`, replacements)
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipSale: Object,
    membershipPlans: {
        type: Array,
        default: () => [],
    },
    people: {
        type: Array,
        default: () => [],
    },
    customerMemberships: {
        type: Array,
        default: () => [],
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    discountTypes: {
        type: Array,
        default: () => [],
    },
    discountsLocked: {
        type: Boolean,
        default: false,
    },
})

const membership = computed(() => props.membershipSale.person_memberships?.[0] ?? null)
const payment = computed(() => props.membershipSale.payments?.[0] ?? null)

const toDate = value => value ? String(value).slice(0, 10) : ''

const form = useForm({
    person_id: props.membershipSale.person_id ?? '',
    membership_plan_id: props.membershipSale.membership_plan_id ?? '',
    membership_discount_ids: [],
    start_date: toDate(membership.value?.start_date),
    end_date: toDate(membership.value?.end_date),
    apply_discount: Number(props.membershipSale.discount_amount || 0) > 0,
    discount_type: props.membershipSale.discount_type ?? props.discountTypes[0] ?? '',
    discount_value: props.membershipSale.discount_value ?? null,
    is_hdm: Boolean(props.membershipSale.is_hdm ?? payment.value?.is_hdm),
    notes: props.membershipSale.notes ?? '',
    trainer_id: membership.value?.trainer_id ?? '',
    is_partial_payment: Boolean(payment.value?.amount && Number(payment.value.amount) < Number(props.membershipSale.final_price || 0)),
    is_full_payment: Boolean(payment.value?.amount && Number(payment.value.amount) >= Number(props.membershipSale.final_price || 0)),
    amount: payment.value?.amount ?? 0,
    payment_method_id: payment.value?.payment_method_id ?? '',
    card_type_id: payment.value?.card_type_id ?? '',
    payment_type: payment.value?.type ?? 'payment',
    payment_record_status: payment.value?.status ?? '',
    payment_notes: payment.value?.notes ?? '',
})

const planName = plan => {
    return plan?.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan?.name
        ?? (plan?.id ? `#${plan.id}` : '-')
}

const selectedPlan = computed(() => {
    return props.membershipSale.membership_plan
        ?? props.membershipPlans.find(item => Number(item.id) === Number(props.membershipSale.membership_plan_id))
})
const matchingCustomerMemberships = computed(() => {
    if (!selectedPlan.value) {
        return []
    }

    return props.customerMemberships.filter(membershipItem => Number(membershipItem.membership_plan_id) === Number(selectedPlan.value.id))
})

const selectedPerson = computed(() => {
    return props.membershipSale.person
        ?? props.people.find(item => Number(item.id) === Number(props.membershipSale.person_id))
})

const selectedTrainer = computed(() => {
    return membership.value?.trainer
        ?? selectedPlan.value?.trainers?.find(item => Number(item.id) === Number(membership.value?.trainer_id))
        ?? null
})

const selectedPaymentMethod = computed(() => {
    return payment.value?.payment_method
        ?? props.paymentMethods.find(item => Number(item.id) === Number(payment.value?.payment_method_id))
        ?? null
})

const selectedCardType = computed(() => {
    return payment.value?.card_type
        ?? selectedPaymentMethod.value?.card_types?.find(item => Number(item.id) === Number(payment.value?.card_type_id))
        ?? selectedPaymentMethod.value?.cardTypes?.find(item => Number(item.id) === Number(payment.value?.card_type_id))
        ?? null
})

const formatPerson = person => `${person?.name ?? ''} ${person?.surname ?? ''}`.trim() || '-'
const formatUser = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || '-'
const discountName = discount => discount?.translations?.find(item => item.locale === currentLocale.value)?.name ?? discount?.name ?? (discount ? `#${discount.id}` : '-')
const paymentMethodName = method => method?.translations?.find(item => item.locale === currentLocale.value)?.name ?? method?.name ?? method?.slug ?? '-'
const cardTypeName = cardType => cardType?.name ?? cardType?.slug ?? (cardType?.id ? `#${cardType.id}` : '-')

const discountTypeLabel = type => ({
    fixed: t('sales.fixed_amount'),
    percent: t('sales.percent'),
}[type] ?? type ?? '-')

const paymentTypeLabel = type => ({
    payment: t('people.payment'),
    refund: t('people.refund'),
}[type] ?? type ?? '-')

const paymentStatusLabel = status => ({
    unpaid: t('people.unpaid'),
    pending: t('people.waiting'),
    paid: t('people.paid'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')

const saleStatusLabel = status => ({
    unpaid: t('people.unpaid'),
    partial: t('sales.partial'),
    paid: t('people.paid'),
    refunded: t('sales.refunded'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')
const planTypeLabel = type => ({
    day: t('sales.daily'),
    month: t('sales.monthly'),
    year: t('sales.yearly'),
    visit: t('sales.by_visits'),
    period: t('membership.period'),
}[type] ?? type ?? '-')
const membershipStatusLabel = status => ({
    waiting: t('people.waiting'),
    active: t('membership.active'),
    frozen: t('people.frozen'),
    expired: t('people.expired'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const membershipPlanName = membershipItem => planName(membershipItem?.membership_plan)
const membershipValidityDate = membershipItem => membershipItem?.valid_at ?? membershipItem?.end_date
const durationLabel = plan => {
    if (!plan) {
        return '-'
    }

    return plan.duration_value ?? '-'
}

const planPrice = computed(() => Number(props.membershipSale.total_price ?? selectedPlan.value?.price ?? 0))
const hasExistingManualDiscount = computed(() => Number(props.membershipSale.discount_amount || 0) > 0)
const membershipDiscounts = computed(() => selectedPlan.value?.discounts ?? [])
const existingMembershipDiscountIds = computed(() => {
    return (props.membershipSale.discounts ?? []).map(item => Number(item.discount_id))
})
const existingMembershipDiscounts = computed(() => {
    return (props.membershipSale.discounts ?? []).map(item => {
        return item.discount ?? {
            id: item.discount_id,
            type: item.discount_type,
            value: item.discount_value,
        }
    })
})
const availableMembershipDiscounts = computed(() => {
    if (props.discountsLocked) {
        return []
    }

    return membershipDiscounts.value.filter(discount => !existingMembershipDiscountIds.value.includes(Number(discount.id)))
})

const calculateDiscountAmount = (type, value, basePrice) => {
    const numericValue = Number(value || 0)
    const numericBasePrice = Number(basePrice || 0)

    if (!type || numericValue <= 0 || numericBasePrice <= 0) {
        return 0
    }

    if (type === 'percent') {
        return Math.min(numericBasePrice * numericValue / 100, numericBasePrice)
    }

    return Math.min(numericValue, numericBasePrice)
}

const selectedAdditionalDiscounts = computed(() => {
    const selectedIds = form.membership_discount_ids.map(id => Number(id))

    return availableMembershipDiscounts.value.filter(discount => selectedIds.includes(Number(discount.id)))
})

const selectedMembershipDiscounts = computed(() => [
    ...existingMembershipDiscounts.value,
    ...selectedAdditionalDiscounts.value,
])

const membershipDiscountRows = computed(() => {
    let price = planPrice.value

    return selectedMembershipDiscounts.value.map(discount => {
        const amount = calculateDiscountAmount(discount.type, discount.value, price)
        price = Math.max(price - amount, 0)

        return {
            discount,
            amount,
            existing: existingMembershipDiscountIds.value.includes(Number(discount.id)),
        }
    })
})

const membershipDiscountAmount = computed(() => membershipDiscountRows.value.reduce((total, row) => total + row.amount, 0))
const membershipDiscountedPrice = computed(() => Math.max(planPrice.value - membershipDiscountAmount.value, 0))
const manualDiscountAmount = computed(() => {
    if (hasExistingManualDiscount.value) {
        return Number(props.membershipSale.discount_amount || 0)
    }

    if (!form.apply_discount) {
        return 0
    }

    return calculateDiscountAmount(form.discount_type, form.discount_value, membershipDiscountedPrice.value)
})
const discountAmount = computed(() => membershipDiscountAmount.value + manualDiscountAmount.value)
const finalTotal = computed(() => Math.max(planPrice.value - discountAmount.value, 0))
const paidAmount = computed(() => (props.membershipSale.payments ?? [])
    .filter(item => item.type === 'payment' && item.status === 'paid')
    .reduce((total, item) => total + Number(item.amount || 0), 0))
const refundedAmount = computed(() => (props.membershipSale.payments ?? [])
    .filter(item => item.type === 'refund' && item.status === 'paid')
    .reduce((total, item) => total + Number(item.amount || 0), 0))
const netPaidAmount = computed(() => Math.max(paidAmount.value - refundedAmount.value, 0))
const remaining = computed(() => Math.max(finalTotal.value - netPaidAmount.value, 0))
const calculatedSaleStatus = computed(() => {
    if (paidAmount.value > 0 && refundedAmount.value >= paidAmount.value) {
        return 'refunded'
    }

    if (netPaidAmount.value >= finalTotal.value && finalTotal.value > 0) {
        return 'paid'
    }

    if (netPaidAmount.value > 0) {
        return 'partial'
    }

    return 'unpaid'
})

const membershipDiscountRowAmount = discount => {
    return membershipDiscountRows.value.find(row => Number(row.discount.id) === Number(discount.id))?.amount
        ?? calculateDiscountAmount(discount.type, discount.value, membershipDiscountedPrice.value)
}

const toggleMembershipDiscount = discountId => {
    if (props.discountsLocked) {
        return
    }

    const id = Number(discountId)
    const selectedIds = form.membership_discount_ids.map(item => Number(item))

    form.membership_discount_ids = selectedIds.includes(id)
        ? selectedIds.filter(item => item !== id)
        : [...selectedIds, id]
}

watch(() => form.apply_discount, (enabled) => {
    if (hasExistingManualDiscount.value || props.discountsLocked) {
        return
    }

    if (!enabled) {
        form.discount_type = props.discountTypes[0] ?? ''
        form.discount_value = null
    }
})

watch(() => form.discount_type, () => {
    if (hasExistingManualDiscount.value || props.discountsLocked) {
        return
    }

    form.discount_value = null
})

watch(() => form.discount_value, (value) => {
    if (hasExistingManualDiscount.value || props.discountsLocked) {
        return
    }

    const discountValue = Number(value) || 0

    if (discountValue < 0) {
        form.discount_value = 0
    }

    if (form.discount_type === 'percent' && discountValue > 100) {
        form.discount_value = 100
    }

    if (form.discount_type !== 'percent' && membershipDiscountedPrice.value > 0 && discountValue > membershipDiscountedPrice.value) {
        form.discount_value = membershipDiscountedPrice.value
    }
})

const submit = () => {
    form
        .transform(data => {
            if (!hasExistingManualDiscount.value && !props.discountsLocked) {
                return data
            }

            const { apply_discount, discount_type, discount_value, ...payload } = data

            return payload
        })
        .patch(route('membership_sale.update', {
            locale: currentLocale.value,
            id: props.membershipSale.id,
        }), {
            onFinish: () => form.transform(data => data),
        })
}
</script>

<template>
    <Head :title="t('sales.edit_membership_sale')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                {{ t('sales.edit_membership_sale') }}
            </h2>
        </template>

        <div class="card">
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('sales.client')" />
                        <input
                            class="form-control"
                            :value="formatPerson(selectedPerson)"
                            readonly
                        />
                        <InputError :message="form.errors.person_id" />
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('people.membership')" />
                        <input
                            class="form-control"
                            :value="planName(selectedPlan)"
                            readonly
                        />
                        <InputError :message="form.errors.membership_plan_id" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <InputLabel :value="t('membership.price')" />
                        <input
                            class="form-control"
                            :value="planPrice.toFixed(2)"
                            readonly
                        />
                    </div>

                    <div class="col-md-4 mb-4">
                        <InputLabel :value="t('membership.start')" />
                        <input
                            class="form-control"
                            :value="form.start_date"
                            readonly
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="col-md-4 mb-4">
                        <InputLabel :value="t('membership.end')" />
                        <input
                            class="form-control"
                            :value="form.end_date"
                            readonly
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div
                    v-if="selectedPlan"
                    class="border rounded p-4 mb-4"
                >
                    <h5 class="mb-4">
                        {{ t('sales.membership_details') }}
                    </h5>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <span class="text-muted d-block">{{ t('people.type') }}</span>
                            <strong>{{ planTypeLabel(selectedPlan.duration_type) }}</strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <span class="text-muted d-block">{{ t('membership.month_count') }}</span>
                            <strong>{{ durationLabel(selectedPlan) }}</strong>
                        </div>
                        <div
                            v-if="selectedPlan.visits_limit"
                            class="col-md-4 mb-3"
                        >
                            <span class="text-muted d-block">{{ t('sales.number_of_visits') }}</span>
                            <strong>{{ selectedPlan.visits_limit }}</strong>
                        </div>
                        <div
                            v-if="selectedPlan.guest_limit"
                            class="col-md-4 mb-3"
                        >
                            <span class="text-muted d-block">{{ t('membership.guest_count') }}</span>
                            <strong>{{ selectedPlan.guest_limit }}</strong>
                        </div>
                        <div
                            v-if="selectedPlan.freeze_limit"
                            class="col-md-4 mb-3"
                        >
                            <span class="text-muted d-block">{{ t('membership.freeze_count') }}</span>
                            <strong>{{ selectedPlan.freeze_limit }}</strong>
                        </div>
                    </div>
                </div>

                <div
                    v-if="matchingCustomerMemberships.length"
                    class="border rounded p-4 mb-4"
                >
                    <h5 class="mb-4">
                        {{ t('sales.client_s_current_memberships') }}
                    </h5>

                    <div class="row">
                        <div
                            v-for="membershipItem in matchingCustomerMemberships"
                            :key="membershipItem.id"
                            class="col-md-6 col-xl-4 mb-3"
                        >
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="fw-bold mb-2">
                                    {{ membershipPlanName(membershipItem) }}
                                </div>
                                <div class="small d-flex justify-content-between mb-1">
                                    <span class="text-muted">{{ t('membership.status') }}</span>
                                    <span>{{ membershipStatusLabel(membershipItem.status) }}</span>
                                </div>
                                <div class="small d-flex justify-content-between mb-1">
                                    <span class="text-muted">{{ t('membership.start') }}</span>
                                    <span>{{ formatDate(membershipItem.start_date) }}</span>
                                </div>
                                <div class="small d-flex justify-content-between mb-1">
                                    <span class="text-muted">{{ t('sales.end_valid_until') }}</span>
                                    <span>{{ formatDate(membershipValidityDate(membershipItem)) }}</span>
                                </div>
                                <div
                                    v-if="membershipItem.visits_left !== null"
                                    class="small d-flex justify-content-between mb-1"
                                >
                                    <span class="text-muted">{{ t('sales.remaining_visits') }}</span>
                                    <span>{{ membershipItem.visits_left }}</span>
                                </div>
                                <div class="small d-flex justify-content-between mb-1">
                                    <span class="text-muted">{{ t('sales.remaining_guests') }}</span>
                                    <span>{{ membershipItem.guest_left ?? 0 }}</span>
                                </div>
                                <div class="small d-flex justify-content-between">
                                    <span class="text-muted">{{ t('sales.remaining_freezes') }}</span>
                                    <span>{{ membershipItem.freeze_left ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('people.trainer')" />
                        <div
                            v-if="selectedTrainer"
                            class="trainer-card readonly mt-2"
                        >
                            <i class="ti tabler-user fs-2 mb-2 text-primary"></i>
                            <div class="fw-bold text-center">
                                {{ formatUser(selectedTrainer) }}
                            </div>
                            <small class="text-muted">
                                {{ selectedTrainer.phone ?? selectedTrainer.email ?? "" }}
                            </small>
                        </div>
                        <div
                            v-else
                            class="alert alert-secondary mt-2 mb-0"
                        >
                            {{ t('sales.without_trainer') }}
                        </div>
                        <InputError :message="form.errors.trainer_id" />
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('people.notes')" />
                        <textarea
                            class="form-control"
                            rows="4"
                            :value="form.notes"
                            readonly
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="border rounded p-4 mb-4">
                    <h5 class="mb-4">
                        {{ t('sales.membership_discounts') }}
                    </h5>

                    <div
                        v-if="discountsLocked"
                        class="alert alert-warning"
                    >
                        {{ t('sales.the_final_payment_is_complete_discounts_on_this_sale_cannot_be_a') }}
                    </div>

                    <div
                        v-if="existingMembershipDiscounts.length"
                        class="mb-4"
                    >
                        <h6 class="mb-3">
                            {{ t('sales.applied_discounts') }}
                        </h6>
                        <div class="discount-grid">
                            <div
                                v-for="discount in existingMembershipDiscounts"
                                :key="`existing-${discount.id}`"
                                class="discount-card selected readonly"
                            >
                                <i class="ti tabler-discount fs-2 mb-2 text-primary"></i>
                                <div class="fw-bold text-center mb-2">
                                    {{ discountName(discount) }}
                                </div>
                                <div class="d-flex justify-content-between mb-2 w-100">
                                    <span>{{ t('people.type') }}</span>
                                    <span>{{ discountTypeLabel(discount.type) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 w-100">
                                    <span>{{ t('membership.cost') }}</span>
                                    <span>{{ discount.value }}</span>
                                </div>
                                <div class="d-flex justify-content-between w-100">
                                    <span>{{ t('sales.calculated_discount') }}</span>
                                    <strong>{{ membershipDiscountRowAmount(discount).toFixed(2) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="availableMembershipDiscounts.length"
                        class="mb-4"
                    >
                        <h6 class="mb-3">
                            {{ t('sales.add_discount') }}
                        </h6>
                        <div class="discount-grid">
                            <div
                                v-for="discount in availableMembershipDiscounts"
                                :key="`available-${discount.id}`"
                                class="discount-card"
                                :class="{ selected: form.membership_discount_ids.map(id => Number(id)).includes(Number(discount.id)) }"
                                @click="toggleMembershipDiscount(discount.id)"
                            >
                                <i class="ti tabler-discount fs-2 mb-2 text-primary"></i>
                                <div class="fw-bold text-center mb-2">
                                    {{ discountName(discount) }}
                                </div>
                                <div class="d-flex justify-content-between mb-2 w-100">
                                    <span>{{ t('people.type') }}</span>
                                    <span>{{ discountTypeLabel(discount.type) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 w-100">
                                    <span>{{ t('membership.cost') }}</span>
                                    <span>{{ discount.value }}</span>
                                </div>
                                <div class="d-flex justify-content-between w-100">
                                    <span>{{ t('sales.calculated_discount') }}</span>
                                    <strong>{{ membershipDiscountRowAmount(discount).toFixed(2) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!existingMembershipDiscounts.length && !availableMembershipDiscounts.length"
                        class="alert alert-secondary mb-4"
                    >
                        {{ t('sales.no_discounts_for_this_membership') }}
                    </div>

                    <div class="alert alert-info mb-0">
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ t('sales.membership_discounts') }}</span>
                            <strong>{{ membershipDiscountAmount.toFixed(2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ t('sales.discounted_price') }}</span>
                            <strong>{{ membershipDiscountedPrice.toFixed(2) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-4 mb-4">
                    <InputLabel :value="t('sales.manual_discount')" />
                    <div
                        v-if="hasExistingManualDiscount"
                        class="alert alert-secondary mt-3 mb-0"
                    >
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ t('sales.discount_type') }}</span>
                            <strong>{{ discountTypeLabel(membershipSale.discount_type) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ t('sales.discount_value') }}</span>
                            <strong>{{ membershipSale.discount_value ?? '-' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ t('sales.manual_discount') }}</span>
                            <strong>{{ manualDiscountAmount.toFixed(2) }}</strong>
                        </div>
                    </div>

                    <label
                        v-else-if="!discountsLocked"
                        class="form-check mt-2"
                    >
                        <input
                            v-model="form.apply_discount"
                            type="checkbox"
                            class="form-check-input"
                        />
                        <span class="form-check-label">
                            {{ t('sales.apply_manual_discount') }}
                        </span>
                    </label>
                    <div
                        v-else
                        class="alert alert-warning mt-3 mb-0"
                    >
                        {{ t('sales.a_manual_discount_cannot_be_added_after_the_final_payment') }}
                    </div>
                    <InputError :message="form.errors.apply_discount" />

                    <div
                        v-if="!discountsLocked && !hasExistingManualDiscount && form.apply_discount"
                        class="mt-4"
                    >
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <InputLabel :value="t('sales.discount_type')" />
                                <select
                                    v-model="form.discount_type"
                                    class="form-select"
                                >
                                    <option
                                        v-for="type in discountTypes"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ discountTypeLabel(type) }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.discount_type" />
                            </div>

                            <div class="col-md-6 mb-3">
                                <InputLabel :value="t('sales.discount_value')" />
                                <input
                                    v-model="form.discount_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :max="form.discount_type === 'percent' ? 100 : membershipDiscountedPrice"
                                    class="form-control"
                                />
                                <InputError :message="form.errors.discount_value" />
                            </div>
                        </div>

                        <div class="alert alert-info mb-0">
                            <div class="d-flex justify-content-between">
                                <span>{{ t('sales.manual_discount') }}</span>
                                <strong>{{ manualDiscountAmount.toFixed(2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="border rounded p-4 h-100">
                            <h5 class="mb-4">
                                {{ t('people.payment') }}
                            </h5>

                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('people.payment_method') }}</span>
                                <strong>{{ paymentMethodName(selectedPaymentMethod) }}</strong>
                            </div>
                            <div
                                v-if="selectedCardType"
                                class="d-flex justify-content-between mb-2"
                            >
                                <span>{{ t('sales.card_type') }}</span>
                                <strong>{{ cardTypeName(selectedCardType) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.payment_type') }}</span>
                                <strong>{{ paymentTypeLabel(payment?.type) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.payment_status') }}</span>
                                <strong>{{ paymentStatusLabel(payment?.status) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.total_paid') }}</span>
                                <strong>{{ paidAmount.toFixed(2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.total_refunded') }}</span>
                                <strong>{{ refundedAmount.toFixed(2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.net_paid') }}</span>
                                <strong>{{ netPaidAmount.toFixed(2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.fiscal_receipt_2') }}</span>
                                <strong>{{ form.is_hdm ? t('membership.yes') : t('membership.no') }}</strong>
                            </div>
                            <div class="mt-3">
                                <InputLabel :value="t('sales.payment_notes')" />
                                <textarea
                                    class="form-control"
                                    rows="2"
                                    :value="form.payment_notes"
                                    readonly
                                />
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="border rounded p-4 h-100">
                            <h5 class="mb-4">
                                {{ t('sales.price_calculation') }}
                            </h5>

                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.membership_price') }}</span>
                                <span>{{ planPrice.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>{{ t('sales.membership_discount') }}</span>
                                <span>- {{ membershipDiscountAmount.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.discounted_price') }}</span>
                                <span>{{ membershipDiscountedPrice.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>{{ t('sales.manual_discount') }}</span>
                                <span>- {{ manualDiscountAmount.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>{{ t('sales.total_discount') }}</span>
                                <span>- {{ discountAmount.toFixed(2) }}</span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between fw-bold mb-2">
                                <span>{{ t('sales.final_price') }}</span>
                                <span class="text-primary">{{ finalTotal.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.total_paid') }}</span>
                                <span class="text-success">{{ paidAmount.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>{{ t('sales.total_refunded') }}</span>
                                <span>- {{ refundedAmount.toFixed(2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ t('sales.net_paid') }}</span>
                                <span>{{ netPaidAmount.toFixed(2) }}</span>
                            </div>
                            <div class="alert alert-success mt-3 mb-3 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">{{ t('sales.balance') }}</span>
                                    <span class="fw-bold fs-4">{{ remaining.toFixed(2) }}</span>
                                </div>
                            </div>
                            <div class="small text-muted d-flex justify-content-between">
                                <span>{{ t('sales.sale_status') }}</span>
                                <span>{{ saleStatusLabel(calculatedSaleStatus) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('membership_sale.list', { locale: currentLocale })"
                    >
                        {{ t('sales.back_to_list') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ t('membership.update') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Index>
</template>

<style scoped>
.trainer-card {
    border-radius: 12px;
    border: 1px solid #e6e9ef;
    min-height: 120px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.trainer-card.readonly {
    background: #fff;
}

.discount-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}

.discount-card {
    cursor: pointer;
    border-radius: 12px;
    transition: 0.2s;
    border: 1px solid #e6e9ef;
    min-height: 150px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: #fff;
}

.discount-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
}

.discount-card.selected {
    border: 2px solid #0d9394;
    background: rgba(13, 147, 148, 0.08);
}

.discount-card.readonly {
    cursor: default;
}

.discount-card.readonly:hover {
    transform: none;
    box-shadow: none;
}
</style>
