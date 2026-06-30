<script setup>
import { computed, ref } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { todayInYerevan } from '@/utils/yerevanDate'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    person: Object,
    entryCode: {
        type: Object,
        default: null,
    },
})

const activeTab = ref('memberships')

const memberships = computed(() => props.person?.memberships ?? [])
const sales = computed(() => props.person?.membership_sales ?? [])
const transactions = computed(() => sales.value.flatMap(sale => (sale.payments ?? []).map(payment => ({
    ...payment,
    membership_sale: sale,
}))))

const translatedName = item => item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
    ?? item?.name
    ?? item?.slug
    ?? (item?.id ? `#${item.id}` : '-')

const fullName = person => `${person?.name ?? ''} ${person?.surname ?? ''}`.trim() || '-'
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const formatAmount = value => Number(value || 0).toFixed(2)

const personTypeLabel = type => ({
    visitor: 'Այցելու',
    guest: 'Հյուր',
}[type] ?? type ?? '-')

const genderLabel = gender => ({
    male: 'Արական',
    female: 'Իգական',
}[gender] ?? gender ?? '-')

const personStatusLabel = computed(() => props.person?.deleted_at || props.person?.mobile_deleted
    ? 'Ապաակտիվ'
    : 'Ակտիվ')

const membershipStatusLabel = status => ({
    waiting: 'Սպասման մեջ',
    active: 'Ակտիվ',
    frozen: 'Սառեցված',
    expired: 'Ժամկետանց',
    deleted: 'Ջնջված',
    cancelled: 'Չեղարկված',
}[status] ?? status ?? '-')

const membershipStatusClass = status => ({
    waiting: 'bg-label-info',
    active: 'bg-label-success',
    frozen: 'bg-label-warning',
    expired: 'bg-label-secondary',
    deleted: 'bg-label-secondary',
    cancelled: 'bg-label-danger',
}[status] ?? 'bg-label-secondary')

const transactionTypeLabel = type => ({
    payment: 'Վճարում',
    refund: 'Վերադարձ',
}[type] ?? type ?? '-')

const paymentStatusLabel = status => ({
    unpaid: 'Չվճարված',
    pending: 'Սպասման մեջ',
    paid: 'Վճարված',
    cancelled: 'Չեղարկված',
}[status] ?? status ?? '-')

const trainerName = trainer => trainer ? fullName(trainer) : '-'
const guestName = guestRecord => fullName(guestRecord?.guest)
const membershipPlanName = membership => translatedName(membership?.membership_plan)
const salePlanName = sale => translatedName(sale?.membership_plan)
const trainerStatusLabel = trainer => trainer?.status ? 'Ակտիվ' : 'Չնշված'
const trainerRoleNames = trainer => {
    const roles = trainer?.roles ?? []

    if (!roles.length) {
        return '-'
    }

    return roles.map(role => role.name ?? role.title ?? `#${role.id}`).join(', ')
}
const membershipSaleId = membership => membership?.membership_sale_id ?? membership?.membership_sale?.id
const dateValue = value => value ? String(value).slice(0, 10) : null
const isMembershipCurrentlyValid = membership => {
    const today = todayInYerevan()
    const startDate = dateValue(membership?.start_date)
    const validAt = dateValue(membership?.valid_at)
    const endDate = dateValue(membership?.end_date)

    if (startDate && startDate > today) {
        return false
    }

    if (validAt && validAt < today) {
        return false
    }

    if (endDate && endDate < today) {
        return false
    }

    return true
}
const canFreezeMembership = membership => {
    return Boolean(membershipSaleId(membership))
        && Number(membership?.freeze_left || 0) > 0
        && ['waiting', 'active', 'frozen'].includes(membership?.status)
        && isMembershipCurrentlyValid(membership)
}
const canAddGuest = membership => {
    return Boolean(membershipSaleId(membership))
        && Number(membership?.guest_left || 0) > 0
        && membership?.status === 'active'
        && isMembershipCurrentlyValid(membership)
}
const saleForMembership = membership => membership?.membership_sale
    ?? sales.value.find(sale => Number(sale.id) === Number(membershipSaleId(membership)))
