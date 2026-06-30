<script setup>
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, router, usePage } from "@inertiajs/vue3";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";
import TableFilter from "@/Components/TableFilter.vue";
import { useAuth } from "@/composables/useAuth";

const props = defineProps({
    people: Object,
});

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? "hy");
const { hasRole, hasAnyRole } = useAuth();
const canManagePeople = computed(() =>
    hasAnyRole(["sales_manager", "super_admin"]),
);
const canManagePersonVisits = computed(() =>
    hasAnyRole(["manager", "sales_manager", "super_admin"]),
);

const peopleList = ref(props.people.data);
const pagination = ref(props.people);
const filters = ref({
    date_field: "created_at",
    ...Object.fromEntries(new URLSearchParams(window.location.search)),
});
const peopleTypes = [
    { value: "visitor", label: "Այցելու" },
    { value: "guest", label: "Հյուր" },
];
const peopleFilterSelectFields = computed(() => [
    {
        name: "type",
        label: "Տեսակ",
        placeholder: "Բոլորը",
        options: peopleTypes,
    },
    {
        name: "has_membership",
        label: "Աբոնեմենտ",
        placeholder: "Բոլորը",
        options: [
            { value: "with", label: "Աբոնեմենտ ունեցողներ" },
            { value: "without", label: "Առանց աբոնեմենտի" },
        ],
    },
]);
const peopleFilterDateFields = [
    { value: "birth_date", label: "Ծննդյան ամսաթիվ" },
    { value: "created_at", label: "Ստեղծման ամսաթիվ" },
];
const personTypeLabel = type => ({
    visitor: "Այցելու",
    guest: "Հյուր",
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

    return names.length ? names.join(", ") : "Ոչ";
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
</script>

<template>
    <Head title="Անձինք" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Անձինք
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
                <h5 class="mb-0">Անձինք</h5>
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
                                Ավելացնել նոր անձ
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
                                <th>Անուն</th>
                                <th>Ազգանուն</th>
                                <th>Էլ. հասցե</th>
                                <th>Հեռախոս</th>
                                <th>Ծննդյան ամսաթիվ</th>
                                <th>Տեսակ</th>
                                <th>Աբոնեմենտ</th>
                                <th v-if="hasRole('owner')">
                                    Մարզասրահ(ներ)
                                </th>
                                <th>Գործողություններ</th>
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
                                                Դիտել պրոֆիլը
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
                                                Վաճառել աբոնեմենտ
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
                                                Այցելությունների կառավարում
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
                                                Խմբագրել
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
