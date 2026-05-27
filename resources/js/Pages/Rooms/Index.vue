<script setup>
import { ref, watch, computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";
import DeleteButton from "@/Components/DeleteButton.vue";


const props = defineProps({
    rooms: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
            from: 0,
            to: 0,
        }),
    },
    roomTypes: {
        type: [Array, Object],
        default: () => [],
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const localRooms = ref([...(props.rooms.data ?? [])]);
const roomTypesList = computed(() => {
    return Array.isArray(props.roomTypes)
        ? props.roomTypes
        : (props.roomTypes?.data ?? []);
});
const pagination = ref(props.rooms);

watch(
    () => props.rooms,
    (newValue) => {
        pagination.value = newValue;
        localRooms.value = newValue.data ?? [];
    },
    { immediate: true },
);

const search = ref("");
const selectedFloor = ref("");
const selectedStatus = ref("");
const selectedType = ref("");

const getRoomTypeName = (room) => {
    return (
        room.room_type_name || room.room_type?.name || room.type?.name || "—"
    );
};

const getRoomNumber = (room) => {
    return room.room_number || room.number || room.id;
};

const getRoomStatusLabel = (room) => {
    if (room.booking_status_label) return room.booking_status_label;
    console.log(room);

    if (room.room_state) return room.room_state;

    if (room.is_active === 1 && room.status === "available") return "Available";

    if (room.is_active === 1 && room.status === "occupied") return "Occupied";

    if (room.is_active === 1 && room.status === "reserved") return " Reserved";

    if (room.is_active === 1 && room.status === "cleaning") return "Cleaning";

    if (room.is_active === 0) return "Repair / Inactive";

    return "Active";
};

const getStatusClass = (status) => {
    const value = String(status).toLowerCase();
    console.log("Determining status class for value:", value);
    if (
        value.includes("repair / inactive") ||
        value.includes("maintenance") ||
        value.includes("inactive")
    ) {
        return "status-repair";
    }
    if (value.includes("available")) {
        return "status-available";
    }

    if (value.includes("occupied")) {
        return "status-occupied";
    }

    if (value.includes("reserved")) {
        return "status-reserved";
    }

    if (value.includes("cleaning")) {
        return "status-cleaning";
    }

    return "status-default";
};

const floors = computed(() => {
    const uniqueFloors = [
        ...new Set(
            localRooms.value
                .map((room) => room.floor)
                .filter(
                    (floor) =>
                        floor !== null && floor !== undefined && floor !== "",
                ),
        ),
    ];
    return uniqueFloors.sort((a, b) => Number(a) - Number(b));
});

const getRoomTypeValue = (room) => {
    return String(
        room.room_type_id || room.room_type?.id || room.type?.id || "",
    );
};

const filteredRooms = computed(() => {
    return localRooms.value.filter((room) => {
        const roomNumber = String(getRoomNumber(room)).toLowerCase();
        const roomSlug = String(room.slug || "").toLowerCase();
        const roomType = String(getRoomTypeName(room)).toLowerCase();
        const roomStatus = String(getRoomStatusLabel(room)).toLowerCase();
        const roomFloor = String(room.floor ?? "");
        const roomTypeValue = getRoomTypeValue(room);

        const matchesSearch =
            !search.value ||
            roomNumber.includes(search.value.toLowerCase()) ||
            roomSlug.includes(search.value.toLowerCase()) ||
            roomType.includes(search.value.toLowerCase());

        const matchesFloor =
            !selectedFloor.value || roomFloor === String(selectedFloor.value);

        const matchesStatus =
            !selectedStatus.value ||
            roomStatus.includes(selectedStatus.value.toLowerCase());

        const matchesType =
            !selectedType.value || roomTypeValue === String(selectedType.value);

        return matchesSearch && matchesFloor && matchesStatus && matchesType;
    });
});

const stats = computed(() => {
    const total = localRooms.value.length;

    const available = localRooms.value.filter((room) => {
        const status = String(getRoomStatusLabel(room)).toLowerCase();
        return (
            status.includes("available") ||
            room.status === true ||
            room.status === 1
        );
    }).length;

    const occupied = localRooms.value.filter((room) => {
        console.log("room status occupied:", getRoomStatusLabel(room));

        const status = String(getRoomStatusLabel(room)).toLowerCase();
        return status.includes("occupied");
    }).length;

    const cleaning = localRooms.value.filter((room) => {
        const status = String(getRoomStatusLabel(room)).toLowerCase();
        return status.includes("cleaning") || status.includes("housekeeping");
    }).length;

    const repair = localRooms.value.filter((room) => {
        const status = String(getRoomStatusLabel(room)).toLowerCase();
        return (
            status.includes("repair") ||
            status.includes("maintenance") ||
            status.includes("inactive")
        );
    }).length;

    const reserved = localRooms.value.filter((room) => {
        const status = String(getRoomStatusLabel(room)).toLowerCase();
        return status.includes("reserved");
    }).length;

    return {
        total,
        available,
        occupied,
        cleaning,
        reserved,
        repair,
    };
});

const selectStatus = (status) => {
    if (status === "total") {
        selectedStatus.value = "";
        return;
    }

    selectedStatus.value = status;
};

const removeRoomType = (id) => {
    localRooms.value = localRooms.value.filter((item) => item.id !== id);
};
</script>

<template>
    <AppLayout>
        <div class="rooms-page">
            <div class="rooms-header">
                <div>
                    <h2 class="page-title">Room Management</h2>
                    <p class="page-subtitle">Total rooms: {{ stats.total }}</p>
                </div>

                <Link
                    :href="route('rooms.create', { locale: currentLocale })"
                    class="btn btn-primary create-btn"
                >
                    <i class="icon-base ti tabler-plus me-1"></i>
                    Add Room
                </Link>
            </div>

            <div class="stats-grid">
                <div
                    class="stat-card stat-total"
                    :class="{ active: selectedStatus === 'total' }"
                    @click="selectStatus('total')"
                >
                    <div class="stat-number">{{ stats.total }}</div>
                    <div class="stat-label">Total</div>
                </div>

                <div
                    class="stat-card stat-available"
                    :class="{ active: selectedStatus === 'available' }"
                    @click="selectStatus('available')"
                >
                    <div class="stat-number">{{ stats.available }}</div>
                    <div class="stat-label">Available</div>
                </div>

                <div
                    class="stat-card stat-occupied"
                    :class="{ active: selectedStatus === 'occupied' }"
                    @click="selectStatus('occupied')"
                >
                    <div class="stat-number">{{ stats.occupied }}</div>
                    <div class="stat-label">Occupied</div>
                </div>

                <div
                    class="stat-card stat-cleaning"
                    :class="{ active: selectedStatus === 'cleaning' }"
                    @click="selectStatus('cleaning')"
                >
                    <div class="stat-number">{{ stats.cleaning }}</div>
                    <div class="stat-label">Cleaning</div>
                </div>

                <div
                    class="stat-card stat-reserved"
                    :class="{ active: selectedStatus === 'reserved' }"
                    @click="selectStatus('reserved')"
                >
                    <div class="stat-number">{{ stats.reserved }}</div>
                    <div class="stat-label">Reserved</div>
                </div>

                <div
                    class="stat-card stat-repair"
                    :class="{ active: selectedStatus === 'repair' }"
                    @click="selectStatus('repair')"
                >
                    <div class="stat-number">{{ stats.repair }}</div>
                    <div class="stat-label">Repair / Inactive</div>
                </div>
            </div>

            <div class="filters-card">
                <div class="filters-grid">
                    <div class="search-box">
                        <i class="icon-base ti tabler-search search-icon"></i>
                        <input
                            v-model="search"
                            type="text"
                            class="form-control custom-input"
                            placeholder="Search by room number, slug, type..."
                        />
                    </div>

                    <select
                        v-model="selectedFloor"
                        class="form-select custom-select"
                    >
                        <option value="">All floors</option>
                        <option
                            v-for="floor in floors"
                            :key="floor"
                            :value="floor"
                        >
                            Floor {{ floor }}
                        </option>
                    </select>

                    <select
                        v-model="selectedType"
                        class="form-select custom-select"
                    >
                        <option value="">All types</option>
                        <option
                            v-for="type in roomTypesList"
                            :key="type.id"
                            :value="type.id"
                        >
                            {{ type.name || type.slug }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="rooms-grid">
                <div
                    v-for="room in filteredRooms"
                    :key="room.id"
                    class="room-card"
                    :class="getStatusClass(getRoomStatusLabel(room))"
                >
                    <div class="room-card-top">
                        <div class="room-icon">
                            <i class="icon-base ti tabler-bed"></i>
                        </div>

                        <div class="room-menu dropdown">
                            <button
                                class="btn btn-sm p-0 border-0 shadow-none"
                                type="button"
                                data-bs-toggle="dropdown"
                            >
                                <i
                                    class="icon-base ti tabler-dots-vertical"
                                ></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end">
                                <Link
                                    class="dropdown-item"
                                    :href="
                                        route('rooms.edit', {
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

                                <!-- <Link
                                    class="dropdown-item"
                                    :href="
                                        route('rooms.destroy', {
                                            locale: currentLocale,
                                            id: room.id,
                                        })
                                    "
                                >
                                    <i class="icon-base ti tabler-trash me-1"></i>
                                    Delete
                                </Link> -->
                                <DeleteButton
                                    :model="'room'"
                                    :model-id="room.id"
                                    @deleted="removeRoomType($event)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="room-number">
                        {{ getRoomNumber(room) }}
                    </div>

                    <div class="room-type">
                        {{ getRoomTypeName(room) }}
                    </div>

                    <div class="room-price">
                        {{
                            room.price
                                ? "$" + Number(room.price).toLocaleString()
                                : "$0"
                        }}/night
                    </div>

                    <div class="room-meta">
                        <span>Floor: {{ room.floor ?? "—" }}</span>
                    </div>

                    <div class="room-status">
                        <span
                            class="status-badge"
                            :class="getStatusClass(getRoomStatusLabel(room))"
                        >
                            {{ getRoomStatusLabel(room) }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="!filteredRooms.length" class="empty-state">
                No rooms found
            </div>

            <div class="pagination-wrapper" v-if="pagination?.links?.length">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </AppLayout>
</template>

<style src="/resources/css/rooms/index.css"></style>
