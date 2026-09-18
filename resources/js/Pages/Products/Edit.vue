<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, watch, ref } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    product: Object,
    categories: {
        type: [Array, Object],
        default: () => [],
    },
    measurementUnits: {
        type: [Array, Object],
        default: () => [],
    },
    warehouses: {
        type: [Array, Object],
        default: () => [],
    },
    langs: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const availableLangs = computed(() =>
    Array.isArray(props.langs) && props.langs.length ? props.langs : ["hy"],
);

const localCategories = computed(() => {
    return props.categories?.data ?? props.categories ?? [];
});

const localMeasurementUnits = computed(() => {
    return props.measurementUnits?.data ?? props.measurementUnits ?? [];
});

const localWarehouses = computed(() => {
    return props.warehouses?.data ?? props.warehouses ?? [];
});

const getTranslationValue = (locale, key) => {
    return (
        props.product.translations?.find((item) => item.locale === locale)?.[
            key
        ] ?? ""
    );
};

const firstWarehouseStock = computed(() => {
    return props.product.warehouse_stocks?.[0] ?? null;
});

const form = useForm({
    category_id: props.product.category_id ?? "",
    sub_category_id: props.product.sub_category_id ?? "",
    measurement_unit_id: props.product.measurement_unit_id ?? "",

    warehouse_id: firstWarehouseStock.value?.warehouse_id ?? "",

    name: Object.fromEntries(
        availableLangs.value.map((code) => [
            code,
            getTranslationValue(code, "name"),
        ]),
    ),

    description: Object.fromEntries(
        availableLangs.value.map((code) => [
            code,
            getTranslationValue(code, "description"),
        ]),
    ),

    sku: props.product.sku ?? "",
    barcode: props.product.barcode ?? "",
    default_purchase_price: props.product.default_purchase_price ?? 0,
    default_sale_price: props.product.default_sale_price ?? 0,
    min_stock_alert: props.product.min_stock_alert ?? 0,

    image: null,
    status: Boolean(props.product.status),

    quantity: firstWarehouseStock.value?.quantity ?? 0,
    reserved_quantity: firstWarehouseStock.value?.reserved_quantity ?? 0,

    _method: "PUT",
});

const selectedCategory = computed(() => {
    return localCategories.value.find(
        (category) => Number(category.id) === Number(form.category_id),
    );
});

const filteredSubCategories = computed(() => {
    return selectedCategory.value?.subcategories ?? [];
});

watch(
    () => form.category_id,
    () => {
        form.sub_category_id = "";
    },
);

const imagePreview = ref(
    props.product.image ? `/storage/${props.product.image}` : null,
);

const handleImageUpload = (event) => {
    const file = event.target.files[0];

    if (!file) {
        return;
    }

    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
};

const clearFrontendErrors = () => {
    form.clearErrors(
        "default_purchase_price",
        "default_sale_price",
        "min_stock_alert",
        "quantity",
        "image",
    );
};

const validateForm = () => {
    clearFrontendErrors();

    const purchasePrice = Number(form.default_purchase_price);
    const salePrice = Number(form.default_sale_price);
    const minStockAlert = Number(form.min_stock_alert);
    const quantity = Number(form.quantity);

    let isValid = true;

    if (purchasePrice >= salePrice) {
        form.setError(
            "default_purchase_price",
            "Purchase Price must be smaller than Sale Price.",
        );
        isValid = false;
    }

    if (minStockAlert >= quantity) {
        form.setError(
            "min_stock_alert",
            "Min Stock Alert must be smaller than Quantity.",
        );
        isValid = false;
    }

    if (form.image && !form.image.type?.startsWith("image/")) {
        form.setError("image", "The image field must be an image.");
        isValid = false;
    }

    return isValid;
};


const submit = () => {
    if (!validateForm()) return;
    form.post(
        route("products.update", {
            locale: currentLocale,
            id: props.product.id,
        }),
        {
            forceFormData: true,
        },
    );
};
</script>

