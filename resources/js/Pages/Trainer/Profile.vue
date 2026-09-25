<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

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

const trainerStatusLabel = computed(() => props.trainer?.active ? t('status.active') : t('people.inactive'))
const trainerStatusClass = computed(() => props.trainer?.active ? 'bg-label-success' : 'bg-label-danger')
const roleNames = computed(() => (props.trainer?.roles ?? []).map(role => role.name ?? role.title ?? `#${role.id}`))

const membershipStatusLabel = status => ({
    waiting: t('status.pending'),
    active: t('status.active'),
    frozen: t('people.frozen'),
    expired: t('staff_reports.completed'),
    deleted: t('status.deleted'),
    cancelled: t('people.cancelled'),
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
    fixed: t('staff_reports.fixed'),
    percent: t('staff_reports.percent'),
}[type] ?? type ?? '-')
const salaryStatusLabel = status => ({
    pending: t('status.pending'),
    paid: t('people.paid'),
    transfer: t('staff_reports.transfer'),
    cancel: t('people.cancelled'),
    reject: t('staff_reports.rejected'),
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
    { label: t('staff_reports.total_memberships'), value: memberships.value.length, icon: 'tabler-id-badge-2', className: 'bg-label-primary' },
    { label: t('people.active_memberships'), value: memberships.value.filter(item => item.status === 'active').length, icon: 'tabler-circle-check', className: 'bg-label-success' },
    { label: t('staff_reports.completed_memberships'), value: memberships.value.filter(item => ['expired', 'cancelled', 'deleted'].includes(item.status)).length, icon: 'tabler-circle-x', className: 'bg-label-secondary' },
    { label: t('staff_reports.total_commissions'), value: formatAmount(totalCommissions.value), icon: 'tabler-cash', className: 'bg-label-info' },
    { label: t('staff_reports.monthly_salaries'), value: formatAmount(totalMonthlySalaries.value), icon: 'tabler-calendar-dollar', className: 'bg-label-primary' },
    { label: t('staff_reports.paid_salaries'), value: formatAmount(paidSalaries.value), icon: 'tabler-check', className: 'bg-label-success' },
    { label: t('staff_reports.pending_salaries'), value: formatAmount(pendingSalaries.value), icon: 'tabler-clock', className: 'bg-label-warning' },
])

const customerName = membership => fullName(membership?.person)
const membershipPlanName = membership => translatedName(membership?.membership_plan)
const commissionMembership = commission => commission?.person_membership
const salaryMembership = salary => salary?.person_membership
</script>

<template>
    <Head :title="t('staff_reports.trainer_profile')" />

    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                        {{ t('staff_reports.trainer_profile') }}
                    </h2>
                    <div class="text-muted">{{ fullName(trainer) }}</div>
                </div>
                <Link
                    class="btn btn-secondary"
                    :href="route('trainer.index', { locale: currentLocale })"
                >
                    {{ t('people.back') }}
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
                <h5 class="mb-0">{{ t('staff_reports.trainer_details') }}</h5>
                <small class="text-muted">{{ t('staff_reports.contact_and_employment_details') }}</small>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="profile-info-group h-100">
                            <h6 class="mb-3">{{ t('people.contact_information') }}</h6>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-primary">
                                    <i class="icon-base ti tabler-user"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ t('people.full_name') }}</div>
                                    <div class="fw-semibold">{{ fullName(trainer) }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-primary">
                                    <i class="icon-base ti tabler-phone"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ t('filter.phone') }}</div>
                                    <div class="fw-semibold">{{ trainer?.phone || '-' }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-primary">
                                    <i class="icon-base ti tabler-mail"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ t('staff_reports.email_address') }}</div>
                                    <div class="fw-semibold">{{ trainer?.email || '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="profile-info-group h-100">
                            <h6 class="mb-3">{{ t('staff_reports.employment_details') }}</h6>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-info">
                                    <i class="icon-base ti tabler-building"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ t('people.gym') }}</div>
                                    <div class="fw-semibold">{{ trainer?.gym?.name || '-' }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-info">
                                    <i class="icon-base ti tabler-circle-check"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ t('status.status') }}</div>
                                    <div class="fw-semibold">{{ trainerStatusLabel }}</div>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <div class="profile-info-icon bg-label-info">
                                    <i class="icon-base ti tabler-shield-check"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ t('staff_reports.roles') }}</div>
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
                            {{ t('sidebar.membership_plans') }}
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
                            {{ t('staff_reports.commissions') }}
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
                            {{ t('staff_reports.monthly_salaries') }}
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body pt-4">
                <div v-if="activeTab === 'memberships'" class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('sales.client') }}</th>
                                <th>{{ t('people.membership') }}</th>
                                <th>{{ t('status.status') }}</th>
                                <th>{{ t('people.start') }}</th>
                                <th>{{ t('people.end') }}</th>
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
                                    {{ t('people.no_memberships') }}
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
                                <th>{{ t('staff_reports.calculation_type') }}</th>
                                <th>{{ t('staff_reports.calculation_value') }}</th>
                                <th>{{ t('people.amount') }}</th>
                                <th>{{ t('status.status') }}</th>
                                <th>{{ t('people.membership') }}</th>
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
                                    {{ t('staff_reports.no_commissions') }}
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
                                <th>{{ t('membership.month') }}</th>
                                <th>{{ t('people.amount') }}</th>
                                <th>{{ t('status.status') }}</th>
                                <th>{{ t('people.membership') }}</th>
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
                                    {{ t('staff_reports.no_monthly_salaries') }}
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
