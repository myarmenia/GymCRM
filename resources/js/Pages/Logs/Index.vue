<script setup>
import Pagination from "@/Components/Pagination.vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    logs: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const page = usePage();
const currentLocale = computed(
    () => page.props.locale ?? page.props.lang ?? "hy",
);
const logs = computed(() => props.logs.data ?? []);
const actionLabels = {
    "person.created": "Անձի գրանցում",
    "membership_sale.created": "Աբոնեմենտի վաճառք",
    "membership_sale.payment_added": "Վճարման ավելացում",
    "membership_sale.refund_added": "Գումարի վերադարձ",
    "membership_sale.cancelled": "Աբոնեմենտի չեղարկում",
    "membership_sale.trainer_changed": "Մարզչի փոփոխություն",
    "membership_sale.frozen": "Աբոնեմենտի սառեցում",
    "membership_sale.updated": "Աբոնեմենտի խմբագրում",
};
const actionLabel = (action) => actionLabels[action] ?? action;
</script>

<template>
    <Head title="Մատյաններ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Մատյաններ
            </h2>
        </template>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Մատյաններ</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Գործողություն</th>
                                <th>Մոդել / Օբյեկտ</th>
                                <th>Օգտատեր</th>
                                <th>Ստեղծվել է</th>
                                <th>Ցույց տալ</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="log in logs" :key="log.id">
                                <td>{{ log.id }}</td>
                                <td>
                                    <span class="badge bg-label-primary">
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>
                                <td>{{ log.subject }}</td>
                                <td>{{ log.user || "-" }}</td>
                                <td>{{ log.created_at }}</td>
                                <td>
                                    <Link
                                        :href="route('logs.show', {
                                            locale: currentLocale,
                                            log: log.id,
                                        })"
                                        class="btn btn-sm btn-icon btn-text-secondary"
                                        title="Մատյանի մանրամասներ"
                                    >
                                        <i class="icon-base ti tabler-eye"></i>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="!logs.length">
                                <td colspan="6" class="text-center text-muted">
                                    Գործողությունների մատյաններ չեն գտնվել
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="props.logs.links?.length" class="card-footer">
                <Pagination :links="props.logs.links" />
            </div>
        </div>
    </Index>
</template>
