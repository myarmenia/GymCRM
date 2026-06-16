<script setup>
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { useConfirm } from '@/composables/useConfirm'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const { confirm } = useConfirm()

const props = defineProps({
    membershipSale: Object,
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    paidAmount: {
        type: [Number, String],
        default: 0,
    },
    debtAmount: {
        type: [Number, String],
        default: 0,
    },
    refundedAmount: {
        type: [Number, String],
        default: 0,
    },
    netPaidAmount: {
        type: [Number, String],
        default: 0,
    },
    availableRefundAmount: {
        type: [Number, String],
        default: 0,
    },
})

const actionForm = useForm({
    is_partial_payment: false,
    is_full_payment: true,
    is_partial_refund: false,
    is_full_refund: true,
    amount: 0,
    payment_method_id: '',
    card_type_id: '',
    notes: '',
    is_hdm: false,
})

const cancelForm = useForm({})

const membership = computed(() => props.membershipSale.person_memberships?.[0] ?? null)
const saleDiscounts = computed(() => props.membershipSale.discounts ?? [])
const transactions = computed(() => props.membershipSale.payments ?? [])
const debt = computed(() => Number(props.debtAmount || 0))
const paid = computed(() => Number(props.paidAmount || 0))
const refunded = computed(() => Number(props.refundedAmount || 0))
const netPaid = computed(() => Number(props.netPaidAmount || 0))
const availableRefund = computed(() => Number(props.availableRefundAmount || 0))

const actionMode = computed(() => {
    if (availableRefund.value > 0) {
        return 'refund'
    }

    if (debt.value > 0) {
        return 'payment'
    }

    return null
})

const actionLimit = computed(() => actionMode.value === 'refund' ? availableRefund.value : debt.value)
const isRefundMode = computed(() => actionMode.value === 'refund')
const isPaymentMode = computed(() => actionMode.value === 'payment')
const isMembershipCancelled = computed(() => membership.value?.status === 'cancelled')
const selectedPaymentMethod = computed(() => props.paymentMethods.find(method => Number(method.id) === Number(actionForm.payment_method_id)))
const availableCardTypes = computed(() => selectedPaymentMethod.value?.card_types ?? selectedPaymentMethod.value?.cardTypes ?? [])

const actionText = computed(() => {
    if (isRefundMode.value) {
        return {
            title: 'Նոր վերադարձ',
            amount: 'Վերադարձի գումար',
            method: 'Վերադարձի եղանակ',
            methodPlaceholder: 'Ընտրել վերադարձի եղանակ',
            notes: 'Վերադարձի նշումներ',
            button: 'Պահպանել վերադարձը',
            info: 'Վերադարձի գումար',
            full: 'Ամբողջական վերադարձ',
            partial: 'Մասնակի վերադարձ',
        }
    }

    return {
        title: 'Նոր վճարում',
        amount: 'Վճարման գումար',
        method: 'Վճարման եղանակ',
        methodPlaceholder: 'Ընտրել վճարման եղանակ',
        notes: 'Վճարման նշումներ',
        button: 'Պահպանել վճարումը',
        info: 'Վճարման գումար',
        full: 'Ամբողջական վճարում',
        partial: 'Մասնակի վճարում',
    }
})

const formatAmount = value => Number(value || 0).toFixed(2)
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const personName = person => `${person?.name ?? ''} ${person?.surname ?? ''}`.trim() || '-'
const userName = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || '-'
const translatedName = item => item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
    ?? item?.name
    ?? item?.slug
    ?? (item?.id ? `#${item.id}` : '-')

const discountName = discount => translatedName(discount?.discount ?? discount)
const discountTypeLabel = type => ({
    fixed: 'Ֆիքսված գումար',
    fix: 'Ֆիքսված գումար',
    percent: 'Տոկոս %',
    '%': 'Տոկոս %',
}[type] ?? type ?? '-')
const saleStatusLabel = status => ({
    unpaid: 'Չվճարված',
    partial: 'Մասնակի',
    paid: 'Վճարված',
    refunded: 'Վերադարձված',
    cancelled: 'Չեղարկված',
}[status] ?? status ?? '-')
const paymentStatusLabel = status => ({
    unpaid: 'Չվճարված',
    pending: 'Սպասման մեջ',
    paid: 'Վճարված',
    cancelled: 'Չեղարկված',
}[status] ?? status ?? '-')
const transactionTypeLabel = type => ({
    payment: 'Վճարում',
    refund: 'Վերադարձ',
}[type] ?? type ?? '-')