const salePaidAmount = sale => (sale?.payments ?? [])
    .filter(payment => payment.type === 'payment' && payment.status === 'paid')
    .reduce((total, payment) => total + Number(payment.amount || 0), 0)
const saleRefundedAmount = sale => (sale?.payments ?? [])
    .filter(payment => payment.type === 'refund' && payment.status === 'paid')
    .reduce((total, payment) => total + Number(payment.amount || 0), 0)
const saleNetPaidAmount = sale => Math.max(salePaidAmount(sale) - saleRefundedAmount(sale), 0)
const saleDebtAmount = sale => Math.max(Number(sale?.final_price || 0) - saleNetPaidAmount(sale), 0)
const membershipPaymentStatus = membership => {
    const sale = saleForMembership(membership)

    if (!sale) {
        return 'unpaid'
    }

    if (sale.payment_status === 'paid' || saleDebtAmount(sale) <= 0) {
        return 'paid'
    }

    if (sale.payment_status === 'partial' || saleNetPaidAmount(sale) > 0) {
        return 'partial'
    }

    return 'unpaid'
}
const membershipPaymentStatusLabel = membership => ({
    paid: 'Վճարված',
    partial: 'Մասնակի վճարված',
    unpaid: 'Չվճարված',
}[membershipPaymentStatus(membership)] ?? '-')
const membershipPaymentStatusClass = membership => ({
    paid: 'bg-label-success',
    partial: 'bg-label-warning',
    unpaid: 'bg-label-danger',
}[membershipPaymentStatus(membership)] ?? 'bg-label-secondary')
const membershipHasDebt = membership => saleDebtAmount(saleForMembership(membership)) > 0

const paidAmount = computed(() => transactions.value
    .filter(payment => payment.type === 'payment' && payment.status === 'paid')
    .reduce((total, payment) => total + Number(payment.amount || 0), 0))

const refundedAmount = computed(() => transactions.value
    .filter(payment => payment.type === 'refund' && payment.status === 'paid')
    .reduce((total, payment) => total + Number(payment.amount || 0), 0))

