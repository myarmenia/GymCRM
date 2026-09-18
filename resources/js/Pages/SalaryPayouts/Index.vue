<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    payables: {
        type: Object,
        required: true,
    },
    payouts: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        required: true,
    },
    historySummary: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Object,
        default: () => ({
            payees: [],
            gyms: [],
            paymentMethods: [],
            types: [],
        }),
    },
    canVoid: {
        type: Boolean,
        default: false,
    },
})

const filters = ref({ ...props.filters })
const activeTab = ref(props.filters.tab === 'history' ? 'history' : 'payables')
const selectedKeys = ref([])
const expandedPayoutIds = ref([])
const showTransferModal = ref(false)
const transferContext = ref(null)
const showRefundModal = ref(false)
const refundContext = ref(null)
const showVoidModal = ref(false)
const voidContext = ref(null)

const localDateTime = () => {
    const now = new Date()
    const offset = now.getTimezoneOffset()

    return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 16)
}

const payoutForm = useForm({
    items: [],
    payment_method_id: '',
    paid_at: localDateTime(),
    reference: '',
    notes: '',
})

const singlePaymentAmount = ref('')

const voidForm = useForm({
    reason: '',
})

const refundForm = useForm({
    payout_item_id: '',
    amount: '',
    payment_method_id: '',
    refunded_at: '',
    reference: '',
    reason: '',
})

const transferForm = useForm({
    amount: '',
    reason: '',
})

const selectedPayables = computed(() => (props.payables.data ?? [])
    .filter(item => selectedKeys.value.includes(item.key)))

const selectionAnchor = computed(() => selectedPayables.value[0] ?? null)

const canSelect = item => !selectionAnchor.value
    || (
        Number(selectionAnchor.value.payee_id) === Number(item.payee_id)
        && Number(selectionAnchor.value.gym_id) === Number(item.gym_id)
    )

const compatibleRows = computed(() => {
    const rows = props.payables.data ?? []

    if (!selectionAnchor.value) {
        return rows
    }

    return rows.filter(canSelect)
})

const allCompatibleSelected = computed(() => compatibleRows.value.length > 0
    && compatibleRows.value.every(item => selectedKeys.value.includes(item.key)))

const selectedTotal = computed(() => {
    if (selectedPayables.value.length === 1) {
        return Number(singlePaymentAmount.value || 0)
    }

    return selectedPayables.value.reduce((sum, item) => sum + Number(item.amount || 0), 0)
})

const selectedPayee = computed(() => selectionAnchor.value?.payee ?? '-')
const selectedGym = computed(() => selectionAnchor.value?.gym ?? '-')

const toggleAll = () => {
    if (allCompatibleSelected.value) {
        selectedKeys.value = []
        return
    }

    const rows = props.payables.data ?? []
    const anchor = selectionAnchor.value ?? rows[0]

    if (!anchor) {
        return
    }

    selectedKeys.value = rows
        .filter(item => (
            Number(item.payee_id) === Number(anchor.payee_id)
            && Number(item.gym_id) === Number(anchor.gym_id)
        ))
        .map(item => item.key)
}

const submitPayout = () => {
    payoutForm.items = selectedPayables.value.map(item => ({
        id: item.id,
        amount: selectedPayables.value.length === 1
            ? Number(singlePaymentAmount.value)
            : Number(item.amount),
    }))

    payoutForm.post(route('salary-payouts.store', {
        locale: currentLocale.value,
    }), {
        preserveScroll: true,
        onSuccess: () => {
            selectedKeys.value = []
            payoutForm.reset()
            payoutForm.paid_at = localDateTime()
        },
    })
}

const openTransferModal = item => {
    transferContext.value = item
    transferForm.clearErrors()
    transferForm.amount = String(item.amount)
    transferForm.reason = ''
    showTransferModal.value = true
}

const closeTransferModal = () => {
    if (transferForm.processing) {
        return
    }

    showTransferModal.value = false
    transferContext.value = null
    transferForm.reset()
    transferForm.clearErrors()
}

const submitTransfer = () => {
    if (!transferContext.value) {
        return
    }

    transferForm.post(route('salary-payouts.transfer', {
        locale: currentLocale.value,
        salaryPayableAssignment: transferContext.value.id,
    }), {
        preserveScroll: true,
        onSuccess: closeTransferModal,
    })
}

