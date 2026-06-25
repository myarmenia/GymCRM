<script setup>
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    products: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    peoples: {
        type: [Array, Object],
        default: () => [],
    },
    categories: {
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

const cloneProducts = (products = []) => {
    return JSON.parse(JSON.stringify(products ?? []));
};

const productsData = ref(cloneProducts(props.products?.data ?? []));
const cart = ref([]);
const selectedPersonId = ref("");
const step = ref("cart");
const discountPercent = ref(0);
const cashReceived = ref("");
const paymentMethod = ref("cash");
const errors = ref("");
const success = ref("");
const isSubmitting = ref(false);

const name = ref(props.filters?.name ?? "");
const categoryId = ref(props.filters?.category_id ?? "");
const subCategoryId = ref(props.filters?.sub_category_id ?? "");
const warehouseId = ref(props.filters?.warehouse_id ?? "");

watch(
    () => props.products?.data,
    (newProducts) => {
        productsData.value = cloneProducts(newProducts ?? []);
    },
    { deep: true },
);

watch(
    () => props.filters,
    (newFilters) => {
        name.value = newFilters?.name ?? "";
        categoryId.value = newFilters?.category_id ?? "";
        subCategoryId.value = newFilters?.sub_category_id ?? "";
        warehouseId.value = newFilters?.warehouse_id ?? "";
    },
    { deep: true },
);

watch(categoryId, () => {
    subCategoryId.value = "";
});

watch(paymentMethod, (method) => {
    if (method === "card") {
        cashReceived.value = "";
    }
});

watch(discountPercent, (value) => {
    const normalized = clampDiscount(value);

    if (normalized !== Number(value || 0)) {
        discountPercent.value = normalized;
    }
});

const paginationLinks = computed(() => props.products?.links ?? []);
const localPeople = computed(() => props.peoples?.data ?? props.peoples ?? []);
const localCategories = computed(
    () => props.categories?.data ?? props.categories ?? [],
);
const localWarehouses = computed(
    () => props.warehouses?.data ?? props.warehouses ?? [],
);

const selectedCategory = computed(() => {
    return localCategories.value.find(
        (category) => Number(category.id) === Number(categoryId.value),
    );
});

const filteredSubCategories = computed(() => {
    return getCategoryChildren(selectedCategory.value);
});

const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        return sum + Number(item.price) * Number(item.quantity);
    }, 0);
});

const totalQuantity = computed(() => {
    return cart.value.reduce((sum, item) => sum + Number(item.quantity), 0);
});

const discountAmount = computed(() => {
    return (subtotal.value * clampDiscount(discountPercent.value)) / 100;
});

const payableTotal = computed(() => {
    return Math.max(subtotal.value - discountAmount.value, 0);
});

const changeAmount = computed(() => {
    if (paymentMethod.value !== "cash") {
        return 0;
    }

    const cash = Number(cashReceived.value || 0);

    return Math.max(cash - payableTotal.value, 0);
});

const canFinishSale = computed(() => {
    if (!cart.value.length || isSubmitting.value) {
        return false;
    }

    if (paymentMethod.value === "cash") {
        return Number(cashReceived.value || 0) >= payableTotal.value;
    }

    return true;
});

const getCategoryChildren = (category) => {
    return category?.subcategories ?? category?.children ?? [];
};

const getProductStock = (product) => {
    return product?.warehouse_stocks?.[0] ?? product?.warehouseStocks?.[0] ?? null;
};

const getProductName = (product) => {
    return product?.translations?.[0]?.name ?? product?.name ?? "-";
};

const getProductImage = (product) => {
    if (product?.image_url) {
        return product.image_url;
    }

    if (product?.image) {
        return `/storage/${product.image}`;
    }

    return null;
};

const getProductQuantity = (product) => {
    return Number(getProductStock(product)?.quantity ?? product?.quantity ?? 0);
};

const getProductReservedQuantity = (product) => {
    return Number(getProductStock(product)?.reserved_quantity ?? 0);
};

