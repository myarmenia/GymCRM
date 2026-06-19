<script setup>
import { ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import Pagination from "@/Components/Pagination.vue";
import { useAuth } from "@/composables/useAuth";

const props = defineProps({
    users: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const usersList = ref(props.users.data);
const pagination = ref(props.users);
const { hasAnyRole } = useAuth();
</script>

<template>
    <Head title="Մարզիչների ցուցակ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Մարզիչների ցուցակ
            </h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Մարզիչների ցուցակ</h5>
            </div>
            <h5 class="card-header">Մարզիչների աղյուսակ</h5>
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Անուն</th>
                                <th>Ազգանուն</th>
                                <th>Հեռախոս</th>
                                <th>Էլ․ հասցե</th>
                                <th>Գործողություններ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in usersList"
                                :key="user.id"
                            >
                                <td>{{ user.id }}</td>
                                <td>{{ user.name }}</td>
                                <td>{{ user.surname }}</td>
                                <td>{{ user.phone }}</td>
                                <td>{{ user.email }}</td>
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
                                                :href="route('trainer.profile', {
                                                    locale: currentLocale,
                                                    id: user.id,
                                                })"
                                            >
                                                <i class="icon-base ti tabler-eye me-1"></i>
                                                Դիտել պրոֆիլը
                                            </Link>
                                            <Link
                                                v-if="hasAnyRole(['owner', 'super_admin'])"
                                                class="dropdown-item waves-effect"
                                                :href="route('trainer.edit', {
                                                    locale: currentLocale,
                                                    id: user.id,
                                                })"
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Խմբագրել
                                            </Link>
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