<template>
    <Head :title="t('inventory.edit_product')" />
    <AppLayout>
        <div class="card">
            <div class="card-header">
                <h5>{{ t('inventory.edit_product') }}</h5>
            </div>

            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('people.category') }}</label>

                            <select
                                v-model="form.category_id"
                                class="form-control"
                            >
                                <option value="">{{ t('inventory.select_the_category') }}</option>

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

                            <div class="text-danger">
                                {{ form.errors.category_id }}
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('inventory.subcategory') }}</label>

                            <select
                                v-model="form.sub_category_id"
                                class="form-control"
                                :disabled="
                                    !form.category_id ||
                                    filteredSubCategories.length === 0
                                "
                            >
                                <option value="">
                                    {{
                                        !form.category_id
                                            ? t('inventory.select_a_category_first')
                                            : t('inventory.select_the_subcategory')
                                    }}
                                </option>

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

                            <div class="text-danger">
                                {{ form.errors.sub_category_id }}
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('inventory.unit_of_measure') }}</label>

                            <select
                                v-model="form.measurement_unit_id"
                                class="form-control"
                            >
                                <option value="">
                                    {{ t('inventory.select_unit_of_measure') }}
                                </option>

                                <option
                                    v-for="unit in localMeasurementUnits"
                                    :key="unit.id"
                                    :value="unit.id"
                                >
                                    {{ unit.name ?? "-" }}
                                </option>
                            </select>

                            <div class="text-danger">
                                {{ form.errors.measurement_unit_id }}
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('inventory.warehouse') }}</label>

                            <select
                                v-model="form.warehouse_id"
                                class="form-control"
                            >
                                <option value="">{{ t('inventory.select_warehouse') }}</option>

                                <option
                                    v-for="warehouse in localWarehouses"
                                    :key="warehouse.id"
                                    :value="warehouse.id"
                                >
                                    {{ warehouse.name ?? "-" }}
                                </option>
                            </select>

                            <div class="text-danger">
                                {{ form.errors.warehouse_id }}
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ t('inventory.quantity') }}</label>
                            <input
                                v-model="form.quantity"
                                type="number"
                                step="1"
                                min="0"
                                class="form-control"
                                @wheel.prevent
                            />

                            <div class="text-danger">
                                {{ form.errors.quantity }}
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ t('inventory.reserved_quantity') }}</label>
                            <input
                                v-model="form.reserved_quantity"
                                type="number"
                                step="1"
                                min="0"
                                class="form-control"
                                @wheel.prevent
                            />

                            <div class="text-danger">
                                {{ form.errors.reserved_quantity }}
                            </div>
                        </div>

                        <template
                            v-for="code in availableLangs"
                            :key="code"
                        >
                            <div class="col-12 mt-2">
                                <h6 class="fw-semibold">
                                    {{ code.toUpperCase() }}
                                </h6>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    {{ t('inventory.name') }}
                                    ({{ code.toUpperCase() }})
                                </label>

                                <input
                                    v-model="form.name[code]"
                                    type="text"
                                    class="form-control"
                                    :placeholder="t('inventory.name')"
                                />

                                <div class="text-danger">
                                    {{ form.errors[`name.${code}`] }}
                                </div>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">
                                    {{ t('inventory.description') }}
                                    ({{ code.toUpperCase() }})
                                </label>

                                <textarea
                                    v-model="form.description[code]"
                                    class="form-control"
                                    rows="3"
                                ></textarea>

                                <div class="text-danger">
                                    {{ form.errors[`description.${code}`] }}
                                </div>
                            </div>
                        </template>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU</label>
                            <input
                                v-model="form.sku"
                                type="text"
                                class="form-control"
                            />
                            <div class="text-danger">
                                {{ form.errors.sku }}
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('logs.barcode') }}</label>
                            <input
                                v-model="form.barcode"
                                type="text"
                                class="form-control"
                            />
                            <div class="text-danger">
                                {{ form.errors.barcode }}
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ t('inventory.purchase_price') }}</label>
                            <input
                                v-model="form.default_purchase_price"
                                type="number"
                                step="0.1"
                                class="form-control"
                                @wheel.prevent
                            />
                            <div class="text-danger">
                                {{ form.errors.default_purchase_price }}
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ t('inventory.sale_price') }}</label>
                            <input
                                v-model="form.default_sale_price"
                                type="number"
                                step="0.1"
                                class="form-control"
                                @wheel.prevent
                            />
                            <div class="text-danger">
                                {{ form.errors.default_sale_price }}
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ t('inventory.low_stock_warning') }}</label>
                            <input
                                v-model="form.min_stock_alert"
                                type="number"
                                step="1"
                                class="form-control"
                                @wheel.prevent
                            />
                            <div class="text-danger">
                                {{ form.errors.min_stock_alert }}
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('inventory.product_image') }}</label>

                            <input
                                type="file"
                                class="form-control"
                                accept="image/*"
                                @change="handleImageUpload"
                            />

                            <div class="text-danger">
                                {{ form.errors.image }}
                            </div>

                            <div v-if="imagePreview" class="mt-3">
                                <img
                                    :src="imagePreview"
                                    :alt="t('ui.preview')"
                                    class="img-fluid rounded border"
                                    style="max-height: 200px"
                                />
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ t('status.status') }}</label>
                            <select v-model="form.status" class="form-control">
                                <option :value="true">{{ t('inventory.next') }}</option>
                                <option :value="false">{{ t('inventory.inactive') }}</option>
                            </select>

                            <div class="text-danger">
                                {{ form.errors.status }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">

                    <button
                        class="btn btn-primary"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ t('people.update') }}
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
