<script setup>
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    reports: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    options: {
        type: Object,
        default: () => ({
            statuses: [],
            actions: [],
            ownerTypes: [],
            reasons: [],
            clients: [],
        }),
    },
    canSelectClient: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? "hy");

const selectedReport = ref(null);
const showDetailsModal = ref(false);

const filterForm = ref({
    search: props.filters.search ?? "",
    status: props.filters.status ?? "",
    action: props.filters.action ?? "",
    reason: props.filters.reason ?? "",
    owner_type: props.filters.owner_type ?? "",
    access_allowed: props.filters.access_allowed ?? "",
    client_id: props.filters.client_id ?? "",
    start_date: props.filters.start_date ?? "",
    end_date: props.filters.end_date ?? "",
    per_page: props.filters.per_page ?? 10,
});

const summaryCards = computed(() => [
    {
        label: "Ընդհանուր",
        value: props.summary.total_count ?? 0,
        icon: "tabler-clipboard-list",
        class: "bg-label-primary",
    },
    {
        label: "Հաջողված",
        value: props.summary.success_count ?? 0,
        icon: "tabler-circle-check",
        class: "bg-label-success",
    },
    {
        label: "Մերժված",
        value: props.summary.denied_count ?? 0,
        icon: "tabler-circle-x",
        class: "bg-label-danger",
    },
    {
        label: "Մուտքեր",
        value: props.summary.entry_count ?? 0,
        icon: "tabler-door-enter",
        class: "bg-label-info",
    },
    {
        label: "Ելքեր",
        value: props.summary.exit_count ?? 0,
        icon: "tabler-door-exit",
        class: "bg-label-warning",
    },
    {
        label: "Սխալ կոդեր",
        value: props.summary.invalid_code_count ?? 0,
        icon: "tabler-qrcode-off",
        class: "bg-label-danger",
    },
    {
        label: "Աբոնեմենտը լրացած",
        value: props.summary.expired_subscription_count ?? 0,
        icon: "tabler-alert-triangle",
        class: "bg-label-warning",
    },
    {
        label: "Այսօր",
        value: props.summary.today_count ?? 0,
        icon: "tabler-calendar",
        class: "bg-label-secondary",
    },
]);

const filledParams = () => {
    return Object.fromEntries(
        Object.entries(filterForm.value).filter(([, value]) => {
            return value !== null && value !== "";
        }),
    );
};

