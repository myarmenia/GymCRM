<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";
import DeleteButton from "@/Components/DeleteButton.vue";
import { useAuth } from "@/composables/useAuth";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    products: Object,
    filters: Object,
    categories: {
        type: [Array, Object],
        default: () => [],
    },
    warehouses: {
        type: [Array, Object],
        default: () => [],
    },
});

const errorModalMessage = ref("");

const showDeleteError = (message) => {
    errorModalMessage.value = message;

    const modal = new bootstrap.Modal(
        document.getElementById("deleteErrorModal"),
    );

    modal.show();
};

const page = usePage();
const currentLocale = page.props.locale ?? "en";
const { hasAnyRole } = useAuth();
const canManageInventory = computed(() =>
    hasAnyRole(["owner", "admin", "super_admin", "sales_manager", "manager"]),
);

const productsData = ref([...(props.products?.data ?? [])]);
const selectedProductIds = ref([]);

const getProductQuantity = (product) => {
    return Number(product.warehouse_stocks?.[0]?.quantity ?? 0);
};

const getProductReservedQuantity = (product) => {
    return Number(product.warehouse_stocks?.[0]?.reserved_quantity ?? 0);
};

const getAvailableQuantity = (product) => {
    return getProductQuantity(product);
};

const isProductOutOfStock = (product) => {
    return getAvailableQuantity(product) <= 0;
};

const isProductLowStock = (product) => {
    const availableQuantity = getAvailableQuantity(product);
    const minStockAlert = Number(product.min_stock_alert ?? 0);

    return (
        availableQuantity > 0 &&
        minStockAlert > 0 &&
        availableQuantity <= minStockAlert
    );
};

const selectableProducts = computed(() => {
    return productsData.value.filter(
        (product) => !isProductOutOfStock(product),
    );
});

const hasSelectedProducts = computed(() => {
    return selectedProductIds.value.length > 0;
});

const allSelectableSelected = computed(() => {
    return (
        selectableProducts.value.length > 0 &&
        selectedProductIds.value.length === selectableProducts.value.length
    );
});

const toggleAllProducts = (event) => {
    if (event.target.checked) {
        selectedProductIds.value = selectableProducts.value.map(
            (product) => product.id,
        );
    } else {
        selectedProductIds.value = [];
    }
};

const toggleProduct = (product) => {
    if (isProductOutOfStock(product)) return;

    const exists = selectedProductIds.value.includes(product.id);

    if (exists) {
        selectedProductIds.value = selectedProductIds.value.filter(
            (id) => id !== product.id,
        );
    } else {
        selectedProductIds.value.push(product.id);
    }
};

const goToProductConsumption = () => {
    if (!selectedProductIds.value.length) return;

    router.get(
        route("product-consumptions.create", {
            locale: currentLocale,
        }),
        {
            product_ids: selectedProductIds.value,
        },
    );
};

const removeProduct = (id) => {
    productsData.value = productsData.value.filter(
        (product) => product.id !== id,
    );

    selectedProductIds.value = selectedProductIds.value.filter(
        (productId) => productId !== id,
    );
};
watch(
    () => props.products?.data,
    (newProducts) => {
        productsData.value = [...(newProducts ?? [])];
        selectedProductIds.value = [];
    },
    { deep: true },
);
const paginationLinks = computed(() => props.products?.links ?? []);

const localCategories = computed(() => {
    return props.categories?.data ?? props.categories ?? [];
});

const name = ref(props.filters?.name ?? "");
const categoryId = ref(props.filters?.category_id ?? "");
const subCategoryId = ref(props.filters?.sub_category_id ?? "");
const warehouseId = ref(props.filters?.warehouse_id ?? "");
const localWarehouses = computed(() => {
    return props.warehouses?.data ?? props.warehouses ?? [];
});

const selectedCategory = computed(() => {
    return localCategories.value.find(
        (category) => Number(category.id) === Number(categoryId.value),
    );
});

const filteredSubCategories = computed(() => {
    return selectedCategory.value?.subcategories ?? [];
});

watch(categoryId, () => {
    subCategoryId.value = "";
});

