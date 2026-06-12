<script setup>
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

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
})

const form = useForm({
    is_partial_payment: false,
    is_full_payment: true,
    amount: Number(props.debtAmount || 0),
    payment_method_id: '',
    card_type_id: '',
    payment_notes: '',
    is_hdm: Boolean(props.membershipSale.is_hdm),
})

const membership = computed(() => props.membershipSale.person_memberships?.[0] ?? null)
const selectedPaymentMethod = computed(() => props.paymentMethods.find(method => Number(method.id) === Number(form.payment_method_id)))
const availableCardTypes = computed(() => selectedPaymentMethod.value?.card_types ?? selectedPaymentMethod.value?.cardTypes ?? [])
const payments = computed(() => props.membershipSale.payments ?? [])
const saleDiscounts = computed(() => props.membershipSale.discounts ?? [])
const debt = computed(() => Number(props.debtAmount || 0))
const paid = computed(() => Number(props.paidAmount || 0))

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
    percent: 'Տոկոս %',
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
const paymentTypeLabel = type => ({
    payment: 'Վճարում',
    refund: 'Վերադարձ',
}[type] ?? type ?? '-')

const membershipDiscountAmount = computed(() => {
    return saleDiscounts.value.reduce((total, discount) => total + Number(discount.discount_amount || 0), 0)
})

watch(() => form.payment_method_id, () => {
    form.card_type_id = ''
})

watch(() => form.is_full_payment, (enabled) => {
    if (enabled) {
        form.is_partial_payment = false
        form.amount = debt.value
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

watch(debt, value => {
    if (form.is_full_payment) {
        form.amount = value
    }

    if (Number(form.amount || 0) > value) {
        form.amount = value
    }
})

watch(() => form.amount, value => {
    const amount = Number(value || 0)

    if (amount < 0) {
        form.amount = 0
    }

    if (debt.value > 0 && amount > debt.value) {
        form.amount = debt.value
    }
})

const submit = () => {
    form.post(route('membership_sale.payments.store', {
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
                        <hr>
                        <div class="d-flex justify-content-between fw-bold mb-2">
                            <span>Վերջնական գին</span>
                            <span class="text-primary">{{ formatAmount(membershipSale.final_price) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Վճարված</span>
                            <span class="text-success">- {{ formatAmount(paid) }}</span>
                        </div>
                        <div class="alert alert-success mt-3 mb-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Պարտք</span>
                                <span class="fw-bold fs-4">{{ formatAmount(debt) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
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

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Վճարումների պատմություն</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="payments.length"
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
                                        <th>Ամսաթիվ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="payment in payments"
                                        :key="payment.id"
                                    >
                                        <td>{{ formatAmount(payment.amount) }}</td>
                                        <td>{{ translatedName(payment.payment_method) }}</td>
                                        <td>{{ payment.card_type?.name ?? payment.card_type?.slug ?? '-' }}</td>
                                        <td>{{ paymentTypeLabel(payment.type) }}</td>
                                        <td>{{ paymentStatusLabel(payment.status) }}</td>
                                        <td>{{ formatDate(payment.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="text-muted"
                        >
                            Վճարումներ չկան
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Նոր վճարում</h5>
                    </div>
                    <form
                        class="card-body"
                        @submit.prevent="submit"
                    >
                        <div
                            v-if="debt <= 0"
                            class="alert alert-success"
                        >
                            Այս վաճառքի պարտքը ամբողջությամբ մարված է։
                        </div>

                        <div class="d-flex gap-4 mb-3">
                            <label class="form-check">
                                <input
                                    v-model="form.is_full_payment"
                                    type="checkbox"
                                    class="form-check-input"
                                    :disabled="debt <= 0"
                                />
                                <span class="form-check-label">Ամբողջական վճարում</span>
                            </label>
                            <label class="form-check">
                                <input
                                    v-model="form.is_partial_payment"
                                    type="checkbox"
                                    class="form-check-input"
                                    :disabled="debt <= 0"
                                />
                                <span class="form-check-label">Մասնակի վճարում</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.is_full_payment" />
                        <InputError :message="form.errors.is_partial_payment" />

                        <div class="mb-3">
                            <InputLabel value="Վճարման եղանակ" />
                            <select
                                v-model="form.payment_method_id"
                                class="form-select"
                                :disabled="debt <= 0"
                            >
                                <option value="">
                                    Ընտրել վճարման եղանակ
                                </option>
                                <option
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    :value="method.id"
                                >
                                    {{ translatedName(method) }}
                                </option>
                            </select>
                            <InputError :message="form.errors.payment_method_id" />
                        </div>

                        <div
                            v-if="availableCardTypes.length"
                            class="mb-3"
                        >
                            <InputLabel value="Քարտի տեսակ" />
                            <select
                                v-model="form.card_type_id"
                                class="form-select"
                                :disabled="debt <= 0"
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

                        <div
                            v-if="form.is_partial_payment"
                            class="mb-3"
                        >
                            <InputLabel value="Վճարվող գումար" />
                            <input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                :max="debt"
                                class="form-control"
                                :disabled="debt <= 0"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div
                            v-else
                            class="alert alert-info py-2"
                        >
                            Վճարվող գումար՝ <strong>{{ formatAmount(debt) }}</strong>
                        </div>

                        <div class="mb-3">
                            <InputLabel value="Վճարման նշումներ" />
                            <textarea
                                v-model="form.payment_notes"
                                class="form-control"
                                rows="2"
                                :disabled="debt <= 0"
                            />
                            <InputError :message="form.errors.payment_notes" />
                        </div>

                        <div class="mb-4">
                            <InputLabel value="ՀԴՄ" />
                            <label class="form-check mt-2">
                                <input
                                    v-model="form.is_hdm"
                                    type="checkbox"
                                    class="form-check-input"
                                    :disabled="debt <= 0"
                                />
                                <span class="form-check-label">
                                    ՀԴՄ կտրոն
                                </span>
                            </label>
                            <InputError :message="form.errors.is_hdm" />
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <Link
                                class="btn btn-label-secondary"
                                :href="route('membership_sale.list', { locale: currentLocale })"
                            >
                                Վերադառնալ
                            </Link>
                            <PrimaryButton :disabled="form.processing || debt <= 0">
                                Պահպանել վճարումը
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Index>
</template>
