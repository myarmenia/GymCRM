<script setup>
import { ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import DeleteButton from "@/Components/DeleteButton.vue";
import ToggleStatus from "@/Components/ToggleStatus.vue";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    roomTypes: {
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

const localRoomTypes = ref([...(props.roomTypes.data ?? [])]);
console.log(localRoomTypes.value);
const pagination = props.roomTypes;
watch(
    () => props.roomTypes,
    (newValue) => {
        pagination.value = newValue;
        localRoomTypes.value = newValue.data ?? [];
    },
    { immediate: true },
);

const removeRoomType = (id) => {
    localRoomTypes.value = localRoomTypes.value.filter(
        (item) => item.id !== id,
    );
};
</script>

<template>
    <AppLayout>
        <div class="card custom-card">
                <div class="card-header custom-card-header flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Room Types List</h5>
                    </div>

                    <Link
                        :href="
                            route('room-types.create', {
                                locale: currentLocale,
                            })
                        "
                        class="btn btn-primary add-btn"
                    >
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                        Add Room Type
                    </Link>
                </div>

            <div class="table-responsive text-nowrap">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ROOM TYPE</th>
                            <th>PRICE</th>
                            <th>PRICE PER HOUR</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>

                    <tbody class="table-border-bottom-0">
                        <tr v-for="room in localRoomTypes" :key="room.id">
                            <td>{{ room.id }}</td>

                            <td>
                                <i
                                    class="icon-base ti tabler-bed icon-md text-primary me-4"
                                ></i>
                                <span class="fw-medium">
                                    {{ room.name || room.slug }}
                                </span>
                            </td>

                            <td>
                                {{ Number(room.price).toLocaleString() }} AMD
                            </td>

                            <td>
                                {{
                                    Number(room.price_per_hour).toLocaleString()
                                }}
                                AMD
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
                                        <div class="dropdown-item">
                                            <ToggleStatus
                                                :model="'room-types'"
                                                :model-id="room.id"
                                                :active="room.status"
                                                :label="'Status'"
                                                @update="room.status = $event"
                                            />
                                        </div>

                                        <Link
                                            class="dropdown-item"
                                            :href="
                                                route('room-types.edit', {
                                                    locale: currentLocale,
                                                    id: room.id,
                                                })
                                            "
                                        >
                                            <i
                                                class="icon-base ti tabler-pencil me-1"
                                            ></i>
                                            Edit
                                        </Link>

                                        <div class="dropdown-item">
                                            <DeleteButton
                                                :model="'room-types'"
                                                :model-id="room.id"
                                                @deleted="
                                                    removeRoomType($event)
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!localRoomTypes.length">
                            <td colspan="5" class="text-center py-4">
                                No room types found
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="card-footer custom-card-footer">
                    <Pagination :links="pagination.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
