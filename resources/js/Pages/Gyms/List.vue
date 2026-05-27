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
    gyms: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const gymsList = ref(props.gyms?.data ?? props.gyms ?? []);
const pagination = ref(props.gyms?.links ? props.gyms : null);
</script>

<template>
    <Head title="Gyms List" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Gyms List
            </h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Gyms List</h5>
                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    type="button"
                    :href="route('gym.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block">Add New Gym</span>
                        </span>
                    </span>
                </Link>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered align-middle"> <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 70px;">Logo</th> <th>Name</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="gym in gymsList" :key="gym.id">
                                <td>{{ gym.id }}</td>

                                <td class="text-center">
                                    <img
                                        v-if="gym.logo"
                                        :src="`/storage/${gym.logo}`"
                                        alt="Gym Logo"
                                        class="rounded-circle object-fit-cover"
                                        style="width: 40px; height: 40px; border: 1px solid #e5e7eb;"
                                    />
                                    <div
                                        v-else
                                        class="rounded-circle bg-label-secondary d-flex align-items-center justify-content-center mx-auto"
                                        style="width: 40px; height: 40px;"
                                    >
                                        <i class="ti tabler-building icon-base text-secondary fs-4"></i>
                                    </div>
                                </td>

                                <td>{{ gym.name }}</td>
                                <td>{{ gym.address }}</td>
                                <td>{{ gym.phone }}</td>
                                <td>{{ gym.email }}</td>

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
                                                :href="route('gym.edit', { locale: currentLocale, id: gym.id })"
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                Edit
                                            </Link>

                                            <a class="dropdown-item waves-effect" href="javascript:void(0);">
                                                <DeleteButton
                                                    :model="'gyms'"
                                                    :prefix="'gym'"
                                                    :locale="currentLocale"
                                                    :model-id="gym.id"
                                                    @deleted="
                                                        gymsList = gymsList.filter((h) => h.id !== $event)
                                                    "
                                                />
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="gymsList.length === 0">
                                <td colspan="7" class="text-center text-muted">No gyms found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="pagination && pagination.links" class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>
