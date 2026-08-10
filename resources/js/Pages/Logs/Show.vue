<script setup>
import Index from "@/Layouts/Index.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import LogValueNode from "./LogValueNode.vue";

const props = defineProps({
    log: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentLocale = computed(
    () => page.props.locale ?? page.props.lang ?? "hy",
);
const hasOldValues = computed(() => hasContent(props.log.old_values));
const hasNewValues = computed(() => hasContent(props.log.new_values));
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
const details = computed(() => [
    {
        label: "Գործողություն",
        value: actionLabels[props.log.action] ?? props.log.action,
    },
    { label: "Մոդել / Օբյեկտ", value: props.log.subject },
    { label: "Օգտատեր", value: props.log.user },
    { label: "Ստեղծվել է", value: props.log.created_at },
]);

function hasContent(value) {
    if (value === null || value === undefined || value === "") {
        return false;
    }

    return Array.isArray(value)
        ? value.length > 0
        : typeof value === "object"
          ? Object.keys(value).length > 0
          : true;
}
</script>

<template>
    <Head :title="`Մատյանի մանրամասներ #${log.id}`" />

    <Index>
        <template #header>
            <div
                class="d-flex flex-wrap align-items-center justify-content-between gap-3"
            >
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 mb-1"
                    >
                        Մատյանի մանրամասներ
                    </h2>
                    <div class="text-muted small">{{ log.title }}</div>
                </div>

                <Link
                    :href="route('logs.index', { locale: currentLocale })"
                    class="btn btn-sm btn-outline-secondary"
                >
                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                    Վերադառնալ մատյաններ
                </Link>
            </div>
        </template>

        <div class="log-details-page">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 text-break">{{ log.title }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div
                            v-for="item in details"
                            :key="item.label"
                            class="col-12 col-md-6 col-xl-3"
                        >
                            <div class="log-meta-item">
                                <div class="log-meta-label">
                                    {{ item.label }}
                                </div>
                                <div class="log-meta-value">
                                    {{ item.value || "-" }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                Հին արժեքներ
                            </h5>
                        </div>
                        <div class="card-body">
                            <LogValueNode
                                v-if="hasOldValues"
                                :value="log.old_values"
                            />
                            <div v-else class="text-muted">-</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                Նոր արժեքներ
                            </h5>
                        </div>
                        <div class="card-body">
                            <LogValueNode
                                v-if="hasNewValues"
                                :value="log.new_values"
                            />
                            <div v-else class="text-muted">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.log-details-page {
    display: grid;
    gap: 1.5rem;
}

.log-meta-item {
    min-height: 100%;
    padding: 0.85rem;
    border: 1px solid rgba(67, 89, 113, 0.12);
    border-radius: 0.375rem;
    background: #fff;
}

.log-meta-label {
    margin-bottom: 0.35rem;
    color: #697a8d;
    font-size: 0.8125rem;
    font-weight: 600;
}

.log-meta-value {
    overflow-wrap: anywhere;
    color: #384551;
}
</style>
