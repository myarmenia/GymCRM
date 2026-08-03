<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'

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
    ? 'Չեղարկված'
    : payout.refunded_amount > 0
        ? 'Մասնակի վերադարձ'
        : 'Վճարված'

const payoutStatusClass = payout => payout.status === 'voided'
    ? 'bg-label-danger'
    : payout.refunded_amount > 0
        ? 'bg-label-warning'
        : 'bg-label-success'

const payableTypeLabel = type => type === 'trainer_monthly_salary' ? 'Մարզիչ' : 'Վաճառող'

const hasAuditNotes = payout => Boolean(
    payout.notes
    || payout.void_reason
    || (payout.refunds ?? []).length
    || (payout.transfers ?? []).length,
)
</script>

<template>
    <Head title="Աշխատավարձերի վճարումներ" />

    <Index>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h2 class="mb-1">Աշխատավարձերի վճարումներ</h2>
                <div class="text-muted">
                    Մարզիչների և վաճառողների բոլոր վճարման ենթակա գումարները մեկ տեղում
                </div>
            </div>
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
                    Վճարման ենթակա աշխատավարձեր
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
                    Վճարումների պատմություն
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
                            <div class="text-muted small">Վճարման ենթակա գրառումներ</div>
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
                            <div class="text-muted small">Ընդհանուր վճարման ենթակա գումար</div>
                            <div class="h4 mb-0">{{ formatAmount(summary.payable_amount) }} AMD</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Ֆիլտրեր</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Աշխատակից</label>
                        <select v-model="filters.payee_id" class="form-select">
                            <option value="">Բոլորը</option>
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
                        <label class="form-label">Տեսակ</label>
                        <select v-model="filters.type" class="form-select">
                            <option value="">Բոլորը</option>
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
                        <label class="form-label">Կարգավիճակ</label>
                        <select v-model="filters.history_status" class="form-select">
                            <option value="">Բոլորը</option>
                            <option value="paid">Վճարված</option>
                            <option value="refunded">Մասնակի վերադարձ</option>
                            <option value="voided">Չեղարկված</option>
                        </select>
                    </div>
                    <div v-if="activeTab === 'history'" class="col-12 col-md-2">
                        <label class="form-label">Վճարման եղանակ</label>
                        <select v-model="filters.payment_method_id" class="form-select">
                            <option value="">Բոլորը</option>
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
                        <label class="form-label">Մարզասրահ</label>
                        <select v-model="filters.gym_id" class="form-select">
                            <option value="">Բոլորը</option>
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
                            {{ activeTab === 'history' ? 'Վճարված՝ սկսած' : 'Սկսած' }}
                        </label>
                        <input v-model="filters.start_date" type="date" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">
                            {{ activeTab === 'history' ? 'Վճարված՝ մինչև' : 'Մինչև' }}
                        </label>
                        <input v-model="filters.end_date" type="date" class="form-control">
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="button" class="btn btn-primary" @click="applyFilters">
                            Կիրառել
                        </button>
                        <button type="button" class="btn btn-outline-secondary" @click="resetFilters">
                            Մաքրել
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
                    <h5 class="mb-1">Նոր վճարում</h5>
                    <div class="text-muted">
                        {{ selectedPayee }} · {{ selectedGym }} · {{ selectedPayables.length }} տող
                    </div>
                </div>
                <div class="h4 text-primary mb-0">{{ formatAmount(selectedTotal) }} AMD</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div v-if="selectedPayables.length === 1" class="col-12 col-md-3">
                        <label class="form-label">Վճարման գումար *</label>
                        <input
                            v-model="singlePaymentAmount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :max="selectedPayables[0].amount"
                            class="form-control"
                        >
                        <small class="text-muted">
                            Հասանելի՝ {{ formatAmount(selectedPayables[0].amount) }} AMD
                        </small>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Վճարման եղանակ *</label>
                        <select
                            v-model="payoutForm.payment_method_id"
                            class="form-select"
                            :class="{ 'is-invalid': payoutForm.errors.payment_method_id }"
                        >
                            <option value="">Ընտրել</option>
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
                        <label class="form-label">Վճարման ամսաթիվ *</label>
                        <input
                            v-model="payoutForm.paid_at"
                            type="datetime-local"
                            class="form-control"
                            :class="{ 'is-invalid': payoutForm.errors.paid_at }"
                        >
                        <div class="invalid-feedback">{{ payoutForm.errors.paid_at }}</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Հղում / փաստաթղթի համար</label>
                        <input
                            v-model="payoutForm.reference"
                            type="text"
                            class="form-control"
                            maxlength="255"
                        >
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Նշումներ</label>
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
                        Չեղարկել ընտրությունը
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
                        Վճարել {{ formatAmount(selectedTotal) }} AMD
                    </button>
                </div>
            </div>
        </div>

        <div v-if="activeTab === 'payables'" class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h5 class="mb-1">Վճարման ենթակա աշխատավարձեր</h5>
                    <small class="text-muted">
                        Մեկ վճարման մեջ ընտրեք միայն նույն աշխատակցի տողերը
                    </small>
                </div>
                <span class="badge bg-label-primary">{{ payables.total ?? 0 }} գրառում</span>
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
                            <th>Աշխատակից</th>
                            <th>Տեսակ</th>
                            <th>Հիմք</th>
                            <th>Ամսաթիվ / ամիս</th>
                            <th>Գեներացվել է</th>
                            <th class="text-end">Գումար</th>
                            <th class="text-end">Գործողություն</th>
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
                                    Բաժնի սկզբնական՝ {{ formatAmount(item.assigned_amount) }}
                                </small>
                            </td>
                            <td class="text-end">
                                <button
                                    v-if="item.can_transfer"
                                    type="button"
                                    class="btn btn-sm btn-outline-info"
                                    :disabled="transferForm.processing"
                                    :title="`Փոխանցել ${item.transfer_target}-ին`"
                                    @click="openTransferModal(item)"
                                >
                                    <i class="icon-base ti tabler-transfer me-1"></i>
                                    Փոխանցել
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!(payables.data ?? []).length">
                            <td colspan="8" class="text-center text-muted py-4">
                                Վճարման ենթակա աշխատավարձեր չկան։
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
                    <h5 class="mb-1">Վճարումների պատմություն</h5>
                    <small class="text-muted">Վճարված և չեղարկված գործարքների audit trail</small>
                </div>
                <span class="badge bg-label-secondary">{{ payouts.total ?? 0 }} վճարում</span>
            </div>
            <div class="history-list">
                <div class="history-grid history-grid-header d-none d-xl-grid">
                    <span></span>
                    <span>#</span>
                    <span>Աշխատակից</span>
                    <span>Եղանակ</span>
                    <span>Վճարել է</span>
                    <span>Վճարման ամսաթիվ</span>
                    <span>Կարգավիճակ</span>
                    <span class="text-end">Գումար</span>
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
                            <small class="text-muted">{{ payout.items_count }} տող</small>
                        </div>
                        <div>
                            <small class="history-mobile-label">Եղանակ</small>
                            {{ payout.payment_method }}
                        </div>
                        <div>
                            <small class="history-mobile-label">Վճարել է</small>
                            {{ payout.paid_by }}
                        </div>
                        <div>
                            <small class="history-mobile-label">Ամսաթիվ</small>
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
                                Վերադարձ՝ {{ formatAmount(payout.refunded_amount) }} ·
                                Զուտ՝ {{ formatAmount(payout.net_amount) }}
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
                                Չեղարկել
                            </button>
                        </div>
                    </div>

                    <div v-if="isPayoutExpanded(payout.id)" class="history-details">
                        <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                            <div>
                                <span class="text-muted">Reference:</span>
                                <span class="ms-1 fw-medium">{{ payout.reference || 'նշված չէ' }}</span>
                            </div>
                            <div class="text-muted">
                                {{ payout.items_count }} աշխատավարձային տող
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
                                        Վերադարձ՝ {{ formatAmount(item.refunded_amount) }}
                                    </small>
                                </div>
                                <button
                                    v-if="canVoid && payout.status === 'paid' && item.refundable_amount > 0"
                                    type="button"
                                    class="btn btn-sm btn-outline-warning"
                                    :disabled="refundForm.processing"
                                    @click="openRefundModal(payout, item)"
                                >
                                    Մասնակի վերադարձ
                                </button>
                            </div>
                        </div>

                        <div v-if="hasAuditNotes(payout)" class="audit-notes mt-4">
                            <h6 class="mb-3">
                                <i class="icon-base ti tabler-notes me-1"></i>
                                Նշումներ և պատճառներ
                            </h6>

                            <div v-if="payout.notes" class="audit-note">
                                <span class="badge bg-label-primary">Վճարման նշում</span>
                                <div class="mt-2">{{ payout.notes }}</div>
                            </div>

                            <div v-if="payout.void_reason" class="audit-note audit-note-danger">
                                <span class="badge bg-label-danger">Չեղարկման պատճառ</span>
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
                                        Վերադարձ #{{ refund.id }}
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
                                        Փոխանցում #{{ transfer.id }}
                                    </span>
                                    <strong>{{ formatAmount(transfer.amount) }} {{ payout.currency }}</strong>
                                </div>
                                <div class="mt-2">
                                    {{ transfer.from_payee }} → {{ transfer.to_payee }}
                                </div>
                                <div>{{ transfer.reason || 'Փոխանցման պատճառ չի նշվել' }}</div>
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
                    Վճարումների պատմություն դեռ չկա։
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
                            <h5 class="modal-title">Փոխանցել աշխատավարձը</h5>
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
                                Հասանելի փոխանցման գումար՝
                                <strong>{{ formatAmount(transferContext.amount) }} AMD</strong>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Փոխանցվող գումար *</label>
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
                                <label class="form-label">Փոխանցման պատճառ</label>
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
                                Փակել
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
                                Հաստատել փոխանցումը
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
                            <h5 class="modal-title">Մասնակի վերադարձ</h5>
                            <small class="text-muted">
                                Վճարում #{{ refundContext.payout.id }} ·
                                առավելագույնը՝ {{ formatAmount(refundContext.item.refundable_amount) }} AMD
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
                                <label class="form-label">Վերադարձվող գումար *</label>
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
                                <label class="form-label">Վերադարձի եղանակ *</label>
                                <select
                                    v-model="refundForm.payment_method_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': refundForm.errors.payment_method_id }"
                                >
                                    <option value="">Ընտրել</option>
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
                                <label class="form-label">Վերադարձի ամսաթիվ *</label>
                                <input
                                    v-model="refundForm.refunded_at"
                                    type="datetime-local"
                                    class="form-control"
                                    :class="{ 'is-invalid': refundForm.errors.refunded_at }"
                                >
                                <div class="invalid-feedback">{{ refundForm.errors.refunded_at }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Հղում / փաստաթղթի համար</label>
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
                                <label class="form-label">Պատճառ *</label>
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
                                Փակել
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
                                Գրանցել վերադարձը
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
                            <h5 class="modal-title">Չեղարկել վճարումը</h5>
                            <small class="text-muted">
                                Վճարում #{{ voidContext.id }} ·
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
                                Գործողությունը կվերադարձնի վճարված մնացորդը համապատասխան աշխատավարձերին։
                            </div>
                            <label class="form-label">Չեղարկման պատճառ *</label>
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
                                Փակել
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
                                Հաստատել չեղարկումը
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