const openRefundModal = (payout, item) => {
    refundContext.value = { payout, item }
    refundForm.clearErrors()
    refundForm.payout_item_id = item.id
    refundForm.amount = String(item.refundable_amount)
    refundForm.payment_method_id = payout.payment_method_id
    refundForm.refunded_at = localDateTime()
    refundForm.reference = ''
    refundForm.reason = ''
    showRefundModal.value = true
}

const closeRefundModal = () => {
    if (refundForm.processing) {
        return
    }

    showRefundModal.value = false
    refundContext.value = null
    refundForm.reset()
    refundForm.clearErrors()
}

const submitRefund = () => {
    if (!refundContext.value) {
        return
    }

    refundForm.post(route('salary-payouts.refund', {
        locale: currentLocale.value,
        salaryPayout: refundContext.value.payout.id,
    }), {
        preserveScroll: true,
        onSuccess: closeRefundModal,
    })
}

const openVoidModal = payout => {
    voidContext.value = payout
    voidForm.reset()
    voidForm.clearErrors()
    showVoidModal.value = true
}

const closeVoidModal = () => {
    if (voidForm.processing) {
        return
    }

    showVoidModal.value = false
    voidContext.value = null
    voidForm.reset()
    voidForm.clearErrors()
}

const submitVoid = () => {
    if (!voidContext.value) {
        return
    }

    voidForm.patch(route('salary-payouts.void', {
        locale: currentLocale.value,
        salaryPayout: voidContext.value.id,
    }), {
        preserveScroll: true,
        onSuccess: closeVoidModal,
    })
}

const cleanQuery = query => Object.fromEntries(
    Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''),
)

const exportHref = computed(() => route('salary-payouts.export', {
    locale: currentLocale.value,
    ...cleanQuery({ ...filters.value, tab: 'history' }),
}))

