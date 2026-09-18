<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, router, usePage } from "@inertiajs/vue3";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";
import TableFilter from "@/Components/TableFilter.vue";
import { useAuth } from "@/composables/useAuth";
import { useToast } from "vue-toastification";
import { translate } from '/resources/js/trans';

const props = defineProps({
    people: Object,
});

const page = usePage();
const t = (key, replacements = {}) => translate(page.props.translations, `app.people.${key}`, replacements);
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? "hy");
const { hasRole, hasAnyRole } = useAuth();
const canManagePeople = computed(() =>
    hasAnyRole(["sales_manager", "super_admin"]),
);
const canManagePersonVisits = computed(() =>
    hasAnyRole(["manager", "sales_manager", "super_admin"]),
);
const canManualScan = computed(() =>
    hasRole("manager") &&
    Boolean(page.props.auth?.user?.gym_id),
);
const toast = useToast();
const manualScanCode = ref("");
const manualScanInput = ref(null);
const manualScanProcessing = ref(false);

const peopleList = ref(props.people.data);
const pagination = ref(props.people);
const filters = ref({
    date_field: "created_at",
    ...Object.fromEntries(new URLSearchParams(window.location.search)),
});
const peopleTypes = computed(() => [
    { value: "visitor", label: t('visitor') },
    { value: "guest", label: t('guest') },
]);
const peopleFilterSelectFields = computed(() => [
    {
        name: "type",
        label: t('type'),
        placeholder: t('all'),
        options: peopleTypes.value,
    },
    {
        name: "has_membership",
        label: t('membership'),
        placeholder: t('all'),
        options: [
            { value: "with", label: t('with_membership') },
            { value: "without", label: t('without_membership') },
        ],
    },
]);
const peopleFilterDateFields = computed(() => [
    { value: "birth_date", label: t('birth_date') },
    { value: "created_at", label: t('created_at') },
]);
const personTypeLabel = type => ({
    visitor: t('visitor'),
    guest: t('guest'),
}[type] ?? type ?? "-");

const personTypeClass = type => ({
    visitor: "bg-label-secondary",
    guest: "bg-label-info",
}[type] ?? "bg-label-secondary");
const planName = plan => {
    return plan?.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan?.name
        ?? (plan?.id ? `#${plan.id}` : null);
};
const membershipNames = person => {
    const names = (person.active_memberships ?? [])
        .map(membership => planName(membership.membership_plan))
        .filter(Boolean);

    return names.length ? names.join(", ") : t('no');
};

watch(
    () => props.people,
    (people) => {
        peopleList.value = people.data;
        pagination.value = people;
    },
);

