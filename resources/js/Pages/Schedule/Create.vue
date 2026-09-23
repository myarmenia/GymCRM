<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import AppLayout from "@/Layouts/Index.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    weekdays: {
        type: Object,
        default: () => ({}),
    },
    authUserRoles: {
        type: Array,
        default: () => [],
    },
    locale: {
        type: String,
        default: "hy",
    },
});

const weekDaysArray = computed(() => {
    return Object.entries(props.weekdays).map(([english, armenian]) => ({
        week_day: english,
        name: armenian,
        day_start_time: "",
        day_end_time: "",
        break_start_time: "",
        break_end_time: "",
        show_break: false,
    }));
});

const currentLocale = computed(() => {
    return props.locale || "hy";
});

const form = useForm({
    name: "",
    status: false,
    week_days: weekDaysArray.value,
});

const canShowStatus = computed(() => {
    return props.authUserRoles.some((role) =>
        ["client_admin", "client_admin_rfID", "client_sport"].includes(role),
    );
});

const showCopyLink = computed(() => {
    return form.week_days.some((day) => day.day_start_time || day.day_end_time);
});

const showCopyModal = ref(false);
const selectedDayIndexes = ref([]);

const addBreakTime = (index) => {
    form.week_days[index].show_break = true;
};

const removeBreakTime = (index) => {
    form.week_days[index].show_break = false;
    form.week_days[index].break_start_time = "";
    form.week_days[index].break_end_time = "";
};

const openCopyModal = () => {
    const sourceIndex = form.week_days.findIndex(
        (day) => day.day_start_time || day.day_end_time,
    );

    selectedDayIndexes.value = sourceIndex === -1 ? [] : [sourceIndex];
    showCopyModal.value = true;
};

const copyToSelectedDays = () => {
    const source = form.week_days.find(
        (day) => day.day_start_time || day.day_end_time,
    );

    if (!source) return;

    selectedDayIndexes.value.forEach((index) => {
        form.week_days[index].day_start_time = source.day_start_time;
        form.week_days[index].day_end_time = source.day_end_time;
        form.week_days[index].break_start_time = source.break_start_time;
        form.week_days[index].break_end_time = source.break_end_time;
        form.week_days[index].show_break = source.show_break;
    });

    selectedDayIndexes.value = [];
    showCopyModal.value = false;
};

const submit = () => {
    form.post(route("schedule.store", { locale: currentLocale.value }));
};
</script>

