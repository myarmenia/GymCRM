<script setup>
import { ref } from "vue";
import AppLayout from "@/Layouts/Index.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    consumptions: Object,
    paginationLinks: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const search = ref(props.filters.search ?? "");

const submitSearch = () => {
    router.get(
        route("product-consumptions.index", { locale: currentLocale }),
        { search: search.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const resetSearch = () => {
    search.value = "";

    router.get(
        route("product-consumptions.index", { locale: currentLocale }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Product Consumption List" />
    <AppLayout>
        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">Ապրանքների սպառման ցուցակ</h5>
            </div>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input
                            v-model="search"
                            type="text"
                            class="form-control"
                            placeholder="Որոնել Ըստ Ապրանքի Անունի"
                            @keyup.enter="submitSearch"
                        />
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="submitSearch"
                        >
                            Որոնել
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary"
                            @click="resetSearch"
                        >
                            <i class="icon-base ti tabler-refresh"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ապրանք</th>
                                <th>Սպառման Քանակ</th>
                                <th>Նկարագրություն</th>
                                <th>Գնման գնով</th>
                                <th>Վաճառքի գնով</th>
                                <th>Ստեղծվել է</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(item, index) in consumptions.data"
                                :key="item.id"
                            >
                                <td>
                                    {{
                                        (consumptions.current_page - 1) *
                                            consumptions.per_page +
                                        index +
                                        1
                                    }}
                                </td>

                                <td>
                                    {{
                                        item.product?.translations?.[0]?.name ??
                                        "-"
                                    }}
                                </td>

                                <td>{{ item.consumption_quantity }}</td>

                                <td>{{ item.description ?? "-" }}</td>

                                <td>{{ item.purchase_price ?? 0 }}</td>

                                <td>{{ item.sale_price ?? 0 }}</td>

                                <td>
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleString("en-US", {
                                            timeZone: "Asia/Yerevan",
                                            year: "numeric",
                                            month: "2-digit",
                                            day: "2-digit",
                                            hour: "2-digit",
                                            minute: "2-digit",
                                        })
                                    }}
                                </td>
                            </tr>

                            <tr v-if="consumptions.data.length === 0">
                                <td colspan="5" class="text-center">
                                    Սպառման տվյալներ չեն գտնվել
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="consumptions.links" />
            </div>
        </div>
    </AppLayout>
</template>
