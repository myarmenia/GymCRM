<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { useAlert } from '@/composables/useAlert'
import { useConfirm } from '@/composables/useConfirm'
import { translate } from '/resources/js/trans'
import { printHdmReceipt } from '@/composables/useHdmPrint'
import axios from 'axios'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const t = (key, replacements = {}) => translate(page.props.translations, `app.${key}`, replacements)
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const { confirm } = useConfirm()
const alert = useAlert()

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
    prepaymentTermination: {
        type: Object,
        default: () => ({
            can_start: false,
            requires_workflow: false,
            available_prepayment: 0,
            sources: [],
            workflow: null,
            reason: null,
        }),
    },
    gateway: {
        type: Object,
        default: () => ({}),
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
    is_hdm: Boolean(props.membershipSale.is_hdm),
    parent_payment_id: '',
})

const cancelForm = useForm({})
const retryingHdmPaymentId = ref(null)
const terminationForm = useForm({
    refund_amount: Number(props.prepaymentTermination.available_prepayment || 0),
    notes: '',
})

const membership = computed(() => props.membershipSale.person_memberships?.[0] ?? null)
const saleDiscounts = computed(() => props.membershipSale.discounts ?? [])
const transactions = computed(() => props.membershipSale.payments ?? [])
const debt = computed(() => Number(props.debtAmount || 0))
const paid = computed(() => Number(props.paidAmount || 0))
const refunded = computed(() => Number(props.refundedAmount || 0))
const netPaid = computed(() => Number(props.netPaidAmount || 0))
const availableRefund = computed(() => Number(props.availableRefundAmount || 0))
const terminationPrepayment = computed(() => Number(props.prepaymentTermination.available_prepayment || 0))
const pendingTermination = computed(() => props.prepaymentTermination.workflow?.status === 'pending')
const terminationRequiresWorkflow = computed(() => Boolean(props.prepaymentTermination.requires_workflow))
const terminationServiceAmount = computed(() => Math.max(
    terminationPrepayment.value - Number(terminationForm.refund_amount || 0),
    0,
))
const terminationRefundBreakdown = computed(() => {
    let serviceLeft = terminationServiceAmount.value

    return (props.prepaymentTermination.sources ?? []).map(source => {
        const available = Number(source.available_amount || 0)
        const used = Math.min(available, serviceLeft)
        serviceLeft = Math.max(serviceLeft - used, 0)

        return { ...source, refund_amount: Math.max(available - used, 0) }
    }).filter(source => source.refund_amount > 0)
})
const unprintedHdmPayments = computed(() => transactions.value.filter(transaction => (
    transaction.type === 'payment'
    && transaction.status === 'paid'
    && transaction.is_hdm
    && !transaction.has_successful_hdm_operation
)))
const retryableHdmPayments = computed(() => unprintedHdmPayments.value.filter(transaction => transaction.can_retry_hdm_receipt))
const selectedActionMode = ref(null)
const refundablePayments = computed(() => transactions.value.filter(transaction => (
    !terminationRequiresWorkflow.value
    &&
    transaction.type === 'payment'
    && transaction.status === 'paid'
    && !transaction.hdm_prepayment_consumed
    && !transaction.hdm_payment_closed_by_return
    && Number(transaction.refundable_amount || 0) > 0
)))
const selectedOriginalPayment = computed(() => refundablePayments.value.find(payment => (
    Number(payment.id) === Number(actionForm.parent_payment_id)
)) ?? null)
const requiresFullHdmRefund = computed(() => Boolean(selectedOriginalPayment.value?.is_final_hdm_payment))

const actionMode = computed(() => {
    if (selectedActionMode.value === 'refund'
        && availableRefund.value > 0
        && refundablePayments.value.length > 0
        && !terminationRequiresWorkflow.value) {
        return selectedActionMode.value
    }

    if (selectedActionMode.value === 'payment' && debt.value > 0) {
        return selectedActionMode.value
    }

    if (debt.value > 0) {
        return 'payment'
    }

    if (availableRefund.value > 0
        && refundablePayments.value.length > 0
        && !terminationRequiresWorkflow.value) {
        return 'refund'
    }

    return null
})

