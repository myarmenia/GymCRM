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
})

const membership = computed(() => props.membershipSale.person_memberships?.[0] ?? null)
const payment = computed(() => props.membershipSale.payments?.[0] ?? null)
const saleDiscount = computed(() => props.membershipSale.discounts?.[0] ?? null)
const commission = computed(() => props.membershipSale.trainer_commissions?.[0] ?? null)

const toDate = value => value ? String(value).slice(0, 10) : ''

const form = useForm({
    person_id: props.membershipSale.person_id ?? '',
    membership_plan_id: props.membershipSale.membership_plan_id ?? '',
    start_date: toDate(membership.value?.start_date),
    end_date: toDate(membership.value?.end_date),
    discount_id: saleDiscount.value?.discount_id ?? '',
    is_hdm: Boolean(props.membershipSale.is_hdm),
    notes: props.membershipSale.notes ?? '',
    trainer_id: membership.value?.trainer_id ?? '',
    price_type: commission.value?.salary_type ?? 'fixed',
    amount: payment.value?.amount ?? 0,
    payment_method_id: payment.value?.payment_method_id ?? '',
    card_type_id: payment.value?.card_type_id ?? '',
    payment_notes: payment.value?.notes ?? '',
})

const planName = plan => {
    return plan.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan.name
        ?? `#${plan.id}`
}

const selectedPlan = computed(() => props.membershipPlans.find(item => Number(item.id) === Number(form.membership_plan_id)))
const selectedPaymentMethod = computed(() => props.paymentMethods.find(item => Number(item.id) === Number(form.payment_method_id)))
const availableDiscounts = computed(() => selectedPlan.value?.discounts ?? [])
const availableTrainers = computed(() => {
    if (!selectedPlan.value?.gym_id) {
        return props.trainers
    }

    return props.trainers.filter(trainer => Number(trainer.gym_id) === Number(selectedPlan.value.gym_id))
})
const availableCardTypes = computed(() => selectedPaymentMethod.value?.card_types ?? selectedPaymentMethod.value?.cardTypes ?? [])

const formatPerson = person => `${person.name ?? ''} ${person.surname ?? ''}`.trim()
const formatUser = user => `${user.name ?? ''} ${user.surname ?? ''}`.trim()
const discountName = discount => discount.translations?.find(item => item.locale === currentLocale.value)?.name ?? discount.name ?? `#${discount.id}`
const paymentMethodName = method => method.translations?.find(item => item.locale === currentLocale.value)?.name ?? method.name ?? method.slug ?? `#${method.id}`

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
    form.discount_id = ''
    form.trainer_id = ''
    calculateEndDate()
})

watch(() => form.start_date, calculateEndDate)

watch(() => form.payment_method_id, () => {
    form.card_type_id = ''
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
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel value="Զեղչ" />
                        <select
                            v-model="form.discount_id"
                            class="form-select"
                        >
                            <option value="">
                                Առանց զեղչի
                            </option>
                            <option
                                v-for="discount in availableDiscounts"
                                :key="discount.id"
                                :value="discount.id"
                            >
                                {{ discountName(discount) }} - {{ discount.type === 'percent' ? `${discount.value}%` : discount.value }}
                            </option>
                        </select>
                        <InputError :message="form.errors.discount_id" />
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel value="Մարզիչ" />
                        <select
                            v-model="form.trainer_id"
                            class="form-select"
                        >
                            <option value="">
                                Առանց մարզչի
                            </option>
                            <option
                                v-for="trainer in availableTrainers"
                                :key="trainer.id"
                                :value="trainer.id"
                            >
                                {{ formatUser(trainer) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.trainer_id" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <InputLabel value="Միջնորդավճարի տեսակ" />
                        <select
                            v-model="form.price_type"
                            class="form-select"
                        >
                            <option value="fixed">Ֆիքսված</option>
                            <option value="percent">Տոկոս</option>
                        </select>
                        <InputError :message="form.errors.price_type" />
                    </div>

                    <div class="col-md-4 mb-4">
                        <InputLabel value="Վճարման գումար" />
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="col-md-4 mb-4">
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
                    <div class="col-md-6 mb-4">
                        <InputLabel value="Վճարման եղանակ" />
                        <select
                            v-model="form.payment_method_id"
                            class="form-select"
                        >
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

                    <div class="col-md-6 mb-4">
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
