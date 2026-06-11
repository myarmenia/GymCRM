<script setup>
import { computed, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? "hy");

const props = defineProps({
    membershipCategories: Array,
    scheduleNames: Array,
});

const form = useForm({
    membership_category_id: "",

    price: 0,

    duration_type: "month",
    duration_value: null,
    visits_limit: null,

    start_date: null,
    end_date: null,

    guest_limit: 0,
    freeze_limit: 0,

    active: true,

    translations: {
        hy: {
            name: "",
            description: "",
        },
    },

    schedule_name_id: "",

    trainers: [],
});

watch(currentLocale, () => {
    form.errors = {};
});

const durationTypes = [
    { value: "day", label: "Օրերով" },
    { value: "month", label: "Ամիսներով" },
    { value: "year", label: "Տարիներով" },
    { value: "visit", label: "Այցերի քանակով" },
    { value: "period", label: "Ժամանակահատվածով" },
];

const priceTypes = [
    { value: "fixed", label: "Ֆիքսված գումար" },
    { value: "percent", label: "Տոկոս" },
];

const showDurationValue = computed(() =>
    ["day", "month", "year"].includes(form.duration_type),
);

const showVisitFields = computed(() => form.duration_type === "visit");

const showPeriodFields = computed(() => form.duration_type === "period");

const selectedSchedule = computed(() => {
    return props.scheduleNames?.find(
        (item) => Number(item.id) === Number(form.schedule_name_id),
    );
});

const scheduleTrainers = computed(() => {
    return selectedSchedule.value?.trainers ?? [];
});

const selectedTrainerIds = computed(() => {
    return form.trainers.map((item) => Number(item.trainer_id));
});

const isTrainerSelected = (trainerId) => {
    return selectedTrainerIds.value.includes(Number(trainerId));
};

const getTrainerIndex = (trainerId) => {
    return form.trainers.findIndex(
        (item) => Number(item.trainer_id) === Number(trainerId),
    );
};

const calculateTrainerTotalPrice = (trainer) => {
    const price = Number(form.price || 0);
    const value = Number(trainer.price_value || 0);

    if (trainer.price_type === "percent") {
        trainer.total_price = Number(((price * value) / 100).toFixed(2));
        return;
    }

    trainer.total_price = Number(value.toFixed(2));
};

const toggleTrainer = (trainerId) => {
    const id = Number(trainerId);

    const index = form.trainers.findIndex(
        (item) => Number(item.trainer_id) === id,
    );

    if (index !== -1) {
        form.trainers.splice(index, 1);
        return;
    }

    form.trainers.push({
        trainer_id: id,
        price_type: "fixed",
        price_value: 0,
        total_price: 0,
    });
};

const updateTrainerPrice = (trainer) => {
    if (trainer.price_type === "percent" && Number(trainer.price_value) > 100) {
        trainer.price_value = 100;
    }

    if (Number(trainer.price_value) < 0) {
        trainer.price_value = 0;
    }

    calculateTrainerTotalPrice(trainer);
};

watch(
    () => form.price,
    () => {
        form.trainers.forEach((trainer) => {
            calculateTrainerTotalPrice(trainer);
        });
    },
);

watch(
    () => form.duration_value,
    (value) => {
        if (form.duration_type === "day") {
            form.visits_limit = value;
        }
    },
);

watch(
    () => form.duration_type,
    (type) => {
        if (type === "day") {
            form.visits_limit = form.duration_value;
        }

        if (type === "month" || type === "year") {
            form.visits_limit = null;
        }

        if (type !== "period") {
            form.start_date = null;
            form.end_date = null;
        }
    },
);

watch(
    () => form.schedule_name_id,
    () => {
        form.trainers = [];
    },
);

const submit = () => {
    form.post(
        route("membership_plan.store", {
            locale: currentLocale.value,
        }),
    );
};
</script>

