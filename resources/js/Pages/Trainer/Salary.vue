<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    trainer: Object,
})

const selectedSalaryIds = ref([])
const bulkForm = useForm({
    salary_ids: [],
    action: '',
})
const transferForm = useForm({
    salary_id: null,
})

const commissions = computed(() => props.trainer?.trainer_commissions ?? [])
const salaries = computed(() => commissions.value.flatMap(commission => commission.monthly_salaries ?? []))
const salaryRows = computed(() => commissions.value.flatMap(commission => {
    return (commission.monthly_salaries ?? []).map(salary => ({
        salary,
        commission,
        membership: salary.person_membership ?? commission.person_membership,
    }))
}))

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

const membershipPlanName = membership => translatedName(membership?.membership_plan)
const customerName = membership => fullName(membership?.person)
const totalGeneratedSalaries = computed(() => salaries.value.reduce((total, salary) => total + Number(salary.price || 0), 0))
const totalPaidSalaries = computed(() => salaries.value
    .filter(salary => salary.status === 'paid')
    .reduce((total, salary) => total + Number(salary.price || 0), 0))
const totalPendingSalaries = computed(() => salaries.value
    .filter(salary => salary.status === 'pending')
    .reduce((total, salary) => total + Number(salary.price || 0), 0))
const totalTransferredSalaries = computed(() => salaries.value
    .filter(salary => salary.status === 'transfer')
    .reduce((total, salary) => total + Number(salary.price || 0), 0))
const totalRejectedCancelledSalaries = computed(() => salaries.value
    .filter(salary => ['reject', 'cancel'].includes(salary.status))
    .reduce((total, salary) => total + Number(salary.price || 0), 0))
const stats = computed(() => [
    { label: t('staff_reports.total_created'), value: formatAmount(totalGeneratedSalaries.value), icon: 'tabler-calendar-dollar', className: 'bg-label-primary' },
    { label: t('people.paid'), value: formatAmount(totalPaidSalaries.value), icon: 'tabler-check', className: 'bg-label-success' },
    { label: t('status.pending'), value: formatAmount(totalPendingSalaries.value), icon: 'tabler-clock', className: 'bg-label-warning' },
    { label: t('staff_reports.transferred'), value: formatAmount(totalTransferredSalaries.value), icon: 'tabler-transfer', className: 'bg-label-info' },
    { label: t('staff_reports.rejected_cancelled'), value: formatAmount(totalRejectedCancelledSalaries.value), icon: 'tabler-x', className: 'bg-label-danger' },
])

const payableStatuses = ['pending', 'transfer']
const selectableSalaryRows = computed(() => salaryRows.value.filter(row => payableStatuses.includes(row.salary.status)))
const selectedCount = computed(() => selectedSalaryIds.value.length)
const allSelectableSelected = computed(() => selectableSalaryRows.value.length > 0
    && selectableSalaryRows.value.every(row => selectedSalaryIds.value.includes(row.salary.id)))
