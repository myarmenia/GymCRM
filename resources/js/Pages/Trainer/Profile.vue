<script setup>
import { computed, ref } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    trainer: Object,
})

const activeTab = ref('memberships')

const memberships = computed(() => props.trainer?.trained_person_memberships ?? [])
const commissions = computed(() => props.trainer?.trainer_commissions ?? [])
const monthlySalaries = computed(() => props.trainer?.trainer_monthly_salaries ?? [])

const translatedName = item => item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
    ?? item?.name
    ?? item?.slug
    ?? (item?.id ? `#${item.id}` : '-')
const fullName = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || '-'
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const formatMonth = value => value ? String(value).slice(0, 7) : '-'
const formatAmount = value => Number(value || 0).toFixed(2)
const initials = computed(() => {
    const first = props.trainer?.name?.[0] ?? ''
    const last = props.trainer?.surname?.[0] ?? ''

    return `${first}${last}`.toUpperCase() || '#'
})

const trainerStatusLabel = computed(() => props.trainer?.deleted_at ? 'Ապաակտիվ' : 'Ակտիվ')
const trainerStatusClass = computed(() => props.trainer?.deleted_at ? 'bg-label-danger' : 'bg-label-success')
const roleNames = computed(() => (props.trainer?.roles ?? []).map(role => role.name ?? role.title ?? `#${role.id}`))

