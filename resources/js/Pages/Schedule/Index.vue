<script setup>
import AppLayout from "@/Layouts/Index.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
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

console.log("props.data",props.data);

const page = usePage();

const currentLocale = computed(() => page.props.locale ?? props.locale);

const roleNames = computed(() => {
    return props.authUserRoles.map((role) => {
        if (typeof role === "string") {
            return role;
        }

        return role.name;
    });
});

const canChangeStatus = computed(() => {
    return roleNames.value.some((role) =>
        ["super_admin", "sales_manager"].includes(role),
    );
});

const isTrainer = computed(() => {
    return roleNames.value.includes("trainer");
});

const hasScheduleCalendarRoute = computed(() => {
    return typeof route === "function" && route().has("schedule-calendar");
});

const canEdit = computed(() => {
    return roleNames.value.some((role) =>
        ["super_admin", "manager", "admin", "sales_manager"].includes(role),
    );
});

const weekDaysTranslations = {
    monday: "Երկուշաբթի",
    tuesday: "Երեքշաբթի",
    wednesday: "Չորեքշաբթի",
    thursday: "Հինգշաբթի",
    friday: "Ուրբաթ",
    saturday: "Շաբաթ",
    sunday: "Կիրակի",
};

const weekOrder = {
    Monday: 1,
    Tuesday: 2,
    Wednesday: 3,
    Thursday: 4,
    Friday: 5,
    Saturday: 6,
    Sunday: 7,
};

const getUniqueWorkTimes = (details = []) => {
    let prev = null;

    return [...details]
        .sort((a, b) => {
            return (
                (weekOrder[a.week_day] ?? 99) - (weekOrder[b.week_day] ?? 99)
            );
        })
        .filter((current) => {
            if (!current.day_start_time || !current.day_end_time) {
                return false;
            }

            if (!prev) {
                prev = current;
                return true;
            }

            const isDifferent =
                current.day_start_time !== prev.day_start_time ||
                current.day_end_time !== prev.day_end_time;

            if (isDifferent) {
                prev = current;
                return true;
            }

            return false;
        });
};

const changeStatus = (item) => {
    router.patch(
        route("schedule.work-time-status", item.id),
        {
            status: !item.status,
        },
        {
            preserveScroll: true,
        },
    );
};

