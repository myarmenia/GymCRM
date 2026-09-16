<script setup>
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import Index from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    salaries: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? "hy");

const planName = (salary) => {
    const membershipPlan = salary.person_membership?.membership_plan;
    const translations = membershipPlan?.translations ?? [];

    return (
        translations.find((translation) => translation.locale === currentLocale.value)
            ?.name ?? membershipPlan?.name ?? "-"
    );
};

const customerName = (salary) => {
    const person = salary.person_membership?.person;

    return `${person?.name ?? ""} ${person?.surname ?? ""}`.trim() || "-";
};

const formatAmount = (amount) =>
    Number(amount ?? 0).toLocaleString("hy-AM", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

const formatDate = (value) => (value ? String(value).slice(0, 10) : "-");
const statusLabel = (status) =>
    ({
        pending: "Սպասման մեջ",
        paid: "Վճարված",
        transfer: "Փոխանցված",
        cancel: "Չեղարկված",
        reject: "Մերժված",
    })[status] ?? status ?? "-";
const statusClass = (status) =>
    ({
        pending: "bg-label-warning",
        paid: "bg-label-success",
        transfer: "bg-label-info",
        cancel: "bg-label-secondary",
        reject: "bg-label-danger",
    })[status] ?? "bg-label-secondary";
</script>

<template>
    <Head title="Իմ աշխատավարձերը" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Իմ աշխատավարձերը
            </h2>
        </template>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Իմ աշխատավարձերի պատմությունը</h5>
            </div>

            <div class="card-body">
                <div v-if="!salaries.data?.length" class="alert alert-info mb-0">
                    Աշխատավարձի գրանցումներ դեռ չկան։
                </div>

                <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ամիս</th>
                                <th>Հաճախորդ</th>
                                <th>Աբոնեմենտ</th>
                                <th>Գումար</th>
                                <th>Կարգավիճակ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="salary in salaries.data" :key="salary.id">
                                <td>{{ formatDate(salary.salary_month) }}</td>
                                <td>{{ customerName(salary) }}</td>
                                <td>{{ planName(salary) }}</td>
                                <td>{{ formatAmount(salary.price) }}</td>
                                <td>
                                    <span class="badge" :class="statusClass(salary.status)">
                                        {{ statusLabel(salary.status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="salaries.links?.length" class="card-footer">
                <Pagination :links="salaries.links" />
            </div>
        </div>
    </Index>
</template>
