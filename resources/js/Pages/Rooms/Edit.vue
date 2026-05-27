<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";


const props = defineProps({
    room: {
        type: Object,
        required: true,
    },
    roomTypes: {
        type: [Array, Object],
        default: () => [],
    },
    roomAmenities: {
        type: [Array, Object],
        default: () => [],
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const roomTypesList = computed(() => {
    return Array.isArray(props.roomTypes)
        ? props.roomTypes
        : (props.roomTypes?.data ?? []);
});

const roomAmenitiesList = computed(() => {
    return Array.isArray(props.roomAmenities)
        ? props.roomAmenities
        : (props.roomAmenities?.data ?? []);
});

const roomAmenityOptions = computed(() => {
    return roomAmenitiesList.value.map((amenity) => ({
        id: amenity.id,
        text: amenity.translations?.[0]?.name ?? amenity.name ?? amenity.slug,
    }));
});

const getRoomTypeName = (type) => {
    return (
        type.name ||
        type.title ||
        type.translation?.name ||
        type.translations?.[0]?.name ||
        type.translations?.[0]?.title ||
        `Room Type #${type.id}`
    );
};

const selectedAmenityIds = computed(() => {
    return props.room.amenities?.map((amenity) => String(amenity.id)) ?? [];
});

const form = useForm({
    id: props.room.id,
    number: props.room.number ?? props.room.room_number ?? "",
    room_type_id: props.room.room_type_id ?? "",
    floor: props.room.floor ?? "",
    status: props.room.status ?? "",
    is_active: Boolean(props.room.is_active),
    max_guests: props.room.max_guests ?? "",
    room_amenity_ids: selectedAmenityIds.value,
});

const statusList = [
    { name: "available" },
    { name: "reserved" },
    { name: "occupied" },
    // { name: "checkout" },
    { name: "cleaning" },
];

const submit = () => {
    form.is_active = form.is_active ? 1 : 0;
    console.log("submitting form");
    console.log(form);

    form.put(
        route("rooms.update", {
            locale: currentLocale,
            id: props.room.id,
        }),
    );
};

onMounted(() => {
    const selectedIds =
        props.room.amenities?.map((amenity) => String(amenity.id)) ?? [];

    form.room_amenity_ids = selectedIds;

    $("#room_amenities")
        .select2({ width: "100%" })
        .val(selectedIds)
        .trigger("change")
        .on("change", function () {
            form.room_amenity_ids = $(this).val() || [];
        });
});

watch(
    () => form.room_amenity_ids,
    (val) => {
        $("#room_amenities").val(val).trigger("change.select2");
    },
);
</script>

<template>
    <AppLayout>
        <div class="room-form-page">
            <div class="page-header">
                <div>
                    <h2 class="page-title">Edit Room</h2>
                    <p class="page-subtitle">Update gym room information</p>
                </div>

                <Link
                    :href="route('rooms.index', { locale: currentLocale })"
                    class="btn btn-light back-btn"
                >
                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                    Back
                </Link>
            </div>

            <div class="form-card">
                <form @submit.prevent="submit">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Room Number</label>
                            <input
                                v-model="form.number"
                                type="text"
                                class="form-control custom-input"
                                placeholder="Example: 101"
                            />
                            <small
                                v-if="page.props.errors?.number"
                                class="text-danger"
                            >
                                {{ page.props.errors.number }}
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Room Type</label>
                            <select
                                v-model="form.room_type_id"
                                class="form-select custom-input"
                            >
                                <option value="">Select room type</option>
                                <option
                                    v-for="type in roomTypesList"
                                    :key="type.id"
                                    :value="type.id"
                                >
                                    {{ getRoomTypeName(type) }}
                                </option>
                            </select>
                            <small
                                v-if="page.props.errors?.room_type_id"
                                class="text-danger"
                            >
                                {{ page.props.errors.room_type_id }}
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Floor</label>
                            <input
                                v-model="form.floor"
                                type="number"
                                class="form-control custom-input"
                                placeholder="Example: 1"
                            />
                        </div>

                        <div class="form-group">
                            <label>Max Guests</label>
                            <input
                                v-model="form.max_guests"
                                type="number"
                                class="form-control custom-input"
                                placeholder="Example: 4"
                            />
                        </div>

                        <div class="form-group full-width">
                            <InputLabel value="Room Amenities" />

                            <select
                                id="room_amenities"
                                class="form-select"
                                multiple
                            >
                                <option
                                    v-for="amenity in roomAmenityOptions"
                                    :key="amenity.id"
                                    :value="amenity.id"
                                >
                                    {{ amenity.text }}
                                </option>
                            </select>

                            <InputError
                                class="mt-2"
                                :message="form.errors.room_amenity_ids"
                            />
                        </div>

                        <div class="form-group">
                            <label>Room Status</label>
                            <select
                                v-model="form.status"
                                class="form-select custom-input"
                            >
                                <option value="">Select room status</option>
                                <option
                                    v-for="status in statusList"
                                    :key="status.name"
                                    :value="status.name"
                                >
                                    {{ status.name }}
                                </option>
                            </select>
                            <small
                                v-if="page.props.errors?.status"
                                class="text-danger"
                            >
                                {{ page.props.errors.status }}
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Active</label>
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    v-model="form.is_active"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <Link
                            :href="
                                route('rooms.index', { locale: currentLocale })
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
                            Update Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style src="/resources/css/rooms/edit.css"></style>
