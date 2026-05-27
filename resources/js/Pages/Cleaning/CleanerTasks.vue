<script setup>
import { computed } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/Index.vue";

const props = defineProps({
    cleaners: {
        type: [Array, Object],
        default: () => [],
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const cleanersList = computed(() => {
    return Array.isArray(props.cleaners)
        ? props.cleaners
        : props.cleaners?.data ?? [];
});

const form = useForm({
    cleaners: [],
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
    const index = form.cleaners.findIndex((item) => item.user_id === cleanerId);

    if (index > -1) {
        form.cleaners.splice(index, 1);
    } else {
        form.cleaners.push({
            user_id: cleanerId,
            description: "",
        });
    }
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
        route("cleaning.cleaner_tasks.store", {
            locale: currentLocale,
        }),
    );
};
</script>

<template>
    <AppLayout>
        <div class="attach-page">
            <div class="page-header">
                <div>
                    <h2 class="page-title">Cleaner Tasks</h2>
                    <p class="page-subtitle">
                        Select cleaners and write cleaning task descriptions.
                    </p>
                </div>

                <Link
                    :href="route('cleaning.index', { locale: currentLocale })"
                    class="btn btn-light back-btn"
                >
                    Back
                </Link>
            </div>

            <div class="form-card">
                <form @submit.prevent="submit">
                    <div class="selected-count">
                        Selected: {{ form.cleaners.length }}
                    </div>

                    <div class="cleaners-section mt-3">
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
                                <label>Task Description</label>

                                <textarea
                                    :value="getCleanerDescription(cleaner.id)"
                                    @input="
                                        setCleanerDescription(
                                            cleaner.id,
                                            $event.target.value,
                                        )
                                    "
                                    class="form-control custom-textarea"
                                    placeholder="Write cleaning task for this cleaner..."
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <Link
                            :href="route('cleaning.index', { locale: currentLocale })"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            class="btn btn-primary save-btn"
                            :disabled="form.processing"
                        >
                            Save Tasks
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
<style src="/resources/css/cleanings/attach-cleaners.css"></style>