const applyFilters = () => {
    router.get(
        route("entry-reports.index", { locale: currentLocale.value }),
        filledParams(),
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const resetFilters = () => {
    filterForm.value = {
        search: "",
        status: "",
        action: "",
        reason: "",
        owner_type: "",
        access_allowed: "",
        client_id: "",
        start_date: "",
        end_date: "",
        per_page: 10,
    };

    router.get(
        route("entry-reports.index", { locale: currentLocale.value }),
        {},
        { preserveScroll: true },
    );
};

const openDetails = (report) => {
    selectedReport.value = report;
    showDetailsModal.value = true;
};

const closeDetails = () => {
    selectedReport.value = null;
    showDetailsModal.value = false;
};

const ownerDisplayName = (report) => {
    if (!report.owner) {
        return "Չի գտնվել";
    }

    return `${report.owner.name ?? ""} ${report.owner.surname ?? ""}`.trim();
};

const ownerMeta = (report) => {
    if (!report.owner) {
        return "Owner տվյալ չկա";
    }

    return report.owner_type === "user"
        ? report.owner.email
        : report.owner.phone || report.owner.email;
};

const accessLabel = (report) => {
    return report.access_allowed ? "Թույլատրված" : "Մերժված";
};

const accessClass = (report) => {
    return report.access_allowed ? "bg-label-success" : "bg-label-danger";
};

const formatPayload = (payload) => {
    if (!payload) {
        return "{}";
    }

    return JSON.stringify(payload, null, 2);
};

const exportHref = computed(() => {
    return route("entry-reports.export", {
        locale: currentLocale.value,
        ...filledParams(),
    });
});
</script>

<template>
    <Head title="Մուտք/ելք հաշվետվություն" />

    <AppLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1">Մուտք/ելք հաշվետվություն</h4>
                <p class="text-muted mb-0">
                    Turnstile մուտքերի, ելքերի և մերժված փորձերի ամբողջական ցուցակ
                </p>
            </div>

            <div class="d-flex gap-2">
                <a :href="exportHref" class="btn btn-outline-success">
                    <i class="icon-base ti tabler-file-export me-1"></i>
                    Export CSV
                </a>
                <button class="btn btn-outline-secondary" @click="resetFilters">
                    <i class="icon-base ti tabler-refresh me-1"></i>
                    Մաքրել ֆիլտրերը
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="col-12 col-sm-6 col-lg-3"
            >
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span
                            class="avatar rounded"
                            :class="card.class"
                        >
                            <i
                                class="icon-base ti"
                                :class="card.icon"
                            ></i>
                        </span>
                        <div>
                            <div class="text-muted small">{{ card.label }}</div>
                            <h4 class="mb-0">{{ card.value }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Որոնում</label>
                        <input
                            v-model="filterForm.search"
                            type="text"
                            class="form-control"
                            placeholder="Կոդ, MAC, անուն, հեռախոս..."
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Կարգավիճակ</label>
                        <select v-model="filterForm.status" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="status in options.statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Գործողություն</label>
                        <select v-model="filterForm.action" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="action in options.actions"
                                :key="action.value"
                                :value="action.value"
                            >
                                {{ action.label }}
                            </option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Owner</label>
                        <select v-model="filterForm.owner_type" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="ownerType in options.ownerTypes"
                                :key="ownerType.value"
                                :value="ownerType.value"
                            >
                                {{ ownerType.label }}
                            </option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Մուտք</label>
                        <select v-model="filterForm.access_allowed" class="form-select">
                            <option value="">Բոլորը</option>
                            <option value="1">Թույլատրված</option>
                            <option value="0">Մերժված</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Պատճառ</label>
                        <select v-model="filterForm.reason" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="reason in options.reasons"
                                :key="reason.value"
                                :value="reason.value"
                            >
                                {{ reason.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="canSelectClient" class="col-12 col-md-3">
                        <label class="form-label">Մասնաճյուղ</label>
                        <select v-model="filterForm.client_id" class="form-select">
                            <option value="">Բոլորը</option>
                            <option
                                v-for="client in options.clients"
                                :key="client.id"
                                :value="client.id"
                            >
                                {{ client.name }}
                            </option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Սկիզբ</label>
                        <input v-model="filterForm.start_date" type="date" class="form-control" />
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">Ավարտ</label>
                        <input v-model="filterForm.end_date" type="date" class="form-control" />
                    </div>

                    <div class="col-6 col-md-1">
                        <label class="form-label">Քանակ</label>
                        <select v-model="filterForm.per_page" class="form-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" @click="applyFilters">
                            <i class="icon-base ti tabler-search me-1"></i>
                            Որոնել
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Ժամանակ</th>
                            <th>Owner</th>
                            <th>Տեսակ</th>
                            <th>Entry code</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Access</th>
                            <th>MAC</th>
                            <th class="text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="report in reports.data" :key="report.id">
                            <td>{{ report.id }}</td>
                            <td>
                                <div class="fw-medium">{{ report.detected_at || report.created_at }}</div>
                                <small class="text-muted">{{ report.device_time }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img
                                        v-if="report.owner?.image"
                                        :src="`/storage/${report.owner.image}`"
                                        class="rounded-circle object-fit-cover"
                                        width="34"
                                        height="34"
                                        alt="Owner"
                                    />
                                    <span
                                        v-else
                                        class="avatar avatar-sm rounded bg-label-secondary"
                                    >
                                        <i class="icon-base ti tabler-user"></i>
                                    </span>
                                    <div>
                                        <div class="fw-medium">{{ ownerDisplayName(report) }}</div>
                                        <small class="text-muted">{{ ownerMeta(report) }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-label-secondary">
                                    {{ report.owner_type || "unknown" }}
                                </span>
                            </td>
                            <td>{{ report.entry_code || "-" }}</td>
                            <td>
                                <span class="badge" :class="report.action_badge_class">
                                    {{ report.action_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="report.status_badge_class">
                                    {{ report.status_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="report.reason_badge_class">
                                    {{ report.reason_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="accessClass(report)">
                                    {{ accessLabel(report) }}
                                </span>
                            </td>
                            <td>{{ report.mac || "-" }}</td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="openDetails(report)"
                                >
                                    <i class="icon-base ti tabler-eye me-1"></i>
                                    Դիտել
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!reports.data.length">
                            <td colspan="11" class="text-center py-5">
                                <i class="icon-base ti tabler-database-off me-1"></i>
                                Տվյալներ չկան
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-body">
                <Pagination :links="reports.links" />
            </div>
        </div>

        <div
            v-if="showDetailsModal && selectedReport"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.5)"
        >
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Report #{{ selectedReport.id }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeDetails"></button>
                    </div>

                    <div class="modal-body">
                        <div
                            v-if="
                                ['subscription_expired', 'no_active_subscription'].includes(
                                    selectedReport.reason,
                                )
                            "
                            class="alert alert-warning"
                        >
                            Մուտքը մերժված է․ aboniment-ի ժամկետը լրացել է կամ active aboniment չկա
                        </div>

                        <div
                            v-if="selectedReport.reason === 'invalid_entry_code'"
                            class="alert alert-danger"
                        >
                            Մուտքը մերժված է․ սխալ կամ չգրանցված մուտքի կոդ
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">Owner</h6>
                                    <p class="mb-1">
                                        <strong>Անուն:</strong>
                                        {{ ownerDisplayName(selectedReport) }}
                                    </p>
                                    <p class="mb-1">
                                        <strong>Email/Հեռախոս:</strong>
                                        {{ ownerMeta(selectedReport) }}
                                    </p>
                                    <p class="mb-0">
                                        <strong>Տեսակ:</strong>
                                        {{ selectedReport.owner_type || "unknown" }}
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">Report</h6>
                                    <p class="mb-1">
                                        <strong>Entry code:</strong>
                                        {{ selectedReport.entry_code || "-" }}
                                    </p>
                                    <p class="mb-1">
                                        <strong>Status:</strong>
                                        {{ selectedReport.status_label }}
                                    </p>
                                    <p class="mb-1">
                                        <strong>Action:</strong>
                                        {{ selectedReport.action_label }}
                                    </p>
                                    <p class="mb-1">
                                        <strong>Reason:</strong>
                                        {{ selectedReport.reason_label }}
                                    </p>
                                    <p class="mb-0">
                                        <strong>MAC:</strong>
                                        {{ selectedReport.mac || "-" }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <strong>Device time:</strong>
                                <div>{{ selectedReport.device_time || "-" }}</div>
                            </div>
                            <div class="col-md-4">
                                <strong>Detected at:</strong>
                                <div>{{ selectedReport.detected_at || "-" }}</div>
                            </div>
                            <div class="col-md-4">
                                <strong>Created at:</strong>
                                <div>{{ selectedReport.created_at || "-" }}</div>
                            </div>
                        </div>

                        <h6>Payload JSON</h6>
                        <pre class="bg-light border rounded p-3 mb-0"><code>{{ formatPayload(selectedReport.payload) }}</code></pre>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="closeDetails">
                            Փակել
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
