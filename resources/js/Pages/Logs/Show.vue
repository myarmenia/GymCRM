<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from "@/Layouts/Index.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import LogValueNode from "./LogValueNode.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

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
    "person.created": t('logs.person_registration'),
    "membership_sale.created": t('staff_reports.membership_sale'),
    "membership_sale.payment_added": t('logs.payment_addition'),
    "membership_sale.refund_added": t('logs.refund'),
    "membership_sale.cancelled": t('logs.membership_cancellation'),
    "membership_sale.trainer_changed": t('logs.trainer_change'),
    "membership_sale.frozen": t('logs.membership_freeze'),
    "membership_sale.updated": t('logs.membership_edit'),
};
const details = computed(() => [
    {
        label: t('people.action'),
        value: actionLabels[props.log.action] ?? props.log.action,
    },
    { label: t('logs.model_object'), value: props.log.subject },
    { label: t('logs.user'), value: props.log.user },
    { label: t('inventory.created_at'), value: props.log.created_at },
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
    <Head :title="t('logs.log_details_number', { id: log.id })" />

    <Index>
        <template #header>
            <div
                class="d-flex flex-wrap align-items-center justify-content-between gap-3"
            >
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 mb-1"
                    >
                        {{ t('logs.log_details') }}
                    </h2>
                    <div class="text-muted small">{{ log.title }}</div>
                </div>

                <Link
                    :href="route('logs.index', { locale: currentLocale })"
                    class="btn btn-sm btn-outline-secondary"
                >
                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                    {{ t('logs.return_to_logs') }}
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
                                {{ t('logs.old_values') }}
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
                                {{ t('logs.new_values') }}
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