const applyFilters = (payload) => {
    router.get(
        route("person.list", { locale: currentLocale.value }),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filters.value = {
        date_field: "created_at",
    };

    router.get(
        route("person.list", { locale: currentLocale.value }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

onMounted(async () => {
    if (!canManualScan.value) {
        return;
    }

    await nextTick();
    manualScanInput.value?.focus();
});

const submitManualScan = async () => {
    const token = manualScanCode.value.trim().split("#")[0];

    if (!token || manualScanProcessing.value) {
        return;
    }

    manualScanProcessing.value = true;

    try {
        await axios.post(route("person.manual-scan", { locale: currentLocale.value }), {
            direction: "enter",
            entry_code: `${token}#${Math.floor(Date.now() / 1000)}`,
            type: "rfId",
        });

        manualScanCode.value = "";
        await nextTick();
        manualScanInput.value?.focus();
    } catch (error) {
        toast.error(error?.response?.data?.message ?? t('scan_failed'));
    } finally {
        manualScanProcessing.value = false;
    }
};
</script>

<template>
    <Head :title="t('clients')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('clients') }}
            </h2>
        </template>

        <TableFilter
            v-model="filters"
            name-mode="separate"
            :select-fields="peopleFilterSelectFields"
            :date-fields="peopleFilterDateFields"
            @filter="applyFilters"
            @reset="resetFilters"
        />

        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <div class="d-flex align-items-center gap-2 flex-wrap">
                <h5 class="mb-0">{{ t('clients') }}</h5>
                    <form
                        v-if="canManualScan"
                        class="d-flex align-items-center gap-2"
                        @submit.prevent="submitManualScan"
                    >
                        <input
                            ref="manualScanInput"
                            v-model="manualScanCode"
                            type="text"
                            class="form-control form-control-sm manual-scan-input"
                            :placeholder="t('scan_entry_code')"
                            :disabled="manualScanProcessing"
                            autocomplete="off"
                            autofocus
                        >
                    </form>
                </div>
                <Link
                    v-if="canManagePeople"
                    class="btn create-new btn-primary"
                    tabindex="0"
                    aria-controls="DataTables_Table_0"
                    type="button"
                    :href="route('person.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block">
                                {{ t('add_person') }}
                            </span>
                        </span>
                    </span>
                </Link>
            </div>
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ t('name') }}</th>
                                <th>{{ t('surname') }}</th>
                                <th>{{ t('email') }}</th>
                                <th>{{ t('phone') }}</th>
                                <th>{{ t('birth_date') }}</th>
                                <th>{{ t('type') }}</th>
                                <th>{{ t('membership') }}</th>
                                <th v-if="hasRole('owner')">
                                    {{ t('gyms') }}
                                </th>
                                <th>{{ t('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="person in peopleList" :key="person.id">
                                <td>{{ person.id }}</td>
                                <td>{{ person.name || '-' }}</td>
                                <td>{{ person.surname || '-' }}</td>
                                <td>{{ person.email || '-' }}</td>
                                <td>{{ person.phone || '-' }}</td>
                                <td>{{ person.birth_date || '-' }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="personTypeClass(person.type)"
                                    >
                                        {{ personTypeLabel(person.type) }}
                                    </span>
                                </td>
                                <td>
                                    {{ membershipNames(person) }}
                                </td>
                                <td v-if="hasRole('owner')">
                                    <span v-if="person.gyms && person.gyms.length">
                                        {{ person.gyms.map(g => g.name).join(', ') }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button
                                            type="button"
                                            class="btn p-0 dropdown-toggle hide-arrow"
                                            data-bs-toggle="dropdown"
                                        >
                                            <i class="icon-base ti tabler-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('person.profile', {
                                                        locale: currentLocale,
                                                        id: person.id,
                                                    })
                                                "
                                            >
                                                <i class="icon-base ti tabler-eye me-1"></i>
                                                {{ t('view_profile') }}
                                            </Link>
                                            <Link
                                                v-if="canManagePeople"
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('membership_sale.create', {
                                                        locale: currentLocale,
                                                        person: person.id,
                                                    })
                                                "
                                            >
                                                <i class="icon-base ti tabler-credit-card-pay me-1"></i>
                                                {{ t('sell_membership') }}
                                            </Link>
                                            <Link
                                                v-if="canManagePersonVisits"
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('person.visits', {
                                                        locale: currentLocale,
                                                        id: person.id,
                                                    })
                                                "
                                            >
                                                <i class="icon-base ti tabler-walk me-1"></i>
                                                {{ t('visits_management') }}
                                            </Link>
                                            <Link
                                                v-if="canManagePeople"
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('person.edit', {
                                                        locale: currentLocale,
                                                        id: person.id,
                                                    })
                                                "
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                {{ t('edit') }}
                                            </Link>
                                            <a
                                                v-if="canManagePeople"
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <DeleteButton
                                                    :model="'people'"
                                                    :prefix="'tables'"
                                                    :model-id="person.id"
                                                    @deleted="
                                                        peopleList =
                                                            peopleList.filter(
                                                                (p) =>
                                                                    p.id !==
                                                                    $event,
                                                            )
                                                    "
                                                />
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>

<style scoped>
.manual-scan-input {
    min-width: 260px;
}

@media (max-width: 575.98px) {
    .manual-scan-input {
        min-width: 200px;
    }
}
</style>
