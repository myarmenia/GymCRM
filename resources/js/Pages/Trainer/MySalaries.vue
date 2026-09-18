<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import Index from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

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
        pending: t('status.pending'),
        paid: t('people.paid'),
        transfer: t('staff_reports.transferred'),
        cancel: t('people.cancelled'),
        reject: t('staff_reports.rejected'),
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
    <Head :title="t('sidebar.my_salaries')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('sidebar.my_salaries') }}
            </h2>
        </template>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('staff_reports.my_salary_history') }}</h5>
            </div>

            <div class="card-body">
                <div v-if="!salaries.data?.length" class="alert alert-info mb-0">
                    {{ t('staff_reports.there_are_no_salary_records_yet') }}
                </div>

                <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ t('membership.month') }}</th>
                                <th>{{ t('sales.client') }}</th>
                                <th>{{ t('people.membership') }}</th>
                                <th>{{ t('people.amount') }}</th>
                                <th>{{ t('status.status') }}</th>
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
