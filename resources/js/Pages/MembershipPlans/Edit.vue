<script setup>
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import {
    existingSalaryPercentage,
    fixedSalaryForPrice,
    normalizePercentage,
    salaryAmountFromPercentage,
    salaryPercentageFromAmount,
} from "@/utils/membershipSalary.js";

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? "hy");

const props = defineProps({
    membershipPlan: Object,
    membershipCategories: Array,
    scheduleNames: Array,
});

const getTrainerId = (trainer) => {
    return Number(trainer.pivot?.user_id ?? trainer.id);
};

const isPlanLocked = computed(() => Boolean(props.membershipPlan.is_locked));
const initialPrice = Number(props.membershipPlan.price ?? 0);
const initialSalaryPercentage = existingSalaryPercentage(
    initialPrice,
    props.membershipPlan.price_type,
    props.membershipPlan.price_value,
);

const form = useForm({
    membership_category_id: props.membershipPlan.membership_category_id ?? "",

    price: props.membershipPlan.price ?? 0,

    price_type: "percent",
    price_value: initialSalaryPercentage,

    duration_type: props.membershipPlan.duration_type ?? "month",
    duration_value: props.membershipPlan.duration_value ?? null,
    visits_limit: props.membershipPlan.visits_limit ?? null,

    start_date: props.membershipPlan.start_date ?? null,
    end_date: props.membershipPlan.end_date ?? null,

    guest_limit: props.membershipPlan.guest_limit ?? 0,
    freeze_limit: props.membershipPlan.freeze_limit ?? 0,

    active: Boolean(props.membershipPlan.active),

    translations: {
        hy: {
            name: props.membershipPlan.translation?.name ?? "",
            description: props.membershipPlan.translation?.description ?? "",
        },
    },

    schedule_name_id:
        props.membershipPlan.schedule_name_id ??
        props.scheduleNames?.[0]?.id ??
        "",
    trainers:
        props.membershipPlan.trainers?.map((item) => {
            const percentage = existingSalaryPercentage(
                initialPrice,
                item.price_type,
                item.price_value,
            );

            return {
                trainer_id: Number(item.trainer_id),
                price_type: "percent",
                price_value: percentage,
                total_price: salaryAmountFromPercentage(
                    initialPrice,
                    percentage,
                ),
            };
        }) ?? [],
});

const salaryFixedAmount = ref(
    salaryAmountFromPercentage(initialPrice, initialSalaryPercentage),
);

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
    trainer.price_type = "percent";
    trainer.total_price = salaryAmountFromPercentage(
        form.price,
        trainer.price_value,
    );
};

const updateSalaryFromPercentage = () => {
    form.price_type = "percent";
    form.price_value = normalizePercentage(form.price_value);
    salaryFixedAmount.value = salaryAmountFromPercentage(
        form.price,
        form.price_value,
    );
};

const updateSalaryFromFixed = () => {
    salaryFixedAmount.value = fixedSalaryForPrice(
        form.price,
        salaryFixedAmount.value,
    );
    form.price_type = "percent";
    form.price_value = salaryPercentageFromAmount(
        form.price,
        salaryFixedAmount.value,
    );
};

const toggleTrainer = (trainerId) => {
    const id = Number(trainerId);

    if (isTrainerSelected(id)) {
        form.trainers = form.trainers.filter(
            (item) => Number(item.trainer_id) !== id,
        );
        return;
    }

    form.trainers.push({
        trainer_id: id,
        price_type: "percent",
        price_value: 0,
        total_price: 0,
    });
};

const updateTrainerFromPercentage = (trainer) => {
    trainer.price_type = "percent";
    trainer.price_value = normalizePercentage(trainer.price_value);
    calculateTrainerTotalPrice(trainer);
};

const updateTrainerFromFixed = (trainer) => {
    trainer.price_type = "percent";
    trainer.total_price = fixedSalaryForPrice(form.price, trainer.total_price);
    trainer.price_value = salaryPercentageFromAmount(
        form.price,
        trainer.total_price,
    );
};