const actionLimit = computed(() => {
    if (actionMode.value !== 'refund') {
        return debt.value
    }

    const isFullFinalHdmRefund = actionForm.is_full_refund
        && selectedOriginalPayment.value?.is_final_hdm_payment

    return isFullFinalHdmRefund
        ? availableRefund.value
        : Math.min(availableRefund.value, Number(selectedOriginalPayment.value?.refundable_amount || 0))
})
const isRefundMode = computed(() => actionMode.value === 'refund')
const isPaymentMode = computed(() => actionMode.value === 'payment')
const partialPaymentError = computed(() => {
    if (!isPaymentMode.value || !actionForm.is_partial_payment) return ''

    const amount = Math.round(Number(actionForm.amount || 0) * 100)
    const remainingDebt = Math.round(actionLimit.value * 100)

    return amount > 0 && amount < remainingDebt
        ? ''
        : t('sales.the_partial_payment_must_be_greater_than_zero_and_less_than_the_4')
})
const isMembershipCancelled = computed(() => membership.value?.status === 'cancelled')
const selectedPaymentMethod = computed(() => props.paymentMethods.find(method => Number(method.id) === Number(actionForm.payment_method_id)))
const availableCardTypes = computed(() => selectedPaymentMethod.value?.card_types ?? selectedPaymentMethod.value?.cardTypes ?? [])

const actionText = computed(() => {
    if (isRefundMode.value) {
        return {
            title: t('sales.new_refund'),
            amount: t('sales.refund_amount'),
            method: t('sales.refund_method'),
            methodPlaceholder: t('sales.select_refund_method'),
            notes: t('sales.refund_notes'),
            button: t('sales.save_refund'),
            info: t('sales.refund_amount'),
            full: t('sales.full_refund'),
            partial: t('sales.partial_refund'),
        }
    }

    return {
        title: t('sales.new_payment'),
        amount: t('sales.payment_amount'),
        method: t('people.payment_method'),
        methodPlaceholder: t('sales.select_payment_method'),
        notes: t('sales.payment_notes'),
        button: t('sales.save_payment'),
        info: t('sales.payment_amount'),
        full: t('sales.full_payment'),
        partial: t('sales.partial_payment'),
    }
})

const formatAmount = value => Number(value || 0).toFixed(2)
const transactionRefundableAmount = transaction => transaction?.is_final_hdm_payment
    ? availableRefund.value
    : Math.min(availableRefund.value, Number(transaction?.refundable_amount || 0))
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const personName = person => `${person?.name ?? ''} ${person?.surname ?? ''}`.trim() || '-'
const userName = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || '-'
const translatedName = item => item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
    ?? item?.name
    ?? item?.slug
    ?? (item?.id ? `#${item.id}` : '-')