const deleteSchedule = (item) => {
    if (item.schedule_name?.is_locked) {
        alert(
            item.schedule_name?.lock_reason ||
                "Այս ժամային գրաֆիկը հնարավոր չէ ջնջել։",
        );
        return;
    }

    if (!confirm("Delete this item?")) {
        return;
    }

    router.delete(
        route("schedule.destroy", {
            locale: currentLocale.value,
            id: item.schedule_name?.id,
        }),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Ժամանակի գրաֆիկ" />

    <AppLayout>
        <main id="main" class="main">
            <section class="section dashboard">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="col-8">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">
                                    <div class="d-flex align-items-center">
                                        <div
                                            class="bg-primary text-white d-flex align-items-center justify-content-center rounded me-2 px-2 py-1"
                                        >
                                            <i
                                                class="icon-base ti tabler-calendar"
                                            ></i>
                                        </div>

                                        <h4 class="mb-0">
                                            Աշխատանքային ժամանակի ղեկավարման
                                            վահանակ
                                        </h4>

                                        <Link
                                            :href="
                                                route('schedule.create', {
                                                    locale: currentLocale,
                                                })
                                            "
                                            class="btn btn-primary btn-sm ms-auto"
                                        >
                                            <i class="bi bi-plus-lg me-1"></i>
                                            Ստեղծել
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-8">
                                <div class="card">
                                    <div class="card-body">
                                        <table class="table align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="py-3 text-start">
                                                        Անվանում
                                                    </th>
                                                    <th
                                                        class="py-3 text-center"
                                                    >
                                                        Աշխատանքային ժամ
                                                    </th>
                                                    <th
                                                        class="py-3 text-center"
                                                    >
                                                        Օրեր
                                                    </th>
                                                    <th
                                                        class="py-3 text-center"
                                                    >
                                                        Գործողություն
                                                    </th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <tr
                                                    v-for="item in data"
                                                    :key="item.id"
                                                    class="align-middle"
                                                >
                                                    <td class="text-start">
                                                        <div
                                                            class="d-flex align-items-center gap-2"
                                                        >
                                                            <div
                                                                class="bg-primary text-white rounded d-flex align-items-center justify-content-center"
                                                                style="
                                                                    width: 32px;
                                                                    height: 32px;
                                                                    opacity: 0.7;
                                                                "
                                                            >
                                                                <i
                                                                    class="icon-base ti tabler-calendar"
                                                                ></i>
                                                            </div>

                                                            <span
                                                                class="fw-medium"
                                                            >
                                                                {{
                                                                    item
                                                                        .schedule_name
                                                                        ?.name
                                                                }}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <td class="text-center">
                                                        <p
                                                            v-for="dt in getUniqueWorkTimes(
                                                                item
                                                                    .schedule_name
                                                                    ?.schedule_details ||
                                                                    [],
                                                            )"
                                                            :key="dt.id"
                                                        >
                                                            <i
                                                                class="bi bi-clock-history text-primary me-1"
                                                            ></i>

                                                            <span
                                                                class="text-success me-1"
                                                                style="
                                                                    opacity: 0.7;
                                                                "
                                                            >
                                                                {{
                                                                    dt.day_start_time
                                                                }}
                                                            </span>

                                                            <span>- </span>

                                                            <span
                                                                class="text-danger"
                                                                style="
                                                                    opacity: 0.7;
                                                                "
                                                            >
                                                                {{
                                                                    dt.day_end_time
                                                                }}
                                                            </span>
                                                        </p>
                                                    </td>

                                                    <td class="text-center">
                                                        <p
                                                            v-for="dt in item
                                                                .schedule_name
                                                                ?.schedule_details ||
                                                            []"
                                                            :key="dt.id"
                                                            class="mb-3 text-center"
                                                        >
                                                            <span
                                                                class="p-1"
                                                                style="
                                                                    background: rgba(
                                                                        219,
                                                                        234,
                                                                        254,
                                                                        1
                                                                    );
                                                                    color: rgba(
                                                                        20,
                                                                        71,
                                                                        230,
                                                                        1
                                                                    );
                                                                    border-radius: 8px;
                                                                "
                                                            >
                                                                <i
                                                                    class="bi bi-calendar-day me-1"
                                                                ></i>
                                                                {{
                                                                    weekDaysTranslations[
                                                                        dt.week_day?.toLowerCase()
                                                                    ] ||
                                                                    dt.week_day
                                                                }}
                                                            </span>
                                                        </p>
                                                    </td>

                                                    <td>
                                                        <div
                                                            class="d-flex justify-content-center align-items-center gap-2"
                                                        >
                                                            <Link
                                                                v-if="
                                                                    isTrainer &&
                                                                    hasScheduleCalendarRoute
                                                                "
                                                                class="btn btn-sm btn-outline-primary"
                                                                :href="
                                                                    route(
                                                                        'schedule-calendar',
                                                                        item.id,
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="bi bi-person-check me-1"
                                                                ></i>
                                                                Գրանցված
                                                                այցելուներ
                                                            </Link>

                                                            <Link
                                                                v-if="canEdit && !item.schedule_name?.is_locked"
                                                                class="btn btn-sm btn-outline-primary"
                                                                :href="
                                                                    route(
                                                                        'schedule.edit',
                                                                        {
                                                                            locale: currentLocale,
                                                                            id: item
                                                                                .schedule_name
                                                                                .id,
                                                                        },
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="icon-base ti tabler-pencil me-1"
                                                                ></i>
                                                            </Link>

                                                            <button
                                                                v-if="canEdit && item.schedule_name?.is_locked"
                                                                type="button"
                                                                class="btn btn-sm btn-outline-secondary"
                                                                :title="
                                                                    item
                                                                        .schedule_name
                                                                        ?.lock_reason
                                                                "
                                                                disabled
                                                            >
                                                                <i
                                                                    class="icon-base ti tabler-lock me-1"
                                                                ></i>
                                                            </button>

                                                            <button
                                                                v-if="canEdit && !item.schedule_name?.is_locked"
                                                                type="button"
                                                                class="btn btn-sm btn-outline-danger"
                                                                @click="
                                                                    deleteSchedule(
                                                                        item,
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="icon-base ti tabler-trash me-1"
                                                                ></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <tr v-if="!data.length">
                                                    <td
                                                        colspan="5"
                                                        class="text-center py-4"
                                                    >
                                                        <i
                                                            class="bi bi-inbox me-1"
                                                        ></i>
                                                        Տվյալներ չկան
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