const membershipDiscountAmount = computed(() => {
    return saleDiscounts.value.reduce((total, discount) => total + Number(discount.discount_amount || 0), 0)
})

const resetActionFormForMode = () => {
    actionForm.clearErrors()
    actionForm.payment_method_id = ''
    actionForm.card_type_id = ''
    actionForm.notes = ''
    actionForm.is_hdm = false
    actionForm.amount = actionLimit.value
    actionForm.is_full_payment = isPaymentMode.value
    actionForm.is_partial_payment = false
    actionForm.is_full_refund = isRefundMode.value
    actionForm.is_partial_refund = false
}

watch(actionMode, resetActionFormForMode, { immediate: true })

watch(() => actionForm.payment_method_id, () => {
    actionForm.card_type_id = ''
})

watch(() => actionForm.is_full_payment, enabled => {
    if (!isPaymentMode.value) {
        return
    }

    if (enabled) {
        actionForm.is_partial_payment = false
        actionForm.amount = actionLimit.value
    } else if (!actionForm.is_partial_payment) {
        actionForm.amount = 0
    }
})

watch(() => actionForm.is_partial_payment, enabled => {
    if (!isPaymentMode.value) {
        return
    }

    if (enabled) {
        actionForm.is_full_payment = false
    } else if (!actionForm.is_full_payment) {
        actionForm.amount = 0
    }
})

watch(() => actionForm.is_full_refund, enabled => {
    if (!isRefundMode.value) {
        return
    }

    if (enabled) {
        actionForm.is_partial_refund = false
        actionForm.amount = actionLimit.value
    } else if (!actionForm.is_partial_refund) {
        actionForm.amount = 0
    }
})

watch(() => actionForm.is_partial_refund, enabled => {
    if (!isRefundMode.value) {
        return
    }

    if (enabled) {
        actionForm.is_full_refund = false
    } else if (!actionForm.is_full_refund) {
        actionForm.amount = 0
    }
})

watch(actionLimit, value => {
    if ((isPaymentMode.value && actionForm.is_full_payment) || (isRefundMode.value && actionForm.is_full_refund)) {
        actionForm.amount = value
    }

    if (Number(actionForm.amount || 0) > value) {
        actionForm.amount = value
    }
})

watch(() => actionForm.amount, value => {
    const amount = Number(value || 0)

    if (amount < 0) {
        actionForm.amount = 0
    }

    if (actionLimit.value > 0 && amount > actionLimit.value) {
        actionForm.amount = actionLimit.value
    }
})

const submitAction = () => {
    if (!actionMode.value) {
        return
    }

    const routeName = isRefundMode.value
        ? 'membership_sale.refunds.store'
        : 'membership_sale.payments.store'

    actionForm
        .transform(data => {
            const payload = {
                amount: data.amount,
                payment_method_id: data.payment_method_id,
                card_type_id: data.card_type_id,
                is_hdm: data.is_hdm,
            }

            if (isRefundMode.value) {
                return {
                    ...payload,
                    is_partial_refund: data.is_partial_refund,
                    is_full_refund: data.is_full_refund,
                    refund_notes: data.notes,
                }
            }

            return {
                ...payload,
                is_partial_payment: data.is_partial_payment,
                is_full_payment: data.is_full_payment,
                payment_notes: data.notes,
            }
        })
        .post(route(routeName, {
            locale: currentLocale.value,
            id: props.membershipSale.id,
        }), {
            onFinish: () => actionForm.transform(data => data),
        })
}

