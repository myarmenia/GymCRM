<script setup>
import { Head, router, usePage } from "@inertiajs/vue3";
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
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const search = ref(props.filters?.search ?? "");
const startDate = ref(props.filters?.start_date ?? "");
const endDate = ref(props.filters?.end_date ?? "");
const paymentMethod = ref(props.filters?.payment_method ?? "");
const personId = ref(props.filters?.person_id ?? "");
const warehouseId = ref(props.filters?.warehouse_id ?? "");
const expandedPurchaseIds = ref([]);

const localPeople = computed(() => props.peoples?.data ?? props.peoples ?? []);
const localWarehouses = computed(
    () => props.warehouses?.data ?? props.warehouses ?? [],
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
    if (method === "cash") {
        return "Կանխիկ";
    }

    if (method === "card") {
        return "Քարտ";
    }

    return method ?? "-";
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

const buildParams = () => {
    const params = {};

    if (search.value?.trim()) params.search = search.value.trim();
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;
    if (paymentMethod.value) params.payment_method = paymentMethod.value;
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
    paymentMethod.value = "";
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
                        <select v-model="paymentMethod" class="form-select">
                            <option value="">Բոլորը</option>
                            <option value="cash">Կանխիկ</option>
                            <option value="card">Քարտ</option>
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
                                                purchase.payment_method === 'cash'
                                                    ? 'bg-label-success'
                                                    : 'bg-label-primary'
                                            "
                                        >
                                            {{
                                                getPaymentMethodLabel(
                                                    purchase.payment_method,
                                                )
                                            }}
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
                                    <td class="text-end">
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
                                                            colspan="5"
                                                            class="text-center text-muted"
                                                        >
                                                            Ապրանքներ չեն գտնվել
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
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
    </AppLayout>
</template>
