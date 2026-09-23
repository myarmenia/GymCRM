<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { ref } from "vue";
import AppLayout from "@/Layouts/Index.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import Pagination from "@/Components/Pagination.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

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
    <Head :title="t('inventory.product_consumption_list')" />
    <AppLayout>
        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">{{ t('inventory.product_consumption_list') }}</h5>
            </div>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input
                            v-model="search"
                            type="text"
                            class="form-control"
                            :placeholder="t('inventory.search_by_product_name')"
                            @keyup.enter="submitSearch"
                        />
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="submitSearch"
                        >
                            {{ t('inventory.search') }}
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
                                <th>{{ t('inventory.product') }}</th>
                                <th>{{ t('inventory.consumption_quantity_2') }}</th>
                                <th>{{ t('people.description') }}</th>
                                <th>{{ t('inventory.at_purchase_price') }}</th>
                                <th>{{ t('inventory.at_sale_price') }}</th>
                                <th>{{ t('inventory.created_at') }}</th>
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
                                    {{ t('inventory.no_consumption_records_found') }}
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
