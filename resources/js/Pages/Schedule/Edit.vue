<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import AppLayout from "@/Layouts/Index.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    data: {
        type: Object,
        default: () => ({}),
    },
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

const currentLocale = computed(() => props.locale || "hy");

const roleNames = computed(() => {
    return props.authUserRoles.map((role) => {
        if (typeof role === "string") {
            return role;
        }

        return role.name;
    });
});

const canShowStatus = computed(() => {
    return roleNames.value.some((role) =>
        [
            "client_admin",
            "client_admin_rfID",
            "client_sport",
            "super_admin",
        ].includes(role),
    );
});

const getDetailByWeekDay = (weekDay) => {
    return props.data.schedule_details?.find(
        (detail) => detail.week_day === weekDay,
    );
};

const weekDaysArray = computed(() => {
    return Object.entries(props.weekdays).map(([english, armenian]) => {
        const detail = getDetailByWeekDay(english);

        return {
            week_day: english,
            name: armenian,

            day_start_time: detail?.day_start_time || "",
            day_end_time: detail?.day_end_time || "",

            break_start_time: detail?.break_start_time || "",
            break_end_time: detail?.break_end_time || "",

            show_break: !!(detail?.break_start_time || detail?.break_end_time),
        };
    });
});

const form = useForm({
    name: props.data.name || "",
    status: Boolean(props.data.status),
    week_days: weekDaysArray.value,
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

const removeWeekDay = (index) => {
    form.week_days[index].day_start_time = "";
    form.week_days[index].day_end_time = "";
    form.week_days[index].break_start_time = "";
    form.week_days[index].break_end_time = "";
    form.week_days[index].show_break = false;
};

const openCopyModal = () => {
    showCopyModal.value = true;
};

const copyToSelectedDays = () => {
    const source = form.week_days.find(
        (day) => day.day_start_time || day.day_end_time,
    );

    if (!source) {
        return;
    }

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
    form.put(
        route("schedule.update", {
            locale: currentLocale.value,
            id: props.data.id,
        }),
    );
};
</script>

<template>
    <Head :title="t('operations.edit_time_schedule')" />

    <AppLayout>
        <main id="main" class="main">
            <div class="pagetitle d-flex justify-content-between">
                <div>
                    <h1>
                        <i class="bi bi-pencil-square me-1"></i>
                        {{ t('operations.edit_working_time') }}
                    </h1>

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

                            <li class="breadcrumb-item active">{{ t('action.edit') }}</li>
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
                                        <i class="bi bi-copy me-1"></i>
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
                                                        <i
                                                            class="bi bi-calendar-day me-1"
                                                        ></i>
                                                        {{ day.name }}
                                                    </h6>

                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        @click="
                                                            removeWeekDay(index)
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-trash me-1"
                                                        ></i>
                                                        {{ t('operations.delete_day') }}
                                                    </button>
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
                                                        class="form-control"
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
                                                        class="form-control"
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
                                                            <i
                                                                class="bi bi-cup-hot"
                                                            ></i>
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
                                                                    <i
                                                                        class="bi bi-cup-hot me-1"
                                                                    ></i>
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
                                                                    <i
                                                                        class="bi bi-trash me-1"
                                                                    ></i>
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
                                    class="col-12 d-flex justify-content-end mt-4"
                                >
                                    <div class="col-md-6 d-flex gap-2">
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            :disabled="form.processing"
                                        >
                                            <i class="bi bi-save me-1"></i>
                                            {{ t('common.save') }}
                                        </button>

                                        <Link
                                            :href="
                                                route('schedule.index', {
                                                    locale: currentLocale,
                                                })
                                            "
                                            class="btn btn-secondary"
                                        >
                                            <i
                                                class="bi bi-arrow-left me-1"
                                            ></i>
                                            {{ t('operations.back') }}
                                        </Link>
                                    </div>
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
                            <label class="form-label">{{ t('operations.select_days') }}</label>

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
