<script setup>
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
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
})

const membership = computed(() => props.membershipSale.person_memberships?.[0] ?? null)
const payment = computed(() => props.membershipSale.payments?.[0] ?? null)

const toDate = value => value ? String(value).slice(0, 10) : ''

const form = useForm({
    person_id: props.membershipSale.person_id ?? '',
    membership_plan_id: props.membershipSale.membership_plan_id ?? '',
    membership_discount_ids: props.membershipSale.discounts?.map(item => item.discount_id) ?? [],
    start_date: toDate(membership.value?.start_date),
    end_date: toDate(membership.value?.end_date),
    apply_discount: Number(props.membershipSale.discount_amount || 0) > Number(props.membershipSale.discount_membership_amount || 0),
    discount_type: props.membershipSale.discount_type ?? props.discountTypes[0] ?? '',
    discount_value: props.membershipSale.discount_value ?? null,
    is_hdm: Boolean(props.membershipSale.is_hdm),
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
    return plan.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan.name
        ?? `#${plan.id}`
}

const selectedPlan = computed(() => props.membershipPlans.find(item => Number(item.id) === Number(form.membership_plan_id)))
const selectedPaymentMethod = computed(() => props.paymentMethods.find(item => Number(item.id) === Number(form.payment_method_id)))
const availableTrainers = computed(() => {
    return selectedPlan.value?.trainers ?? []
})
const availableCardTypes = computed(() => selectedPaymentMethod.value?.card_types ?? selectedPaymentMethod.value?.cardTypes ?? [])

const formatPerson = person => `${person.name ?? ''} ${person.surname ?? ''}`.trim()
const formatUser = user => `${user.name ?? ''} ${user.surname ?? ''}`.trim()
const discountName = discount => discount?.translations?.find(item => item.locale === currentLocale.value)?.name ?? discount?.name ?? (discount ? `#${discount.id}` : '-')
const paymentMethodName = method => method.translations?.find(item => item.locale === currentLocale.value)?.name ?? method.name ?? method.slug ?? `#${method.id}`
const numericPaymentAmount = computed(() => Number(form.amount) || 0)
const planPrice = computed(() => Number(selectedPlan.value?.price ?? props.membershipSale.total_price ?? 0))
const discountTypeLabel = type => ({
    fixed: 'Ֆիքսված գումար',
    percent: 'Տոկոս %',
}[type] ?? type)
const membershipDiscounts = computed(() => selectedPlan.value?.discounts ?? [])
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
const selectedMembershipDiscounts = computed(() => {
    return membershipDiscounts.value.filter(discount => form.membership_discount_ids
        .map(id => Number(id))
        .includes(Number(discount.id)))
})
const membershipDiscountRows = computed(() => {
    let price = planPrice.value

    return selectedMembershipDiscounts.value.map(discount => {
        const amount = calculateDiscountAmount(discount.type, discount.value, price)
        price = Math.max(price - amount, 0)

        return {
            discount,
            amount,
        }
    })
})
const membershipDiscountAmount = computed(() => membershipDiscountRows.value.reduce((total, row) => total + row.amount, 0))
const membershipDiscountedPrice = computed(() => Math.max(planPrice.value - membershipDiscountAmount.value, 0))
const manualDiscountAmount = computed(() => {
    if (!form.apply_discount) {
        return 0
    }

    return calculateDiscountAmount(form.discount_type, form.discount_value, membershipDiscountedPrice.value)
})
const discountAmount = computed(() => membershipDiscountAmount.value + manualDiscountAmount.value)
const finalTotal = computed(() => Math.max(planPrice.value - discountAmount.value, 0))
const remaining = computed(() => Math.max(finalTotal.value - numericPaymentAmount.value, 0))
const calculatedSaleStatus = computed(() => {
    if (numericPaymentAmount.value >= finalTotal.value && finalTotal.value > 0) {
        return 'Վճարված'
    }

    if (numericPaymentAmount.value > 0) {
        return 'Մասնակի'
    }

    return 'Չվճարված'
})

const addDays = (date, days) => {
    const result = new Date(`${date}T00:00:00`)
    result.setDate(result.getDate() + days)
    return result.toISOString().slice(0, 10)
}

const addMonths = (date, months) => {
    const result = new Date(`${date}T00:00:00`)
    result.setMonth(result.getMonth() + months)
    return result.toISOString().slice(0, 10)
}

const calculateEndDate = () => {
    if (!selectedPlan.value || !form.start_date) {
        form.end_date = ''
        return
    }

    const value = Number(selectedPlan.value.duration_value || 0)

    if (selectedPlan.value.duration_type === 'period') {
        form.end_date = selectedPlan.value.end_date ? String(selectedPlan.value.end_date).slice(0, 10) : ''
        return
    }

    if (!value) {
        form.end_date = ''
        return
    }

    if (['day', 'visit'].includes(selectedPlan.value.duration_type)) {
        form.end_date = addDays(form.start_date, value - 1)
    } else if (selectedPlan.value.duration_type === 'month') {
        form.end_date = addDays(addMonths(form.start_date, value), -1)
    } else if (selectedPlan.value.duration_type === 'year') {
        form.end_date = addDays(addMonths(form.start_date, value * 12), -1)
    }
}

watch(() => form.membership_plan_id, () => {
    form.membership_discount_ids = []
    form.apply_discount = false
    form.discount_type = props.discountTypes[0] ?? ''
    form.discount_value = null
    form.trainer_id = ''
    calculateEndDate()
})

watch(availableTrainers, (trainers) => {
    if (!trainers.length) {
        form.trainer_id = ''
        return
    }

    if (form.trainer_id && !trainers.some(trainer => Number(trainer.id) === Number(form.trainer_id))) {
        form.trainer_id = ''
    }
})

watch(membershipDiscounts, (discounts) => {
    const availableIds = discounts.map(discount => Number(discount.id))
    form.membership_discount_ids = form.membership_discount_ids
        .map(id => Number(id))
        .filter(id => availableIds.includes(id))
}, { immediate: true })


const toggleTrainer = trainerId => {
    const id = Number(trainerId)
    form.trainer_id = Number(form.trainer_id) === id ? '' : id
}

const toggleMembershipDiscount = discountId => {
    const id = Number(discountId)
    const selectedIds = form.membership_discount_ids.map(item => Number(item))

    form.membership_discount_ids = selectedIds.includes(id)
        ? selectedIds.filter(item => item !== id)
        : [...selectedIds, id]
}

const membershipDiscountRowAmount = discount => {
    return membershipDiscountRows.value.find(row => Number(row.discount.id) === Number(discount.id))?.amount
        ?? calculateDiscountAmount(discount.type, discount.value, membershipDiscountedPrice.value)
}

watch(() => form.apply_discount, (enabled) => {
    if (!enabled) {
        form.discount_type = props.discountTypes[0] ?? ''
        form.discount_value = null
    }
})

watch(() => form.discount_type, () => {
    form.discount_value = null
})

watch(() => form.discount_value, (value) => {
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

watch(() => form.start_date, calculateEndDate)

watch(() => form.payment_method_id, () => {
    form.card_type_id = ''
})

watch(() => form.is_full_payment, (enabled) => {
    if (enabled) {
        form.is_partial_payment = false
        form.amount = finalTotal.value
    } else if (!form.is_partial_payment) {
        form.amount = 0
    }
})

watch(() => form.is_partial_payment, (enabled) => {
    if (enabled) {
        form.is_full_payment = false
    } else if (!form.is_full_payment) {
        form.amount = 0
    }
})

watch(finalTotal, (total) => {
    if (form.is_full_payment) {
        form.amount = total
    }

    if (form.amount > total) {
        form.amount = total
    }
})

watch(() => form.amount, (value) => {
    const amount = Number(value) || 0

    if (amount < 0) {
        form.amount = 0
    }

    if (finalTotal.value > 0 && amount > finalTotal.value) {
        form.amount = finalTotal.value
    }
})

const submit = () => {
    form.patch(route('membership_sale.update', {
        locale: currentLocale.value,
        id: props.membershipSale.id,
    }))
}
</script>

<template>
    <Head title="Խմբագրել աբոնեմենտի վաճառքը" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                Խմբագրել աբոնեմենտի վաճառքը
            </h2>
        </template>

        <div class="card">
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel value="Հաճախորդ" />
                        <select
                            v-model="form.person_id"
                            class="form-select"
                        >
                            <option
                                v-for="person in people"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{ formatPerson(person) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.person_id" />
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel value="Աբոնեմենտ" />
                        <select
                            v-model="form.membership_plan_id"
                            class="form-select"
                        >
                            <option
                                v-for="plan in membershipPlans"
                                :key="plan.id"
                                :value="plan.id"
                            >
                                {{ planName(plan) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.membership_plan_id" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <InputLabel value="Գին" />
                        <input
                            class="form-control"
                            :value="selectedPlan?.price ?? membershipSale.total_price"
                            readonly
                        />
                    </div>

                    <div class="col-md-4 mb-4">
                        <InputLabel value="Սկիզբ" />
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="form-control"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="col-md-4 mb-4">
                        <InputLabel value="Ավարտ" />
                        <input
                            v-model="form.end_date"
                            type="date"
                            class="form-control"
                            readonly
                            disabled
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel value="Զեղչ" />
                        <div v-if="membershipDiscounts.length">
                            <div
                                v-for="discount in membershipDiscounts"
                                :key="discount.id"
                                class="alert mb-3"
                                :class="form.membership_discount_ids.map(id => Number(id)).includes(Number(discount.id)) ? 'alert-info' : 'alert-light'"
                            >
                                <label class="form-check mb-2">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :checked="form.membership_discount_ids.map(id => Number(id)).includes(Number(discount.id))"
                                        @change="toggleMembershipDiscount(discount.id)"
                                    />
                                    <span class="form-check-label fw-bold">
                                        {{ discountName(discount) }}
                                    </span>
                                </label>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Տեսակ</span>
                                    <span>{{ discountTypeLabel(discount.type) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Արժեք</span>
                                    <span>{{ discount.value }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Հաշվարկված զեղչ</span>
                                    <strong>{{ membershipDiscountRowAmount(discount).toFixed(2) }}</strong>
                                </div>
                            </div>
                            <div class="alert alert-info mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Ընտրված աբոնեմենտի զեղչեր</span>
                                    <strong>{{ membershipDiscountAmount.toFixed(2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Զեղչված գին</span>
                                    <strong>{{ membershipDiscountedPrice.toFixed(2) }}</strong>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="alert alert-secondary mb-3"
                        >
                            Այս աբոնեմենտի համար կցված զեղչ չկա։
                        </div>

                        <label class="form-check mt-2">
                            <input
                                v-model="form.apply_discount"
                                type="checkbox"
                                class="form-check-input"
                            />
                            <span class="form-check-label">
                                Կիրառել ձեռքով զեղչ
                            </span>
                        </label>
                        <InputError :message="form.errors.apply_discount" />

                        <div
                            v-if="form.apply_discount"
                            class="mt-3"
                        >
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <InputLabel value="Զեղչի տեսակ" />
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
                                    <InputLabel value="Զեղչի արժեք" />
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
                                    <span>Ձեռքով մուտքագրված զեղչ</span>
                                    <strong>{{ manualDiscountAmount.toFixed(2) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel value="Մարզիչ" />
                        <div
                            v-if="selectedPlan && availableTrainers.length"
                            class="trainer-grid mt-2"
                        >
                            <div
                                v-for="trainer in availableTrainers"
                                :key="trainer.id"
                                class="trainer-card"
                                :class="{ selected: Number(form.trainer_id) === Number(trainer.id) }"
                                @click="toggleTrainer(trainer.id)"
                            >
                                <i class="ti tabler-user fs-2 mb-2 text-primary"></i>
                                <div class="fw-bold text-center">
                                    {{ formatUser(trainer) }}
                                </div>
                                <small class="text-muted">
                                    {{ trainer.phone ?? trainer.email ?? "" }}
                                </small>
                            </div>
                        </div>
                        <InputError :message="form.errors.trainer_id" />
                        <div
                            v-if="selectedPlan && !availableTrainers.length"
                            class="small text-muted mt-1"
                        >
                            Այս աբոնեմենտը մարզիչ չունի․ կպահպանվի առանց մարզչի։
                        </div>
                    </div>
                </div>

                <div class="row">

                    <div class="col-md-6 mb-4">
                        <InputLabel value="ՀԴՄ" />
                        <label class="form-check mt-2">
                            <input
                                v-model="form.is_hdm"
                                type="checkbox"
                                class="form-check-input"
                            />
                            <span class="form-check-label">
                                ՀԴՄ կտրոն
                            </span>
                        </label>
                        <InputError :message="form.errors.is_hdm" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="border rounded p-4 h-100">
                            <h5 class="mb-4">
                                Վճարում
                            </h5>

                            <div class="mb-3">
                                <InputLabel value="Վճարման եղանակ" />
                                <select
                                    v-model="form.payment_method_id"
                                    class="form-select"
                                >
                                    <option value="">
                                        Ընտրել վճարման եղանակ
                                    </option>
                                    <option
                                        v-for="method in paymentMethods"
                                        :key="method.id"
                                        :value="method.id"
                                    >
                                        {{ paymentMethodName(method) }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.payment_method_id" />
                            </div>

                            <div
                                v-if="selectedPaymentMethod?.slug === 'card'"
                                class="mb-3"
                            >
                                <InputLabel value="Քարտի տեսակ" />
                                <select
                                    v-model="form.card_type_id"
                                    class="form-select"
                                >
                                    <option value="">
                                        Ընտրել քարտի տեսակը
                                    </option>
                                    <option
                                        v-for="cardType in availableCardTypes"
                                        :key="cardType.id"
                                        :value="cardType.id"
                                    >
                                        {{ cardType.name ?? cardType.slug ?? `#${cardType.id}` }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.card_type_id" />
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <InputLabel value="Վճարման տեսակ" />
                                    <select
                                        v-model="form.payment_type"
                                        class="form-select"
                                    >
                                        <option value="payment">
                                            Վճարում
                                        </option>
                                        <option value="refund">
                                            Վերադարձ
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.payment_type" />
                                </div>

                                <div class="col-md-6 mb-3">
                                    <InputLabel value="Վճարման կարգավիճակ" />
                                    <select
                                        v-model="form.payment_record_status"
                                        class="form-select"
                                    >
                                        <option value="">
                                            Ավտոմատ
                                        </option>
                                        <option value="unpaid">
                                            Չվճարված
                                        </option>
                                        <option value="pending">
                                            Սպասման մեջ
                                        </option>
                                        <option value="paid">
                                            Վճարված
                                        </option>
                                        <option value="cancelled">
                                            Չեղարկված
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.payment_record_status" />
                                </div>
                            </div>

                            <div class="d-flex gap-4 mb-3">
                                <label class="form-check">
                                    <input
                                        v-model="form.is_partial_payment"
                                        type="checkbox"
                                        class="form-check-input"
                                    />
                                    <span class="form-check-label">Partial payment</span>
                                </label>
                                <label class="form-check">
                                    <input
                                        v-model="form.is_full_payment"
                                        type="checkbox"
                                        class="form-check-input"
                                    />
                                    <span class="form-check-label">Full payment</span>
                                </label>
                            </div>
                            <InputError :message="form.errors.is_partial_payment" />
                            <InputError :message="form.errors.is_full_payment" />

                            <div class="mb-3">
                                <InputLabel value="Վճարված գումար" />
                                <input
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    :readonly="form.is_full_payment || !form.is_partial_payment"
                                />
                                <InputError :message="form.errors.amount" />
                            </div>

                            <div class="mb-0">
                                <InputLabel value="Վճարման նշումներ" />
                                <textarea
                                    v-model="form.payment_notes"
                                    class="form-control"
                                    rows="2"
                                />
                                <InputError :message="form.errors.payment_notes" />
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="border rounded p-4 h-100">
                            <h5 class="mb-4">
                                Գնի հաշվարկ
                            </h5>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Աբոնեմենտի գին</span>
                                <span>{{ planPrice.toFixed(2) }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>Աբոնեմենտի զեղչ</span>
                                <span>- {{ membershipDiscountAmount.toFixed(2) }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Զեղչված գին</span>
                                <span>{{ membershipDiscountedPrice.toFixed(2) }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>Ձեռքով զեղչ</span>
                                <span>- {{ manualDiscountAmount.toFixed(2) }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 text-danger">
                                <span>Ընդհանուր զեղչ</span>
                                <span>- {{ discountAmount.toFixed(2) }}</span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between fw-bold mb-2">
                                <span>Վերջնական գին</span>
                                <span class="text-primary">{{ finalTotal.toFixed(2) }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Վճարված</span>
                                <span class="text-success">- {{ numericPaymentAmount.toFixed(2) }}</span>
                            </div>

                            <div class="alert alert-success mt-3 mb-3 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Մնացորդ</span>
                                    <span class="fw-bold fs-4">{{ remaining.toFixed(2) }}</span>
                                </div>
                            </div>

                            <div class="small text-muted d-flex justify-content-between">
                                <span>Վաճառքի կարգավիճակ</span>
                                <span>{{ calculatedSaleStatus }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <InputLabel value="Նշումներ" />
                    <textarea
                        v-model="form.notes"
                        class="form-control"
                        rows="3"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :disabled="form.processing">
                        Թարմացնել
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Index>
</template>

<style scoped>
.trainer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
}

.trainer-card {
    cursor: pointer;
    border-radius: 12px;
    transition: 0.2s;
    border: 1px solid #e6e9ef;
    min-height: 120px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.trainer-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
}

.trainer-card.selected {
    border: 2px solid #0d9394;
    background: rgba(13, 147, 148, 0.08);
}
</style>