const canSelectSalary = salary => payableStatuses.includes(salary.status)
const canTransfer = row => {
    const currentTrainerId = Number(row.membership?.trainer_id || 0)
    const salaryTrainerId = Number(row.salary?.trainer_id || 0)

    return payableStatuses.includes(row.salary?.status)
        && !row.salary?.salary_payout_id
        && currentTrainerId
        && salaryTrainerId
        && currentTrainerId !== salaryTrainerId
}
const toggleAllSelectable = () => {
    selectedSalaryIds.value = allSelectableSelected.value
        ? []
        : selectableSalaryRows.value.map(row => row.salary.id)
}
const submitBulkAction = action => {
    bulkForm.salary_ids = selectedSalaryIds.value
    bulkForm.action = action
    bulkForm.patch(
        route('trainer.salary.status', {
            locale: currentLocale.value,
            id: props.trainer.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedSalaryIds.value = []
            },
        },
    )
}
const transferSalary = salaryId => {
    transferForm.salary_id = salaryId
    transferForm.patch(route('trainer.salary.transfer', {
        locale: currentLocale.value,
        id: props.trainer.id,
    }), {
        preserveScroll: true,
        onSuccess: () => {
            transferForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="t('staff_reports.trainer_salary')" />

    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                        {{ t('staff_reports.trainer_salary') }}
                    </h2>
                    <div class="text-muted">{{ fullName(trainer) }}</div>
                </div>
                <Link
                    class="btn btn-secondary"
                    :href="route('trainer.index', { locale: currentLocale })"
                >
                    {{ t('people.back') }}
                </Link>
                <Link
                    class="btn btn-success"
                    :href="route('salary-payouts.index', {
                        locale: currentLocale,
                        payee_id: trainer.id,
                        type: 'trainer_monthly_salary',
                    })"
                >
                    <i class="icon-base ti tabler-cash me-1"></i>
                    {{ t('inventory.proceed_to_payment') }}
                </Link>
            </div>
        </template>

        <div class="card mb-4">
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
                class="col-12 col-sm-6 col-xl"
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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h5 class="mb-0">{{ t('staff_reports.trainer_salary_table') }}</h5>
                    <small class="text-muted">{{ t('staff_reports.select_salaries_to_change_their_status') }}</small>
                </div>
            </div>
            <div class="card-body">
                <div
                    v-if="selectedCount"
                    class="salary-action-bar mb-3"
                >
                    <div class="fw-semibold">
                        {{ t('staff_reports.selected_count', { count: selectedCount }) }}
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            :disabled="bulkForm.processing"
                            @click="submitBulkAction('cancel')"
                        >
                            <i class="icon-base ti tabler-x me-1"></i>
                            {{ t('staff_reports.cancel_selected') }}
                        </button>
                    </div>
                </div>
                <div
                    v-if="bulkForm.errors.salary_ids || transferForm.errors.salary_id"
                    class="text-danger mb-3"
                >
                    {{ bulkForm.errors.salary_ids || transferForm.errors.salary_id }}
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 48px;">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="allSelectableSelected"
                                        :disabled="!selectableSalaryRows.length"
                                        @change="toggleAllSelectable"
                                    >
                                </th>
                                <th>{{ t('membership.month') }}</th>
                                <th>{{ t('sales.client') }}</th>
                                <th>{{ t('people.membership') }}</th>
                                <th>{{ t('sales.term') }}</th>
                                <th>{{ t('staff_reports.commission_amount') }}</th>
                                <th>{{ t('staff_reports.monthly_salary') }}</th>
                                <th>{{ t('status.status') }}</th>
                                <th>{{ t('inventory.created_at') }}</th>
                                <th>{{ t('people.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in salaryRows"
                                :key="row.salary.id"
                                :class="{ 'table-active': selectedSalaryIds.includes(row.salary.id) }"
                            >
                                <td>
                                    <input
                                        v-if="canSelectSalary(row.salary)"
                                        v-model="selectedSalaryIds"
                                        class="form-check-input"
                                        type="checkbox"
                                        :value="row.salary.id"
                                    >
                                </td>
                                <td>{{ formatMonth(row.salary.salary_month) }}</td>
                                <td>
                                    <Link
                                        v-if="row.membership?.person?.id"
                                        :href="route('person.profile', {
                                            locale: currentLocale,
                                            id: row.membership.person.id,
                                        })"
                                        class="fw-semibold"
                                    >
                                        {{ customerName(row.membership) }}
                                    </Link>
                                    <span v-else>{{ customerName(row.membership) }}</span>
                                </td>
                                <td>{{ membershipPlanName(row.membership) }}</td>
                                <td>
                                    <div>
                                        <span class="text-muted">{{ t('sales.start') }}</span>
                                        {{ formatDate(row.membership?.start_date) }}
                                    </div>
                                    <div>
                                        <span class="text-muted">{{ t('sales.end') }}</span>
                                        {{ formatDate(row.membership?.valid_at || row.membership?.end_date) }}
                                    </div>
                                </td>
                                <td>{{ formatAmount(row.commission.salary_amount) }}</td>
                                <td>{{ formatAmount(row.salary.price) }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="salaryStatusClass(row.salary.status)"
                                    >
                                        {{ salaryStatusLabel(row.salary.status) }}
                                    </span>
                                </td>
                                <td>{{ formatDate(row.salary.created_at) }}</td>
                                <td>
                                    <button
                                        v-if="canTransfer(row)"
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        :disabled="transferForm.processing"
                                        @click="transferSalary(row.salary.id)"
                                    >
                                        {{ t('operations.transfer') }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!salaryRows.length">
                                <td
                                    colspan="10"
                                    class="text-center text-muted"
                                >
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

.salary-action-bar {
    align-items: center;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .875rem 1rem;
}
</style>
