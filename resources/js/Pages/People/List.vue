<script setup>
import { ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, usePage } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    people: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const peopleList = ref(props.people.data);
const pagination = ref(props.people);
</script>

<template>
    <Head title="Անձինք" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Անձինք
            </h2>
        </template>

        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">Անձինք</h5>
                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    aria-controls="DataTables_Table_0"
                    type="button"
                    :href="route('person.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block"
                                >Ավելացնել նոր անձ</span
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
                                <th>Անուն</th>
                                <th>Ազգանուն</th>
                                <th>Էլ. հասցե</th>
                                <th>Հեռախոս</th>
                                <th>Տեսակ</th>
                                <th>Մարզադահլիճ(ներ)</th>
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
                                <td>
                                    <span
                                        class="badge"
                                        :class="person.type === 'employee' ? 'bg-label-primary' : 'bg-label-secondary'"
                                    >
                                        {{ useTrans(`page.people.type.${person.type}`) || person.type === 'employee' ? 'Աշխատակից' : 'Այցելու' }}
                                    </span>
                                </td>
                                <td>
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