const getAvailableQuantity = (product) => {
    return Math.max(getProductQuantity(product), 0);
};

const getSalePrice = (product) => {
    return Number(product?.default_sale_price ?? product?.sale_price ?? 0);
};

const isProductOutOfStock = (product) => {
    return getAvailableQuantity(product) <= 0;
};

const isProductLowStock = (product) => {
    const availableQuantity = getAvailableQuantity(product);
    const minStockAlert = Number(product?.min_stock_alert ?? 0);

    return (
        availableQuantity > 0 &&
        minStockAlert > 0 &&
        availableQuantity <= minStockAlert
    );
};

const findCartItem = (productId) => {
    return cart.value.find(
        (item) => Number(item.productId) === Number(productId),
    );
};

const getCartItemTotal = (item) => {
    return Number(item.price) * Number(item.quantity);
};

const formatMoney = (value) => {
    return `${Number(value || 0).toLocaleString("hy-AM")} ֏`;
};

const setError = (message) => {
    errors.value = message;
    success.value = "";
};

const clearMessages = () => {
    errors.value = "";
    success.value = "";
};

const clampDiscount = (value) => {
    const numericValue = Number(value || 0);

    if (numericValue <= 0 || Number.isNaN(numericValue)) {
        return 0;
    }

    return Math.min(numericValue, 100);
};

const resetOrderState = ({ clearSuccess = true } = {}) => {
    selectedPersonId.value = "";
    step.value = "cart";
    discountPercent.value = 0;
    cashReceived.value = "";
    paymentMethod.value = "cash";
    errors.value = "";

    if (clearSuccess) {
        success.value = "";
    }
};

const clearCart = ({ clearSuccess = true } = {}) => {
    cart.value = [];
    resetOrderState({ clearSuccess });
};

const validateQuantity = (item, showLimitError = true) => {
    const availableQuantity = Math.max(Number(item.availableQuantity || 0), 0);
    let quantity = Number(item.quantity);

    if (!quantity || quantity < 1) {
        quantity = 1;
    }

    if (availableQuantity <= 0) {
        quantity = 1;
    }

    if (quantity > availableQuantity && availableQuantity > 0) {
        quantity = availableQuantity;

        if (showLimitError) {
            setError("Քանակը չի կարող գերազանցել պահեստում առկա քանակը");
        }
    }

    item.quantity = quantity;
};

const addToCart = (product) => {
    const availableQuantity = getAvailableQuantity(product);

    if (availableQuantity <= 0) {
        setError("Ապրանքը պահեստում առկա չէ");
        return;
    }

    errors.value = "";
    success.value = "";
    step.value = "cart";

    const existingItem = findCartItem(product.id);

    if (existingItem) {
        existingItem.availableQuantity = availableQuantity;

        if (existingItem.quantity >= availableQuantity) {
            setError("Պահեստում բավարար քանակ չկա");
            return;
        }

        existingItem.quantity += 1;
        return;
    }

    cart.value.push({
        productId: product.id,
        name: getProductName(product),
        image: getProductImage(product),
        price: getSalePrice(product),
        quantity: 1,
        availableQuantity,
        sku: product?.sku ?? "",
    });
};

const increaseQuantity = (item) => {
    if (Number(item.quantity) >= Number(item.availableQuantity)) {
        setError("Պահեստում բավարար քանակ չկա");
        return;
    }

    errors.value = "";
    item.quantity += 1;
};

const decreaseQuantity = (item) => {
    item.quantity = Math.max(Number(item.quantity) - 1, 1);
    errors.value = "";
};

const updateQuantity = (item) => {
    errors.value = "";
    validateQuantity(item, true);
};

const removeFromCart = (productId) => {
    cart.value = cart.value.filter(
        (item) => Number(item.productId) !== Number(productId),
    );

    if (!cart.value.length) {
        resetOrderState();
    }
};

