<script setup>
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import ToggleStatus from "@/Components/ToggleStatus.vue";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";
import TableFilter from "@/Components/TableFilter.vue";
import { useAuth } from "@/composables/useAuth";

const props = defineProps({
    entryCodes: Object,
    gyms: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? "hy");
const { hasRole } = useAuth();

const entryCodesList = ref(props.entryCodes.data);
const pagination = ref(props.entryCodes);
const filters = ref(Object.fromEntries(new URLSearchParams(window.location.search)));

const entryCodeFilterSelectFields = computed(() => {
    const fields = [
        {
            name: "type",
            label: "Type",
            placeholder: "All types",
            options: [
                { value: "rfId", label: "RF ID" },
                { value: "FaceId", label: "Face ID" },
            ],
        },
    ];

    if (hasRole("owner")) {
        fields.push({
            name: "gym_id",
            label: "Gym",
            placeholder: "All gyms",
            options: props.gyms,
        });
    }

    fields.push({
        name: "status",
        label: "Status",
        placeholder: "All statuses",
        options: [
            { value: "1", label: useTrans("app.status.active") },
            { value: "0", label: useTrans("app.status.inactive") },
        ],
    });

    return fields;
});

watch(
    () => props.entryCodes,
    (entryCodes) => {
        entryCodesList.value = entryCodes.data;
        pagination.value = entryCodes;
    },
);

const applyFilters = (payload) => {
    router.get(
        route("entry-code.list", { locale: currentLocale.value }),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filters.value = {};

    router.get(
        route("entry-code.list", { locale: currentLocale.value }),
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
    <Head title="Մուտքի կոդեր" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Մուտքի կոդեր
            </h2>
        </template>

        <TableFilter
            v-model="filters"
            :text-fields="[]"
            :select-fields="entryCodeFilterSelectFields"
            :date-fields="[]"
            default-date-field=""
            @filter="applyFilters"
            @reset="resetFilters"
        />

        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">Մուտքի կոդերի ցանկ</h5>
                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    type="button"
                    :href="route('entry-code.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block"
                                >Ավելացնել նոր կոդ</span
                            >
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
                                <th>Թոքեն</th>
                                <th>Տեսակ</th>
                                <th>Մարզասրահ</th>
                                <th>Կարգավիճակ</th>
                                <th>Ակտիվացում</th>
                                <th>Գործողություններ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="code in entryCodesList" :key="code.id">
                                <td>{{ code.id }}</td>
                                <td>{{ code.token }}</td>
                                <td>{{ code.type }}</td>
                                <td>{{ code.gym?.name ?? '-' }}</td>
                                <!-- Status (active/inactive) -->
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="
                                            code.status
                                                ? 'bg-label-success'
                                                : 'bg-label-danger'
                                        "
                                    >
                                        {{
                                            code.status
                                                ? useTrans("app.status.active")
                                                : useTrans("app.status.inactive")
                                        }}
                                    </span>
                                </td>
                                <!-- Activation (additional flag) -->
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="
                                            code.activation
                                                ? 'bg-label-info'
                                                : 'bg-label-secondary'
                                        "
                                    >
                                        {{ code.activation ? 'Ակտիվացված է' : 'Ակտիվացված չէ' }}
                                    </span>
                                </td>
                                <!-- Actions -->
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
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);">
                                              <ToggleStatus
                                                    :model="'entry-codes'"
                                                    :model-id="code.id"
                                                    :prefix="'entry-code'"
                                                    :locale="currentLocale"
                                                    :active="Boolean(code.status)"
                                                    :column="'status'"
                                                    :label="''"
                                                    @update="code.status = $event"
                                                />
                                            </a>
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('entry-code.edit', {
                                                        locale: currentLocale,
                                                        id: code.id,
                                                    })
                                                "
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Խմբագրել
                                            </Link>
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);">
                                                <DeleteButton
                                                    :model="'entry-codes'"
                                                    :prefix="'tables'"
                                                    :model-id="code.id"
                                                    @deleted="
                                                        entryCodesList = entryCodesList.filter(
                                                            (c) => c.id !== $event
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