const applyFilters = () => {
    const query = {
        ...filters.value,
        tab: activeTab.value,
    }
    delete query.payables_page
    delete query.history_page

    router.get(
        route('salary-payouts.index', { locale: currentLocale.value }),
        cleanQuery(query),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const resetFilters = () => {
    filters.value = {
        tab: activeTab.value,
    }
    applyFilters()
}

const changeTab = tab => {
    if (tab === activeTab.value) {
        return
    }

    activeTab.value = tab
    selectedKeys.value = []
    filters.value = {
        ...filters.value,
        tab,
    }

    applyFilters()
}

watch(
    () => props.filters,
    value => {
        filters.value = { ...(value ?? {}) }
        activeTab.value = value?.tab === 'history' ? 'history' : 'payables'
    },
    { deep: true },
)

watch(
    () => props.payables.data,
    () => {
        selectedKeys.value = []
    },
)

watch(
    selectedPayables,
    value => {
        singlePaymentAmount.value = value.length === 1 ? String(value[0].amount) : ''
    },
    { deep: true },
)

const formatAmount = value => Number(value || 0).toLocaleString('hy-AM', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
})

const formatDateTime = value => value
    ? new Date(value).toLocaleString('hy-AM')
    : '-'

const formatDate = value => value ? String(value).slice(0, 10) : '-'

const togglePayout = payoutId => {
    expandedPayoutIds.value = expandedPayoutIds.value.includes(payoutId)
        ? expandedPayoutIds.value.filter(id => id !== payoutId)
        : [...expandedPayoutIds.value, payoutId]
}

const isPayoutExpanded = payoutId => expandedPayoutIds.value.includes(payoutId)

const payoutStatusLabel = payout => payout.status === 'voided'
    ? t('people.cancelled')
    : Number(payout.refunded_amount) >= Number(payout.amount)
        ? t('operations.fully_refunded')
    : payout.refunded_amount > 0
        ? t('sales.partial_refund')
        : t('people.paid')

const payoutStatusClass = payout => payout.status === 'voided'
    ? 'bg-label-danger'
    : payout.refunded_amount > 0
        ? 'bg-label-warning'
        : 'bg-label-success'

const payableTypeLabel = type => type === 'trainer_monthly_salary' ? t('roles.trainer') : t('staff_reports.salesperson')

const hasAuditNotes = payout => Boolean(
    payout.notes
    || payout.void_reason
    || (payout.refunds ?? []).length
    || (payout.transfers ?? []).length,
)
</script>

<template>
    <Head :title="t('sidebar.salary_payouts')" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">{{ t('sidebar.salary_payouts') }}</h2>
                <div class="text-muted">
                    {{ t('operations.all_payable_amounts_for_trainers_and_salespeople_in_one_place') }}
                </div>
            </div>
            <a v-if="activeTab === 'history'" :href="exportHref" class="btn btn-outline-success">
                <i class="icon-base ti tabler-file-export me-1"></i>
                {{ t('staff_reports.export_to_excel') }}
            </a>
        </div>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: activeTab === 'payables' }"
                    @click="changeTab('payables')"
                >
                    <i class="icon-base ti tabler-list-check me-1"></i>
                    {{ t('operations.salaries_payable') }}
                    <span class="badge bg-label-primary ms-1">{{ payables.total ?? 0 }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: activeTab === 'history' }"
                    @click="changeTab('history')"
                >
                    <i class="icon-base ti tabler-history me-1"></i>
                    {{ t('operations.payment_history') }}
                    <span class="badge bg-label-secondary ms-1">{{ payouts.total ?? 0 }}</span>
                </button>
            </li>
        </ul>

        <div v-if="activeTab === 'payables'" class="row g-4 mb-4">
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar rounded bg-label-primary text-primary">
                            <i class="icon-base ti tabler-list-check"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ t('operations.records_payable') }}</div>
                            <div class="h4 mb-0">{{ summary.payable_count ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar rounded bg-label-warning text-warning">
                            <i class="icon-base ti tabler-cash"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ t('operations.total_amount_payable') }}</div>
                            <div class="h4 mb-0">{{ formatAmount(summary.payable_amount) }} AMD</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="activeTab === 'history'" class="row g-4 mb-4">
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">{{ t('operations.payment_count') }}</div>
                    <div class="h4 mb-0">{{ historySummary.payout_count ?? 0 }}</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">{{ t('sales.total_paid') }}</div>
                    <div class="h4 mb-0">{{ formatAmount(historySummary.paid_amount) }} AMD</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">{{ t('sales.refunded') }}</div>
                    <div class="h4 mb-0 text-warning">{{ formatAmount(historySummary.refunded_amount) }} AMD</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">{{ t('sales.net_paid') }}</div>
                    <div class="h4 mb-0 text-success">{{ formatAmount(historySummary.net_amount) }} AMD</div>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ t('operations.filters') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label">{{ t('roles.staff') }}</label>
                        <select v-model="filters.payee_id" class="form-select">
                            <option value="">{{ t('common.all') }}</option>
                            <option
                                v-for="option in filterOptions.payees"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">{{ t('people.type') }}</label>
                        <select v-model="filters.type" class="form-select">
                            <option value="">{{ t('common.all') }}</option>
                            <option
                                v-for="option in filterOptions.types"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="activeTab === 'history'" class="col-12 col-md-2">
                        <label class="form-label">{{ t('status.status') }}</label>
                        <select v-model="filters.history_status" class="form-select">
                            <option value="">{{ t('common.all') }}</option>
                            <option value="paid">{{ t('people.paid') }}</option>
                            <option value="refunded">{{ t('sales.partial_refund') }}</option>
                            <option value="voided">{{ t('people.cancelled') }}</option>
                        </select>
                    </div>
                    <div v-if="activeTab === 'history'" class="col-12 col-md-2">
                        <label class="form-label">{{ t('people.payment_method') }}</label>
                        <select v-model="filters.payment_method_id" class="form-select">
                            <option value="">{{ t('common.all') }}</option>
                            <option
                                v-for="option in filterOptions.paymentMethods"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div
                        v-if="filterOptions.gyms?.length > 1"
                        class="col-12 col-md-2"
                    >
                        <label class="form-label">{{ t('people.gym') }}</label>
                        <select v-model="filters.gym_id" class="form-select">
                            <option value="">{{ t('common.all') }}</option>
                            <option
                                v-for="option in filterOptions.gyms"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">
                            {{ activeTab === 'history' ? t('operations.paid_from') : t('operations.from') }}
                        </label>
                        <input v-model="filters.start_date" type="date" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">
                            {{ activeTab === 'history' ? t('operations.paid_to') : t('operations.to') }}
                        </label>
                        <input v-model="filters.end_date" type="date" class="form-control">
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="button" class="btn btn-primary" @click="applyFilters">
                            {{ t('staff_reports.apply') }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary" @click="resetFilters">
                            {{ t('inventory.clear') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="activeTab === 'payables' && selectedPayables.length"
            class="card border-primary mb-4"
        >
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">{{ t('sales.new_payment') }}</h5>
                    <div class="text-muted">
                        {{ t('operations.selected_payables_summary', { payee: selectedPayee, gym: selectedGym, count: selectedPayables.length }) }}
                    </div>
                </div>
                <div class="h4 text-primary mb-0">{{ formatAmount(selectedTotal) }} AMD</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div v-if="selectedPayables.length === 1" class="col-12 col-md-3">
                        <label class="form-label">{{ t('operations.payment_amount') }}</label>
                        <input
                            v-model="singlePaymentAmount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :max="selectedPayables[0].amount"
                            class="form-control"
                        >
                        <small class="text-muted">
                            {{ t('operations.available_amount', { amount: formatAmount(selectedPayables[0].amount) }) }}
                        </small>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">{{ t('operations.payment_method') }}</label>
                        <select
                            v-model="payoutForm.payment_method_id"
                            class="form-select"
                            :class="{ 'is-invalid': payoutForm.errors.payment_method_id }"
                        >
                            <option value="">{{ t('people.select') }}</option>
                            <option
                                v-for="option in filterOptions.paymentMethods"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <div class="invalid-feedback">{{ payoutForm.errors.payment_method_id }}</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">{{ t('operations.payment_date_2') }}</label>
                        <input
                            v-model="payoutForm.paid_at"
                            type="datetime-local"
                            class="form-control"
                            :class="{ 'is-invalid': payoutForm.errors.paid_at }"
                        >
                        <div class="invalid-feedback">{{ payoutForm.errors.paid_at }}</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">{{ t('inventory.reference_document_number') }}</label>
                        <input
                            v-model="payoutForm.reference"
                            type="text"
                            class="form-control"
                            maxlength="255"
                        >
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">{{ t('people.notes') }}</label>
                        <input
                            v-model="payoutForm.notes"
                            type="text"
                            class="form-control"
                            maxlength="2000"
                        >
                    </div>
                </div>
                <div
                    v-if="payoutForm.errors.items"
                    class="text-danger mt-3"
                >
                    {{ payoutForm.errors.items }}
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="payoutForm.processing"
                        @click="selectedKeys = []"
                    >
                        {{ t('operations.clear_selection') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-success"
                        :disabled="payoutForm.processing
                            || !payoutForm.payment_method_id
                            || selectedTotal <= 0
                            || (selectedPayables.length === 1 && selectedTotal > Number(selectedPayables[0].amount))"
                        @click="submitPayout"
                    >
                        <i class="icon-base ti tabler-cash me-1"></i>
                        {{ t('operations.pay_amount', { amount: formatAmount(selectedTotal) }) }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="activeTab === 'payables'" class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h5 class="mb-1">{{ t('operations.salaries_payable') }}</h5>
                    <small class="text-muted">
                        {{ t('operations.select_only_rows_for_the_same_employee_in_one_payment') }}
                    </small>
                </div>
                <span class="badge bg-label-primary">{{ t('staff_reports.records_count', { count: payables.total ?? 0 }) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 48px;">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    :checked="allCompatibleSelected"
                                    :disabled="!(payables.data ?? []).length"
                                    @change="toggleAll"
                                >
                            </th>
                            <th>{{ t('roles.staff') }}</th>
                            <th>{{ t('people.type') }}</th>
                            <th>{{ t('operations.basis') }}</th>
                            <th>{{ t('operations.date_month') }}</th>
                            <th>{{ t('operations.generated') }}</th>
                            <th class="text-end">{{ t('people.amount') }}</th>
                            <th class="text-end">{{ t('people.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in payables.data"
                            :key="item.key"
                            :class="{
                                'table-active': selectedKeys.includes(item.key),
                                'opacity-50': !canSelect(item),
                            }"
                        >
                            <td>
                                <input
                                    v-model="selectedKeys"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="item.key"
                                    :disabled="!canSelect(item)"
                                >
                            </td>
                            <td class="fw-semibold">{{ item.payee }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="item.type === 'trainer_monthly_salary'
                                        ? 'bg-label-info'
                                        : 'bg-label-primary'"
                                >
                                    {{ item.type_label }}
                                </span>
                            </td>
                            <td>{{ item.description }}</td>
                            <td>{{ formatDate(item.due_at) }}</td>
                            <td>{{ formatDate(item.generated_at) }}</td>
                            <td class="text-end">
                                <div class="fw-semibold">{{ formatAmount(item.amount) }} AMD</div>
                                <small
                                    v-if="Number(item.assigned_amount) !== Number(item.amount)"
                                    class="text-muted"
                                >
                                    {{ t('operations.section_initial_amount', { amount: formatAmount(item.assigned_amount) }) }}
                                </small>
                            </td>
                            <td class="text-end">
                                <button
                                    v-if="item.can_transfer"
                                    type="button"
                                    class="btn btn-sm btn-outline-info"
                                    :disabled="transferForm.processing"
                                    :title="t('operations.transfer_to_target', { target: item.transfer_target })"
                                    @click="openTransferModal(item)"
                                >
                                    <i class="icon-base ti tabler-transfer me-1"></i>
                                    {{ t('operations.transfer') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!(payables.data ?? []).length">
                            <td colspan="8" class="text-center text-muted py-4">
                                {{ t('operations.there_are_no_salaries_payable') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="payables.links?.length" class="card-footer">
                <Pagination :links="payables.links" />
            </div>
        </div>

        <div v-if="activeTab === 'history'" class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h5 class="mb-1">{{ t('operations.payment_history') }}</h5>
                    <small class="text-muted">{{ t('operations.audit_trail_of_paid_and_cancelled_transactions') }}</small>
                </div>
                <span class="badge bg-label-secondary">{{ t('operations.payment_count_value', { count: payouts.total ?? 0 }) }}</span>
            </div>
            <div class="history-list">
                <div class="history-grid history-grid-header d-none d-xl-grid">
                    <span></span>
                    <span>#</span>
                    <span>{{ t('roles.staff') }}</span>
                    <span>{{ t('sales.method') }}</span>
                    <span>{{ t('people.amount_paid') }}</span>
                    <span>{{ t('operations.payment_date') }}</span>
                    <span>{{ t('status.status') }}</span>
                    <span class="text-end">{{ t('people.amount') }}</span>
                    <span></span>
                </div>

                <div
                    v-for="payout in payouts.data"
                    :key="payout.id"
                    class="history-entry"
                >
                    <div class="history-grid history-summary">
                        <button
                            type="button"
                            class="btn btn-icon btn-sm btn-label-secondary history-toggle"
                            :aria-expanded="isPayoutExpanded(payout.id)"
                            @click="togglePayout(payout.id)"
                        >
                            <i
                                class="icon-base ti"
                                :class="isPayoutExpanded(payout.id)
                                    ? 'tabler-chevron-down'
                                    : 'tabler-chevron-right'"
                            ></i>
                        </button>
                        <div class="fw-semibold">#{{ payout.id }}</div>
                        <div>
                            <div class="fw-semibold">{{ payout.payee }}</div>
                            <small class="text-muted">{{ t('operations.row_count_value', { count: payout.items_count }) }}</small>
                        </div>
                        <div>
                            <small class="history-mobile-label">{{ t('sales.method') }}</small>
                            {{ payout.payment_method }}
                        </div>
                        <div>
                            <small class="history-mobile-label">{{ t('people.amount_paid') }}</small>
                            {{ payout.paid_by }}
                        </div>
                        <div>
                            <small class="history-mobile-label">{{ t('filter.date') }}</small>
                            {{ formatDateTime(payout.paid_at) }}
                        </div>
                        <div>
                            <span class="badge" :class="payoutStatusClass(payout)">
                                {{ payoutStatusLabel(payout) }}
                            </span>
                            <div
                                v-if="payout.status === 'voided'"
                                class="small text-muted mt-1"
                            >
                                {{ payout.voided_by }} · {{ formatDateTime(payout.voided_at) }}
                            </div>
                        </div>
                        <div class="text-xl-end">
                            <div class="fw-bold">
                                {{ formatAmount(payout.amount) }} {{ payout.currency }}
                            </div>
                            <small v-if="payout.refunded_amount > 0" class="text-muted">
                                {{ t('operations.refund_net_summary', { refund: formatAmount(payout.refunded_amount), net: formatAmount(payout.net_amount) }) }}
                            </small>
                        </div>
                        <div class="text-xl-end">
                            <button
                                v-if="canVoid && payout.status === 'paid'"
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                :disabled="voidForm.processing"
                                @click="openVoidModal(payout)"
                            >
                                {{ t('confirm.cancel') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="isPayoutExpanded(payout.id)" class="history-details">
                        <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                            <div>
                                <span class="text-muted">{{ t('logs.reference') }}:</span>
                                <span class="ms-1 fw-medium">{{ payout.reference || t('operations.not_specified') }}</span>
                            </div>
                            <div class="text-muted">
                                {{ t('operations.salary_row_count', { count: payout.items_count }) }}
                            </div>
                        </div>

                        <div class="history-items">
                            <div
                                v-for="item in payout.items"
                                :key="item.id"
                                class="history-item"
                            >
                                <div class="history-item-dot"></div>
                                <div class="flex-grow-1">
                                    <div class="fw-medium">{{ item.description }}</div>
                                    <div class="small text-muted mt-1">
                                        <span class="badge bg-label-secondary me-2">
                                            {{ payableTypeLabel(item.type) }}
                                        </span>
                                        {{ formatDate(item.earned_for_date) }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold">
                                        {{ formatAmount(item.amount) }} {{ payout.currency }}
                                    </div>
                                    <small v-if="item.refunded_amount > 0" class="text-warning">
                                        {{ t('inventory.refund_amount', { amount: formatAmount(item.refunded_amount) }) }}
                                    </small>
                                </div>
                                <button
                                    v-if="canVoid && payout.status === 'paid' && item.refundable_amount > 0"
                                    type="button"
                                    class="btn btn-sm btn-outline-warning"
                                    :disabled="refundForm.processing"
                                    @click="openRefundModal(payout, item)"
                                >
                                    {{ t('sales.partial_refund') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="hasAuditNotes(payout)" class="audit-notes mt-4">
                            <h6 class="mb-3">
                                <i class="icon-base ti tabler-notes me-1"></i>
                                {{ t('operations.notes_and_reasons') }}
                            </h6>

                            <div v-if="payout.notes" class="audit-note">
                                <span class="badge bg-label-primary">{{ t('operations.payment_note') }}</span>
                                <div class="mt-2">{{ payout.notes }}</div>
                            </div>

                            <div v-if="payout.void_reason" class="audit-note audit-note-danger">
                                <span class="badge bg-label-danger">{{ t('operations.cancellation_reason') }}</span>
                                <div class="mt-2">{{ payout.void_reason }}</div>
                                <small class="text-muted">
                                    {{ payout.voided_by }} · {{ formatDateTime(payout.voided_at) }}
                                </small>
                            </div>

                            <div
                                v-for="refund in payout.refunds"
                                :key="`refund-${refund.id}`"
                                class="audit-note audit-note-warning"
                            >
                                <div class="d-flex justify-content-between gap-2 flex-wrap">
                                    <span class="badge bg-label-warning">
                                        {{ t('operations.refund_number', { id: refund.id }) }}
                                    </span>
                                    <strong>{{ formatAmount(refund.amount) }} {{ payout.currency }}</strong>
                                </div>
                                <div class="mt-2">{{ refund.reason }}</div>
                                <small class="text-muted">
                                    {{ refund.refunded_by }} ·
                                    {{ formatDateTime(refund.refunded_at) }} ·
                                    {{ refund.payment_method }}
                                    <template v-if="refund.reference">
                                        · {{ refund.reference }}
                                    </template>
                                </small>
                            </div>

                            <div
                                v-for="transfer in payout.transfers"
                                :key="`transfer-${transfer.id}`"
                                class="audit-note audit-note-info"
                            >
                                <div class="d-flex justify-content-between gap-2 flex-wrap">
                                    <span class="badge bg-label-info">
                                        {{ t('operations.transfer_number', { id: transfer.id }) }}
                                    </span>
                                    <strong>{{ formatAmount(transfer.amount) }} {{ payout.currency }}</strong>
                                </div>
                                <div class="mt-2">
                                    {{ transfer.from_payee }} → {{ transfer.to_payee }}
                                </div>
                                <div>{{ transfer.reason || t('operations.no_transfer_reason_specified') }}</div>
                                <small class="text-muted">
                                    {{ transfer.transferred_by }} ·
                                    {{ formatDateTime(transfer.transferred_at) }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!(payouts.data ?? []).length"
                    class="text-center text-muted py-5"
                >
                    {{ t('operations.there_is_no_payment_history_yet') }}
                </div>
            </div>
            <div v-if="payouts.links?.length" class="card-footer">
                <Pagination :links="payouts.links" />
            </div>
        </div>

        <div
            v-if="showTransferModal && transferContext"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="background: rgba(0, 0, 0, 0.5)"
            @click.self="closeTransferModal"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">{{ t('operations.transfer_salary') }}</h5>
                            <small class="text-muted">
                                {{ transferContext.payee }} → {{ transferContext.transfer_target }}
                            </small>
                        </div>
                        <button
                            type="button"
                            class="btn-close"
                            :disabled="transferForm.processing"
                            @click="closeTransferModal"
                        ></button>
                    </div>
                    <form @submit.prevent="submitTransfer">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                {{ t('operations.amount_available_for_transfer') }}
                                <strong>{{ formatAmount(transferContext.amount) }} AMD</strong>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ t('operations.transfer_amount') }}</label>
                                <input
                                    v-model="transferForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    :max="transferContext.amount"
                                    class="form-control"
                                    :class="{ 'is-invalid': transferForm.errors.amount }"
                                >
                                <div class="invalid-feedback">{{ transferForm.errors.amount }}</div>
                            </div>
                            <div>
                                <label class="form-label">{{ t('operations.transfer_reason') }}</label>
                                <textarea
                                    v-model="transferForm.reason"
                                    rows="3"
                                    maxlength="2000"
                                    class="form-control"
                                    :class="{ 'is-invalid': transferForm.errors.reason }"
                                ></textarea>
                                <div class="invalid-feedback">{{ transferForm.errors.reason }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-label-secondary"
                                :disabled="transferForm.processing"
                                @click="closeTransferModal"
                            >
                                {{ t('confirm.close') }}
                            </button>
                            <button
                                type="submit"
                                class="btn btn-info"
                                :disabled="transferForm.processing
                                    || Number(transferForm.amount) <= 0
                                    || Number(transferForm.amount) > Number(transferContext.amount)"
                            >
                                <span
                                    v-if="transferForm.processing"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                {{ t('operations.confirm_transfer') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showRefundModal && refundContext"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="background: rgba(0, 0, 0, 0.5)"
            @click.self="closeRefundModal"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">{{ t('sales.partial_refund') }}</h5>
                            <small class="text-muted">
                                {{ t('operations.payment_maximum', { id: refundContext.payout.id, amount: formatAmount(refundContext.item.refundable_amount) }) }}
                            </small>
                        </div>
                        <button
                            type="button"
                            class="btn-close"
                            :disabled="refundForm.processing"
                            @click="closeRefundModal"
                        ></button>
                    </div>
                    <form @submit.prevent="submitRefund">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ t('operations.refund_amount') }}</label>
                                <input
                                    v-model="refundForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    :max="refundContext.item.refundable_amount"
                                    class="form-control"
                                    :class="{ 'is-invalid': refundForm.errors.amount }"
                                >
                                <div class="invalid-feedback">{{ refundForm.errors.amount }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ t('inventory.refund_method') }}</label>
                                <select
                                    v-model="refundForm.payment_method_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': refundForm.errors.payment_method_id }"
                                >
                                    <option value="">{{ t('people.select') }}</option>
                                    <option
                                        v-for="option in filterOptions.paymentMethods"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <div class="invalid-feedback">
                                    {{ refundForm.errors.payment_method_id }}
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ t('operations.refund_date') }}</label>
                                <input
                                    v-model="refundForm.refunded_at"
                                    type="datetime-local"
                                    class="form-control"
                                    :class="{ 'is-invalid': refundForm.errors.refunded_at }"
                                >
                                <div class="invalid-feedback">{{ refundForm.errors.refunded_at }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ t('inventory.reference_document_number') }}</label>
                                <input
                                    v-model="refundForm.reference"
                                    type="text"
                                    maxlength="255"
                                    class="form-control"
                                    :class="{ 'is-invalid': refundForm.errors.reference }"
                                >
                                <div class="invalid-feedback">{{ refundForm.errors.reference }}</div>
                            </div>
                            <div>
                                <label class="form-label">{{ t('operations.reason') }}</label>
                                <textarea
                                    v-model="refundForm.reason"
                                    rows="3"
                                    maxlength="2000"
                                    class="form-control"
                                    :class="{ 'is-invalid': refundForm.errors.reason }"
                                ></textarea>
                                <div class="invalid-feedback">{{ refundForm.errors.reason }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-label-secondary"
                                :disabled="refundForm.processing"
                                @click="closeRefundModal"
                            >
                                {{ t('confirm.close') }}
                            </button>
                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="refundForm.processing
                                    || !refundForm.reason?.trim()
                                    || !refundForm.payment_method_id
                                    || Number(refundForm.amount) <= 0
                                    || Number(refundForm.amount) > Number(refundContext.item.refundable_amount)"
                            >
                                <span
                                    v-if="refundForm.processing"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                {{ t('inventory.record_the_refund') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showVoidModal && voidContext"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="background: rgba(0, 0, 0, 0.5)"
            @click.self="closeVoidModal"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">{{ t('operations.cancel_payment') }}</h5>
                            <small class="text-muted">
                                {{ t('operations.payment_number', { id: voidContext.id }) }} ·
                                {{ formatAmount(voidContext.net_amount) }} {{ voidContext.currency }}
                            </small>
                        </div>
                        <button
                            type="button"
                            class="btn-close"
                            :disabled="voidForm.processing"
                            @click="closeVoidModal"
                        ></button>
                    </div>
                    <form @submit.prevent="submitVoid">
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                {{ t('operations.this_action_will_return_the_paid_balance_to_the_corresponding_sa') }}
                            </div>
                            <label class="form-label">{{ t('operations.cancellation_reason_2') }}</label>
                            <textarea
                                v-model="voidForm.reason"
                                rows="4"
                                maxlength="2000"
                                class="form-control"
                                :class="{ 'is-invalid': voidForm.errors.reason }"
                            ></textarea>
                            <div class="invalid-feedback">{{ voidForm.errors.reason }}</div>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-label-secondary"
                                :disabled="voidForm.processing"
                                @click="closeVoidModal"
                            >
                                {{ t('confirm.close') }}
                            </button>
                            <button
                                type="submit"
                                class="btn btn-danger"
                                :disabled="voidForm.processing || !voidForm.reason?.trim()"
                            >
                                <span
                                    v-if="voidForm.processing"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                {{ t('operations.confirm_cancellation') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.avatar {
    align-items: center;
    display: inline-flex;
    height: 42px;
    justify-content: center;
    width: 42px;
}

.history-grid {
    align-items: center;
    display: grid;
    gap: 1rem;
    grid-template-columns: 44px 64px minmax(150px, 1.35fr) 105px 135px 165px 155px 145px 105px;
}

.history-grid-header {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.85rem 1.25rem;
    text-transform: uppercase;
}

.history-entry {
    border-top: 1px solid var(--bs-border-color);
}

.history-summary {
    min-height: 88px;
    padding: 1rem 1.25rem;
}

.history-entry:first-of-type {
    border-top: 0;
}

.history-toggle {
    border-radius: 50%;
}

.history-details {
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    border-top: 1px solid var(--bs-border-color);
    padding: 1.25rem 1.5rem 1.5rem 5.5rem;
}

.history-items {
    display: grid;
    gap: 0.75rem;
}

.history-item {
    align-items: center;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 0.65rem;
    box-shadow: 0 0.1rem 0.35rem rgba(0, 0, 0, 0.04);
    display: grid;
    gap: 1rem;
    grid-template-columns: 10px minmax(180px, 1fr) auto auto;
    padding: 0.9rem 1rem;
}

.history-item-dot {
    background: var(--bs-success);
    border-radius: 50%;
    height: 9px;
    width: 9px;
}

.audit-notes {
    display: grid;
    gap: 0.75rem;
}

.audit-notes h6 {
    grid-column: 1 / -1;
}

.audit-note {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-left: 3px solid var(--bs-primary);
    border-radius: 0.5rem;
    padding: 0.9rem 1rem;
}

.audit-note-danger {
    border-left-color: var(--bs-danger);
}

.audit-note-warning {
    border-left-color: var(--bs-warning);
}

.audit-note-info {
    border-left-color: var(--bs-info);
}

.history-mobile-label {
    color: var(--bs-secondary-color);
    display: none;
    font-size: 0.72rem;
    margin-right: 0.35rem;
}

@media (max-width: 1199.98px) {
    .history-summary {
        align-items: start;
        grid-template-columns: 40px minmax(0, 1fr) auto;
    }

    .history-summary > :nth-child(1) {
        grid-column: 1;
        grid-row: 1 / span 2;
    }

    .history-summary > :nth-child(2) {
        grid-column: 2;
        grid-row: 1;
    }

    .history-summary > :nth-child(3) {
        grid-column: 2 / -1;
        grid-row: 2;
    }

    .history-summary > :nth-child(4),
    .history-summary > :nth-child(5),
    .history-summary > :nth-child(6) {
        grid-column: 2 / -1;
    }

    .history-summary > :nth-child(7) {
        grid-column: 3;
        grid-row: 1;
    }

    .history-summary > :nth-child(8) {
        grid-column: 2;
    }

    .history-summary > :nth-child(9) {
        grid-column: 3;
        grid-row: 6;
    }

    .history-mobile-label {
        display: inline;
    }

    .history-details {
        padding-left: 1.25rem;
    }
}

@media (max-width: 767.98px) {
    .history-item {
        grid-template-columns: 10px minmax(0, 1fr);
    }

    .history-item > :nth-child(3),
    .history-item > :nth-child(4) {
        grid-column: 2;
        justify-self: start;
        text-align: left !important;
    }
}
</style>
