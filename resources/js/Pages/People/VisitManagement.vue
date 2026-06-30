<script setup>
import { computed, ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import {
    formatDateTimeInYerevan,
    nowDateTimeLocalInYerevan,
} from "@/utils/yerevanDate";

const props = defineProps({
    person: Object,
    memberships: Array,
    recentAttendances: Array,
    lastAttendance: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? "hy");

const fullName = computed(() => {
    const name = props.person?.name ?? "";
    const surname = props.person?.surname ?? "";

    return `${name} ${surname}`.trim() || "-";
});

const imageUrl = computed(() => {
    if (!props.person?.image) {
        return null;
    }

    return props.person.image.startsWith("http")
        ? props.person.image
        : `/storage/${props.person.image}`;
});

const translatedName = (item) =>
    item?.translations?.find((translation) => translation.locale === currentLocale.value)?.name
    ?? item?.name
    ?? item?.slug
    ?? (item?.id ? `#${item.id}` : "-");

const membershipPlanName = (membership) => translatedName(membership?.membership_plan);
const membershipCategoryName = (membership) => translatedName(membership?.membership_plan?.membership_category);

const membershipStatusLabel = (status) => ({
    waiting: "Սպասման մեջ",
    active: "Ակտիվ",
}[status] ?? status ?? "-");

const membershipStatusClass = (status) => ({
    waiting: "bg-label-info",
    active: "bg-label-success",
}[status] ?? "bg-label-secondary");

const directionLabel = (direction) => ({
    entry: "Մուտք",
    exit: "Ելք",
}[direction] ?? direction ?? "-");

const directionClass = (direction) => ({
    entry: "bg-label-success",
    exit: "bg-label-secondary",
}[direction] ?? "bg-label-secondary");

const formatDateTime = (value) => formatDateTimeInYerevan(value);

const formatDateOnly = (value) => {
    if (!value) {
        return "-";
    }

    return String(value).slice(0, 10);
};

const membershipDurationLabel = (membership) => {
    const durationType = membership?.membership_plan?.duration_type;
    const durationValue = Number(membership?.membership_plan?.duration_value || 0);

    if (!durationType || !durationValue) {
        return "Անսահմանափակ";
    }

    if (durationType === "month") {
        return `${durationValue} ամիս`;
    }

    if (durationType === "year") {
        return `${durationValue * 12} ամիս`;
    }

    if (durationType === "day") {
        return `${durationValue} օր`;
    }

    if (durationType === "visit") {
        return `${durationValue} այց`;
    }

    return `${durationValue}`;
};

const insideNow = computed(() => props.lastAttendance?.direction === "entry");
const visitDateTime = ref(nowDateTimeLocalInYerevan());

const entryForm = useForm({
    action: "entry",
    membership_id: null,
    manual_datetime: visitDateTime.value,
});

const exitForm = useForm({
    action: "exit",
    membership_id: null,
    manual_datetime: visitDateTime.value,
});

const submitEntry = (membershipId) => {
    entryForm.action = "entry";
    entryForm.membership_id = membershipId;
    entryForm.manual_datetime = visitDateTime.value;
    entryForm.post(route("person.visits.store", {
        locale: currentLocale.value,
        id: props.person.id,
    }), {
        preserveScroll: true,
    });
};

const submitExit = () => {
    exitForm.action = "exit";
    exitForm.membership_id = null;
    exitForm.manual_datetime = visitDateTime.value;
    exitForm.post(route("person.visits.store", {
        locale: currentLocale.value,
        id: props.person.id,
    }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Այցելությունների կառավարում" />

    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                        Այցելությունների կառավարում
                    </h2>
                    <div class="text-muted">{{ fullName }}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('person.profile', { locale: currentLocale, id: person.id })"
                    >
                        Պրոֆիլ
                    </Link>
                    <Link
                        class="btn btn-secondary"
                        :href="route('person.list', { locale: currentLocale })"
                    >
                        Վերադառնալ
                    </Link>
                </div>
            </div>
        </template>

        <div class="card mb-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div
                        v-if="imageUrl"
                        class="person-avatar-image"
                    >
                        <img :src="imageUrl" :alt="fullName">
                    </div>
                    <div
                        v-else
                        class="person-avatar-placeholder bg-primary text-white"
                    >
                        {{ fullName.slice(0, 1) }}
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <h3 class="mb-0">{{ fullName }}</h3>
                            <span
                                class="badge"
                                :class="insideNow ? 'bg-label-success' : 'bg-label-secondary'"
                            >
                                {{ insideNow ? "Ներսում է" : "Դրսում է" }}
                            </span>
                        </div>

                        <div class="d-flex gap-4 flex-wrap text-muted">
                            <span>
                                <i class="icon-base ti tabler-phone me-1"></i>
                                {{ person?.phone || "-" }}
                            </span>
                            <span>
                                <i class="icon-base ti tabler-mail me-1"></i>
                                {{ person?.email || "-" }}
                            </span>
                            <span>
                                <i class="icon-base ti tabler-calendar me-1"></i>
                                {{ person?.birth_date || "-" }}
                            </span>
                        </div>
                    </div>

                    <div class="action-panel">
                        <div class="mb-3">
                            <label class="form-label">Գրանցման ժամ</label>
                            <input
                                v-model="visitDateTime"
                                type="datetime-local"
                                class="form-control"
                            >
                            <div class="small text-muted mt-2">
                                Հոսանքի անջատման դեպքում կարող եք նշել իրական մուտքի կամ ելքի ժամը։
                            </div>
                        </div>
                        <div class="text-muted small mb-1">Վերջին գրանցումը</div>
                        <div class="fw-semibold mb-3">
                            {{ lastAttendance ? directionLabel(lastAttendance.direction) : "-" }}
                        </div>
                        <div
                            v-if="entryForm.errors.manual_datetime || exitForm.errors.manual_datetime"
                            class="text-danger small mb-3"
                        >
                            {{ entryForm.errors.manual_datetime || exitForm.errors.manual_datetime }}
                        </div>
                        <button
                            type="button"
                            class="btn btn-outline-secondary w-100"
                            :disabled="exitForm.processing || !insideNow"
                            @click="submitExit"
                        >
                            <span
                                v-if="exitForm.processing"
                                class="spinner-border spinner-border-sm me-2"
                            ></span>
                            Ավելացնել ելք
                        </button>
                        <div
                            v-if="!insideNow"
                            class="small text-muted mt-2"
                        >
                            Ելքը հասանելի է միայն վերջին մուտքից հետո։
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Ակտիվ բաժանորդագրություններ</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="memberships.length"
                            class="d-flex flex-column gap-3"
                        >
                            <div
                                v-for="membership in memberships"
                                :key="membership.id"
                                class="membership-card"
                            >
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                                    <div>
                                        <h5 class="mb-1">{{ membershipPlanName(membership) }}</h5>
                                        <div class="text-muted small">
                                            {{ membershipCategoryName(membership) }}
                                        </div>
                                    </div>
                                    <span
                                        class="badge"
                                        :class="membershipStatusClass(membership.status)"
                                    >
                                        {{ membershipStatusLabel(membership.status) }}
                                    </span>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="text-muted small">Վավեր է մինչև</div>
                                            <div class="fw-semibold">
                                                {{ formatDateOnly(membership.valid_at || membership.end_date) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="text-muted small">Այցերի հաշվառում</div>
                                            <div class="fw-semibold">
                                                <span v-if="membership.visits_left === null">
                                                    {{ membershipDurationLabel(membership) }}
                                                </span>
                                                <span v-else>
                                                    {{ membership.visits_left }} մնացել / {{ membership.visits_used ?? 0 }} օգտագործվել
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Մուտքի օր և ժամ</label>
                                    <input
                                        v-model="visitDateTime"
                                        type="datetime-local"
                                        class="form-control"
                                    >
                                    <div class="small text-muted mt-2">
                                        Մուտքը ավելացնելուց առաջ ընտրեք իրական մուտքի օրը և ժամը։
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <div class="small text-muted">
                                        {{ membership.gym?.name || "-" }}
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        :disabled="entryForm.processing || insideNow"
                                        @click="submitEntry(membership.id)"
                                    >
                                        <span
                                            v-if="entryForm.processing && entryForm.membership_id === membership.id"
                                            class="spinner-border spinner-border-sm me-2"
                                        ></span>
                                        Ավելացնել մուտք
                                    </button>
                                </div>
                                <div
                                    v-if="insideNow"
                                    class="small text-muted mt-2"
                                >
                                    Նոր մուտքը հասանելի կլինի ելքից հետո։
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="empty-state"
                        >
                            Ակտիվ կամ սպասման մեջ անդամակցություններ չկան։
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Վերջին այցելությունները</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="recentAttendances.length"
                            class="table-responsive"
                        >
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Ամսաթիվ</th>
                                        <th>Գործողություն</th>
                                        <th>Աբոնեմենտ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="attendance in recentAttendances"
                                        :key="attendance.id"
                                    >
                                        <td>{{ formatDateTime(attendance.date) }}</td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="directionClass(attendance.direction)"
                                            >
                                                {{ directionLabel(attendance.direction) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ attendance.membership_plan ? translatedName(attendance.membership_plan) : "-" }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-else
                            class="empty-state"
                        >
                            Այցելությունների պատմություն չկա։
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.person-avatar-image,
.person-avatar-placeholder {
    align-items: center;
    border-radius: 10px;
    display: flex;
    flex: 0 0 84px;
    height: 84px;
    justify-content: center;
    overflow: hidden;
    width: 84px;
}

.person-avatar-image img {
    height: 100%;
    object-fit: cover;
    width: 100%;
}

.person-avatar-placeholder {
    font-size: 2rem;
    font-weight: 700;
    text-transform: uppercase;
}

.action-panel,
.metric-box,
.membership-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
}

.action-panel {
    min-width: 260px;
    padding: 1rem;
}

.membership-card {
    padding: 1rem;
}

.metric-box {
    padding: .875rem 1rem;
    height: 100%;
}

.empty-state {
    align-items: center;
    background: var(--bs-body-bg);
    border: 1px dashed var(--bs-border-color);
    border-radius: 10px;
    color: var(--bs-secondary-color);
    display: flex;
    justify-content: center;
    min-height: 120px;
    padding: 1rem;
    text-align: center;
}
</style>
