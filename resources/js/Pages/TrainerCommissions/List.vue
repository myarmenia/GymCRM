<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    commissions: Object,
});

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? "hy");

const commissionList = ref(props.commissions.data);
const pagination = ref(props.commissions);

watch(
    () => props.commissions,
    (commissions) => {
        commissionList.value = commissions.data;
        pagination.value = commissions;
    },
);

const fullName = user => {
    const name = `${user?.name ?? ""} ${user?.surname ?? ""}`.trim();

    return name || user?.email || "-";
};

const personName = person => {
    const name = `${person?.name ?? ""} ${person?.surname ?? ""}`.trim();

    return name || person?.email || "-";
};

const planName = plan => {
    return plan?.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan?.name
        ?? (plan?.id ? `#${plan.id}` : "-");
};

const salaryTypeLabel = type => ({
    fixed: t('staff_reports.fixed'),
    percent: t('staff_reports.percent'),
}[type] ?? type ?? "-");

const statusLabel = status => ({
    pending: t('status.pending'),
    paid: t('people.paid'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? "-");

const statusClass = status => ({
    pending: "bg-label-warning",
    paid: "bg-label-success",
    cancelled: "bg-label-danger",
}[status] ?? "bg-label-secondary");

const formatAmount = value => Number(value || 0).toFixed(2);
const formatDate = value => value ? String(value).slice(0, 10) : "-";
</script>

<template>
    <Head :title="t('sidebar.trainer_salaries')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('sidebar.trainer_salaries') }}
            </h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ t('sidebar.trainer_salaries') }}</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ t('roles.trainer') }}</th>
                                <th>{{ t('staff_reports.member') }}</th>
                                <th>{{ t('people.membership') }}</th>
                                <th>{{ t('staff_reports.membership_sale') }}</th>
                                <th>{{ t('staff_reports.calculation_type') }}</th>
                                <th>{{ t('staff_reports.calculation_value') }}</th>
                                <th>{{ t('staff_reports.calculated_salary') }}</th>
                                <th>{{ t('status.status') }}</th>
                                <th>{{ t('inventory.created_at') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="commission in commissionList"
                                :key="commission.id"
                            >
                                <td>{{ commission.id }}</td>
                                <td>{{ fullName(commission.trainer) }}</td>
                                <td>{{ personName(commission.person_membership?.person) }}</td>
                                <td>{{ planName(commission.membership_sale?.membership_plan) }}</td>
                                <td>
                                    <Link
                                        v-if="commission.membership_sale"
                                        :href="route('membership_sale.payments', { locale: currentLocale, id: commission.membership_sale.id })"
                                    >
                                        #{{ commission.membership_sale.id }}
                                    </Link>
                                    <span v-else>-</span>
                                </td>
                                <td>{{ salaryTypeLabel(commission.salary_type) }}</td>
                                <td>{{ formatAmount(commission.salary_value) }}</td>
                                <td>{{ formatAmount(commission.salary_amount) }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="statusClass(commission.status)"
                                    >
                                        {{ statusLabel(commission.status) }}
                                    </span>
                                </td>
                                <td>{{ formatDate(commission.created_at) }}</td>
                            </tr>
                            <tr v-if="!commissionList.length">
                                <td
                                    colspan="10"
                                    class="text-center text-muted"
                                >
                                    {{ t('staff_reports.no_records') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>
