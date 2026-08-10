<script setup>
import { computed } from "vue";

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
    name: "Անուն",
    surname: "Ազգանուն",
    email: "Էլ․ հասցե",
    phone: "Հեռախոս",
    type: "Տեսակ",
    birth_date: "Ծննդյան ամսաթիվ",
    gender: "Սեռ",
    mobile_deleted: "Հեռացված է բջջայինից",
    gyms: "Մարզասրահներ",
    entry_code_id: "Մուտքի կոդի ID",
    person: "Հաճախորդ",
    gym: "Մարզասրահ",
    membership_plan: "Աբոնեմենտ",
    salesperson: "Վաճառող",
    membership: "Անդամակցություն",
    status: "Կարգավիճակ",
    start_date: "Սկսվելու ամսաթիվ",
    end_date: "Ավարտվելու ամսաթիվ",
    trainer: "Մարզիչ",
    visits_left: "Մնացած այցելություններ",
    freeze_left: "Մնացած սառեցումներ",
    guest_left: "Մնացած հյուրեր",
    total_price: "Ընդհանուր արժեք",
    discount_type: "Զեղչի տեսակ",
    discount_value: "Զեղչի արժեք",
    discount_amount: "Ձեռքով զեղչի գումար",
    discount_membership_amount: "Աբոնեմենտի զեղչերի գումար",
    final_price: "Վերջնական արժեք",
    payment_status: "Վճարման կարգավիճակ",
    notes: "Նշումներ",
    sold_at: "Վաճառվել է",
    discounts: "Զեղչեր",
    payments: "Վճարումներ",
    value: "Արժեք",
    amount: "Գումար",
    payment_method: "Վճարման եղանակ",
    card_type: "Քարտի տեսակ",
    is_hdm: "ՀԴՄ",
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
