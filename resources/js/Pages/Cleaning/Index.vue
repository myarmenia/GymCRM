<script setup>
import { ref, watch, computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";
import Pagination from "@/Components/Pagination.vue";

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
    cleaningTasks: {
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
console.log("tasks", props.rooms);

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const localRooms = ref([...(props.rooms.data ?? [])]);
const localCleaningTasks = ref([...(props.cleaningTasks ?? [])]);

const selectedType = ref("rooms");

watch(
    () => props.rooms,
    (newValue) => {
        localRooms.value = newValue.data ?? [];
    },
    { immediate: true },
);

watch(
    () => props.cleaningTasks,
    (newValue) => {
        localCleaningTasks.value = newValue ?? [];
    },
    { immediate: true },
);

const getRoomNumber = (room) => {
    return room.room_number || room.number || room.id;
};


const getCleanerName = (cleaner) => {
    return (
        `${cleaner?.name ?? ""} ${cleaner?.surname ?? ""}`.trim() ||
        cleaner?.email ||
        "—"
    );
};

const roomCleaningsCount = computed(() => localRooms.value.length);
const cleaningTasksCount = computed(() => localCleaningTasks.value.length);

const selectType = (type) => {
    selectedType.value = type;
};

const visibleRooms = computed(() => {
    return selectedType.value === "rooms" ? localRooms.value : [];
});

const visibleTasks = computed(() => {
    return selectedType.value === "tasks" ? localCleaningTasks.value : [];
});

const getFirstCleaner = (room) => {
    return room.cleaners?.[0] ?? "pending";
};

console.log("visibleRooms",visibleRooms);


const getStatusLabel = (status) => {
    if (status === 1 || status === "1") return "Active";
    if (status === 0 || status === "0") return "Inactive";

    return status ?? "pending";
};

const getStatusClass = (status) => {
    if (status === 1 || status === "1") return "status-active";
    if (status === 0 || status === "0") return "status-inactive";

    return "status-default";
};
</script>

<template>
    <AppLayout>
        <div class="rooms-page">
            <div class="status-legend">
                <div class="legend-item">
                    <span class="legend-color passive"></span>
                    <span class="legend-text">Passive</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color pending"></span>
                    <span class="legend-text">Pending</span>
                </div>

                <div class="legend-item">
                    <span class="legend-color in-progress"></span>
                    <span class="legend-text">In Progress</span>
                </div>

                <div class="legend-item">
                    <span class="legend-color completed"></span>
                    <span class="legend-text">Completed</span>
                </div>
            </div>
            <div class="rooms-header">
                <div>
                    <h2 class="page-title">Cleanings</h2>
                    <p class="page-subtitle">
                        Room Cleanings: {{ roomCleaningsCount }} | Cleaning
                        Tasks: {{ cleaningTasksCount }}
                    </p>
                </div>

                <Link
                    :href="
                        route('cleaning.cleaner_tasks', {
                            locale: currentLocale,
                        })
                    "
                    class="btn btn-primary create-btn"
                >
                    <i class="icon-base ti tabler-plus me-1"></i>
                    Add Cleaning Task
                </Link>
            </div>

            <div class="stats-grid">
                <div
                    class="stat-card stat-total"
                    :class="{ active: selectedType === 'rooms' }"
                    @click="selectType('rooms')"
                >
                    <div class="stat-number">{{ roomCleaningsCount }}</div>
                    <div class="stat-label">Cleanings Rooms</div>
                </div>

                <div
                    class="stat-card stat-tasks"
                    :class="{ active: selectedType === 'tasks' }"
                    @click="selectType('tasks')"
                >
                    <div class="stat-number">{{ cleaningTasksCount }}</div>
                    <div class="stat-label">Cleaning Tasks</div>
                </div>
            </div>

            <div v-if="selectedType === 'rooms'" class="rooms-grid">
                <div
                    v-for="room in visibleRooms"
                    :key="room.id"
                    class="room-card"
                    :class="
                        room.cleaners?.length
                            ? room.cleaning_status === 'pending'
                                ? 'room-type-pending-with-cleaner'
                                : 'room-type-' + room.cleaning_status
                            : 'room-type-pending'
                    "
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
                                        route('cleaning.attach_cleaner', {
                                            locale: currentLocale,
                                            roomId: room.id,
                                        })
                                    "
                                >
                                    <i
                                        class="icon-base ti tabler-pencil me-1"
                                    ></i>
                                    Attach Cleaner
                                </Link>
                                <Link
                                    class="dropdown-item"
                                    method="post"
                                    v-if="room.cleaning_status === 'completed'"
                                    as="button"
                                    :href="
                                        route('rooms.updateRoomStatus', {
                                            locale: currentLocale,
                                        })
                                    "
                                    :data="{
                                        id: room.id,
                                        status: 'available',
                                    }"
                                >
                                    <i
                                        class="icon-base ti tabler-check me-1"
                                        style="color: green"
                                    ></i>
                                    Done
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="room-number">
                        {{ getRoomNumber(room) }}
                    </div>

                    <div class="room-meta">
                        <span>Floor: {{ room.floor ?? "—" }}</span>
                    </div>

                    <div class="room-type">
                        Cleaner:
                        {{ getCleanerName(getFirstCleaner(room)) }}
                    </div>

                    <div class="room-status">
                        <span
                            class="status-badge"
                            :class="
                                'room-type-' +
                                (room.cleaning_status ?? 'pending')
                            "
                        >
                            {{
                                room.cleaning_status === "pending" &&
                                !room.cleaners?.length
                                    ? "passive"
                                    : (room.cleaning_status ?? "pending")
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="selectedType === 'tasks'" class="tasks-list">
                <div
                    v-for="task in visibleTasks"
                    :key="task.id"
                    class="task-card"
                    :class="'task-type-' + (task.status ?? 'pending')"
                >
                    <div class="task-icon">
                        <i class="icon-base ti tabler-clipboard-check"></i>
                    </div>

                    <div class="task-content">
                        <div class="task-title">
                            {{ getCleanerName(task.cleaner) }}
                        </div>

                        <div class="task-description">
                            {{ task.description ?? "No description" }}
                        </div>
                    </div>

                    <span
                        class="status-badge"
                        :class="'task-type-' + (task.status ?? 'pending')"
                    >
                        {{ getStatusLabel(task.status) }}
                    </span>
                </div>
            </div>

            <div
                v-if="selectedType === 'rooms' && !visibleRooms.length"
                class="empty-state"
            >
                No room cleanings found
            </div>

            <div
                v-if="selectedType === 'tasks' && !visibleTasks.length"
                class="empty-state"
            >
                No cleaning tasks found
            </div>
        </div>
    </AppLayout>
</template>

<style src="/resources/css/cleanings/index.css"></style>
