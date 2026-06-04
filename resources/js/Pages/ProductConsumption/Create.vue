<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";
console.log(props.products);
const form = useForm({
    products: props.products.map((product) => ({
        id: product.id,
        name: product.translations[0].name,
        measurement_unit: product.measurement_unit.name,
        default_purchase_price: product.default_purchase_price,
        default_sale_price: product.default_sale_price,
        available_quantity:
            product.warehouse_stocks[0].quantity +
            product.warehouse_stocks[0].reserved_quantity,
        quantity: "",
        description: "",
        purchase_price: 0,
        sale_price: 0,
    })),
});

const isInvalidQuantity = (product) => {
    return (
        Number(product.quantity) > Number(product.available_quantity) ||
        Number(product.quantity) <= 0
    );
};

const hasInvalidProducts = () => {
    return form.products.some((product) => isInvalidQuantity(product));
};

const calculatePrices = (product) => {
    const quantity = Number(product.quantity) || 0;

    product.purchase_price =
        quantity * Number(product.default_purchase_price || 0);

    product.sale_price = quantity * Number(product.default_sale_price || 0);
};

const submit = () => {
    if (hasInvalidProducts()) return;

    form.post(route("product-consumptions.store", { locale: currentLocale }));
};
</script>

<template>
    <Head title="Create Product Consumption" />
    <AppLayout>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Ապրանքների սպառում</h5>
            </div>

            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Measurement</th>
                                    <th>Available Quantity</th>
                                    <th>Consumption Quantity</th>
                                    <th>Description</th>
                                    <th>Purchase Price</th>
                                    <th>Sale Price</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(product, index) in form.products"
                                    :key="product.id"
                                >
                                    <td>{{ product.name }}</td>

                                    <td>{{ product.measurement_unit }}</td>

                                    <td>{{ product.available_quantity }}</td>

                                    <td>
                                        <input
                                            v-model="product.quantity"
                                            type="number"
                                            step="1"
                                            min="0"
                                            class="form-control"
                                            :max="product.available_quantity"
                                            placeholder="Enter quantity"
                                            @input="calculatePrices(product)"
                                            @wheel.prevent
                                        />

                                        <div
                                            v-if="
                                                Number(product.quantity) >
                                                Number(
                                                    product.available_quantity,
                                                )
                                            "
                                            class="text-danger mt-1 small"
                                        >
                                            Quantity cannot be greater than
                                            stock
                                        </div>

                                        <div
                                            v-if="
                                                product.quantity !== '' &&
                                                Number(product.quantity) <= 0
                                            "
                                            class="text-danger mt-1 small"
                                        >
                                            Quantity must be greater than 0
                                        </div>

                                        <div class="text-danger mt-1 small">
                                            {{
                                                form.errors[
                                                    `products.${index}.quantity`
                                                ]
                                            }}
                                        </div>
                                    </td>

                                    <td>
                                        <textarea
                                            v-model="product.description"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Description"
                                        ></textarea>

                                        <div class="text-danger mt-1 small">
                                            {{
                                                form.errors[
                                                    `products.${index}.description`
                                                ]
                                            }}
                                        </div>
                                    </td>

                                    <td>
                                        {{
                                            product.purchase_price ?? 0
                                        }}
                                    </td>

                                    <td>
                                        {{ product.sale_price ?? 0 }}
                                    </td>
                                </tr>

                                <tr v-if="!form.products.length">
                                    <td colspan="5" class="text-center">
                                        No selected products
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="form.errors.products" class="text-danger mt-2">
                        {{ form.errors.products }}
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary mt-3"
                        :disabled="form.processing || hasInvalidProducts()"
                    >
                        Save Consumption
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
