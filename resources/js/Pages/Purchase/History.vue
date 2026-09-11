<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    purchases: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    peoples: {
        type: [Array, Object],
        default: () => [],
    },
    warehouses: {
        type: [Array, Object],
        default: () => [],
    },
    paymentMethods: {
        type: [Array, Object],
        default: () => [],
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const search = ref(props.filters?.search ?? "");
const startDate = ref(props.filters?.start_date ?? "");
const endDate = ref(props.filters?.end_date ?? "");
const paymentMethodId = ref(props.filters?.payment_method_id ?? "");
const personId = ref(props.filters?.person_id ?? "");
const warehouseId = ref(props.filters?.warehouse_id ?? "");
const expandedPurchaseIds = ref([]);
const refundPurchase = ref(null);
const showRefundModal = ref(false);
const refundForm = useForm({
    payment_method_id: "",
    card_type_id: "",
    reference: "",
    reason: "",
    items: [],
});

const localPeople = computed(() => props.peoples?.data ?? props.peoples ?? []);
const localWarehouses = computed(
    () => props.warehouses?.data ?? props.warehouses ?? [],
);
const localPaymentMethods = computed(
    () => props.paymentMethods?.data ?? props.paymentMethods ?? [],
);
const selectedRefundPaymentMethod = computed(() =>
    localPaymentMethods.value.find(
        (method) => Number(method.id) === Number(refundForm.payment_method_id),
    ),
);
const refundCardTypes = computed(
    () => selectedRefundPaymentMethod.value?.card_types ?? [],
);
const hasRefundQuantity = computed(() =>
    refundForm.items.some((item) => Number(item.quantity) > 0),
);

const formatMoney = (value) => {
    return `${Number(value || 0).toLocaleString("hy-AM")} ֏`;
};

const getPersonName = (person) => {
    if (!person) {
        return "Հաճախորդ ընտրված չէ";
    }

    return `${person.name ?? ""}${person.surname ? ` ${person.surname}` : ""}`.trim();
};

const getPaymentMethodLabel = (method) => {
    return (
        method?.translations?.find(
            (translation) => translation.locale === currentLocale,
        )?.name ??
        method?.name ??
        method?.slug ??
        "-"
    );
};

const isExpanded = (purchaseId) => {
    return expandedPurchaseIds.value.includes(purchaseId);
};

const togglePurchase = (purchaseId) => {
    if (isExpanded(purchaseId)) {
        expandedPurchaseIds.value = expandedPurchaseIds.value.filter(
            (id) => id !== purchaseId,
        );
        return;
    }

    expandedPurchaseIds.value.push(purchaseId);
};

const openRefundModal = (purchase) => {
    refundPurchase.value = purchase;
    refundForm.clearErrors();
    refundForm.payment_method_id = purchase.payment_method_id ?? "";
    refundForm.card_type_id = purchase.card_type_id ?? "";
    refundForm.reference = "";
    refundForm.reason = "";
    refundForm.items = purchase.items
        .filter((item) => Number(item.refundable_quantity) > 0)
        .map((item) => ({
            purchase_item_id: item.id,
            quantity: Number(item.refundable_quantity),
        }));
    showRefundModal.value = true;
};

const closeRefundModal = () => {
    if (refundForm.processing) return;
    showRefundModal.value = false;
    refundPurchase.value = null;
    refundForm.reset();
    refundForm.clearErrors();
};

const submitRefund = () => {
    if (!refundPurchase.value) return;

    refundForm
        .transform((data) => ({
            ...data,
            card_type_id: data.card_type_id || null,
            items: data.items.filter((item) => Number(item.quantity) > 0),
        }))
        .post(
            route("purchase.refund", {
                locale: currentLocale,
                purchase: refundPurchase.value.id,
            }),
            {
                preserveScroll: true,
                onSuccess: closeRefundModal,
            },
        );
};

const getRefundItem = (purchaseItemId) =>
    refundPurchase.value?.items.find(
        (item) => Number(item.id) === Number(purchaseItemId),
    );

const buildParams = () => {
    const params = {};

    if (search.value?.trim()) params.search = search.value.trim();
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;
    if (paymentMethodId.value) {
        params.payment_method_id = paymentMethodId.value;
    }
    if (personId.value) params.person_id = personId.value;
    if (warehouseId.value) params.warehouse_id = warehouseId.value;

    return params;
};

const applyFilters = () => {
    router.get(
        route("purchase.history", { locale: currentLocale }),
        buildParams(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    search.value = "";
    startDate.value = "";
    endDate.value = "";
    paymentMethodId.value = "";
    personId.value = "";
    warehouseId.value = "";

    router.get(
        route("purchase.history", { locale: currentLocale }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <Head title="Վաճառքների պատմություն" />

    <AppLayout>
        <div class="card">
            <div
                class="card-header d-flex flex-column flex-md-row justify-content-between gap-3"
            >
                <div>
                    <h5 class="mb-1">Վաճառքների պատմություն</h5>
                    <p class="text-muted mb-0">
                        Դիտեք վաճառքները, վճարման տվյալները և վաճառված ապրանքները։
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-outline-secondary align-self-md-start"
                    @click="resetFilters"
                >
                    <i class="icon-base ti tabler-refresh me-1"></i>
                    Մաքրել
                </button>
            </div>

            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Որոնում</label>
                        <input
                            v-model="search"
                            type="text"
                            class="form-control"
                            placeholder="ID, ապրանք, SKU կամ հաճախորդ"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Սկիզբ</label>
                        <input
                            v-model="startDate"
                            type="date"
                            class="form-control"
                        />
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Ավարտ</label>
                        <input
                            v-model="endDate"
                            type="date"
                            class="form-control"
                        />
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Վճարում</label>
                        <select v-model="paymentMethodId" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="method in localPaymentMethods"
                                :key="method.id"
                                :value="method.id"
                            >
                                {{ getPaymentMethodLabel(method) }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Հաճախորդ</label>
                        <select v-model="personId" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="person in localPeople"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{ person.name }}{{
                                    person.surname ? ` ${person.surname}` : ""
                                }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Պահեստ</label>
                        <select v-model="warehouseId" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="warehouse in localWarehouses"
                                :key="warehouse.id"
                                :value="warehouse.id"
                            >
                                {{ warehouse.name ?? "-" }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6 d-flex align-items-end">
                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            @click="applyFilters"
                        >
                            <i class="icon-base ti tabler-search me-1"></i>
                            Որոնել
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="70">#</th>
                                <th>Ամսաթիվ</th>
                                <th>Հաճախորդ</th>
                                <th>Վճարում</th>
                                <th>Միջանկյալ</th>
                                <th>Զեղչ</th>
                                <th>Ընդամենը</th>
                                <th>Ստացված</th>
                                <th>Մանր</th>
                                <th width="90"></th>
                            </tr>
                        </thead>

                        <tbody>
                            <template
                                v-for="purchase in purchases.data"
                                :key="purchase.id"
                            >
                                <tr>
                                    <td>#{{ purchase.id }}</td>
                                    <td>{{ purchase.date ?? "-" }}</td>
                                    <td>{{ getPersonName(purchase.person) }}</td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="
                                                purchase.payment_method?.slug === 'cash'
                                                    ? 'bg-label-success'
                                                    : 'bg-label-primary'
                                            "
                                        >
                                            {{
                                                getPaymentMethodLabel(
                                                    purchase.payment_method,
                                                )
                                            }}
                                            <span
                                                v-if="purchase.card_type"
                                                class="d-block small mt-1"
                                            >
                                                {{ purchase.card_type.name }}
                                            </span>
                                        </span>
                                    </td>
                                    <td>{{ formatMoney(purchase.subtotal) }}</td>
                                    <td>
                                        {{
                                            formatMoney(
                                                purchase.discount_amount,
                                            )
                                        }}
                                        <span
                                            v-if="purchase.discount_percent"
                                            class="text-muted small"
                                        >
                                            ({{ purchase.discount_percent }}%)
                                        </span>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        {{ formatMoney(purchase.total) }}
                                        <span
                                            v-if="purchase.refunded_amount"
                                            class="d-block small text-danger"
                                        >
                                            Վերադարձ՝ {{ formatMoney(purchase.refunded_amount) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{
                                            formatMoney(
                                                purchase.cash_received,
                                            )
                                        }}
                                    </td>
                                    <td>
                                        {{
                                            formatMoney(
                                                purchase.change_amount,
                                            )
                                        }}
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button
                                            v-if="purchase.can_refund"
                                            type="button"
                                            class="btn btn-sm btn-outline-warning me-1"
                                            title="Գրանցել վերադարձ"
                                            @click="openRefundModal(purchase)"
                                        >
                                            <i class="icon-base ti tabler-arrow-back-up"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            @click="togglePurchase(purchase.id)"
                                        >
                                            <i
                                                class="icon-base ti"
                                                :class="
                                                    isExpanded(purchase.id)
                                                        ? 'tabler-chevron-up'
                                                        : 'tabler-chevron-down'
                                                "
                                            ></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="isExpanded(purchase.id)">
                                    <td colspan="10" class="bg-light">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Ապրանք</th>
                                                        <th>SKU</th>
                                                        <th>Քանակ</th>
                                                        <th>Վերադարձված</th>
                                                        <th>Հասանելի</th>
                                                        <th>Գին</th>
                                                        <th>Ընդամենը</th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    <tr
                                                        v-for="item in purchase.items"
                                                        :key="item.id"
                                                    >
                                                        <td>
                                                            {{
                                                                item.product_name
                                                            }}
                                                        </td>
                                                        <td>
                                                            {{ item.sku ?? "-" }}
                                                        </td>
                                                        <td>
                                                            {{ item.quantity }}
                                                        </td>
                                                        <td>{{ item.refunded_quantity }}</td>
                                                        <td>{{ item.refundable_quantity }}</td>
                                                        <td>
                                                            {{
                                                                formatMoney(
                                                                    item.price,
                                                                )
                                                            }}
                                                        </td>
                                                        <td>
                                                            {{
                                                                formatMoney(
                                                                    item.total,
                                                                )
                                                            }}
                                                        </td>
                                                    </tr>

                                                    <tr
                                                        v-if="
                                                            !purchase.items
                                                                .length
                                                        "
                                                    >
                                                        <td
                                                            colspan="7"
                                                            class="text-center text-muted"
                                                        >
                                                            Ապրանքներ չեն գտնվել
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div v-if="purchase.refunds.length" class="mt-3">
                                            <h6 class="mb-2">Վերադարձների պատմություն</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Ամսաթիվ</th>
                                                            <th>Գումար</th>
                                                            <th>Վճարում</th>
                                                            <th>Գրանցող</th>
                                                            <th>Պատճառ / հղում</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="refund in purchase.refunds" :key="refund.id">
                                                            <td>#{{ refund.id }}</td>
                                                            <td>{{ refund.date }}</td>
                                                            <td class="text-danger fw-semibold">{{ formatMoney(refund.amount) }}</td>
                                                            <td>{{ getPaymentMethodLabel(refund.payment_method) }}</td>
                                                            <td>{{ refund.refunder ?? "-" }}</td>
                                                            <td>{{ refund.reason || refund.reference || "-" }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!purchases.data.length">
                                <td
                                    colspan="10"
                                    class="text-center text-muted py-4"
                                >
                                    Վաճառքներ չեն գտնվել
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    v-if="purchases.links?.length"
                    :links="purchases.links"
                />
            </div>
        </div>

        <div
            v-if="showRefundModal && refundPurchase"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="background: rgba(0, 0, 0, 0.5)"
            @click.self="closeRefundModal"
        >
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">Ապրանքի վերադարձ</h5>
                            <small class="text-muted">
                                Վաճառք #{{ refundPurchase.id }} · վերադարձման մնացորդ՝
                                {{ formatMoney(refundPurchase.refundable_amount) }}
                            </small>
                        </div>
                        <button
                            type="button"
                            class="btn-close"
                            :disabled="refundForm.processing"
                            @click="closeRefundModal"
                        ></button>
                    </div>

                    <form @submit.prevent="submitRefund">
                        <div class="modal-body">
                            <div v-if="refundForm.errors.refund" class="alert alert-danger">
                                {{ refundForm.errors.refund }}
                            </div>
                            <div v-if="refundForm.errors.items" class="alert alert-danger">
                                {{ refundForm.errors.items }}
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ապրանք</th>
                                            <th>Վաճառված</th>
                                            <th>Արդեն վերադարձված</th>
                                            <th style="width: 160px">Վերադարձնել</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="formItem in refundForm.items" :key="formItem.purchase_item_id">
                                            <td>{{ getRefundItem(formItem.purchase_item_id)?.product_name ?? "-" }}</td>
                                            <td>{{ getRefundItem(formItem.purchase_item_id)?.quantity ?? 0 }}</td>
                                            <td>{{ getRefundItem(formItem.purchase_item_id)?.refunded_quantity ?? 0 }}</td>
                                            <td>
                                                <input
                                                    v-model.number="formItem.quantity"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    :max="getRefundItem(formItem.purchase_item_id)?.refundable_quantity ?? 0"
                                                    class="form-control form-control-sm"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Վերադարձի եղանակ *</label>
                                    <select
                                        v-model="refundForm.payment_method_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': refundForm.errors.payment_method_id }"
                                        @change="refundForm.card_type_id = ''"
                                    >
                                        <option value="">Ընտրել</option>
                                        <option v-for="method in localPaymentMethods" :key="method.id" :value="method.id">
                                            {{ getPaymentMethodLabel(method) }}
                                        </option>
                                    </select>
                                    <div class="invalid-feedback">{{ refundForm.errors.payment_method_id }}</div>
                                </div>
                                <div v-if="refundCardTypes.length" class="col-md-6">
                                    <label class="form-label">Քարտի տեսակ *</label>
                                    <select
                                        v-model="refundForm.card_type_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': refundForm.errors.card_type_id }"
                                    >
                                        <option value="">Ընտրել</option>
                                        <option v-for="cardType in refundCardTypes" :key="cardType.id" :value="cardType.id">
                                            {{ cardType.name }}
                                        </option>
                                    </select>
                                    <div class="invalid-feedback">{{ refundForm.errors.card_type_id }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Հղում / փաստաթղթի համար</label>
                                    <input v-model="refundForm.reference" type="text" maxlength="255" class="form-control" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Պատճառ</label>
                                    <input v-model="refundForm.reason" type="text" maxlength="1000" class="form-control" />
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" :disabled="refundForm.processing" @click="closeRefundModal">
                                Փակել
                            </button>
                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="refundForm.processing || !refundForm.payment_method_id || !hasRefundQuantity || (refundCardTypes.length && !refundForm.card_type_id)"
                            >
                                <span v-if="refundForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                Գրանցել վերադարձը
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
