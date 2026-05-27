<script setup>
import AppLayout from "@/Layouts/Index.vue";
import DeleteButton from "@/Components/DeleteButton.vue";
import ToggleStatus from "@/Components/ToggleStatus.vue";
import Pagination from "@/Components/Pagination.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const props = defineProps({
    serviceTypes: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
            from: 0,
            to: 0,
        }),
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const pagination = ref(props.serviceTypes);
const localServiceTypes = ref(props.serviceTypes.data ?? []);

watch(
    () => props.serviceTypes,
    (newValue) => {
        pagination.value = newValue;
        localServiceTypes.value = newValue.data ?? [];
    },
    { immediate: true },
);

const removeServiceType = (id) => {
    localServiceTypes.value = localServiceTypes.value.filter(
        (item) => item.id !== id,
    );
};
</script>

<template>
    <AppLayout>
        <div class="service-types-page">
            <div class="card custom-card">
                <div class="card-header custom-card-header">
                    <div>
                        <h5 class="mb-0">Service Types List</h5>
                    </div>

                    <Link
                        :href="
                            route('service-types.create', {
                                locale: currentLocale,
                            })
                        "
                        class="btn btn-primary add-btn"
                    >
                        <i class="icon-base ti tabler-plus icon-sm"></i>
                        Add Service Type
                    </Link>
                </div>

                <div class="card-body p-6">
                    <div class="table-responsive">
                        <table
                            class="table custom-bordered-table align-middle mb-0"
                        >
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>GYM ID</th>
                                    <th>SERVICE TYPE</th>
                                    <th>CREATED AT</th>
                                    <th>UPDATED AT</th>
                                    <th>ACTIVE</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="service in localServiceTypes"
                                    :key="service.id"
                                >
                                    <td>{{ service.id }}</td>

                                    <td>{{ service.gym_id }}</td>

                                    <td>
                                        <div class="service-name-cell">
                                            <i
                                                class="icon-base ti tabler-building-store icon-md text-primary me-2"
                                            ></i>
                                            <span class="fw-medium">
                                                {{ service.name }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>{{ service.created_at }}</td>
                                    <td>{{ service.updated_at }}</td>

                                    <td>
                                        <span
                                            class="badge status-badge"
                                            :class="
                                                service.status
                                                    ? 'badge-active'
                                                    : 'badge-inactive'
                                            "
                                        >
                                            {{
                                                service.status
                                                    ? "Active"
                                                    : "Inactive"
                                            }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="dropdown">
                                            <button
                                                type="button"
                                                class="btn action-btn p-0 border-0 bg-transparent"
                                                data-bs-toggle="dropdown"
                                            >
                                                <i
                                                    class="icon-base ti tabler-dots-vertical"
                                                ></i>
                                            </button>

                                            <div
                                                class="dropdown-menu dropdown-menu-end"
                                            >
                                                <div class="dropdown-item">
                                                    <ToggleStatus
                                                        :model="'service-types'"
                                                        :model-id="service.id"
                                                        :active="service.status"
                                                        :label="'Status'"
                                                        @update="
                                                            service.status =
                                                                $event
                                                        "
                                                    />
                                                </div>

                                                <Link
                                                    class="dropdown-item"
                                                    :href="
                                                        route(
                                                            'service-types.edit',
                                                            {
                                                                locale: currentLocale,
                                                                id: service.id,
                                                            },
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="icon-base ti tabler-pencil me-1"
                                                    ></i>
                                                    Edit
                                                </Link>

                                                <div class="dropdown-item">
                                                    <DeleteButton
                                                        :model="'service-types'"
                                                        :model-id="service.id"
                                                        @deleted="
                                                            removeServiceType(
                                                                $event,
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!localServiceTypes.length">
                                    <td
                                        colspan="8"
                                        class="text-center py-4 empty-state"
                                    >
                                        No service types found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer custom-card-footer">
                    <Pagination :links="pagination.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style src="/resources/css/service-types/index.css"></style>