<template>
    <Head :title="t('operations.create_time_schedule')" />

    <AppLayout>
        <main id="main" class="main">
            <div class="pagetitle d-flex justify-content-between">
                <div>
                    <h1>{{ t('operations.create_working_time') }}</h1>

                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <Link
                                    :href="
                                        route('schedule.index', {
                                            locale: currentLocale,
                                        })
                                    "
                                >
                                    {{ t('sidebar.schedule') }}
                                </Link>
                            </li>
                            <li class="breadcrumb-item active">{{ t('people.create') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <form @submit.prevent="submit">
                <section class="section dashboard">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-8">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row mt-3">
                                                <div>
                                                    <label class="form-label">
                                                        {{ t('membership.title') }}
                                                    </label>

                                                    <input
                                                        v-model="form.name"
                                                        type="text"
                                                        class="form-control"
                                                        :class="{
                                                            'is-invalid':
                                                                form.errors
                                                                    .name,
                                                        }"
                                                    />

                                                    <div
                                                        v-if="form.errors.name"
                                                        class="text-danger fs-6 mt-1"
                                                    >
                                                        {{ form.errors.name }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="canShowStatus"
                                                class="row mb-3 col-6 d-flex align-items-center gap-2 mt-3"
                                            >
                                                <label
                                                    class="col-4 col-form-label"
                                                >
                                                    {{ t('operations.activation') }}
                                                </label>

                                                <div class="col-1">
                                                    <div
                                                        class="form-check form-switch"
                                                    >
                                                        <input
                                                            v-model="
                                                                form.status
                                                            "
                                                            class="form-check-input"
                                                            type="checkbox"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="col-8 pagetitle d-flex justify-content-end mb-3"
                                >
                                    <button
                                        v-if="showCopyLink"
                                        type="button"
                                        class="btn btn-link text-decoration-none"
                                        style="
                                            color: rgba(21, 93, 252, 1);
                                            margin-right: 60px;
                                        "
                                        @click="openCopyModal"
                                    >
                                        {{ t('operations.apply_to_weekdays') }}
                                    </button>
                                </div>

                                <div
                                    v-if="form.errors.week_days"
                                    class="col-8 alert alert-danger mt-2"
                                >
                                    {{ form.errors.week_days }}
                                </div>

                                <div
                                    v-for="(day, index) in form.week_days"
                                    :key="day.week_day"
                                    class="col-8 mt-5"
                                >
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row mt-3 day-row">
                                                <div
                                                    class="d-flex justify-content-between"
                                                >
                                                    <h6 class="fw-bold">
                                                        {{ day.name }}
                                                    </h6>
                                                </div>

                                                <input
                                                    v-model="day.week_day"
                                                    type="hidden"
                                                />

                                                <div class="col-md-4">
                                                    <label class="form-label">
                                                        {{ t('operations.working_time_start') }}
                                                    </label>

                                                    <input
                                                        v-model="
                                                            day.day_start_time
                                                        "
                                                        type="time"
                                                        class="form-control border-start-1"
                                                    />
                                                </div>

                                                <div class="col-md-4">
                                                    <label class="form-label">
                                                        {{ t('operations.working_time_end') }}
                                                    </label>

                                                    <input
                                                        v-model="
                                                            day.day_end_time
                                                        "
                                                        type="time"
                                                        class="form-control border-start-1"
                                                    />
                                                </div>

                                                <div class="col-md-4">
                                                    <label class="form-label">
                                                        {{ t('staff_reports.break') }}
                                                    </label>

                                                    <div class="d-flex gap-2">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm mb-2 d-flex align-items-center justify-content-center gap-2 flex-fill"
                                                            style="
                                                                border-radius: 8px;
                                                                padding: 8px
                                                                    14px;
                                                                background: rgba(
                                                                    220,
                                                                    252,
                                                                    231,
                                                                    1
                                                                );
                                                                color: #28a745;
                                                                font-weight: 600;
                                                            "
                                                            @click="
                                                                addBreakTime(
                                                                    index,
                                                                )
                                                            "
                                                        >
                                                            {{ t('operations.break_time') }}
                                                        </button>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        form.errors[
                                                            `week_days.${index}.day_time`
                                                        ]
                                                    "
                                                    class="text-danger small mt-1"
                                                >
                                                    {{
                                                        form.errors[
                                                            `week_days.${index}.day_time`
                                                        ]
                                                    }}
                                                </div>

                                                <div
                                                    v-if="day.show_break"
                                                    class="col-md-12 mt-3"
                                                >
                                                    <div class="card border">
                                                        <div class="card-body">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-2"
                                                            >
                                                                <h6
                                                                    class="mb-0"
                                                                >
                                                                    {{ t('operations.break_time') }}
                                                                </h6>

                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-danger"
                                                                    @click="
                                                                        removeBreakTime(
                                                                            index,
                                                                        )
                                                                    "
                                                                >
                                                                    {{ t('action.delete') }}
                                                                </button>
                                                            </div>

                                                            <div class="row">
                                                                <div
                                                                    class="col-md-6"
                                                                >
                                                                    <label
                                                                        class="form-label"
                                                                    >
                                                                        {{ t('people.start') }}
                                                                    </label>

                                                                    <input
                                                                        v-model="
                                                                            day.break_start_time
                                                                        "
                                                                        type="time"
                                                                        class="form-control"
                                                                    />
                                                                </div>

                                                                <div
                                                                    class="col-md-6"
                                                                >
                                                                    <label
                                                                        class="form-label"
                                                                    >
                                                                        {{ t('people.end') }}
                                                                    </label>

                                                                    <input
                                                                        v-model="
                                                                            day.break_end_time
                                                                        "
                                                                        type="time"
                                                                        class="form-control"
                                                                    />
                                                                </div>
                                                                <div
                                                                    v-if="
                                                                        form
                                                                            .errors[
                                                                            `week_days.${index}.break_time`
                                                                        ]
                                                                    "
                                                                    class="text-danger small mt-1"
                                                                >
                                                                    {{
                                                                        form
                                                                            .errors[
                                                                            `week_days.${index}.break_time`
                                                                        ]
                                                                    }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="col-8 d-flex justify-content-end mt-4"
                                >
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        :disabled="form.processing"
                                    >
                                        {{ t('people.create') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </form>

            <div
                v-if="showCopyModal"
                class="modal fade show d-block"
                tabindex="-1"
                style="background: rgba(0, 0, 0, 0.5)"
            >
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ t('operations.apply_to_days') }}</h5>

                            <button
                                type="button"
                                class="btn-close"
                                @click="showCopyModal = false"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <label class="form-label">
                                {{ t('operations.select_the_days_to_apply_the_time_to') }}
                            </label>

                            <div
                                v-for="(day, index) in form.week_days"
                                :key="day.week_day"
                                class="form-check mb-2"
                            >
                                <input
                                    :id="`day-${index}`"
                                    v-model="selectedDayIndexes"
                                    :value="index"
                                    class="form-check-input"
                                    type="checkbox"
                                />

                                <label
                                    :for="`day-${index}`"
                                    class="form-check-label"
                                >
                                    {{ day.name }}
                                </label>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                @click="showCopyModal = false"
                            >
                                {{ t('confirm.close') }}
                            </button>

                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="copyToSelectedDays"
                            >
                                {{ t('operations.apply') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
.form-label {
    color: rgba(49, 65, 88, 1);
}
</style>