const search = () => {
    const params = {};

    if (name.value) {
        params.name = name.value;
    }

    if (categoryId.value) {
        params.category_id = categoryId.value;
    }

    if (subCategoryId.value) {
        params.sub_category_id = subCategoryId.value;
    }

    if (warehouseId.value) {
        params.warehouse_id = warehouseId.value;
    }

    router.get(route("products.index", { locale: currentLocale }), params, {
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

    router.get(
        route("products.index", { locale: currentLocale }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

console.log(props.products.data);
</script>

<template>
    <Head :title="t('inventory.product_list')" />

    <AppLayout>
        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">{{ t('sidebar.products') }}</h5>

                <div class="d-flex gap-2">
                    <button
                        v-if="canManageInventory"
                        type="button"
                        class="btn product-consumption-btn"
                        :class="
                            hasSelectedProducts
                                ? 'btn-success'
                                : 'btn-secondary'
                        "
                        :disabled="!hasSelectedProducts"
                        @click="goToProductConsumption"
                    >
                        {{ t('inventory.product_consumption') }}
                    </button>

                    <Link
                        v-if="canManageInventory"
                        class="btn btn-primary add-product-btn"
                        :href="
                            route('products.create', { locale: currentLocale })
                        "
                    >
                        <i class="icon-base ti tabler-plus icon-sm"></i>
                        {{ t('inventory.add_product') }}
                    </Link>
                </div>
            </div>

            <div class="card-body">
<div class="row mb-4">
    <div class="col-md-2 mb-2">
        <input
            v-model="name"
            type="text"
            class="form-control"
            :placeholder="t('inventory.search_by_name')"
            @keyup.enter="search"
        />
    </div>

    <div class="col-md-2 mb-2">
        <select v-model="warehouseId" class="form-control">
            <option value="">{{ t('inventory.all_warehouses') }}</option>

            <option
                v-for="warehouse in localWarehouses"
                :key="warehouse.id"
                :value="warehouse.id"
            >
                {{ warehouse.name ?? "-" }}
            </option>
        </select>
    </div>

    <div class="col-md-2 mb-2">
        <select v-model="categoryId" class="form-control">
            <option value="">{{ t('inventory.all_categories') }}</option>

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

    <div class="col-md-2 mb-2">
        <select
            v-model="subCategoryId"
            class="form-control"
            :disabled="
                !categoryId ||
                filteredSubCategories.length === 0
            "
        >
            <option value="">{{ t('inventory.all_subcategories') }}</option>

            <option
                v-for="sub in filteredSubCategories"
                :key="sub.id"
                :value="sub.id"
            >
                {{
                    sub.translations?.[0]?.name ??
                    sub.name ??
                    "-"
                }}
            </option>
        </select>
    </div>

    <div class="col-md-4 mb-2 d-flex gap-2">
        <button class="btn btn-secondary w-100" @click="search">
            {{ t('inventory.search') }}
        </button>

        <button
            class="btn btn-outline-secondary w-100"
            @click="resetFilters"
        >
            <i class="icon-base ti tabler-refresh"></i>
        </button>
    </div>
</div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :checked="allSelectableSelected"
                                        :disabled="!selectableProducts.length"
                                        @change="toggleAllProducts"
                                    />
                                </th>
                                <th>ID</th>
                                <th>{{ t('auth.name') }}</th>
                                <!-- <th>Measurement</th> -->
                                <!-- <th>SKU</th>
                                <th>Barcode</th> -->
                                <th>{{ t('inventory.quantity') }}</th>
                                <th>{{ t('inventory.reserved_quantity') }}</th>
                                <th>{{ t('inventory.low_stock_warning') }}</th>
                                <th>{{ t('inventory.purchase_price') }}</th>
                                <th>{{ t('inventory.sale_price') }}</th>
                                <th width="140">{{ t('people.action') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="product in productsData"
                                :key="product.id"
                                :class="{
                                    'low-stock-row': isProductLowStock(product),
                                    'out-of-stock-row':
                                        isProductOutOfStock(product),
                                }"
                            >
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :value="product.id"
                                        :checked="
                                            selectedProductIds.includes(
                                                product.id,
                                            )
                                        "
                                        :disabled="isProductOutOfStock(product)"
                                        @change="toggleProduct(product)"
                                    />
                                </td>

                                <td>{{ product.id }}</td>

                                <td>
                                    {{ product.translations?.[0]?.name ?? "-" }}
                                </td>

                                <!-- <td>
                                    {{ product.measurement_unit?.name ?? "-" }}
                                </td> -->

                                <!-- <td>{{ product.sku ?? "-" }}</td>
                                <td>{{ product.barcode ?? "-" }}</td> -->

                                <td>
                                    {{ getAvailableQuantity(product) }}
                                </td>

                                <td>
                                    {{ getProductReservedQuantity(product) }}
                                </td>

                                <td>
                                    {{ product.min_stock_alert ?? 0 }}
                                </td>

                                <td>
                                    {{ product.default_purchase_price ?? 0 }}
                                </td>

                                <td>
                                    {{ product.default_sale_price ?? 0 }}
                                </td>

                                <td class="text-end">
                                    <div class="dropdown">
                                        <button
                                            class="btn btn-sm btn-icon"
                                            data-bs-toggle="dropdown"
                                            @click.stop
                                        >
                                            <i
                                                class="icon-base ti tabler-dots-vertical"
                                            ></i>
                                        </button>

                                        <div class="dropdown-menu">
                                            <Link
                                                v-if="canManageInventory"
                                                class="product-action-item"
                                                :href="
                                                    route('products.edit', {
                                                        locale: currentLocale,
                                                        id: product.id,
                                                    })
                                                "
                                            >
                                                <i
                                                    class="icon-base ti tabler-pencil me-1"
                                                ></i>
                                                <span class="edit-span"
                                                    >{{ t('action.edit') }}</span
                                                >
                                            </Link>

                                            <div v-if="canManageInventory" @click.stop>
                                                <DeleteButton
                                                    prefix="tables"
                                                    model="inventory-product"
                                                    :model-id="product.id"
                                                    @deleted="removeProduct"
                                                    @delete-error="
                                                        showDeleteError
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!productsData.length">
                                <td colspan="11" class="text-center">
                                    {{ t('inventory.no_product') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="paginationLinks" />
            </div>
        </div>

        <div
            class="modal fade"
            id="deleteErrorModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">{{ t('inventory.could_not_delete') }}</h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        {{ errorModalMessage }}
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            {{ t('confirm.close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style src="/resources/css/product/index.css"></style>