const membershipStatusLabel = status => ({
    waiting: 'Սպասման մեջ',
    active: 'Ակտիվ',
    frozen: 'Սառեցված',
    expired: 'Ավարտված',
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
const commissionTypeLabel = type => ({
    fixed: 'Ֆիքսված',
    percent: 'Տոկոս',
}[type] ?? type ?? '-')
const salaryStatusLabel = status => ({
    pending: 'Սպասման մեջ',
    paid: 'Վճարված',
    transfer: 'Փոխանցում',
    cancel: 'Չեղարկված',
    reject: 'Մերժված',
}[status] ?? status ?? '-')
const salaryStatusClass = status => ({
    pending: 'bg-label-warning',
    paid: 'bg-label-success',
    transfer: 'bg-label-info',
    cancel: 'bg-label-secondary',
    reject: 'bg-label-danger',
}[status] ?? 'bg-label-secondary')

const totalCommissions = computed(() => commissions.value.reduce((total, item) => total + Number(item.salary_amount || 0), 0))
const totalMonthlySalaries = computed(() => monthlySalaries.value.reduce((total, item) => total + Number(item.price || 0), 0))
const paidSalaries = computed(() => monthlySalaries.value
    .filter(item => item.status === 'paid')
    .reduce((total, item) => total + Number(item.price || 0), 0))
const pendingSalaries = computed(() => monthlySalaries.value
    .filter(item => item.status === 'pending')
    .reduce((total, item) => total + Number(item.price || 0), 0))
const stats = computed(() => [
    { label: 'Ընդհանուր աբոնեմենտներ', value: memberships.value.length, icon: 'tabler-id-badge-2', className: 'bg-label-primary' },
    { label: 'Ակտիվ աբոնեմենտներ', value: memberships.value.filter(item => item.status === 'active').length, icon: 'tabler-circle-check', className: 'bg-label-success' },
    { label: 'Ավարտված աբոնեմենտներ', value: memberships.value.filter(item => ['expired', 'cancelled', 'deleted'].includes(item.status)).length, icon: 'tabler-circle-x', className: 'bg-label-secondary' },
    { label: 'Ընդհանուր կոմիսիաներ', value: formatAmount(totalCommissions.value), icon: 'tabler-cash', className: 'bg-label-info' },
    { label: 'Ամսական աշխատավարձեր', value: formatAmount(totalMonthlySalaries.value), icon: 'tabler-calendar-dollar', className: 'bg-label-primary' },
    { label: 'Վճարված աշխատավարձեր', value: formatAmount(paidSalaries.value), icon: 'tabler-check', className: 'bg-label-success' },
    { label: 'Սպասման աշխատավարձեր', value: formatAmount(pendingSalaries.value), icon: 'tabler-clock', className: 'bg-label-warning' },
])

const customerName = membership => fullName(membership?.person)
const membershipPlanName = membership => translatedName(membership?.membership_plan)
const commissionMembership = commission => commission?.person_membership
const salaryMembership = salary => salary?.person_membership
</script>

<template>
    <Head title="Մարզչի պրոֆիլ" />

    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                        Մարզչի պրոֆիլ
                    </h2>
                    <div class="text-muted">{{ fullName(trainer) }}</div>
                </div>
                <Link
                    class="btn btn-secondary"
                    :href="route('trainer.index', { locale: currentLocale })"
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
                            <h3 class="mb-0">{{ fullName(trainer) }}</h3>
                            <span
                                class="badge"
                                :class="trainerStatusClass"
                            >
                                {{ trainerStatusLabel }}
                            </span>
                            <span
                                v-for="role in roleNames"
                                :key="role"
                                class="badge bg-label-info"
                            >
                                {{ role }}
                            </span>
                        </div>
                        <div class="d-flex gap-4 flex-wrap text-muted">
                            <span>
                                <i class="icon-base ti tabler-phone me-1"></i>
                                {{ trainer?.phone || '-' }}
                            </span>
                            <span>
                                <i class="icon-base ti tabler-mail me-1"></i>
                                {{ trainer?.email || '-' }}
                            </span>
                            <span>
                                <i class="icon-base ti tabler-building me-1"></i>
                                {{ trainer?.gym?.name || '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div
                v-for="stat in stats"
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
            <div class="card-header">
                <h5 class="mb-0">Մարզչի տվյալներ</h5>
                <small class="text-muted">Կոնտակտային և աշխատանքային տվյալներ</small>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="profile-info-group h-100">
                            <h6 class="mb-3">Կոնտակտային տվյալներ</h6>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-primary">
                                    <i class="icon-base ti tabler-user"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Անուն Ազգանուն</div>
                                    <div class="fw-semibold">{{ fullName(trainer) }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-primary">
                                    <i class="icon-base ti tabler-phone"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Հեռախոս</div>
                                    <div class="fw-semibold">{{ trainer?.phone || '-' }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-primary">
                                    <i class="icon-base ti tabler-mail"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Էլ․ հասցե</div>
                                    <div class="fw-semibold">{{ trainer?.email || '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="profile-info-group h-100">
                            <h6 class="mb-3">Աշխատանքային տվյալներ</h6>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-info">
                                    <i class="icon-base ti tabler-building"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Մարզասրահ</div>
                                    <div class="fw-semibold">{{ trainer?.gym?.name || '-' }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-info">
                                    <i class="icon-base ti tabler-circle-check"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Կարգավիճակ</div>
                                    <div class="fw-semibold">{{ trainerStatusLabel }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-info">
                                    <i class="icon-base ti tabler-shield-check"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Դերեր</div>
                                    <div class="fw-semibold">{{ roleNames.length ? roleNames.join(', ') : '-' }}</div>
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
                            :class="{ active: activeTab === 'commissions' }"
                            @click="activeTab = 'commissions'"
                        >
                            <i class="icon-base ti tabler-cash me-1"></i>
                            Կոմիսիաներ
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            :class="{ active: activeTab === 'monthly_salaries' }"
                            @click="activeTab = 'monthly_salaries'"
                        >
                            <i class="icon-base ti tabler-calendar-dollar me-1"></i>
                            Ամսական աշխատավարձեր
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body pt-4">
                <div v-if="activeTab === 'memberships'" class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Հաճախորդ</th>
                                <th>Աբոնեմենտ</th>
                                <th>Կարգավիճակ</th>
                                <th>Սկիզբ</th>
                                <th>Ավարտ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="membership in memberships"
                                :key="membership.id"
                            >
                                <td>{{ customerName(membership) }}</td>
                                <td>{{ membershipPlanName(membership) }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="membershipStatusClass(membership.status)"
                                    >
                                        {{ membershipStatusLabel(membership.status) }}
                                    </span>
                                </td>
                                <td>{{ formatDate(membership.start_date) }}</td>
                                <td>{{ formatDate(membership.valid_at || membership.end_date) }}</td>
                            </tr>
                            <tr v-if="!memberships.length">
                                <td colspan="5" class="text-center text-muted">
                                    Աբոնեմենտներ չկան
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="activeTab === 'commissions'" class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Հաշվարկման տեսակ</th>
                                <th>Հաշվարկման արժեք</th>
                                <th>Գումար</th>
                                <th>Կարգավիճակ</th>
                                <th>Աբոնեմենտ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="commission in commissions"
                                :key="commission.id"
                            >
                                <td>{{ commission.id }}</td>
                                <td>{{ commissionTypeLabel(commission.salary_type) }}</td>
                                <td>{{ formatAmount(commission.salary_value) }}</td>
                                <td>{{ formatAmount(commission.salary_amount) }}</td>
                                <td>{{ salaryStatusLabel(commission.status) }}</td>
                                <td>{{ membershipPlanName(commissionMembership(commission)) }}</td>
                            </tr>
                            <tr v-if="!commissions.length">
                                <td colspan="6" class="text-center text-muted">
                                    Կոմիսիաներ չկան
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ամիս</th>
                                <th>Գումար</th>
                                <th>Կարգավիճակ</th>
                                <th>Աբոնեմենտ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="salary in monthlySalaries"
                                :key="salary.id"
                            >
                                <td>{{ salary.id }}</td>
                                <td>{{ formatMonth(salary.salary_month) }}</td>
                                <td>{{ formatAmount(salary.price) }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="salaryStatusClass(salary.status)"
                                    >
                                        {{ salaryStatusLabel(salary.status) }}
                                    </span>
                                </td>
                                <td>{{ membershipPlanName(salaryMembership(salary)) }}</td>
                            </tr>
                            <tr v-if="!monthlySalaries.length">
                                <td colspan="5" class="text-center text-muted">
                                    Ամսական աշխատավարձեր չկան
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
</style>
