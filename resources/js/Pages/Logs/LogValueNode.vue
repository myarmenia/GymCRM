<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed } from "vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

defineOptions({ name: "LogValueNode" });

const props = defineProps({
    label: { type: String, default: "" },
    value: { type: null, default: null },
    nested: { type: Boolean, default: false },
});

const parsedValue = computed(() => parseJsonString(props.value));
const isMissing = computed(
    () =>
        parsedValue.value === null ||
        parsedValue.value === undefined ||
        parsedValue.value === "",
);
const isArray = computed(() => Array.isArray(parsedValue.value));
const isObject = computed(() => isPlainObject(parsedValue.value));
const isScalarList = computed(
    () =>
        isArray.value &&
        parsedValue.value.every((item) => {
            const parsed = parseJsonString(item);
            return !isPlainObject(parsed) && !Array.isArray(parsed);
        }),
);
const objectEntries = computed(() => Object.entries(parsedValue.value ?? {}));
const humanLabel = computed(() => humanize(props.label));

const labels = {
    id: "ID",
    name: t('auth.name'),
    surname: t('filter.surname'),
    email: t('staff_reports.email_address'),
    phone: t('filter.phone'),
    type: t('people.type'),
    birth_date: t('filter.birth_date'),
    gender: t('people.gender'),
    mobile_deleted: t('logs.removed_from_mobile'),
    gyms: t('logs.gyms'),
    entry_code_id: t('logs.entry_code_id'),
    person: t('sales.client'),
    gym: t('people.gym'),
    membership_plan: t('people.membership'),
    salesperson: t('staff_reports.salesperson'),
    membership: t('logs.membership'),
    status: t('status.status'),
    start_date: t('logs.start_date'),
    end_date: t('logs.end_date'),
    trainer: t('roles.trainer'),
    visits_left: t('sales.remaining_visits'),
    freeze_left: t('sales.remaining_freezes'),
    guest_left: t('sales.remaining_guests'),
    total_price: t('logs.total_value'),
    discount_type: t('sales.discount_type'),
    discount_value: t('sales.discount_value'),
    discount_amount: t('logs.manual_discount_amount'),
    discount_membership_amount: t('logs.membership_discount_amount'),
    final_price: t('logs.final_value'),
    payment_status: t('sales.payment_status'),
    notes: t('people.notes'),
    sold_at: t('logs.sold'),
    discounts: t('sidebar.discounts'),
    payments: t('people.payments'),
    value: t('membership.cost'),
    amount: t('people.amount'),
    payment_method: t('people.payment_method'),
    card_type: t('sales.card_type'),
    is_hdm: t('sales.fiscal_receipt_2'),
};

function parseJsonString(value) {
    if (typeof value !== "string") return value;

    const trimmed = value.trim();
    if (!trimmed || (!trimmed.startsWith("{") && !trimmed.startsWith("["))) {
        return value;
    }

    try {
        return JSON.parse(trimmed);
    } catch {
        return value;
    }
}

function isPlainObject(value) {
    return value !== null && typeof value === "object" && !Array.isArray(value);
}

function humanize(value) {
    if (labels[value]) {
        return labels[value];
    }

    return String(value || "")
        .replace(/[_-]+/g, " ")
        .replace(/\s+/g, " ")
        .trim()
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatScalar(value) {
    const normalized = parseJsonString(value);

    if (normalized === null || normalized === undefined || normalized === "") {
        return "-";
    }

    if (typeof normalized === "boolean") {
        return normalized ? "true" : "false";
    }

    return String(normalized);
}
</script>

<template>
    <section class="log-node" :class="{ 'log-node-nested': nested }">
        <div v-if="humanLabel" class="log-node-label">{{ humanLabel }}</div>
        <div v-if="isMissing" class="text-muted">-</div>

        <div v-else-if="isObject" class="log-object">
            <LogValueNode
                v-for="[key, item] in objectEntries"
                :key="key"
                :label="key"
                :value="item"
                nested
            />
        </div>

        <div v-else-if="isArray && !parsedValue.length" class="text-muted">
            -
        </div>

        <ul v-else-if="isScalarList" class="log-scalar-list">
            <li v-for="(item, index) in parsedValue" :key="index">
                {{ formatScalar(item) }}
            </li>
        </ul>

        <div v-else-if="isArray" class="log-array">
            <div
                v-for="(item, index) in parsedValue"
                :key="index"
                class="log-array-item"
            >
                <div class="log-array-title">#{{ index + 1 }}</div>
                <LogValueNode :value="item" nested />
            </div>
        </div>

        <div v-else class="log-scalar">{{ formatScalar(parsedValue) }}</div>
    </section>
</template>

<style scoped>
.log-node {
    min-width: 0;
}

.log-node-nested,
.log-array-item {
    padding: 0.75rem;
    border: 1px solid rgba(67, 89, 113, 0.12);
    border-radius: 0.375rem;
    background: #fff;
}

.log-node-label {
    margin-bottom: 0.35rem;
    color: #566a7f;
    font-size: 0.8125rem;
    font-weight: 600;
}

.log-object,
.log-array {
    display: grid;
    gap: 0.75rem;
}

.log-array-item {
    min-width: 0;
    background: #f8f9fa;
}

.log-array-title {
    margin-bottom: 0.5rem;
    color: #697a8d;
    font-size: 0.75rem;
    font-weight: 700;
}

.log-scalar-list {
    display: grid;
    gap: 0.25rem;
    margin: 0;
    padding-left: 1.1rem;
}

.log-scalar {
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}
</style>
