<script setup>
import { ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, usePage } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import ToggleStatus from "@/Components/ToggleStatus.vue";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    membershipPlans: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "hy";
const membershipPlansList = ref(props.membershipPlans.data);
const pagination = ref(props.membershipPlans);
const durationTypes = [
    { value: 'day', label: 'Օր' },
    { value: 'month', label: 'Ամիս' },
    { value: 'year', label: 'Տարի' },
    { value: 'visit', label: 'Անգամյա' },
    { value: 'period', label: 'Ժամանակահատված' },
]


function findingDurationType(value) {
    const type = durationTypes.find(type => type.value === value);
    return type ? type.label : value;
}
</script>

<template>
    <Head title="Աբոնեմենտներ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Աբոնեմենտներ
            </h2>
        </template>

        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">Ցանկ</h5>
                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    aria-controls="DataTables_Table_0"
                    type="button"
                    :href="route('membership_plan.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block"
                                >Ստեղծել նոր աբոնեմենտ</span
                            >
                        </span>
                    </span>
                </Link>
            </div>
            <h5 class="card-header">Աղյուսակ</h5>
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Անուն</th>
                                <th>Կատեգորիա</th>
                                <th>Արժեք</th>
                                <th>Տևողություն</th>
                                <th>has person</th>
                                <th>Կարգավիճակ</th>
                                <th>Գործողություններ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="membershipPlan in membershipPlansList" :key="membershipPlan.id">
                                <td>{{ membershipPlan.id }}</td>
                                <td>{{ membershipPlan.name }}</td>
                                <td>{{ membershipPlan.membership_category?.name }}</td>
                                <td>{{ membershipPlan.price }}</td>
                                <td>{{ membershipPlan.duration_value }} {{ findingDurationType(membershipPlan.duration_type) }}</td>
                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="
                                            membershipPlan.is_locked
                                                ? 'bg-label-success'
                                                : 'bg-label-danger'
                                        "
                                    >
                                        {{
                                            membershipPlan.is_locked
                                                ? 'yes'
                                                : 'no'
                                        }}
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="badge me-1"
                                        :class="
                                            membershipPlan.active
                                                ? 'bg-label-success'
                                                : 'bg-label-danger'
                                        "
                                    >
                                        {{
                                            membershipPlan.active
                                                ? useTrans("app.status.active")
                                                : useTrans(
                                                      "app.status.inactive",
                                                  )
                                        }}
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button
                                            type="button"
                                            class="btn p-0 dropdown-toggle hide-arrow"
                                            data-bs-toggle="dropdown"
                                        >
                                            <i
                                                class="icon-base ti tabler-dots-vertical"
                                            ></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a
                                                v-if="!membershipPlan.is_locked"
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <ToggleStatus
                                                    :model="'membership_plans'"
                                                    :model-id="membershipPlan.id"
                                                    :active="membershipPlan.active"
                                                    :prefix="'tables'"
                                                    :label="
                                                        useTrans(
                                                            'app.status.status',
                                                        )
                                                    "
                                                    @update="
                                                        membershipPlan.active = $event
                                                    "
                                                />
                                            </a>
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="
                                                    route('membership_plan.edit', {
                                                        locale: currentLocale,
                                                        id: membershipPlan.id,
                                                    })
                                                "
                                            >
                                                <i
                                                    class="icon-base ti tabler-pencil me-1"
                                                ></i>
                                                Edit
                                            </Link>
                                            <a
                                                v-if="!membershipPlan.is_locked"
                                                class="dropdown-item waves-effect"
                                                href="javascript:void(0);"
                                            >
                                                <DeleteButton
                                                    :model="'membership-plans'"
                                                    :model-id="membershipPlan.id"
                                                    :prefix="'tables'"
                                                    @deleted="
                                                        membershipPlansList =
                                                            membershipPlansList.filter(
                                                                (mp) =>
                                                                    mp.id !==
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
