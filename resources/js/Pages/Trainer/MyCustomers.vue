<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import Index from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? "hy");

const statusLabels = {
    waiting: t('status.pending'),
    active: t('status.active'),
    frozen: t('people.frozen'),
};

const statusClasses = {
    waiting: "bg-label-warning",
    active: "bg-label-success",
    frozen: "bg-label-info",
};

const planName = (membership) => {
    const translations = membership.membership_plan?.translations ?? [];

    return (
        translations.find((translation) => translation.locale === currentLocale.value)
            ?.name ??
        membership.membership_plan?.name ??
        "-"
    );
};

const statusLabel = (status) => statusLabels[status] ?? status ?? "-";
const statusClass = (status) => statusClasses[status] ?? "bg-label-secondary";
</script>

<template>
    <Head :title="t('sidebar.my_customers')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('sidebar.my_customers') }}
            </h2>
        </template>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('staff_reports.customers_assigned_to_me') }}</h5>
            </div>

            <div class="card-body">
                <div v-if="!customers.data?.length" class="alert alert-info mb-0">
                    {{ t('staff_reports.no_active_customer_has_been_assigned_to_you_yet') }}
                </div>

                <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ t('sales.client') }}</th>
                                <th>{{ t('filter.phone') }}</th>
                                <th>{{ t('auth.email') }}</th>
                                <th>{{ t('staff_reports.membership_s') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="customer in customers.data" :key="customer.id">
                                <td>
                                    {{ customer.name || "-" }} {{ customer.surname || "" }}
                                </td>
                                <td>{{ customer.phone || "-" }}</td>
                                <td>{{ customer.email || "-" }}</td>
                                <td>
                                    <div
                                        v-for="membership in customer.memberships"
                                        :key="membership.id"
                                        class="d-flex align-items-center gap-2 mb-1"
                                    >
                                        <span>{{ planName(membership) }}</span>
                                        <span class="badge" :class="statusClass(membership.status)">
                                            {{ statusLabel(membership.status) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <Link
                                        :href="
                                            route('trainer.my-customers.show', {
                                                locale: currentLocale,
                                                personId: customer.id,
                                            })
                                        "
                                        class="btn btn-sm btn-outline-primary"
                                        title="Դիտել հաճախորդի տվյալները"
                                    >
                                        <i class="icon-base ti tabler-eye"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="customers.links?.length" class="card-footer">
                <Pagination :links="customers.links" />
            </div>
        </div>
    </Index>
</template>
