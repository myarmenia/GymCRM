<script setup>
import { computed, watch, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

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
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

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

    name: {
        hy: "",
        // ru: "",
        // en: "",
    },

    description: {
        hy: "",
        // ru: "",
        // en: "",
    },
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
    <Head title="Ապրանք ստեղծել" />

    <AppLayout>
        <div class="card">
            <div class="card-header">
                <h5>Ապրանք ստեղծել</h5>
            </div>

            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Կատեգորիա</label>

                            <select
                                v-model="form.category_id"
                                class="form-control"
                            >
                                <option value="">Ընտրել կատեգորիան</option>

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
                            <label class="form-label">Ենթակատեգորիա</label>

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
                                            ? "ընտրել նախ կատեգորիան"
                                            : "ընտրել ենթակատեգորիան"
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
                            <label class="form-label">Չափման միավոր</label>

                            <select
                                v-model="form.measurement_unit_id"
                                class="form-control"
                            >
                                <option value="">Ընտրել չափման միավորը</option>

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
                            <label class="form-label">Պահեստ</label>

                            <select
                                v-model="form.warehouse_id"
                                class="form-control"
                            >
                                <option value="">ընտրել պահեստը</option>

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
                            <label class="form-label">Քանակ</label>
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
                            <label class="form-label">Ամրագրված քանակ</label>
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
                            <label class="form-label">Average Cost</label>
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

                        <div class="col-md-6 mb-3">
                            <label class="form-label"> Անվանում (HY) </label>

                            <input
                                v-model="form.name.hy"
                                type="text"
                                class="form-control"
                                placeholder="Ապրանքի անուն հայերենով"
                            />

                            <div class="text-danger">
                                {{ form.errors["name.hy"] }}
                            </div>
                        </div>

                        <!-- <div class="col-md-6 mb-3">
                            <label class="form-label"> Name (RU) </label>

                            <input
                                v-model="form.name.ru"
                                type="text"
                                class="form-control"
                                placeholder="Product name in Russian"
                            />

                            <div class="text-danger">
                                {{ form.errors["name.ru"] }}
                            </div>
                        </div>


                        <div class="col-md-6 mb-3">
                            <label class="form-label"> Name (EN) </label>

                            <input
                                v-model="form.name.en"
                                type="text"
                                class="form-control"
                                placeholder="Product name in English"
                            />

                            <div class="text-danger">
                                {{ form.errors["name.en"] }}
                            </div>
                        </div> -->

                        <div class="col-md-12 mb-3">
                            <label class="form-label">
                                Նկարագրություն (HY)
                            </label>

                            <textarea
                                v-model="form.description.hy"
                                class="form-control"
                                rows="3"
                            ></textarea>

                            <div class="text-danger">
                                {{ form.errors["description.hy"] }}
                            </div>
                        </div>

                        <!-- <div class="col-md-12 mb-3">
                            <label class="form-label"> Description (RU) </label>

                            <textarea
                                v-model="form.description.ru"
                                class="form-control"
                                rows="3"
                            ></textarea>

                            <div class="text-danger">
                                {{ form.errors["description.ru"] }}
                            </div>
                        </div>


                        <div class="col-md-12 mb-3">
                            <label class="form-label"> Description (EN) </label>

                            <textarea
                                v-model="form.description.en"
                                class="form-control"
                                rows="3"
                            ></textarea>

                            <div class="text-danger">
                                {{ form.errors["description.en"] }}
                            </div>
                        </div> -->

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
                            <label class="form-label">Barcode</label>
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
                            <label class="form-label">Գնման գին</label>
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
                            <label class="form-label">Վաճառքի գին</label>
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
                                >Նվազագույն պահեստի զգուշացում</label
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
                            <label class="form-label"> Ապրանքի նկար </label>

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
                                    alt="Preview"
                                    class="img-fluid rounded border"
                                    style="max-height: 200px"
                                />
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Կարգավիճակ</label>
                            <select v-model="form.status" class="form-control">
                                <option :value="true">Ակտիվ</option>
                                <option :value="false">Պասիվ</option>
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
                            Ստեղծել
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
