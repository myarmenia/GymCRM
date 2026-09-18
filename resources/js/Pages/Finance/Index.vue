<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    transactions: Object,
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    creators: { type: Array, default: () => [] },
    gyms: { type: Array, default: () => [] },
    canManage: Boolean,
});

const currentLocale = usePage().props.locale ?? "hy";
const showManualForm = ref(false);
const showCategoryForm = ref(false);
const reversalTransaction = ref(null);
const reversalReason = ref("");
const reversalError = ref("");
const reversalProcessing = ref(false);
const filters = ref({
    gym_id: props.filters.gym_id ?? "",
    direction: props.filters.direction ?? "",
    payment_method_id: props.filters.payment_method_id ?? "",
    category_id: props.filters.category_id ?? "",
    creator_id: props.filters.creator_id ?? "",
    start_date: props.filters.start_date ?? "",
    end_date: props.filters.end_date ?? "",
    search: props.filters.search ?? "",
});

const form = useForm({
    gym_id: props.filters.gym_id ?? props.gyms[0]?.id ?? "",
    direction: "income",
    category_id:
        props.categories.find(
            (category) =>
                !category.is_system && category.direction === "income",
        )?.id ?? "",
    amount: "",
    payment_method_id:
        props.paymentMethods.find((method) => method.slug === "cash")?.id ?? "",
    card_type_id: "",
    occurred_at: "",
    description: "",
    reference: "",
});
const categoryForm = useForm({
    gym_id: props.filters.gym_id ?? props.gyms[0]?.id ?? "",
    name: "",
    direction: "expense",
});

const selectedPaymentMethod = computed(() =>
    props.paymentMethods.find(
        (method) => Number(method.id) === Number(form.payment_method_id),
    ),
);
const availableCardTypes = computed(
    () =>
        selectedPaymentMethod.value?.card_types ??
        selectedPaymentMethod.value?.cardTypes ??
        [],
);
const manualCategories = computed(() =>
    props.categories.filter(
        (category) =>
            !category.is_system &&
            category.direction === form.direction &&
            (!category.gym_id ||
                !form.gym_id ||
                Number(category.gym_id) === Number(form.gym_id)),
    ),
);

watch(
    () => form.payment_method_id,
    () => {
        form.card_type_id = "";
    },
);
watch(
    () => [form.direction, form.gym_id],
    () => {
        form.category_id = manualCategories.value[0]?.id ?? "";
    },
);

const translatedName = (item) =>
    item?.translations?.find(
        (translation) => translation.locale === currentLocale,
    )?.name ??
    item?.name ??
    item?.slug ??
    "-";

const money = (value) =>
    `${Number(value || 0).toLocaleString("hy-AM", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })} ֏`;

const activeFilterParams = computed(() =>
    Object.fromEntries(
        Object.entries(filters.value).filter(
            ([, value]) => value !== "" && value !== null,
        ),
    ),
);

const exportHref = computed(() =>
    route("finance.export", {
        locale: currentLocale,
        ...activeFilterParams.value,
    }),
);

const printHref = computed(() =>
    route("finance.print", {
        locale: currentLocale,
        ...activeFilterParams.value,
    }),
);

