<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, watch, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
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

console.log("localCategories", localCategories);

const localMeasurementUnits = computed(() => {
    return props.measurementUnits?.data ?? props.measurementUnits ?? [];
});

const localWarehouses = computed(() => {
    return props.warehouses?.data ?? props.warehouses ?? [];
});

const form = useForm({
    category_id: "",
    sub_category_id: "",
    measurement_unit_id: "",
    warehouse_id: "",

    name: Object.fromEntries(
        availableLangs.value.map((code) => [code, ""]),
    ),

    description: Object.fromEntries(
        availableLangs.value.map((code) => [code, ""]),
    ),
    sku: "",
    barcode: "",
    default_purchase_price: 0,
    default_sale_price: 0,
    min_stock_alert: 0,
    image: "",
    status: true,

    quantity: 0,
    reserved_quantity: 0,
    //average_cost: 0,
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

const imagePreview = ref(null);

const handleImageUpload = (event) => {
    const file = event.target.files[0];

    if (!file) {
        return;
    }

    form.image = file;

    imagePreview.value = URL.createObjectURL(file);
};

const submit = () => {
    form.post(route("products.store", { locale: currentLocale }));
};
</script>

<template>
    <Head :title="t('inventory.create_product')" />

    <AppLayout>
        <div class="card">
            <div class="card-header">
                <h5>{{ t('inventory.create_product') }}</h5>
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
                                            ? t('inventory.select_a_category_first_2')
                                            : t('inventory.select_subcategory_2')
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
                                <option value="">{{ t('inventory.select_unit_of_measure') }}</option>

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

                        <!-- <div class="col-md-4 mb-3">
                            <label class="form-label">{{ t('logs.average_cost') }}</label>
                            <input
                                v-model="form.average_cost"
                                type="number"
                                step="0.1"
                                min="0"
                                class="form-control"
                                @wheel.prevent
                            />

                            <div class="text-danger">
                                {{ form.errors.average_cost }}
                            </div>
                        </div> -->

                        <!-- Armenian -->

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
                            <div class="text-danger">{{ form.errors.sku }}</div>
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
                            <label class="form-label"
                                >{{ t('inventory.low_stock_warning') }}</label
                            >
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
                            <label class="form-label"> {{ t('inventory.product_image') }} </label>

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
                                <option :value="true">{{ t('status.active') }}</option>
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
                            {{ t('people.create') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