const goToPayment = () => {
    clearMessages();

    if (!cart.value.length) {
        setError("Ավելացրեք առնվազն մեկ ապրանք");
        return;
    }

    step.value = "payment";
};

const setDiscount = (percent) => {
    discountPercent.value = clampDiscount(percent);
    errors.value = "";
};

const getFilterRoute = () => {
    if (typeof route === "function" && route().current?.("purchase.index")) {
        return route("purchase.index", { locale: currentLocale });
    }

    return route("products.index", { locale: currentLocale });
};

const search = () => {
    const params = {};

    if (name.value?.trim()) {
        const query = name.value.trim();

        params.name = query;

        if (typeof route === "function" && route().current?.("purchase.index")) {
            params.search = query;
        }
    }

    if (warehouseId.value) {
        params.warehouse_id = warehouseId.value;
    }

    if (categoryId.value) {
        params.category_id = categoryId.value;
    }

    if (subCategoryId.value) {
        params.sub_category_id = subCategoryId.value;
    }

    router.get(getFilterRoute(), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    name.value = "";
    categoryId.value = "";
    subCategoryId.value = "";
    warehouseId.value = "";

    router.get(getFilterRoute(), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const getFirstServerError = (serverErrors) => {
    for (const value of Object.values(serverErrors ?? {})) {
        if (Array.isArray(value) && value.length) {
            return value[0];
        }

        if (typeof value === "string" && value.trim()) {
            return value;
        }
    }

    return "";
};

const finishSale = () => {
    clearMessages();

    if (!cart.value.length) {
        setError("Ավելացրեք առնվազն մեկ ապրանք");
        return;
    }

    discountPercent.value = clampDiscount(discountPercent.value);

    if (
        paymentMethod.value === "cash" &&
        Number(cashReceived.value || 0) < payableTotal.value
    ) {
        setError("Ստացված կանխիկ գումարը պետք է բավարար լինի վճարման համար");
        return;
    }

    const soldItems = cart.value.map((item) => ({
        product_id: item.productId,
        quantity: Number(item.quantity),
        price: Number(item.price),
        total: getCartItemTotal(item),
    }));

    router.post(
        route("purchase.sell", { locale: currentLocale }),
        {
            person_id: selectedPersonId.value || null,
            payment_method: paymentMethod.value,
            discount_percent: clampDiscount(discountPercent.value),
            subtotal: subtotal.value,
            total: payableTotal.value,
            cash_received:
                paymentMethod.value === "cash"
                    ? Number(cashReceived.value || 0)
                    : 0,
            change_amount:
                paymentMethod.value === "cash" ? changeAmount.value : 0,
            items: soldItems,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSubmitting.value = true;
            },
            onSuccess: () => {
                clearCart({ clearSuccess: false });
                success.value = "Վաճառքը հաջողությամբ կատարվեց";

                router.reload({
                    only: ["products"],
                    preserveScroll: true,
                    preserveState: true,
                });
            },
            onError: (serverErrors) => {
                errors.value =
                    getFirstServerError(serverErrors) ||
                    "Վաճառքը չհաջողվեց կատարել";
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Ապրանքի վաճառք" />

    <AppLayout>
        <div class="purchase-page">
            <div class="card border-0 shadow-sm purchase-shell">
                <div class="card-body p-3 p-xl-4">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="filters-panel card border-0 shadow-sm mb-4">
                                <div class="card-body p-3 p-md-4">
                                    <div
                                        class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
                                    >
                                        <div>
                                            <h5 class="mb-1">Վաճառքի պատուհան</h5>
                                            <p class="text-muted mb-0">
                                                Ընտրեք ապրանքը, սահմանեք քանակը և ավարտեք վաճառքը։
                                            </p>
                                        </div>

                                        <div class="summary-chip-group">
                                            <span class="summary-chip">
                                                <i class="icon-base ti tabler-box"></i>
                                                {{ productsData.length }} ապրանք
                                            </span>
                                            <span class="summary-chip">
                                                <i class="icon-base ti tabler-shopping-cart"></i>
                                                {{ totalQuantity }} հատ զամբյուղում
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-xl-4 col-md-6">
                                            <label class="form-label filter-label">
                                                Որոնում
                                            </label>
                                            <div class="input-group input-group-merge">
                                                <span class="input-group-text bg-white">
                                                    <i class="icon-base ti tabler-search text-muted"></i>
                                                </span>
                                                <input
                                                    v-model="name"
                                                    type="text"
                                                    class="form-control"
                                                    placeholder="Ապրանքի անուն կամ կոդ"
                                                    @keyup.enter="search"
                                                />
                                            </div>
                                        </div>

                                        <div class="col-xl-3 col-md-6">
                                            <label class="form-label filter-label">
                                                Պահեստ
                                            </label>
                                            <select v-model="warehouseId" class="form-select">
                                                <option value="">Բոլոր պահեստները</option>
                                                <option
                                                    v-for="warehouse in localWarehouses"
                                                    :key="warehouse.id"
                                                    :value="warehouse.id"
                                                >
                                                    {{ warehouse.name ?? "-" }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-xl-2 col-md-6">
                                            <label class="form-label filter-label">
                                                Կատեգորիա
                                            </label>
                                            <select v-model="categoryId" class="form-select">
                                                <option value="">Բոլորը</option>
                                                <option
                                                    v-for="category in localCategories"
                                                    :key="category.id"
                                                    :value="category.id"
                                                >
                                                    {{
                                                        category.translations?.[0]?.name ??
                                                        category.name ??
                                                        "-"
                                                    }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-xl-3 col-md-6">
                                            <label class="form-label filter-label">
                                                Ենթակատեգորիա
                                            </label>
                                            <select
                                                v-model="subCategoryId"
                                                class="form-select"
                                                :disabled="!categoryId || !filteredSubCategories.length"
                                            >
                                                <option value="">Ընտրել ենթակատեգորիա</option>
                                                <option
                                                    v-for="subCategory in filteredSubCategories"
                                                    :key="subCategory.id"
                                                    :value="subCategory.id"
                                                >
                                                    {{
                                                        subCategory.translations?.[0]?.name ??
                                                        subCategory.name ??
                                                        "-"
                                                    }}
                                                </option>
                                            </select>
                                        </div>

                                        <div
                                            class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="btn btn-primary action-btn"
                                                @click="search"
                                            >
                                                <i class="icon-base ti tabler-search me-1"></i>
                                                Որոնել
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary action-btn"
                                                @click="resetFilters"
                                            >
                                                <i class="icon-base ti tabler-refresh me-1"></i>
                                                Մաքրել ֆիլտրերը
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div
                                    v-for="product in productsData"
                                    :key="product.id"
                                    class="col-xl-4 col-md-6"
                                >
                                    <div
                                        class="product-card card border-0 shadow-sm h-100"
                                        :class="{
                                            'product-card-disabled': isProductOutOfStock(product),
                                            'product-card-low': isProductLowStock(product),
                                        }"
                                    >
                                        <div class="product-card-media">
                                            <img
                                                v-if="getProductImage(product)"
                                                :src="getProductImage(product)"
                                                :alt="getProductName(product)"
                                            />

                                            <div
                                                v-else
                                                class="product-card-placeholder d-flex align-items-center justify-content-center"
                                            >
                                                <i class="icon-base ti tabler-package"></i>
                                            </div>

                                            <div class="product-card-badges">
                                                <span
                                                    class="badge rounded-pill"
                                                    :class="
                                                        isProductOutOfStock(product)
                                                            ? 'bg-label-danger'
                                                            : 'bg-label-success'
                                                    "
                                                >
                                                    {{
                                                        isProductOutOfStock(product)
                                                            ? "Առկա չէ"
                                                            : `${getAvailableQuantity(product)} հատ`
                                                    }}
                                                </span>

                                                <span
                                                    v-if="isProductLowStock(product)"
                                                    class="badge rounded-pill bg-label-warning"
                                                >
                                                    Քիչ քանակ
                                                </span>
                                            </div>
                                        </div>

                                        <div class="card-body d-flex flex-column">
                                            <div class="mb-2">
                                                <div class="product-title">
                                                    {{ getProductName(product) }}
                                                </div>
                                                <div class="product-sku">
                                                    {{ product.sku ?? "SKU չկա" }}
                                                </div>
                                            </div>

                                            <div class="product-price mb-3">
                                                {{ formatMoney(getSalePrice(product)) }}
                                            </div>

                                            <div class="product-meta-list mb-3">
                                                <div class="product-meta-row">
                                                    <span>Պահեստում</span>
                                                    <strong>{{ getAvailableQuantity(product) }}</strong>
                                                </div>
                                                <div class="product-meta-row">
                                                    <span>Ամրագրված</span>
                                                    <strong>{{ getProductReservedQuantity(product) }}</strong>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                class="btn btn-primary w-100 mt-auto"
                                                :disabled="isProductOutOfStock(product)"
                                                @click="addToCart(product)"
                                            >
                                                <i class="icon-base ti tabler-plus me-1"></i>
                                                {{
                                                    isProductOutOfStock(product)
                                                        ? "Ապրանքը սպառված է"
                                                        : "Ավելացնել զամբյուղ"
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="!productsData.length"
                                    class="col-12"
                                >
                                    <div class="empty-products card border-0 shadow-sm">
                                        <div class="card-body text-center py-5">
                                            <div class="empty-icon mb-3">
                                                <i class="icon-base ti tabler-search-off"></i>
                                            </div>
                                            <h6 class="mb-1">Ապրանք չի գտնվել</h6>
                                            <p class="text-muted mb-0">
                                                Փորձեք փոխել որոնման կամ ֆիլտրերի պայմանները։
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4" v-if="paginationLinks.length">
                                <Pagination :links="paginationLinks" />
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="cart-sidebar card border-0 shadow-sm">
                                <div class="card-body p-3 p-md-4">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                        <div>
                                            <h5 class="mb-1">Զամբյուղ</h5>
                                            <p class="text-muted mb-0">
                                                Կառավարեք պատվերը և ավարտեք վաճառքը։
                                            </p>
                                        </div>

                                        <button
                                            v-if="cart.length"
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="clearCart()"
                                        >
                                            <i class="icon-base ti tabler-trash me-1"></i>
                                            Մաքրել
                                        </button>
                                    </div>

                                    <div class="person-card mb-3">
                                        <label class="form-label fw-semibold mb-2">
                                            Հաճախորդ (ոչ պարտադիր)
                                        </label>
                                        <select
                                            v-model="selectedPersonId"
                                            class="form-select"
                                        >
                                            <option value="">Ընտրեք հաճախորդ (ոչ պարտադիր)</option>
                                            <option
                                                v-for="person in localPeople"
                                                :key="person.id"
                                                :value="person.id"
                                            >
                                                {{ person.name }}{{ person.surname ? ` ${person.surname}` : "" }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="cart-steps mb-3">
                                        <button
                                            type="button"
                                            class="step-pill"
                                            :class="{ active: step === 'cart' }"
                                            @click="step = 'cart'"
                                        >
                                            <i class="icon-base ti tabler-shopping-cart me-1"></i>
                                            Զամբյուղ
                                        </button>
                                        <button
                                            type="button"
                                            class="step-pill"
                                            :class="{ active: step === 'payment' }"
                                            :disabled="!cart.length"
                                            @click="goToPayment"
                                        >
                                            <i class="icon-base ti tabler-credit-card me-1"></i>
                                            Վճարում
                                        </button>
                                    </div>

                                    <div v-if="!cart.length" class="empty-cart">
                                        <div class="empty-icon mb-3">
                                            <i class="icon-base ti tabler-shopping-cart-off"></i>
                                        </div>
                                        <h6 class="mb-1">Զամբյուղը դատարկ է</h6>
                                        <p class="text-muted mb-0">
                                            Ընտրեք ապրանքներ ձախ հատվածից։
                                        </p>
                                    </div>

                                    <template v-else>
                                        <div class="cart-items">
                                            <div
                                                v-for="item in cart"
                                                :key="item.productId"
                                                class="cart-item"
                                            >
                                                <div class="cart-item-media">
                                                    <img
                                                        v-if="item.image"
                                                        :src="item.image"
                                                        :alt="item.name"
                                                    />
                                                    <div
                                                        v-else
                                                        class="cart-item-placeholder d-flex align-items-center justify-content-center"
                                                    >
                                                        <i class="icon-base ti tabler-package"></i>
                                                    </div>
                                                </div>

                                                <div class="cart-item-content">
                                                    <div class="d-flex justify-content-between gap-2">
                                                        <div>
                                                            <div class="cart-item-title">
                                                                {{ item.name }}
                                                            </div>
                                                            <div class="cart-item-subtitle">
                                                                {{ item.sku || "Կոդ չկա" }}
                                                            </div>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-icon btn-outline-danger"
                                                            @click="removeFromCart(item.productId)"
                                                        >
                                                            <i class="icon-base ti tabler-trash"></i>
                                                        </button>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-end gap-2 mt-2">
                                                        <div>
                                                            <div class="cart-item-price">
                                                                {{ formatMoney(item.price) }}
                                                            </div>
                                                            <small class="text-muted">
                                                                Առկա՝ {{ item.availableQuantity }} հատ
                                                            </small>
                                                        </div>

                                                        <div class="qty-box">
                                                            <button
                                                                type="button"
                                                                class="qty-btn"
                                                                @click="decreaseQuantity(item)"
                                                            >
                                                                <i class="icon-base ti tabler-minus"></i>
                                                            </button>

                                                            <input
                                                                v-model.number="item.quantity"
                                                                type="number"
                                                                min="1"
                                                                :max="item.availableQuantity"
                                                                class="qty-input"
                                                                @change="updateQuantity(item)"
                                                                @blur="updateQuantity(item)"
                                                            />

                                                            <button
                                                                type="button"
                                                                class="qty-btn"
                                                                @click="increaseQuantity(item)"
                                                            >
                                                                <i class="icon-base ti tabler-plus"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="cart-item-total mt-2">
                                                        Ընդամենը՝ {{ formatMoney(getCartItemTotal(item)) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="order-summary mt-3">
                                            <div class="summary-row">
                                                <span>Ապրանքների քանակ</span>
                                                <strong>{{ totalQuantity }}</strong>
                                            </div>
                                            <div class="summary-row">
                                                <span>Միջանկյալ գումար</span>
                                                <strong>{{ formatMoney(subtotal) }}</strong>
                                            </div>
                                            <div class="summary-row">
                                                <span>Զեղչ</span>
                                                <strong>{{ formatMoney(discountAmount) }}</strong>
                                            </div>
                                            <div class="summary-row total-row">
                                                <span>Վճարման ենթակա</span>
                                                <strong>{{ formatMoney(payableTotal) }}</strong>
                                            </div>
                                        </div>

                                        <div v-if="step === 'cart'" class="mt-3">
                                            <button
                                                type="button"
                                                class="btn btn-primary w-100 py-2"
                                                @click="goToPayment"
                                            >
                                                <i class="icon-base ti tabler-arrow-right me-1"></i>
                                                Անցնել վճարման
                                            </button>
                                        </div>

                                        <div v-else class="payment-panel mt-3">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    Զեղչի տոկոս
                                                </label>
                                                <div class="discount-row">
                                                    <input
                                                        v-model.number="discountPercent"
                                                        type="number"
                                                        class="form-control"
                                                        min="0"
                                                        max="100"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        :class="{ active: discountPercent === 5 }"
                                                        @click="setDiscount(5)"
                                                    >
                                                        5%
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        :class="{ active: discountPercent === 10 }"
                                                        @click="setDiscount(10)"
                                                    >
                                                        10%
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    Վճարման եղանակ
                                                </label>
                                                <div class="payment-methods">
                                                    <button
                                                        type="button"
                                                        class="payment-method-btn"
                                                        :class="{ active: paymentMethod === 'cash' }"
                                                        @click="paymentMethod = 'cash'"
                                                    >
                                                        <i class="icon-base ti tabler-cash me-1"></i>
                                                        Կանխիկ
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="payment-method-btn"
                                                        :class="{ active: paymentMethod === 'card' }"
                                                        @click="paymentMethod = 'card'"
                                                    >
                                                        <i class="icon-base ti tabler-credit-card me-1"></i>
                                                        Քարտ
                                                    </button>
                                                </div>
                                            </div>

                                            <div v-if="paymentMethod === 'cash'" class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    Ստացված գումար
                                                </label>
                                                <input
                                                    v-model.number="cashReceived"
                                                    type="number"
                                                    class="form-control"
                                                    min="0"
                                                    placeholder="Մուտքագրեք ստացված գումարը"
                                                />
                                                <div class="change-box mt-2">
                                                    <span>Մանր</span>
                                                    <strong>{{ formatMoney(changeAmount) }}</strong>
                                                </div>
                                            </div>

                                            <div v-else class="info-box mb-3">
                                                <i class="icon-base ti tabler-info-circle"></i>
                                                <span>Քարտային վճարման դեպքում կանխիկ գումար մուտքագրել պետք չէ։</span>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-primary py-2"
                                                    :disabled="!canFinishSale"
                                                    @click="finishSale"
                                                >
                                                    <i class="icon-base ti tabler-check me-1"></i>
                                                    {{ isSubmitting ? "Պահպանվում է..." : "Ավարտել վաճառքը" }}
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary"
                                                    @click="step = 'cart'"
                                                >
                                                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                                                    Վերադառնալ զամբյուղ
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <div v-if="errors" class="alert alert-danger mt-3 mb-0">
                                        {{ errors }}
                                    </div>

                                    <div v-if="success" class="alert alert-success mt-3 mb-0">
                                        {{ success }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.purchase-page {
    padding-bottom: 1.5rem;
}

.purchase-shell {
    background:
        radial-gradient(circle at top right, rgba(115, 103, 240, 0.08), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, #f6f8fc 100%);
}

.filters-panel,
.cart-sidebar,
.empty-products {
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(6px);
}

.filter-label {
    color: #5d6679;
    font-size: 0.82rem;
    font-weight: 600;
}

.summary-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.summary-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.9rem;
    border-radius: 999px;
    background: #eef4ff;
    color: #49617f;
    font-size: 0.84rem;
    font-weight: 600;
}

.action-btn {
    min-width: 160px;
}

.product-card {
    border-radius: 1rem;
    overflow: hidden;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 1rem 2rem rgba(67, 89, 113, 0.12) !important;
}

.product-card-disabled {
    opacity: 0.72;
}

.product-card-low {
    box-shadow: 0 0 0 1px rgba(255, 159, 67, 0.25) inset;
}

.product-card-media {
    position: relative;
    height: 220px;
    background: linear-gradient(135deg, #edf3ff 0%, #f8fbff 100%);
}

.product-card-media img,
.cart-item-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-card-placeholder,
.cart-item-placeholder,
.empty-icon {
    color: #adb5c3;
}

.product-card-placeholder {
    height: 100%;
    font-size: 2.75rem;
}

.product-card-badges {
    position: absolute;
    inset: 1rem 1rem auto;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.product-title {
    font-size: 1rem;
    font-weight: 700;
    color: #344054;
    line-height: 1.4;
    min-height: 2.8rem;
}

.product-sku,
.cart-item-subtitle {
    color: #8a94a6;
    font-size: 0.82rem;
}

.product-price,
.cart-item-price {
    font-size: 1rem;
    font-weight: 700;
    color: #0d6efd;
}

.product-meta-list {
    display: grid;
    gap: 0.6rem;
}

.product-meta-row,
.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    color: #5d6679;
    font-size: 0.92rem;
}

.empty-products,
.empty-cart {
    border-radius: 1rem;
}

.empty-cart {
    text-align: center;
    padding: 2rem 1rem;
    background: linear-gradient(180deg, #f8fbff 0%, #f2f5fb 100%);
}

.empty-icon {
    font-size: 2.25rem;
}

.cart-sidebar {
    position: sticky;
    top: 88px;
    border-radius: 1rem;
}

.person-card {
    padding: 1rem;
    border-radius: 0.9rem;
    background: #f7f9fc;
}

.cart-steps {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.step-pill,
.payment-method-btn {
    border: 1px solid #d9deea;
    background: #fff;
    color: #5d6679;
    border-radius: 0.9rem;
    min-height: 44px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.step-pill.active,
.payment-method-btn.active {
    background: rgba(13, 110, 253, 0.08);
    border-color: rgba(13, 110, 253, 0.28);
    color: #0d6efd;
}

.step-pill:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.cart-items {
    display: grid;
    gap: 0.9rem;
    max-height: 420px;
    overflow-y: auto;
    padding-right: 0.15rem;
}

.cart-item {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 0.9rem;
    padding: 0.9rem;
    border-radius: 1rem;
    background: #f9fafc;
}

.cart-item-media {
    width: 72px;
    height: 72px;
    border-radius: 0.9rem;
    overflow: hidden;
    background: linear-gradient(180deg, #eef3fb 0%, #f9fbff 100%);
}

.cart-item-title {
    color: #344054;
    font-weight: 700;
    line-height: 1.4;
}

.qty-box {
    display: inline-flex;
    align-items: center;
    border: 1px solid #d9deea;
    border-radius: 0.9rem;
    overflow: hidden;
    background: #fff;
}

.qty-btn {
    width: 38px;
    height: 38px;
    border: 0;
    background: #fff;
    color: #5d6679;
}

.qty-input {
    width: 64px;
    height: 38px;
    border: 0;
    text-align: center;
    font-weight: 600;
    color: #344054;
}

.qty-input:focus,
.qty-btn:focus {
    outline: none;
}

.cart-item-total,
.total-row {
    font-weight: 700;
    color: #344054;
}

.order-summary,
.change-box,
.info-box {
    padding: 1rem;
    border-radius: 1rem;
    background: #f7f9fc;
}

.order-summary {
    display: grid;
    gap: 0.75rem;
}

.total-row {
    padding-top: 0.75rem;
    border-top: 1px dashed #d9deea;
    font-size: 1rem;
}

.discount-row,
.payment-methods {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 0.75rem;
}

.payment-methods {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.change-box,
.info-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    color: #5d6679;
}

.info-box {
    justify-content: flex-start;
}

@media (max-width: 991.98px) {
    .cart-sidebar {
        position: static;
    }
}

@media (max-width: 767.98px) {
    .discount-row,
    .payment-methods,
    .cart-steps {
        grid-template-columns: 1fr;
    }

    .cart-item {
        grid-template-columns: 1fr;
    }

    .cart-item-media {
        width: 100%;
        height: 180px;
    }

    .action-btn {
        width: 100%;
    }
}

@media print {
    .filters-panel,
    .btn,
    .cart-steps,
    .person-card,
    .payment-panel,
    .alert {
        display: none !important;
    }

    .col-lg-8 {
        display: none !important;
    }

    .col-lg-4 {
        width: 100% !important;
    }

    .cart-sidebar,
    .purchase-shell {
        box-shadow: none !important;
        background: #fff !important;
    }
}
</style>