const cancelMembership = async () => {
    const approved = await confirm('Համոզվա՞ծ եք, որ ցանկանում եք չեղարկել աբոնեմենտը', {
        title: 'Հաստատում',
        confirmText: 'Հաստատել',
        cancelText: 'Չեղարկել',
        confirmClass: 'btn-danger',
    })

    if (!approved) {
        return
    }

    cancelForm.post(route('membership_sale.cancel', {
        locale: currentLocale.value,
        id: props.membershipSale.id,
    }))
}
</script>

<template>
    <Head title="Վճարումներ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                Վճարումներ
            </h2>
        </template>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Վաճառքի տվյալներ</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Հաճախորդ</span>
                            <strong>{{ personName(membershipSale.person) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Աբոնեմենտ</span>
                            <strong>{{ translatedName(membershipSale.membership_plan) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Մարզիչ</span>
                            <strong>{{ userName(membership?.trainer) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Վաճառքի օր</span>
                            <strong>{{ formatDate(membershipSale.sold_at) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Կարգավիճակ</span>
                            <span class="badge bg-label-primary">{{ saleStatusLabel(membershipSale.payment_status) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Աբոնեմենտի կարգավիճակ</span>
                            <span
                                class="badge"
                                :class="isMembershipCancelled ? 'bg-label-danger' : 'bg-label-success'"
                            >
                                {{ isMembershipCancelled ? 'Չեղարկված' : 'Ակտիվ' }}
                            </span>
                        </div>
                        <button
                            v-if="!isMembershipCancelled"
                            type="button"
                            class="btn btn-label-danger w-100"
                            :disabled="cancelForm.processing"
                            @click="cancelMembership"
                        >
                            Չեղարկել աբոնեմենտը
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Գնի հաշվարկ</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Աբոնեմենտի գին</span>
                            <span>{{ formatAmount(membershipSale.total_price) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Աբոնեմենտի զեղչ</span>
                            <span>- {{ formatAmount(membershipDiscountAmount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Ձեռքով զեղչ</span>
                            <span>- {{ formatAmount(membershipSale.discount_amount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Ընդհանուր զեղչ</span>
                            <span>- {{ formatAmount(Number(membershipDiscountAmount || 0) + Number(membershipSale.discount_amount || 0)) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold mb-2">
                            <span>Վերջնական վճարման ենթակա գումար</span>
                            <span class="text-primary">{{ formatAmount(membershipSale.final_price) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ընդհանուր վճարված</span>
                            <span class="text-success">- {{ formatAmount(paid) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Ընդհանուր վերադարձված</span>
                            <span>- {{ formatAmount(refunded) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Զուտ վճարված</span>
                            <span>{{ formatAmount(netPaid) }}</span>
                        </div>
                        <div class="alert alert-success mt-3 mb-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Պարտք</span>
                                <span class="fw-bold fs-4">{{ formatAmount(debt) }}</span>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Հասանելի վերադարձ</span>
                                <span class="fw-bold fs-4">{{ formatAmount(availableRefund) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Գործարքների պատմություն</h5>
            </div>
            <div class="card-body">
                <div
                    v-if="transactions.length"
                    class="table-responsive text-nowrap"
                >
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Գումար</th>
                                <th>Եղանակ</th>
                                <th>Քարտ</th>
                                <th>Տեսակ</th>
                                <th>Կարգավիճակ</th>
                                <th>ՀԴՄ</th>
                                <th>Ամսաթիվ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in transactions"
                                :key="transaction.id"
                                :class="{ 'table-danger': transaction.type === 'refund' }"
                            >
                                <td>{{ formatAmount(transaction.amount) }}</td>
                                <td>{{ translatedName(transaction.payment_method) }}</td>
                                <td>{{ transaction.card_type?.name ?? transaction.card_type?.slug ?? '-' }}</td>
                                <td>{{ transactionTypeLabel(transaction.type) }}</td>
                                <td>{{ paymentStatusLabel(transaction.status) }}</td>
                                <td>{{ transaction.is_hdm ? 'Այո' : 'Ոչ' }}</td>
                                <td>{{ formatDate(transaction.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="text-muted"
                >
                    Գործարքներ չկան
                </div>
            </div>
        </div>

        <div class="row align-items-stretch">
            <div :class="actionMode ? 'col-lg-7 mb-4' : 'col-12 mb-4'">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Կիրառված աբոնեմենտի զեղչեր</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="saleDiscounts.length"
                            class="table-responsive text-nowrap"
                        >
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Զեղչ</th>
                                        <th>Տեսակ</th>
                                        <th>Արժեք</th>
                                        <th>Գումար</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="discount in saleDiscounts"
                                        :key="discount.id"
                                    >
                                        <td>{{ discountName(discount) }}</td>
                                        <td>{{ discountTypeLabel(discount.discount_type) }}</td>
                                        <td>{{ discount.discount_value }}</td>
                                        <td>{{ formatAmount(discount.discount_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="text-muted"
                        >
                            Աբոնեմենտի զեղչեր չկան
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="actionMode"
                class="col-lg-5 mb-4"
            >
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ actionText.title }}</h5>
                    </div>
                    <form
                        class="card-body"
                        @submit.prevent="submitAction"
                    >
                        <div class="d-flex flex-wrap gap-4 mb-3">
                            <label class="form-check">
                                <input
                                    v-if="isRefundMode"
                                    v-model="actionForm.is_full_refund"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <input
                                    v-else
                                    v-model="actionForm.is_full_payment"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <span class="form-check-label">{{ actionText.full }}</span>
                            </label>
                            <label class="form-check">
                                <input
                                    v-if="isRefundMode"
                                    v-model="actionForm.is_partial_refund"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <input
                                    v-else
                                    v-model="actionForm.is_partial_payment"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <span class="form-check-label">{{ actionText.partial }}</span>
                            </label>
                        </div>
                        <InputError :message="actionForm.errors.is_full_payment || actionForm.errors.is_full_refund" />
                        <InputError :message="actionForm.errors.is_partial_payment || actionForm.errors.is_partial_refund" />

                        <div class="mb-3">
                            <InputLabel :value="actionText.method" />
                            <select
                                v-model="actionForm.payment_method_id"
                                class="form-select"
                            >
                                <option value="">
                                    {{ actionText.methodPlaceholder }}
                                </option>
                                <option
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    :value="method.id"
                                >
                                    {{ translatedName(method) }}
                                </option>
                            </select>
                            <InputError :message="actionForm.errors.payment_method_id" />
                        </div>

                        <div
                            v-if="availableCardTypes.length"
                            class="mb-3"
                        >
                            <InputLabel value="Քարտի տեսակ" />
                            <select
                                v-model="actionForm.card_type_id"
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
                            <InputError :message="actionForm.errors.card_type_id" />
                        </div>

                        <div
                            v-if="(isRefundMode && actionForm.is_partial_refund) || (isPaymentMode && actionForm.is_partial_payment)"
                            class="mb-3"
                        >
                            <InputLabel :value="actionText.amount" />
                            <input
                                v-model="actionForm.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                :max="actionLimit"
                                class="form-control"
                            />
                            <InputError :message="actionForm.errors.amount" />
                        </div>

                        <div
                            v-else
                            class="alert alert-info py-2"
                        >
                            {{ actionText.info }}՝ <strong>{{ formatAmount(actionLimit) }}</strong>
                        </div>

                        <div class="mb-3">
                            <InputLabel :value="actionText.notes" />
                            <textarea
                                v-model="actionForm.notes"
                                class="form-control"
                                rows="2"
                            />
                            <InputError :message="actionForm.errors.payment_notes || actionForm.errors.refund_notes" />
                        </div>

                        <div class="mb-4">
                            <InputLabel value="ՀԴՄ" />
                            <label class="form-check mt-2">
                                <input
                                    v-model="actionForm.is_hdm"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <span class="form-check-label">
                                    ՀԴՄ կտրոն
                                </span>
                            </label>
                            <InputError :message="actionForm.errors.is_hdm" />
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <Link
                                class="btn btn-label-secondary"
                                :href="route('membership_sale.list', { locale: currentLocale })"
                            >
                                Վերադառնալ
                            </Link>
                            <PrimaryButton :disabled="actionForm.processing">
                                {{ actionText.button }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Index>
</template>
