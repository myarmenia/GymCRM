<script setup>
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";

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
    fixed: "Ֆիքսված",
    percent: "Տոկոս",
}[type] ?? type ?? "-");

const statusLabel = status => ({
    pending: "Սպասման մեջ",
    paid: "Վճարված",
    cancelled: "Չեղարկված",
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
    <Head title="Մարզիչների աշխատավարձեր" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Մարզիչների աշխատավարձեր
            </h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Մարզիչների աշխատավարձեր</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Մարզիչ</th>
                                <th>Անդամ</th>
                                <th>Աբոնեմենտ</th>
                                <th>Աբոնեմենտի վաճառք</th>
                                <th>Հաշվարկման տեսակ</th>
                                <th>Հաշվարկման արժեք</th>
                                <th>Հաշվարկված աշխատավարձ</th>
                                <th>Կարգավիճակ</th>
                                <th>Ստեղծվել է</th>
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
                                    Գրառումներ չկան
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
