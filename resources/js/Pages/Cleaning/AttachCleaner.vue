<script setup>
import { computed } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

const props = defineProps({
    roomId: Number,
    cleaners: {
        type: [Array, Object],
        default: () => [],
    },
    room: {
        type: [Array, Object],
        default: () => ({}),
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const currentRoom = computed(() => {
    return Array.isArray(props.room) ? props.room[0] : props.room;
});

const cleanersList = computed(() => {
    return Array.isArray(props.cleaners)
        ? props.cleaners
        : (props.cleaners?.data ?? []);
});

const attachedCleaners = computed(() => {
    return currentRoom.value?.cleaners ?? [];
});

const form = useForm({
    room_id: props.roomId,
    cleaners: attachedCleaners.value.reduce((result, cleaner) => {
        const exists = result.some((item) => item.user_id === cleaner.id);

        if (!exists) {
            result.push({
                user_id: cleaner.id,
                description: cleaner.pivot?.description ?? "",
            });
        }

        return result;
    }, []),
});

const getCleanerName = (cleaner) => {
    return (
        `${cleaner.name ?? ""} ${cleaner.surname ?? ""}`.trim() ||
        cleaner.email ||
        `Cleaner #${cleaner.id}`
    );
};

const isCleanerSelected = (cleanerId) => {
    return form.cleaners.some((item) => item.user_id === cleanerId);
};

const toggleCleaner = (cleanerId) => {
    const exists = form.cleaners.some((item) => item.user_id === cleanerId);

    if (exists) {
        form.cleaners = form.cleaners.filter(
            (item) => item.user_id !== cleanerId
        );

        return;
    }

    form.cleaners.push({
        user_id: cleanerId,
        description: "",
    });
};

const getCleanerDescription = (cleanerId) => {
    const cleaner = form.cleaners.find((item) => item.user_id === cleanerId);

    return cleaner ? cleaner.description : "";
};

const setCleanerDescription = (cleanerId, value) => {
    const cleaner = form.cleaners.find((item) => item.user_id === cleanerId);

    if (cleaner) {
        cleaner.description = value;
    }
};

const submit = () => {
    form.post(
        route("cleaning.attach_cleaner.store", {
            locale: currentLocale,
            roomId: props.roomId,
        }),
    );
};
</script>

<template>
    <AppLayout>
        <div class="attach-page">
            <div class="page-header">
                <div>
                    <h2 class="page-title">Attach Cleaner</h2>
                    <p class="page-subtitle">
                        Room:
                        <strong>{{ currentRoom?.number ?? "—" }}</strong>
                    </p>
                </div>

                <Link
                    :href="route('cleaning.index', { locale: currentLocale })"
                    class="btn btn-light back-btn"
                >
                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                    Back
                </Link>
            </div>

            <div class="form-card">
                <form @submit.prevent="submit">
                    <div class="room-info">
                        <div>
                            <span class="info-label">Room Number</span>
                            <h3 class="room-number">
                                {{ currentRoom?.number ?? "—" }}
                            </h3>
                        </div>

                        <div class="selected-count">
                            Selected: {{ form.cleaners.length }}
                        </div>
                    </div>

                    <div class="cleaners-section">
                        <div class="section-header">
                            <div class="col-cleaner">Cleaner</div>
                            <div class="col-count">Cleanings Count</div>
                            <div class="col-action">Select</div>
                        </div>

                        <div
                            v-for="cleaner in cleanersList"
                            :key="cleaner.id"
                            class="cleaner-row-wrapper"
                            :class="{ selected: isCleanerSelected(cleaner.id) }"
                        >
                            <div class="cleaner-row">
                                <div class="col-cleaner cleaner-user">
                                    <div class="avatar">
                                        {{ getCleanerName(cleaner).charAt(0) }}
                                    </div>

                                    <div>
                                        <div class="cleaner-name">
                                            {{ getCleanerName(cleaner) }}
                                        </div>
                                        <div class="cleaner-email">
                                            {{ cleaner.email ?? "" }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-count">
                                    <span class="cleaning-count">
                                        {{ cleaner.cleanings_count ?? 0 }}
                                    </span>
                                </div>

                                <div class="col-action">
                                    <input
                                        type="checkbox"
                                        class="cleaner-checkbox"
                                        :checked="isCleanerSelected(cleaner.id)"
                                        @change="toggleCleaner(cleaner.id)"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="isCleanerSelected(cleaner.id)"
                                class="description-box"
                            >
                                <label>Description</label>

                                <textarea
                                    :value="getCleanerDescription(cleaner.id)"
                                    @input="
                                        setCleanerDescription(
                                            cleaner.id,
                                            $event.target.value,
                                        )
                                    "
                                    class="form-control custom-textarea"
                                    placeholder="Write description for this cleaner..."
                                ></textarea>
                            </div>
                        </div>

                        <div v-if="!cleanersList.length" class="empty-state">
                            No cleaners found.
                        </div>
                    </div>

                    <small
                        v-if="page.props.errors?.cleaners"
                        class="text-danger d-block mt-2"
                    >
                        {{ page.props.errors.cleaners }}
                    </small>

                    <div class="form-actions">
                        <Link
                            :href="
                                route('cleaning.index', {
                                    locale: currentLocale,
                                })
                            "
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            class="btn btn-primary save-btn"
                            :disabled="form.processing"
                        >
                            <i
                                class="icon-base ti tabler-device-floppy me-1"
                            ></i>
                            Attach
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
<<<<<<< cssRout

=======
>>>>>>> dev
<style src="/resources/css/cleanings/attach-cleaners.css"></style>