const discountName = discount => translatedName(discount?.discount ?? discount)
const discountTypeLabel = type => ({
    fixed: t('sales.fixed_amount'),
    fix: t('sales.fixed_amount'),
    percent: t('sales.percent'),
    '%': t('sales.percent'),
}[type] ?? type ?? '-')
const saleStatusLabel = status => ({
    unpaid: t('people.unpaid'),
    partial: t('sales.partial'),
    paid: t('people.paid'),
    refunded: t('sales.refunded'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')
const paymentStatusLabel = status => ({
    unpaid: t('people.unpaid'),
    pending: t('people.waiting'),
    paid: t('people.paid'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')
const transactionTypeLabel = type => ({
    payment: t('people.payment'),
    refund: t('people.refund'),
}[type] ?? type ?? '-')

const membershipDiscountAmount = computed(() => {
    return saleDiscounts.value.reduce((total, discount) => total + Number(discount.discount_amount || 0), 0)
})

const resetActionFormForMode = () => {
    actionForm.clearErrors()
    actionForm.payment_method_id = ''
    actionForm.card_type_id = ''
    actionForm.notes = ''
    actionForm.is_hdm = Boolean(props.membershipSale.is_hdm)
    actionForm.parent_payment_id = ''
    actionForm.amount = actionLimit.value
    actionForm.is_full_payment = isPaymentMode.value
    actionForm.is_partial_payment = false
    actionForm.is_full_refund = isRefundMode.value
    actionForm.is_partial_refund = false
}

const selectActionMode = mode => {
    selectedActionMode.value = mode
}

const selectRefundPayment = paymentId => {
    selectedActionMode.value = 'refund'
    nextTick(() => {
        actionForm.parent_payment_id = paymentId
    })
}

watch(actionMode, resetActionFormForMode, { immediate: true })

watch(() => actionForm.payment_method_id, () => {
    if (isRefundMode.value) {
        return
    }

    actionForm.card_type_id = ''
})

watch(() => actionForm.parent_payment_id, () => {
    if (!isRefundMode.value) {
        return
    }

    const payment = selectedOriginalPayment.value
    actionForm.payment_method_id = payment?.payment_method_id ?? ''
    actionForm.card_type_id = payment?.card_type_id ?? ''
    actionForm.is_hdm = Boolean(payment?.is_hdm)
    actionForm.amount = transactionRefundableAmount(payment)
    actionForm.is_full_refund = Boolean(payment)
    actionForm.is_partial_refund = false
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

    if (!enabled && requiresFullHdmRefund.value) {
        actionForm.is_full_refund = true
        actionForm.is_partial_refund = false
        actionForm.amount = actionLimit.value
    } else if (enabled) {
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

    if (enabled && requiresFullHdmRefund.value) {
        actionForm.is_partial_refund = false
        actionForm.is_full_refund = true
        actionForm.amount = actionLimit.value
    } else if (enabled) {
        actionForm.is_full_refund = false
    } else if (!actionForm.is_full_refund) {
        actionForm.amount = 0
    }
})

watch(actionLimit, value => {
    if ((isPaymentMode.value && actionForm.is_full_payment) || (isRefundMode.value && actionForm.is_full_refund)) {
        actionForm.amount = value
    }

    if (!(isPaymentMode.value && actionForm.is_partial_payment) && Number(actionForm.amount || 0) > value) {
        actionForm.amount = value
    }
})

watch(() => actionForm.amount, value => {
    const amount = Number(value || 0)

    if (amount < 0) {
        actionForm.amount = 0
    }

    if (!(isPaymentMode.value && actionForm.is_partial_payment) && actionLimit.value > 0 && amount > actionLimit.value) {
        actionForm.amount = actionLimit.value
    }
})

watch(() => terminationForm.refund_amount, value => {
    const amount = Number(value || 0)
    if (amount < 0) terminationForm.refund_amount = 0
    if (amount > terminationPrepayment.value) terminationForm.refund_amount = terminationPrepayment.value
})

const printTerminationSteps = async steps => {
    for (const step of steps ?? []) {
        alert.info(step.kind === 'service'
            ? t('sales.the_provided_service_fiscal_receipt_is_printing')
            : t('sales.the_prepayment_refund_fiscal_receipt_is_printing'))

        const result = await printHdmReceipt(step.data, props.gateway, currentLocale.value)
        if (!result.success) {
            alert.warning(t('sales.fiscal_operation_incomplete_with_message', { message: result.message }))
            return false
        }
    }

    alert.success(t('sales.contract_terminated_and_prepayment_refunded_successfully'))
    return true
}

const submitTermination = async () => {
    terminationForm.clearErrors()

    const amount = Number(terminationForm.refund_amount || 0)
    if (amount <= 0 || amount > terminationPrepayment.value) {
        terminationForm.setError('refund_amount', t('sales.the_refund_must_be_greater_than_zero_and_not_exceed_the_availabl'))
        return
    }

    const approved = await confirm(
        translate(page.props.translations, 'app.confirm.termination_message', {
            refund: formatAmount(amount),
            service: formatAmount(terminationServiceAmount.value),
        }),
        {
            title: translate(page.props.translations, 'app.confirm.termination_title'),
            confirmClass: 'btn-danger',
        },
    )

    if (!approved) return

    terminationForm.processing = true
    try {
        const response = await axios.post(route('membership_sale.terminate', {
            locale: currentLocale.value,
            id: props.membershipSale.id,
        }), {
            refund_amount: terminationForm.refund_amount,
            notes: terminationForm.notes,
        })

        await printTerminationSteps(response.data.print_steps)
        router.visit(response.data.redirect)
    } catch (error) {
        if (error.response?.status === 422 && error.response.data?.errors) {
            Object.entries(error.response.data.errors).forEach(([field, messages]) => {
                terminationForm.setError(field, Array.isArray(messages) ? messages[0] : messages)
            })
            return
        }

        alert.error(error.response?.data?.message ?? t('sales.could_not_start_contract_termination'))
    } finally {
        terminationForm.processing = false
    }
}

const resumeTermination = async () => {
    terminationForm.processing = true
    try {
        const response = await axios.post(route('membership_sale.terminate.resume', {
            locale: currentLocale.value,
            id: props.membershipSale.id,
        }))

        await printTerminationSteps(response.data.print_steps)
        router.visit(response.data.redirect)
    } catch (error) {
        alert.error(error.response?.data?.message ?? t('sales.could_not_resume_the_fiscal_operation'))
    } finally {
        terminationForm.processing = false
    }
}

const retryHdmPaymentReceipt = async payment => {
    retryingHdmPaymentId.value = payment.id

    try {
        const response = await axios.post(route('membership_sale.payments.hdm.retry', {
            locale: currentLocale.value,
            id: props.membershipSale.id,
            payment: payment.id,
        }))

        if (!response.data.need_print || !response.data.print_data) {
            alert.success(t('sales.fiscal_receipt_has_already_been_printed'))
            router.visit(response.data.redirect)
            return
        }

        alert.info(t('sales.printing_payment_receipt', { id: payment.id }))
        const result = await printHdmReceipt(response.data.print_data, props.gateway, currentLocale.value)

        if (result.success) {
            alert.success(t('sales.fiscal_receipt_printed_successfully'))
        } else {
            alert.warning(t('sales.receipt_print_failed_with_message', { message: result.message }))
        }

        router.visit(response.data.redirect)
    } catch (error) {
        alert.error(error.response?.data?.message ?? t('sales.could_not_prepare_the_fiscal_receipt_reprint'))
    } finally {
        retryingHdmPaymentId.value = null
    }
}

const submitAction = async () => {
    if (!actionMode.value) {
        return
    }

    actionForm.clearErrors()
    if (partialPaymentError.value) {
        actionForm.setError('amount', partialPaymentError.value)
        return
    }

    const routeName = isRefundMode.value
        ? 'membership_sale.refunds.store'
        : 'membership_sale.payments.store'

    const payload = {
        amount: actionForm.amount,
        payment_method_id: actionForm.payment_method_id,
        card_type_id: actionForm.card_type_id,
        is_hdm: actionForm.is_hdm,
        ...(isRefundMode.value ? {
            parent_payment_id: actionForm.parent_payment_id,
            is_partial_refund: actionForm.is_partial_refund,
            is_full_refund: actionForm.is_full_refund,
            refund_notes: actionForm.notes,
        } : {
            is_partial_payment: actionForm.is_partial_payment,
            is_full_payment: actionForm.is_full_payment,
            payment_notes: actionForm.notes,
        }),
    }

    actionForm.processing = true

    try {
        const response = await axios.post(route(routeName, {
            locale: currentLocale.value,
            id: props.membershipSale.id,
        }), payload)

        if (response.data.need_print && response.data.print_data) {
            alert.info(isRefundMode.value
                ? t('sales.refund_saved_the_fiscal_refund_receipt_is_printing')
                : t('sales.payment_saved_the_fiscal_receipt_is_printing'))
            const printResult = await printHdmReceipt(
                response.data.print_data,
                props.gateway,
                currentLocale.value,
            )

            if (printResult.success) {
                alert.success(isRefundMode.value
                    ? t('sales.fiscal_refund_receipt_printed_successfully')
                    : t('sales.fiscal_payment_receipt_printed_successfully'))
            } else {
                alert.warning(t('sales.operation_saved_receipt_failed', { message: printResult.message }))
            }
        } else if (response.data.print_error) {
            alert.warning(response.data.message)
        } else {
            alert.success(isRefundMode.value
                ? t('sales.refund_saved_successfully')
                : t('sales.payment_saved_successfully'))
        }

        router.visit(response.data.redirect)
    } catch (error) {
        if (error.response?.status === 422 && error.response.data?.errors) {
            Object.entries(error.response.data.errors).forEach(([field, messages]) => {
                actionForm.setError(field, Array.isArray(messages) ? messages[0] : messages)
            })
            return
        }

        alert.error(error.response?.data?.message ?? t('sales.could_not_save_the_operation'))
    } finally {
        actionForm.processing = false
    }
}

const cancelMembership = async () => {
    const approved = await confirm(translate(page.props.translations, 'app.confirm.cancel_membership'), {
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
    <Head :title="t('people.payments')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                {{ t('people.payments') }}
            </h2>
        </template>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('sales.sale_details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.client') }}</span>
                            <strong>{{ personName(membershipSale.person) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('people.membership') }}</span>
                            <strong>{{ translatedName(membershipSale.membership_plan) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('people.trainer') }}</span>
                            <strong>{{ userName(membership?.trainer) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.sale_date') }}</span>
                            <strong>{{ formatDate(membershipSale.sold_at) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('membership.status') }}</span>
                            <span class="badge bg-label-primary">{{ saleStatusLabel(membershipSale.payment_status) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ t('sales.membership_status') }}</span>
                            <span
                                class="badge"
                                :class="isMembershipCancelled ? 'bg-label-danger' : 'bg-label-success'"
                            >
                                {{ isMembershipCancelled ? t('people.cancelled') : t('membership.active') }}
                            </span>
                        </div>
                        <button
                            v-if="!isMembershipCancelled && !terminationRequiresWorkflow"
                            type="button"
                            class="btn btn-label-danger w-100"
                            :disabled="cancelForm.processing"
                            @click="cancelMembership"
                        >
                            {{ t('sales.cancel_membership') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('sales.price_calculation') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ t('sales.membership_price') }}</span>
                            <span>{{ formatAmount(membershipSale.total_price) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>{{ t('sales.membership_discount') }}</span>
                            <span>- {{ formatAmount(membershipDiscountAmount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>{{ t('sales.manual_discount') }}</span>
                            <span>- {{ formatAmount(membershipSale.discount_amount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>{{ t('sales.total_discount') }}</span>
                            <span>- {{ formatAmount(Number(membershipDiscountAmount || 0) + Number(membershipSale.discount_amount || 0)) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold mb-2">
                            <span>{{ t('sales.final_amount_due') }}</span>
                            <span class="text-primary">{{ formatAmount(membershipSale.final_price) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ t('sales.total_paid') }}</span>
                            <span class="text-success">- {{ formatAmount(paid) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>{{ t('sales.total_refunded') }}</span>
                            <span>- {{ formatAmount(refunded) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ t('sales.net_paid') }}</span>
                            <span>{{ formatAmount(netPaid) }}</span>
                        </div>
                        <div class="alert alert-success mt-3 mb-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">{{ t('sales.debt') }}</span>
                                <span class="fw-bold fs-4">{{ formatAmount(debt) }}</span>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">{{ t('sales.available_refund') }}</span>
                                <span class="fw-bold fs-4">{{ formatAmount(availableRefund) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="terminationRequiresWorkflow || prepaymentTermination.workflow"
            class="card border-warning mb-4"
        >
            <div class="card-header">
                <h5 class="mb-0">{{ t('sales.contract_termination_and_prepayment_refund') }}</h5>
            </div>
            <div class="card-body">
                <div v-if="pendingTermination" class="alert alert-warning mb-3">
                    <div class="fw-bold mb-1">{{ t('sales.the_fiscal_operation_has_not_finished_yet') }}</div>
                    <div>{{ t('sales.service_refund_summary', {
                        service: formatAmount(prepaymentTermination.workflow.service_amount),
                        refund: formatAmount(prepaymentTermination.workflow.refund_amount),
                    }) }}</div>
                </div>

                <div
                    v-else-if="prepaymentTermination.workflow?.status === 'success'"
                    class="alert alert-success mb-0"
                >
                    {{ t('sales.termination_completed_summary', {
                        service: formatAmount(prepaymentTermination.workflow.service_amount),
                        refund: formatAmount(prepaymentTermination.workflow.refund_amount),
                    }) }}
                </div>

                <div v-else-if="prepaymentTermination.reason" class="alert alert-danger mb-0">
                    <div>{{ prepaymentTermination.reason }}</div>
                    <div v-if="retryableHdmPayments.length" class="d-flex flex-wrap gap-2 mt-3">
                        <button
                            v-for="payment in retryableHdmPayments"
                            :key="payment.id"
                            type="button"
                            class="btn btn-danger btn-sm"
                            :disabled="retryingHdmPaymentId !== null"
                            @click="retryHdmPaymentReceipt(payment)"
                        >
                            {{ t('sales.print_payment_receipt_with_id', { id: payment.id }) }}
                        </button>
                    </div>
                    <div
                        v-else-if="unprintedHdmPayments.some(payment => payment.hdm_print_status === 'pending')"
                        class="mt-2 small"
                    >
                        {{ t('sales.the_fiscal_operation_is_pending_check_its_status_first') }}
                    </div>
                </div>

                <form v-else-if="prepaymentTermination.can_start" @submit.prevent="submitTermination">
                    <div class="alert alert-info py-2">
                        {{ t('sales.visits_are_not_calculated_automatically_yet_the_cashier_sets_the') }}
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <InputLabel :value="t('sales.available_prepayment')" />
                            <div class="form-control bg-light fw-bold">{{ formatAmount(terminationPrepayment) }}</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <InputLabel :value="t('sales.refund_amount')" />
                            <input
                                v-model="terminationForm.refund_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="terminationPrepayment"
                                class="form-control"
                            />
                            <InputError :message="terminationForm.errors.refund_amount" />
                        </div>
                        <div class="col-md-4 mb-3">
                            <InputLabel :value="t('sales.provided_service_amount')" />
                            <div class="form-control bg-light fw-bold">{{ formatAmount(terminationServiceAmount) }}</div>
                        </div>
                    </div>

                    <div v-if="terminationRefundBreakdown.length" class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>{{ t('sales.initial_payment') }}</th>
                                    <th>{{ t('sales.refund_method') }}</th>
                                    <th>{{ t('people.refund') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="source in terminationRefundBreakdown" :key="source.payment_uuid">
                                    <td>#{{ source.payment_id }} — {{ formatAmount(source.available_amount) }}</td>
                                    <td>
                                        {{ translatedName(source.payment_method) }}
                                        <span v-if="source.card_type">— {{ source.card_type.name ?? source.card_type.slug }}</span>
                                    </td>
                                    <td>{{ formatAmount(source.refund_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <InputLabel :value="t('people.notes')" />
                        <textarea v-model="terminationForm.notes" class="form-control" rows="2" />
                        <InputError :message="terminationForm.errors.notes" />
                    </div>

                    <div class="d-flex justify-content-end">
                        <PrimaryButton :disabled="terminationForm.processing">
                            {{ t('sales.terminate_contract_and_issue_refund') }}
                        </PrimaryButton>
                    </div>
                </form>

                <div v-if="pendingTermination" class="d-flex justify-content-end">
                    <button
                        type="button"
                        class="btn btn-warning"
                        :disabled="terminationForm.processing"
                        @click="resumeTermination"
                    >
                        {{ t('sales.resume_fiscal_operation') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ t('sales.transaction_history') }}</h5>
            </div>
            <div class="card-body">
                <div
                    v-if="transactions.length"
                    class="table-responsive text-nowrap"
                >
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ t('people.amount') }}</th>
                                <th>{{ t('sales.method') }}</th>
                                <th>{{ t('sales.card') }}</th>
                                <th>{{ t('people.type') }}</th>
                                <th>{{ t('membership.status') }}</th>
                                <th>{{ t('sales.fiscal_receipt_2') }}</th>
                                <th>{{ t('people.date') }}</th>
                                <th>{{ t('people.action') }}</th>
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
                                <td>{{ transaction.is_hdm ? t('membership.yes') : t('membership.no') }}</td>
                                <td>{{ formatDate(transaction.created_at) }}</td>
                                <td>
                                    <span
                                        v-if="transaction.type === 'payment' && transaction.hdm_payment_closed_by_return"
                                        class="badge bg-label-secondary"
                                    >
                                        {{ t('sales.fiscal_receipt_fully_refunded') }}
                                    </span>
                                    <span
                                        v-else-if="transaction.type === 'payment' && transaction.hdm_prepayment_consumed"
                                        class="badge bg-label-info"
                                    >
                                        {{ t('sales.included_in_the_final_fiscal_receipt') }}
                                    </span>
                                    <button
                                        v-else-if="transaction.can_retry_hdm_receipt"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        :disabled="retryingHdmPaymentId !== null"
                                        @click="retryHdmPaymentReceipt(transaction)"
                                    >
                                        {{ t('sales.print_fiscal_receipt') }}
                                    </button>
                                    <button
                                        v-else-if="transaction.type === 'payment' && Number(transaction.refundable_amount || 0) > 0"
                                        type="button"
                                        class="btn btn-sm btn-outline-warning"
                                        @click="selectRefundPayment(transaction.id)"
                                    >
                                        {{ t('sales.refund_up_to_amount', { amount: formatAmount(transactionRefundableAmount(transaction)) }) }}
                                    </button>
                                    <span v-else>-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="text-muted"
                >
                    {{ t('people.no_transactions') }}
                </div>
            </div>
        </div>

        <div class="row align-items-stretch">
            <div :class="actionMode ? 'col-lg-7 mb-4' : 'col-12 mb-4'">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('sales.applied_membership_discounts') }}</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="saleDiscounts.length"
                            class="table-responsive text-nowrap"
                        >
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ t('sales.discount') }}</th>
                                        <th>{{ t('people.type') }}</th>
                                        <th>{{ t('membership.cost') }}</th>
                                        <th>{{ t('people.amount') }}</th>
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
                            {{ t('sales.no_membership_discounts') }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="actionMode"
                class="col-lg-5 mb-4"
            >
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <h5 class="mb-0">{{ actionText.title }}</h5>
                        <div
                            v-if="debt > 0 && availableRefund > 0"
                            class="btn-group btn-group-sm"
                        >
                            <button
                                type="button"
                                class="btn"
                                :class="isPaymentMode ? 'btn-primary' : 'btn-outline-primary'"
                                @click="selectActionMode('payment')"
                            >
                                {{ t('people.payment') }}
                            </button>
                            <button
                                type="button"
                                class="btn"
                                :class="isRefundMode ? 'btn-warning' : 'btn-outline-warning'"
                                @click="selectActionMode('refund')"
                            >
                                {{ t('people.refund') }}
                            </button>
                        </div>
                    </div>
                    <form
                        class="card-body"
                        @submit.prevent="submitAction"
                    >
                        <div
                            v-if="isRefundMode"
                            class="mb-3"
                        >
                            <InputLabel :value="t('sales.payment_being_refunded')" />
                            <select
                                v-model="actionForm.parent_payment_id"
                                class="form-select"
                            >
                                <option value="">{{ t('sales.select_payment') }}</option>
                                <option
                                    v-for="payment in refundablePayments"
                                    :key="payment.id"
                                    :value="payment.id"
                                >
                                    #{{ payment.id }} — {{ formatAmount(payment.amount) }} —
                                    {{ translatedName(payment.payment_method) }} —
                                    {{ t('sales.refund_balance_amount', { amount: formatAmount(transactionRefundableAmount(payment)) }) }}
                                    {{ payment.is_hdm ? t('sales.fiscal_receipt') : '' }}
                                </option>
                            </select>
                            <InputError :message="actionForm.errors.parent_payment_id" />
                        </div>

                        <div class="d-flex flex-wrap gap-4 mb-3">
                            <label class="form-check">
                                <input
                                    v-if="isRefundMode"
                                    v-model="actionForm.is_full_refund"
                                    type="checkbox"
                                    class="form-check-input"
                                    :disabled="requiresFullHdmRefund"
                                />
                                <input
                                    v-else
                                    v-model="actionForm.is_full_payment"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <span class="form-check-label">{{ actionText.full }}</span>
                            </label>
                            <label
                                v-if="!isRefundMode || !requiresFullHdmRefund"
                                class="form-check"
                            >
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
                        <div
                            v-if="isRefundMode && requiresFullHdmRefund"
                            class="alert alert-info py-2"
                        >
                            {{ t('sales.the_final_fiscal_receipt_includes_all_membership_payments_and_ca') }}
                        </div>
                        <InputError :message="actionForm.errors.is_full_payment || actionForm.errors.is_full_refund" />
                        <InputError :message="actionForm.errors.is_partial_payment || actionForm.errors.is_partial_refund" />

                        <div class="mb-3">
                            <InputLabel :value="actionText.method" />
                            <select
                                v-model="actionForm.payment_method_id"
                                class="form-select"
                                :disabled="isRefundMode"
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
                            <InputLabel :value="t('sales.card_type')" />
                            <select
                                v-model="actionForm.card_type_id"
                                class="form-select"
                                :disabled="isRefundMode"
                            >
                                <option value="">
                                    {{ t('sales.select_card_type') }}
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
                            <InputError :message="partialPaymentError || actionForm.errors.amount" />
                        </div>

                        <div
                            v-else
                            class="alert alert-info py-2"
                        >
                            {{ actionText.info }}: <strong>{{ formatAmount(actionLimit) }}</strong>
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
                            <InputLabel :value="t('sales.fiscal_receipt_2')" />
                            <label class="form-check mt-2">
                                <input
                                    v-model="actionForm.is_hdm"
                                    type="checkbox"
                                    class="form-check-input"
                                    disabled
                                />
                                <span class="form-check-label">
                                    {{ t('sales.fiscal_receipt_3') }}
                                </span>
                            </label>
                            <InputError :message="actionForm.errors.is_hdm" />
                            <p v-if="membershipSale.is_hdm === null" class="text-danger mt-2">
                                {{ t('sales.the_fiscal_mode_of_this_older_sale_is_unknown_check_its_payment') }}
                            </p>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <Link
                                class="btn btn-label-secondary"
                                :href="route('membership_sale.list', { locale: currentLocale })"
                            >
                                {{ t('people.back') }}
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