const netPaidAmount = computed(() => Math.max(paidAmount.value - refundedAmount.value, 0))
const finalPriceTotal = computed(() => sales.value.reduce((total, sale) => total + Number(sale.final_price || 0), 0))
const totalDebt = computed(() => Math.max(finalPriceTotal.value - netPaidAmount.value, 0))
const refundDueAmount = computed(() => sales.value.reduce((total, sale) => {
    const salePayments = sale.payments ?? []
    const salePaidAmount = salePayments
        .filter(payment => payment.type === 'payment' && payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
    const saleRefundedAmount = salePayments
        .filter(payment => payment.type === 'refund' && payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
    const membership = memberships.value.find(item => Number(item.membership_sale_id) === Number(sale.id))
    const refundObligation = membership?.status === 'cancelled'
        ? salePaidAmount
        : Math.max(salePaidAmount - Number(sale.final_price || 0), 0)

    return total + Math.max(refundObligation - saleRefundedAmount, 0)
}, 0))
const activeMembershipCount = computed(() => memberships.value.filter(membership => membership.status === 'active').length)

const initials = computed(() => {
    const first = props.person?.name?.[0] ?? ''
    const last = props.person?.surname?.[0] ?? ''

    return `${first}${last}`.toUpperCase() || '#'
})

const visitStats = computed(() => [
    { label: 'Այցելություններ', value: '0', icon: 'tabler-walk', className: 'bg-label-primary' },
    { label: 'Այս ամիս', value: '0', icon: 'tabler-calendar-stats', className: 'bg-label-info' },
    { label: 'Վերջին այց', value: '-', icon: 'tabler-clock', className: 'bg-label-warning' },
    { label: 'Ակտիվ աբոնեմենտներ', value: activeMembershipCount.value, icon: 'tabler-id-badge-2', className: 'bg-label-success' },
])

const primaryInfoItems = computed(() => [
    { label: 'Անուն', value: props.person?.name || '-', icon: 'tabler-user' },
    { label: 'Ազգանուն', value: props.person?.surname || '-', icon: 'tabler-user' },
    { label: 'Հեռախոսահամար', value: props.person?.phone || '-', icon: 'tabler-phone' },
    { label: 'Էլ․ փոստ', value: props.person?.email || '-', icon: 'tabler-mail' },
    { label: 'Մուտքի կոդ', value: props.entryCode?.token || '-', icon: 'tabler-key' },
])
const secondaryInfoItems = computed(() => [
    { label: 'Տեսակ', value: personTypeLabel(props.person?.type), icon: 'tabler-users' },
    { label: 'Կարգավիճակ', value: personStatusLabel.value, icon: 'tabler-circle-check' },
    { label: 'Ծննդյան ամսաթիվ', value: formatDate(props.person?.birth_date), icon: 'tabler-calendar' },
    { label: 'Սեռ', value: genderLabel(props.person?.gender), icon: 'tabler-gender-bigender' },
    { label: 'Մարզասրահ', value: props.person?.gyms?.length ? props.person.gyms.map(gym => gym.name).join(', ') : '-', icon: 'tabler-building' },
])
</script>

<template>
    <Head title="Անձի պրոֆիլ" />

    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                        Անձի պրոֆիլ
                    </h2>
                    <div class="text-muted">
                        {{ fullName(person) }}
                    </div>
                </div>
                <Link
                    class="btn btn-secondary"
                    :href="route('person.list', { locale: currentLocale })"
                >
                    Վերադառնալ
                </Link>
            </div>
        </template>

        <div class="card mb-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="profile-avatar bg-primary text-white">
                        {{ initials }}
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <h3 class="mb-0">{{ fullName(person) }}</h3>
                            <span class="badge bg-label-info">{{ personTypeLabel(person?.type) }}</span>
                            <span class="badge bg-label-success">{{ personStatusLabel }}</span>
                        </div>
                        <div class="d-flex gap-4 flex-wrap text-muted">
                            <span>
                                <i class="icon-base ti tabler-phone me-1"></i>
                                {{ person?.phone || '-' }}
                            </span>
                            <span>
                                <i class="icon-base ti tabler-mail me-1"></i>
                                {{ person?.email || '-' }}
                            </span>
                            <span>
                                <i class="icon-base ti tabler-key me-1"></i>
                                {{ entryCode?.token || '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div
                v-for="stat in visitStats"
                :key="stat.label"
                class="col-12 col-sm-6 col-xl-3"
            >
                <div class="card h-100 stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div
                            class="avatar rounded"
                            :class="stat.className"
                        >
                            <i :class="['icon-base ti', stat.icon]"></i>
                        </div>
                        <div>
                            <div class="text-muted small mb-1">{{ stat.label }}</div>
                            <div class="h4 mb-0">{{ stat.value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Անձնական տվյալներ</h5>
                    <small class="text-muted">Հիմնական տվյալներ և նույնականացում</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="profile-info-group h-100">
                            <h6 class="mb-3">Կոնտակտային տվյալներ</h6>
                            <div
                                v-for="item in primaryInfoItems"
                                :key="item.label"
                                class="profile-info-row"
                            >
                                <div class="profile-info-icon bg-label-primary">
                                    <i :class="['icon-base ti', item.icon]"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-muted small">{{ item.label }}</div>
                                    <div class="fw-semibold text-break">{{ item.value }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="profile-info-group h-100">
                            <h6 class="mb-3">Լրացուցիչ տվյալներ</h6>
                            <div
                                v-for="item in secondaryInfoItems"
                                :key="item.label"
                                class="profile-info-row"
                            >
                                <div class="profile-info-icon bg-label-info">
                                    <i :class="['icon-base ti', item.icon]"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-muted small">{{ item.label }}</div>
                                    <div class="fw-semibold text-break">{{ item.value }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header pb-0">
                <ul class="nav nav-pills gap-2">
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            :class="{ active: activeTab === 'memberships' }"
                            @click="activeTab = 'memberships'"
                        >
                            <i class="icon-base ti tabler-id-badge-2 me-1"></i>
                            Աբոնեմենտներ
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            :class="{ active: activeTab === 'finance' }"
                            @click="activeTab = 'finance'"
                        >
                            <i class="icon-base ti tabler-wallet me-1"></i>
                            Ֆինանսական մաս
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body pt-4">
                <div v-if="activeTab === 'memberships'">
                    <div
                        v-if="memberships.length"
                        class="d-flex flex-column gap-4"
                    >
                        <div
                            v-for="membership in memberships"
                            :key="membership.id"
                            class="membership-card"
                        >
                            <div class="membership-header mb-4">
                                <div class="membership-header-section">
                                    <h5 class="mb-2">{{ membershipPlanName(membership) }}</h5>
                                    <span
                                        class="badge"
                                        :class="membershipStatusClass(membership.status)"
                                    >
                                        {{ membershipStatusLabel(membership.status) }}
                                    </span>
                                </div>
                                <div class="membership-header-section membership-period">
                                    <div>
                                        <div class="text-muted small">Սկիզբ</div>
                                        <div class="fw-semibold">{{ formatDate(membership.start_date) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Ավարտ</div>
                                        <div class="fw-semibold">{{ formatDate(membership.valid_at || membership.end_date) }}</div>
                                    </div>
                                </div>
                                <div class="membership-header-section payment-box">
                                    <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap mb-2">
                                        <span
                                            class="badge"
                                            :class="membershipPaymentStatusClass(membership)"
                                        >
                                            {{ membershipPaymentStatusLabel(membership) }}
                                        </span>
                                        <Link
                                            v-if="membershipSaleId(membership)"
                                            class="btn btn-sm btn-outline-primary"
                                            :href="route('membership_sale.payments', {
                                                locale: currentLocale,
                                                id: membershipSaleId(membership),
                                            })"
                                        >
                                            <i class="icon-base ti tabler-cash me-1"></i>
                                            Վճարումներ
                                        </Link>
                                    </div>
                                    <div
                                        v-if="membershipHasDebt(membership)"
                                        class="text-danger fw-semibold"
                                    >
                                        Պարտք՝ {{ formatAmount(saleDebtAmount(saleForMembership(membership))) }}
                                    </div>
                                    <div
                                        v-else
                                        class="text-success fw-semibold"
                                    >
                                        Պարտք չկա
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="membership.trainer"
                                class="trainer-section mb-4"
                            >
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="trainer-avatar bg-label-primary">
                                            <i class="icon-base ti tabler-user-star"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small mb-1">Մարզիչ</div>
                                            <h6 class="mb-2">{{ trainerName(membership.trainer) }}</h6>
                                            <div
                                                v-if="membership.trainer"
                                                class="trainer-details"
                                            >
                                                <span>
                                                    <i class="icon-base ti tabler-phone me-1"></i>
                                                    {{ membership.trainer.phone || '-' }}
                                                </span>
                                                <span>
                                                    <i class="icon-base ti tabler-mail me-1"></i>
                                                    {{ membership.trainer.email || '-' }}
                                                </span>
                                                <span>
                                                    <i class="icon-base ti tabler-shield-check me-1"></i>
                                                    {{ trainerRoleNames(membership.trainer) }}
                                                </span>
                                                <span>
                                                    <i class="icon-base ti tabler-circle-check me-1"></i>
                                                    {{ trainerStatusLabel(membership.trainer) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            disabled
                                        >
                                            <i class="icon-base ti tabler-eye me-1"></i>
                                            Դիտել մարզչի պրոֆիլը
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            disabled
                                        >
                                            <i class="icon-base ti tabler-chart-bar me-1"></i>
                                            Մարզչի վիճակագրություն
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="metric-box">
                                        <div class="text-muted small">Այցեր</div>
                                        <div class="fw-semibold">
                                            {{ membership.visits_left ?? 0 }} մնացել / {{ membership.visits_used ?? 0 }} օգտագործվել
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="metric-box">
                                        <div class="text-muted small">Հյուրեր</div>
                                        <div class="fw-semibold">
                                            {{ membership.guest_left ?? 0 }} մնացել / {{ membership.guests?.length ?? 0 }} գրառում
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="metric-box">
                                        <div class="text-muted small">Սառեցումներ</div>
                                        <div class="fw-semibold">
                                            {{ membership.freeze_left ?? 0 }} մնացել / {{ membership.freezes?.length ?? 0 }} գրառում
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="subsection">
                                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                                            <h6 class="mb-0">Սառեցումներ</h6>
                                            <Link
                                                v-if="canFreezeMembership(membership)"
                                                class="btn btn-sm btn-outline-primary"
                                                :href="route('membership_sale.freezes', {
                                                    locale: currentLocale,
                                                    id: membershipSaleId(membership),
                                                })"
                                            >
                                                <i class="icon-base ti tabler-snowflake me-1"></i>
                                                Սառեցնել աբոնեմենտը
                                            </Link>
                                        </div>
                                        <div
                                            v-if="membership.freezes?.length"
                                            class="table-responsive"
                                        >
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Սկիզբ</th>
                                                        <th>Ավարտ</th>
                                                        <th>Նշումներ</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="freeze in membership.freezes"
                                                        :key="freeze.id"
                                                    >
                                                        <td>{{ formatDate(freeze.start_date) }}</td>
                                                        <td>{{ formatDate(freeze.end_date) }}</td>
                                                        <td>{{ freeze.notes || '-' }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div
                                            v-else
                                            class="empty-state"
                                        >
                                            Սառեցումներ չկան
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="subsection">
                                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                                            <h6 class="mb-0">Հյուրեր</h6>
                                            <Link
                                                v-if="canAddGuest(membership)"
                                                class="btn btn-sm btn-outline-secondary"
                                                :href="route('membership_sale.guests', {
                                                    locale: currentLocale,
                                                    id: membershipSaleId(membership),
                                                })"
                                            >
                                                <i class="icon-base ti tabler-user-plus me-1"></i>
                                                Ավելացնել հյուր
                                            </Link>
                                        </div>
                                        <div
                                            v-if="membership.guests?.length"
                                            class="table-responsive"
                                        >
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Անուն Ազգանուն</th>
                                                        <th>Հեռախոսահամար</th>
                                                        <th>Ավելացվել է</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="guest in membership.guests"
                                                        :key="guest.id"
                                                    >
                                                        <td>{{ guestName(guest) }}</td>
                                                        <td>{{ guest.guest?.phone || '-' }}</td>
                                                        <td>{{ formatDate(guest.created_at) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div
                                            v-else
                                            class="empty-state"
                                        >
                                            Հյուրեր չկան
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="empty-state py-5"
                    >
                        Աբոնեմենտներ չկան
                    </div>
                </div>

                <div v-else>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl">
                            <div class="finance-card border-danger">
                                <div class="text-muted small">Ընդհանուր պարտք</div>
                                <div class="h4 mb-0 text-danger">{{ formatAmount(totalDebt) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl">
                            <div class="finance-card">
                                <div class="text-muted small">Պետք է վճարի</div>
                                <div class="h4 mb-0">{{ formatAmount(finalPriceTotal) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl">
                            <div class="finance-card border-success">
                                <div class="text-muted small">Վճարել է</div>
                                <div class="h4 mb-0 text-success">{{ formatAmount(netPaidAmount) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl">
                            <div class="finance-card border-warning">
                                <div class="text-muted small">Վերադարձված գումար</div>
                                <div class="h4 mb-0 text-warning">{{ formatAmount(refundedAmount) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl">
                            <div class="finance-card border-info">
                                <div class="text-muted small">Վերադարձման ենթակա գումար</div>
                                <div class="h4 mb-0 text-info">{{ formatAmount(refundDueAmount) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Տեսակ</th>
                                    <th>Աբոնեմենտ</th>
                                    <th>Գումար</th>
                                    <th>Վիճակ</th>
                                    <th>Վճարման եղանակ</th>
                                    <th>Ամսաթիվ</th>
                                    <th>Նշումներ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="payment in transactions"
                                    :key="payment.id"
                                    :class="{ 'table-danger': payment.type === 'refund' }"
                                >
                                    <td>{{ payment.id }}</td>
                                    <td>{{ transactionTypeLabel(payment.type) }}</td>
                                    <td>{{ salePlanName(payment.membership_sale) }}</td>
                                    <td>{{ formatAmount(payment.amount) }}</td>
                                    <td>{{ paymentStatusLabel(payment.status) }}</td>
                                    <td>{{ translatedName(payment.payment_method) }}</td>
                                    <td>{{ formatDate(payment.created_at) }}</td>
                                    <td>{{ payment.notes || '-' }}</td>
                                </tr>
                                <tr v-if="!transactions.length">
                                    <td
                                        colspan="8"
                                        class="text-center text-muted"
                                    >
                                        Գործարքներ չկան
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.profile-avatar {
    align-items: center;
    border-radius: 8px;
    display: flex;
    font-size: 1.75rem;
    font-weight: 700;
    height: 72px;
    justify-content: center;
    width: 72px;
}

.stat-card {
    border-left: 4px solid var(--bs-primary);
}

.avatar {
    align-items: center;
    display: flex;
    font-size: 1.4rem;
    height: 46px;
    justify-content: center;
    width: 46px;
}

.metric-box,
.finance-card,
.subsection {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 1rem;
}

.profile-info-group {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 1rem 1.25rem;
}

.profile-info-row {
    align-items: center;
    border-bottom: 1px solid var(--bs-border-color);
    display: flex;
    gap: .875rem;
    padding: .75rem 0;
}

.profile-info-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.profile-info-row:first-of-type {
    padding-top: 0;
}

.profile-info-icon {
    align-items: center;
    border-radius: 8px;
    display: flex;
    flex: 0 0 38px;
    height: 38px;
    justify-content: center;
    width: 38px;
}

.membership-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 1.25rem;
}

.membership-header {
    align-items: stretch;
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(0, 1.2fr) minmax(220px, .9fr) minmax(260px, 1fr);
}

.membership-header-section {
    min-width: 0;
}

.membership-period {
    background: var(--bs-body-bg);
    border-radius: 8px;
    display: flex;
    gap: 1.25rem;
    padding: .75rem 1rem;
}

.trainer-section {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 1rem;
}

.trainer-avatar {
    align-items: center;
    border-radius: 8px;
    display: flex;
    font-size: 1.4rem;
    height: 48px;
    justify-content: center;
    width: 48px;
}

.trainer-details {
    color: var(--bs-secondary-color);
    display: flex;
    flex-wrap: wrap;
    gap: .5rem 1.25rem;
    font-size: .875rem;
}

.payment-box {
    background: var(--bs-body-bg);
    border-radius: 8px;
    padding: .75rem 1rem;
    text-align: right;
}

.finance-card {
    height: 100%;
    border-left-width: 4px;
}

.empty-state {
    align-items: center;
    background: var(--bs-body-bg);
    border: 1px dashed var(--bs-border-color);
    border-radius: 8px;
    color: var(--bs-secondary-color);
    display: flex;
    justify-content: center;
    min-height: 72px;
    text-align: center;
}

@media (max-width: 991.98px) {
    .membership-header {
        grid-template-columns: 1fr;
    }

    .payment-box {
        text-align: left;
    }

    .payment-box .justify-content-end {
        justify-content: flex-start !important;
    }
}
</style>