<template>
    <Head title="Նոր աբոնեմենտ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">Նոր աբոնեմենտ</h2>
        </template>

        <div class="card">
            <h5 class="card-header">Ավելացնել նոր աբոնեմենտ</h5>

            <form class="card-body" @submit.prevent="submit">
                <div class="mb-4">
                    <InputLabel value="Կատեգորիա" />

                    <select
                        v-model="form.membership_category_id"
                        class="form-select"
                    >
                        <option value="">Ընտրել</option>

                        <option
                            v-for="category in membershipCategories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>

                    <InputError :message="form.errors.membership_category_id" />
                </div>

                <div class="border rounded p-3 mb-4">
                    <h5>Հայերեն</h5>

                    <div class="mb-3">
                        <InputLabel value="Անվանում" />

                        <input
                            v-model="form.translations.hy.name"
                            class="form-control"
                            type="text"
                            @wheel.prevent
                        />

                        <InputError
                            :message="form.errors['translations.hy.name']"
                        />
                    </div>

                    <div>
                        <InputLabel value="Նկարագրություն" />

                        <textarea
                            v-model="form.translations.hy.description"
                            class="form-control"
                        />

                        <InputError
                            :message="
                                form.errors['translations.hy.description']
                            "
                        />
                    </div>
                </div>

                <div class="mb-4">
                    <InputLabel value="Գին" />

                    <input
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        @wheel.prevent
                    />

                    <InputError :message="form.errors.price" />
                </div>

                <div class="mb-4">
                    <InputLabel value="Աբոնեմենտի տեսակ" />

                    <select v-model="form.duration_type" class="form-select">
                        <option
                            v-for="item in durationTypes"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option>
                    </select>

                    <InputError :message="form.errors.duration_type" />
                </div>

                <div v-if="showDurationValue" class="mb-4">
                    <InputLabel
                        :value="
                            form.duration_type === 'day'
                                ? 'Օրերի քանակ'
                                : form.duration_type === 'month'
                                  ? 'Ամիսների քանակ'
                                  : 'Տարիների քանակ'
                        "
                    />

                    <input
                        v-model="form.duration_value"
                        type="number"
                        class="form-control"
                        @wheel.prevent
                    />

                    <InputError :message="form.errors.duration_value" />
                </div>

                <template v-if="showVisitFields">
                    <div class="mb-4">
                        <InputLabel value="Այցերի քանակ" />

                        <input
                            v-model="form.visits_limit"
                            type="number"
                            class="form-control"
                            @wheel.prevent
                        />

                        <InputError :message="form.errors.visits_limit" />
                    </div>

                    <div class="mb-4">
                        <InputLabel value="Քանի ամիս ուժի մեջ կլինի" />

                        <input
                            v-model="form.duration_value"
                            type="number"
                            class="form-control"
                            @wheel.prevent
                        />

                        <InputError :message="form.errors.duration_value" />
                    </div>
                </template>

                <div v-if="showPeriodFields" class="row">
                    <div class="col-md-6">
                        <InputLabel value="Սկիզբ" />

                        <input
                            v-model="form.start_date"
                            type="date"
                            class="form-control"
                        />

                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Ավարտ" />

                        <input
                            v-model="form.end_date"
                            type="date"
                            class="form-control"
                        />

                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <InputLabel value="Հյուրերի քանակ" />

                        <input
                            v-model="form.guest_limit"
                            type="number"
                            class="form-control"
                            @wheel.prevent
                        />

                        <InputError :message="form.errors.guest_limit" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Սառեցումների քանակ" />

                        <input
                            v-model="form.freeze_limit"
                            type="number"
                            class="form-control"
                            @wheel.prevent
                        />

                        <InputError :message="form.errors.freeze_limit" />
                    </div>
                </div>

                <div class="mt-4 border rounded p-3">
                    <h5 class="mb-3">Գրաֆիկ և մարզիչներ</h5>

                    <div class="mb-4">
                        <InputLabel value="Գրաֆիկ" />

                        <select
                            v-model="form.schedule_name_id"
                            class="form-select"
                        >
                            <option value="">Ընտրել գրաֆիկ</option>

                            <option
                                v-for="schedule in scheduleNames"
                                :key="schedule.id"
                                :value="schedule.id"
                            >
                                {{ schedule.name }}
                            </option>
                        </select>

                        <InputError :message="form.errors.schedule_name_id" />
                    </div>

                    <div>
                        <InputLabel value="Գրաֆիկին կցված մարզիչներ" />

                        <div
                            v-if="!form.schedule_name_id"
                            class="alert alert-info mb-0"
                        >
                            Նախ ընտրեք գրաֆիկը։
                        </div>

                        <div
                            v-else-if="scheduleTrainers.length === 0"
                            class="alert alert-warning mb-0"
                        >
                            Այս գրաֆիկին մարզիչ կցված չէ։
                        </div>

                        <div v-else class="row g-3">
                            <div
                                v-for="trainer in scheduleTrainers"
                                :key="trainer.id"
                                class="col-md-4"
                            >
                                <div
                                    class="trainer-card border rounded p-3 h-100"
                                    :class="{
                                        active: isTrainerSelected(trainer.id),
                                    }"
                                >
                                    <div
                                        class="d-flex justify-content-between align-items-start gap-3"
                                        @click="toggleTrainer(trainer.id)"
                                    >
                                        <div>
                                            <h6 class="mb-1">
                                                {{ trainer.name }}
                                                {{ trainer.surname }}
                                            </h6>

                                            <small class="text-muted">
                                                Սեղմեք ընտրելու համար
                                            </small>
                                        </div>

                                        <span
                                            v-if="isTrainerSelected(trainer.id)"
                                            class="badge "
                                        >
                                            Ընտրված է
                                        </span>

                                        <span
                                            v-else
                                            class="badge bg-light text-dark"
                                        >
                                            Ընտրված չէ
                                        </span>
                                    </div>

                                    <div
                                        v-if="isTrainerSelected(trainer.id)"
                                        class="trainer-price-block mt-3 pt-3 border-top"
                                        @click.stop
                                    >
                                        <div class="mb-3">
                                            <InputLabel value="Գնի տեսակ" />

                                            <select
                                                v-model="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.id,
                                                        )
                                                    ].price_type
                                                "
                                                class="form-select"
                                                @change="
                                                    updateTrainerPrice(
                                                        form.trainers[
                                                            getTrainerIndex(
                                                                trainer.id,
                                                            )
                                                        ],
                                                    )
                                                "
                                            >
                                                <option
                                                    v-for="item in priceTypes"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>

                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `trainers.${getTrainerIndex(trainer.id)}.price_type`
                                                    ]
                                                "
                                            />
                                        </div>

                                        <div class="mb-3">
                                            <InputLabel
                                                :value="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.id,
                                                        )
                                                    ].price_type === 'percent'
                                                        ? 'Տոկոս'
                                                        : 'Ֆիքսված գումար'
                                                "
                                            />

                                            <input
                                                v-model.number="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.id,
                                                        )
                                                    ].price_value
                                                "
                                                type="number"
                                                min="0"
                                                :max="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.id,
                                                        )
                                                    ].price_type === 'percent'
                                                        ? 100
                                                        : null
                                                "
                                                step="0.01"
                                                class="form-control"
                                                @wheel.prevent
                                                @input="
                                                    updateTrainerPrice(
                                                        form.trainers[
                                                            getTrainerIndex(
                                                                trainer.id,
                                                            )
                                                        ],
                                                    )
                                                "
                                            />

                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `trainers.${getTrainerIndex(trainer.id)}.price_value`
                                                    ]
                                                "
                                            />
                                        </div>

                                        <div>
                                            <InputLabel
                                                value="Ընդհանուր գումար"
                                            />

                                            <input
                                                v-model="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.id,
                                                        )
                                                    ].total_price
                                                "
                                                type="number"
                                                readonly
                                                class="form-control bg-light"
                                                @wheel.prevent
                                            />

                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `trainers.${getTrainerIndex(trainer.id)}.total_price`
                                                    ]
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <InputError :message="form.errors.trainers" />
                        <InputError
                            :message="form.errors['trainers.0.trainer_id']"
                        />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-check">
                        <input
                            v-model="form.active"
                            type="checkbox"
                            class="form-check-input"
                        />

                        <span class="form-check-label"> Ակտիվ </span>
                    </label>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <PrimaryButton :disabled="form.processing">
                        Պահպանել
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Index>
</template>

<style scoped>
.trainer-card {
    cursor: pointer;
    transition: 0.2s ease;
    background: #fff;
}

.trainer-card:hover {
    border-color: #675dd8 !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.trainer-card.active {
    border-color: #675dd8 !important;
    background: rgba(251, 252, 252, 0.08);
}

.trainer-price-block {
    cursor: default;
}
</style>