const applyFilters = () => {
    router.get(
        route("finance.index", { locale: currentLocale }),
        Object.fromEntries(
            Object.entries(filters.value).filter(
                ([, value]) => value !== "" && value !== null,
            ),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    Object.keys(filters.value).forEach((key) => {
        filters.value[key] = "";
    });
    applyFilters();
};

const submitManual = () => {
    form.post(route("finance.store", { locale: currentLocale }), {
        preserveScroll: true,
        onSuccess: () => {
            const gymId = form.gym_id;
            const paymentMethodId =
                props.paymentMethods.find((method) => method.slug === "cash")
                    ?.id ?? "";
            form.reset();
            form.gym_id = gymId;
            form.direction = "income";
            form.category_id =
                props.categories.find(
                    (category) =>
                        !category.is_system &&
                        category.direction === "income" &&
                        (!category.gym_id ||
                            Number(category.gym_id) === Number(gymId)),
                )?.id ?? "";
            form.payment_method_id = paymentMethodId;
            showManualForm.value = false;
        },
    });
};

const submitCategory = () => {
    categoryForm.post(
        route("finance.categories.store", { locale: currentLocale }),
        {
            preserveScroll: true,
            onSuccess: () => {
                categoryForm.name = "";
                showCategoryForm.value = false;
            },
        },
    );
};

const reverseTransaction = (transaction) => {
    reversalTransaction.value = transaction;
    reversalReason.value = "";
    reversalError.value = "";
};

const closeReversalModal = () => {
    if (reversalProcessing.value) return;

    reversalTransaction.value = null;
    reversalReason.value = "";
    reversalError.value = "";
};

const submitReversal = () => {
    const reason = reversalReason.value.trim();

    if (!reason) {
        reversalError.value = t('operations.specify_the_refund_reason');
        return;
    }

    reversalError.value = "";
    reversalProcessing.value = true;

    router.post(
        route("finance.reverse", {
            locale: currentLocale,
            financialTransaction: reversalTransaction.value.id,
        }),
        { reason },
        {
            preserveScroll: true,
            onSuccess: () => {
                reversalTransaction.value = null;
                reversalReason.value = "";
            },
            onError: (errors) => {
                reversalError.value =
                    errors.reason ?? t('operations.could_not_process_the_refund');
            },
            onFinish: () => {
                reversalProcessing.value = false;
            },
        },
    );
};

const creatorName = (creator) =>
    creator
        ? `${creator.name ?? ""} ${creator.surname ?? ""}`.trim()
        : t('operations.system');

const rowNumber = (index) =>
    (Number(props.transactions.current_page ?? 1) - 1) *
        Number(props.transactions.per_page ?? 25) +
    index +
    1;
</script>

<template>
    <Head :title="t('sidebar.cashier')" />

    <AppLayout>
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ t('sidebar.cashier') }}</h4>
                    <p class="text-muted mb-0">{{ t('operations.all_financial_income_and_expenses_in_one_unified_ledger') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        v-if="canManage"
                        class="btn btn-primary"
                        type="button"
                        @click="showManualForm = !showManualForm"
                    >
                        <i class="ti tabler-plus me-1"></i>
                        {{ t('operations.manual_income_expense') }}
                    </button>
                    <button
                        v-if="canManage"
                        class="btn btn-outline-primary"
                        type="button"
                        @click="showCategoryForm = !showCategoryForm"
                    >
                        {{ t('sidebar.categories') }}
                    </button>
                    <!-- <a
                        :href="printHref"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-outline-secondary"
                    >
                        <i class="ti tabler-printer me-1"></i>
                        {{ t('operations.print') }}
                    </a> -->
                    <a :href="exportHref" class="btn btn-outline-success">
                        <i class="ti tabler-file-export me-1"></i>
                        {{ t('staff_reports.export_to_excel') }}
                    </a>
                </div>
            </div>

            <div v-if="showCategoryForm" class="card border-0 shadow-sm mb-4">
                <form class="card-body" @submit.prevent="submitCategory">
                    <div class="row g-3 align-items-end">
                        <div v-if="gyms.length" class="col-md-3">
                            <label class="form-label">{{ t('people.gym') }}</label>
                            <select v-model="categoryForm.gym_id" class="form-select">
                                <option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ t('operations.category_type') }}</label>
                            <select v-model="categoryForm.direction" class="form-select">
                                <option value="income">{{ t('auth.login') }}</option>
                                <option value="expense">{{ t('people.exit') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ t('membership.title') }}</label>
                            <input v-model="categoryForm.name" class="form-control" :placeholder="t('operations.e_g_utility_payment')" />
                            <div class="text-danger small">{{ categoryForm.errors.name }}</div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" :disabled="categoryForm.processing">{{ t('operations.add') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">{{ t('operations.current_balance') }}</h5>
                <small class="text-muted">{{ t('operations.using_the_full_history') }}</small>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="summary-card">
                        <span>{{ t('operations.total_balance') }}</span>
                        <strong>{{ money(summary.balance) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card cash">
                        <span>{{ t('operations.cash') }}</span>
                        <strong>{{ money(summary.cash_balance) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card noncash">
                        <span>{{ t('operations.non_cash') }}</span>
                        <strong>{{ money(summary.noncash_balance) }}</strong>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">{{ t('operations.selected_period') }}</h5>
                <small class="text-muted">{{ t('operations.with_applied_filters') }}</small>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="summary-card income">
                        <span>{{ t('staff_reports.entries') }}</span>
                        <strong>{{ money(summary.income) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card expense">
                        <span>{{ t('staff_reports.exits') }}</span>
                        <strong>{{ money(summary.expense) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card" :class="Number(summary.period_net) < 0 ? 'expense' : 'income'">
                        <span>{{ t('operations.net_difference') }}</span>
                        <strong>{{ money(summary.period_net) }}</strong>
                    </div>
                </div>
            </div>

            <div v-if="showManualForm" class="card border-0 shadow-sm mb-4">
                <div class="card-header"><strong>{{ t('operations.new_manual_transaction') }}</strong></div>
                <form class="card-body" @submit.prevent="submitManual">
                    <div class="row g-3">
                        <div v-if="gyms.length" class="col-lg-3 col-md-6">
                            <label class="form-label">{{ t('people.gym') }}</label>
                            <select v-model="form.gym_id" class="form-select">
                                <option value="" disabled>{{ t('people.select') }}</option>
                                <option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option>
                            </select>
                            <div class="text-danger small">{{ form.errors.gym_id }}</div>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">{{ t('people.type') }}</label>
                            <select v-model="form.direction" class="form-select">
                                <option value="income">{{ t('auth.login') }}</option>
                                <option value="expense">{{ t('people.exit') }}</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">{{ t('people.category') }}</label>
                            <select v-model="form.category_id" class="form-select">
                                <option value="" disabled>{{ t('people.select') }}</option>
                                <option v-for="category in manualCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <div class="text-danger small">{{ form.errors.category_id }}</div>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">{{ t('people.amount') }}</label>
                            <input v-model="form.amount" type="number" min="0.01" step="0.01" class="form-control" />
                            <div class="text-danger small">{{ form.errors.amount }}</div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">{{ t('people.payment_method') }}</label>
                            <select v-model="form.payment_method_id" class="form-select">
                                <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                    {{ translatedName(method) }}
                                </option>
                            </select>
                        </div>
                        <div v-if="availableCardTypes.length" class="col-lg-2 col-md-6">
                            <label class="form-label">{{ t('sales.card_type') }}</label>
                            <select v-model="form.card_type_id" class="form-select">
                                <option value="" disabled>{{ t('people.select') }}</option>
                                <option v-for="card in availableCardTypes" :key="card.id" :value="card.id">{{ card.name }}</option>
                            </select>
                            <div class="text-danger small">{{ form.errors.card_type_id }}</div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">{{ t('operations.date_and_time') }}</label>
                            <input v-model="form.occurred_at" type="datetime-local" class="form-control" />
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label class="form-label">{{ t('people.description') }}</label>
                            <input v-model="form.description" class="form-control" />
                            <div class="text-danger small">{{ form.errors.description }}</div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">{{ t('operations.reference_document') }}</label>
                            <input v-model="form.reference" class="form-control" />
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showManualForm = false">{{ t('confirm.close') }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ t('operations.record_2') }}</button>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm">
                 <div class="card-body border-bottom"> 
                    <div class="row g-2">
                        <div class="col-lg-2 col-md-4"><input v-model="filters.search" class="form-control" :placeholder="t('inventory.search_2')" @keyup.enter="applyFilters" /></div>
                        <div v-if="gyms.length" class="col-lg-2 col-md-4">
                            <select v-model="filters.gym_id" class="form-select"><option value="">{{ t('operations.all_gyms') }}</option><option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option></select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <select v-model="filters.direction" class="form-select"><option value="">{{ t('operations.income_and_expense') }}</option><option value="income">{{ t('auth.login') }}</option><option value="expense">{{ t('people.exit') }}</option></select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <select v-model="filters.payment_method_id" class="form-select"><option value="">{{ t('operations.all_methods') }}</option><option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ translatedName(method) }}</option></select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <select v-model="filters.category_id" class="form-select"><option value="">{{ t('inventory.all_categories') }}</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <select v-model="filters.creator_id" class="form-select">
                                <option value="">{{ t('operations.all_creators') }}</option>
                                <option v-for="creator in creators" :key="creator.id" :value="creator.id">
                                    {{ creatorName(creator) }}
                                </option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4"><input v-model="filters.start_date" type="date" class="form-control" /></div>
                        <div class="col-lg-2 col-md-4"><input v-model="filters.end_date" type="date" class="form-control" /></div>
                        <div class="col-lg-2 col-md-4 d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1" @click="applyFilters">{{ t('filter.submit') }}</button>
                            <button class="btn btn-outline-secondary" @click="resetFilters">×</button>
                        </div>
                    </div>
                </div> 

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>{{ t('filter.date') }}</th><th>{{ t('people.category') }}</th><th>{{ t('people.payment') }}</th><th>{{ t('people.description') }}</th><th>{{ t('auth.login') }}</th><th>{{ t('people.exit') }}</th><th>{{ t('inventory.recorded_by') }}</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="(transaction, index) in transactions.data" :key="transaction.id">
                                <td>{{ rowNumber(index) }}/#{{ transaction.id }}</td>
                                <td class="text-nowrap">{{ new Date(transaction.occurred_at).toLocaleString("hy-AM") }}</td>
                                <td><span class="badge bg-label-secondary">{{ transaction.category?.name }}</span></td>
                                <td>
                                    {{ translatedName(transaction.payment_method) }}
                                    <small v-if="transaction.card_type" class="d-block text-muted">{{ transaction.card_type.name }}</small>
                                </td>
                                <td>
                                    <div>{{ transaction.description ?? "-" }}</div>
                                    <small v-if="transaction.reference" class="text-muted">{{ transaction.reference }}</small>
                                    <small v-if="transaction.status === 'reversed'" class="d-block text-warning">{{ t('staff_reports.refund') }}</small>
                                </td>
                                <td class="fw-semibold text-success">{{ transaction.direction === "income" ? money(transaction.amount) : "—" }}</td>
                                <td class="fw-semibold text-danger">{{ transaction.direction === "expense" ? money(transaction.amount) : "—" }}</td>
                                <td>{{ creatorName(transaction.creator) }}</td>
                                <td>
                                    <button
                                        v-if="canManage && transaction.source_type === 'manual' && transaction.status !== 'reversed' && !transaction.reversal_of_id"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="reverseTransaction(transaction)"
                                    >{{ t('staff_reports.refund') }}</button>
                                </td>
                            </tr>
                            <tr v-if="!transactions.data?.length"><td colspan="9" class="text-center text-muted py-5">{{ t('people.no_transactions') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer"><Pagination :links="transactions.links" /></div>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="reversalTransaction"
                class="modal fade show d-block"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                @click.self="closeReversalModal"
            >
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" @submit.prevent="submitReversal">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ t('operations.confirm_refund') }}</h5>
                            <button
                                type="button"
                                class="btn-close"
                                :aria-label="t('confirm.close')"
                                :disabled="reversalProcessing"
                                @click="closeReversalModal"
                            ></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">
                                {{ t('operations.reverse_transaction_notice') }}
                            </p>
                            <label for="reversal-reason" class="form-label">
                                {{ t('operations.refund_reason') }}
                            </label>
                            <textarea
                                id="reversal-reason"
                                v-model="reversalReason"
                                class="form-control"
                                :class="{ 'is-invalid': reversalError }"
                                rows="3"
                                autofocus
                                :disabled="reversalProcessing"
                                @input="reversalError = ''"
                            ></textarea>
                            <div v-if="reversalError" class="invalid-feedback">
                                {{ reversalError }}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                :disabled="reversalProcessing"
                                @click="closeReversalModal"
                            >
                                {{ t('confirm.cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="btn btn-danger"
                                :disabled="reversalProcessing"
                            >
                                <span
                                    v-if="reversalProcessing"
                                    class="spinner-border spinner-border-sm me-1"
                                    aria-hidden="true"
                                ></span>
                                {{ t('operations.confirm_refund') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div
                v-if="reversalTransaction"
                class="modal-backdrop fade show"
            ></div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.summary-card { height: 100%; padding: 1.15rem; border-radius: 1rem; background: #fff; box-shadow: 0 0.2rem 1rem rgba(34, 48, 62, 0.08); border-left: 4px solid #7367f0; }
.summary-card span { display: block; color: #6d6b77; font-size: 0.85rem; margin-bottom: 0.4rem; }
.summary-card strong { font-size: 1.25rem; }
.summary-card.cash { border-color: #28c76f; }
.summary-card.noncash { border-color: #00bad1; }
.summary-card.income { border-color: #28c76f; }
.summary-card.expense { border-color: #ff4c51; }
</style>
