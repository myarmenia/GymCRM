<script setup>
import { ref, watch, computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
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
const showConfirmModal = ref(false);
const pendingStatusData = ref({
    id: null,
    status: null,
    type: null,
});
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

const roomCleaningsCount = computed(() => localRooms.value.length);
const cleaningTasksCount = computed(() => localCleaningTasks.value.length);

const selectType = (type) => {
    selectedType.value = type;
};

const visibleRooms = computed(() => {
    return selectedType.value === "rooms" ? localRooms.value : [];
});

const openStatusModal = (id, status, type) => {
    pendingStatusData.value = {
        id,
        status,
        type,
    };

    showConfirmModal.value = true;
};

const confirmStatusUpdate = async () => {
    try {
        const { id, status, type } = pendingStatusData.value;

        // if (type === "room") {
        const room = localRooms.value.find((r) => r.id === id);

        if (room) {
            room.status = status;
        }

        router.put(
            route("task.task.updateStatus", {
                id: id,
                status: status,
                locale: currentLocale,
            }),
            {
                status,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                },
                onError: (errors) => {
                    console.error(errors);
                },
            },
        );
        // }

        // if (type === "task") {
        //     const task = localCleaningTasks.value.find((t) => t.id === id);

        //     if (task) {
        //         task.status = status;
        //     }

        //     await axios.put(`/cleaning-tasks/${id}/status`, {
        //         status,
        //     });
        // }

        closeModal();
    } catch (error) {
        console.error("Status update failed", error);
    }
};

const closeModal = () => {
    showConfirmModal.value = false;

    pendingStatusData.value = {
        id: null,
        status: null,
        type: null,
    };
};

const visibleTasks = computed(() => {
    return selectedType.value === "tasks" ? localCleaningTasks.value : [];
});
</script>

<template>
    <AppLayout>
        <div class="rooms-page">
            <div class="status-legend">
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
                    :class="'room-type-' + room.status"
                >
                    <div class="room-card-top">
                        <div class="room-icon">
                            <i class="icon-base ti tabler-bed"></i>
                        </div>
                    </div>

                    <div class="room-number">
                        {{ room.room.number }}
                    </div>

                    <div class="room-meta">
                        <span>Floor: {{ room.room.floor ?? "—" }}</span>
                    </div>

                    <div class="task-content">
                        <div class="task-description">
                            {{ room.description ?? "No description" }}
                        </div>
                    </div>

                    <!-- <div class="room-status"> -->
                    <div class="room-status">
                        <select
                            class="status-badge"
                            :class="'room-type-' + (room.status ?? 'pending')"
                            :value="room.status ?? 'pending'"
                            @change="
                                openStatusModal(
                                    room.id,
                                    $event.target.value,
                                    'room',
                                )
                            "
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <!-- </div> -->
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
                        <div class="task-description">
                            {{ task.description ?? "No description" }}
                        </div>
                    </div>

                    <select
                        class="status-badge"
                        :class="'room-type-' + (task.status ?? 'pending')"
                        :value="task.status ?? 'pending'"
                        @change="
                            openStatusModal(
                                task.id,
                                $event.target.value,
                                'task',
                            )
                        "
                    >
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
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

        <!-- Confirm Modal -->
        <div v-if="showConfirmModal" class="modal-overlay">
            <div class="confirm-modal">
                <h3>Confirm Status Change</h3>

                <p>Are you sure you want to change status?</p>

                <div class="modal-actions">
                    <button class="btn-cancel" @click="closeModal">
                        Cancel
                    </button>

                    <button class="btn-confirm" @click="confirmStatusUpdate">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style src="/resources/css/tasks/index.css"></style>