watch(
    () => form.price,
    () => {
        salaryFixedAmount.value = salaryAmountFromPercentage(
            form.price,
            form.price_value,
        );

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
    (newValue, oldValue) => {
        if (oldValue !== "" && Number(newValue) !== Number(oldValue)) {
            form.trainers = [];
        }
    },
);

const submit = () => {
    form.put(
        route("membership_plan.update", {
            locale: currentLocale.value,
            id: props.membershipPlan.id,
        }),
    );
};
</script>

<template>
    <Head title="Խմբագրել աբոնեմենտ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">Խմբագրել աբոնեմենտ</h2>
        </template>

        <div class="card">
            <h5 class="card-header">Խմբագրել աբոնեմենտ</h5>

            <form class="card-body" @submit.prevent="submit">
                <div v-if="isPlanLocked" class="alert alert-warning mb-4">
                    {{
                        membershipPlan.lock_reason ||
                        "Այս աբոնեմենտը կապված է այցելուի աբոնեմենտի հետ։ Կարելի է փոփոխել միայն մարզիչներին։"
                    }}
                </div>

                <div class="mb-4">
                    <InputLabel value="Կատեգորիա" />

                    <select
                        v-model="form.membership_category_id"
                        class="form-select"
                        :disabled="isPlanLocked"
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
                            :disabled="isPlanLocked"
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
                            :disabled="isPlanLocked"
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
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        :disabled="isPlanLocked"
                        @wheel.prevent
                    />

                    <InputError :message="form.errors.price" />
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <InputLabel value="Աշխատավարձ (%)" />

                        <input
                            v-model.number="form.price_value"
                            type="number"
                            min="0"
                            max="100"
                            step="0.000001"
                            class="form-control"
                            :disabled="isPlanLocked"
                            @wheel.prevent
                            @input="updateSalaryFromPercentage"
                        />

                        <InputError :message="form.errors.price_value" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Աշխատավարձ (ֆիքսված)" />

                        <input
                            v-model.number="salaryFixedAmount"
                            type="number"
                            min="0"
                            :max="Number(form.price || 0)"
                            step="0.01"
                            class="form-control"
                            :disabled="isPlanLocked || Number(form.price || 0) <= 0"
                            @wheel.prevent
                            @input="updateSalaryFromFixed"
                        />
                    </div>
                </div>

                <div class="mb-4">
                    <InputLabel value="Աբոնեմենտի տեսակ" />

                    <select
                        v-model="form.duration_type"
                        class="form-select"
                        :disabled="isPlanLocked"
                    >
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
                        :disabled="isPlanLocked"
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
                            :disabled="isPlanLocked"
                        />

                        <InputError :message="form.errors.visits_limit" />
                    </div>

                    <div class="mb-4">
                        <InputLabel value="Քանի ամիս ուժի մեջ կլինի" />

                        <input
                            v-model="form.duration_value"
                            type="number"
                            class="form-control"
                            :disabled="isPlanLocked"
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
                            :disabled="isPlanLocked"
                        />

                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Ավարտ" />

                        <input
                            v-model="form.end_date"
                            type="date"
                            class="form-control"
                            :disabled="isPlanLocked"
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
                            :disabled="isPlanLocked"
                        />

                        <InputError :message="form.errors.guest_limit" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Սառեցումների քանակ" />

                        <input
                            v-model="form.freeze_limit"
                            type="number"
                            class="form-control"
                            :disabled="isPlanLocked"
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
                            :disabled="isPlanLocked"
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
                                :key="trainer.pivot?.user_id ?? trainer.id"
                                class="col-md-4"
                            >
                                <div
                                    class="trainer-card border rounded p-3 h-100"
                                    :class="{
                                        active: isTrainerSelected(
                                            getTrainerId(trainer),
                                        ),
                                    }"
                                    @click="
                                        toggleTrainer(getTrainerId(trainer))
                                    "
                                >
                                    <div
                                        class="d-flex justify-content-between align-items-start gap-3"
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
                                            v-if="
                                                isTrainerSelected(
                                                    trainer.pivot?.user_id ??
                                                        trainer.id,
                                                )
                                            "
                                            class="badge"
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
                                        v-if="
                                            isTrainerSelected(
                                                trainer.pivot?.user_id ??
                                                    trainer.id,
                                            )
                                        "
                                        class="trainer-price-block mt-3 pt-3 border-top"
                                        @click.stop
                                    >
                                        <div class="mb-3">
                                            <InputLabel value="Աշխատավարձ (%)" />

                                            <input
                                                v-model.number="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.pivot
                                                                ?.user_id ??
                                                                trainer.id,
                                                        )
                                                    ].price_value
                                                "
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.000001"
                                                class="form-control"
                                                @wheel.prevent
                                                @input="
                                                    updateTrainerFromPercentage(
                                                        form.trainers[
                                                            getTrainerIndex(
                                                                trainer.pivot
                                                                    ?.user_id ??
                                                                    trainer.id,
                                                            )
                                                        ],
                                                    )
                                                "
                                            />

                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `trainers.${getTrainerIndex(trainer.pivot?.user_id ?? trainer.id)}.price_value`
                                                    ]
                                                "
                                            />
                                        </div>

                                        <div>
                                            <InputLabel value="Աշխատավարձ (ֆիքսված)" />

                                            <input
                                                v-model.number="
                                                    form.trainers[
                                                        getTrainerIndex(
                                                            trainer.pivot
                                                                ?.user_id ??
                                                                trainer.id,
                                                        )
                                                    ].total_price
                                                "
                                                type="number"
                                                min="0"
                                                :max="Number(form.price || 0)"
                                                step="0.01"
                                                class="form-control"
                                                :disabled="Number(form.price || 0) <= 0"
                                                @wheel.prevent
                                                @input="
                                                    updateTrainerFromFixed(
                                                        form.trainers[
                                                            getTrainerIndex(
                                                                trainer.pivot
                                                                    ?.user_id ??
                                                                    trainer.id,
                                                            )
                                                        ],
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <InputError :message="form.errors.trainers" />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-check">
                        <input
                            v-model="form.active"
                            type="checkbox"
                            class="form-check-input"
                            :disabled="isPlanLocked"
                        />

                        <span class="form-check-label">Ակտիվ</span>
                    </label>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <PrimaryButton :disabled="form.processing">
                        Թարմացնել
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
    background: #f4f4f5;
}

.trainer-price-block {
    cursor: default;
}
</style>
