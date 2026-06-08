<script setup>
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const page = usePage()

const currentLocale = page.props.lang ?? 'hy'

const props = defineProps({
    membershipPlan: Object,
    membershipCategories: Array,
    langs: Array,
})

const translations = {}

props.langs.forEach(code => {

    const translation = props.membershipPlan.translations?.find(
        item => item.locale === code
    )

    translations[code] = {
        name: translation?.name ?? '',
        description: translation?.description ?? '',
    }
})

const form = useForm({
    membership_category_id: props.membershipPlan.membership_category_id,

    price: props.membershipPlan.price,

    duration_type: props.membershipPlan.duration_type,

    duration_value: props.membershipPlan.duration_value,
    visits_limit: props.membershipPlan.visits_limit,

    start_date: props.membershipPlan.start_date,
    end_date: props.membershipPlan.end_date,

    guest_limit: props.membershipPlan.guest_limit,
    freeze_limit: props.membershipPlan.freeze_limit,

    active: Boolean(props.membershipPlan.active),

    translations,
})

const durationTypes = [
    { value: 'day', label: 'Օրերով' },
    { value: 'month', label: 'Ամիսներով' },
    { value: 'year', label: 'Տարիներով' },
    { value: 'visit', label: 'Այցերի քանակով' },
    { value: 'period', label: 'Ժամանակահատվածով' },
]

const showDurationValue = computed(() =>
    ['day', 'month', 'year'].includes(form.duration_type)
)

const showVisitFields = computed(() =>
    form.duration_type === 'visit'
)

const showPeriodFields = computed(() =>
    form.duration_type === 'period'
)

watch(
    () => form.duration_value,
    (value) => {

        if (form.duration_type === 'day') {
            form.visits_limit = value
        }

    }
)

watch(
    () => form.duration_type,
    (type) => {

        if (type === 'day') {
            form.visits_limit = form.duration_value
        }

        if (
            type === 'month' ||
            type === 'year'
        ) {
            form.visits_limit = null
        }

        if (type !== 'period') {
            form.start_date = null
            form.end_date = null
        }
    }
)

const submit = () => {

    form.patch(
        route(
            'membership_plan.update',
            {
                id: props.membershipPlan.id,
                locale: currentLocale,

            }
        )
    )
}
</script>

<template>
    <Head title="Խմբագրել աբոնեմենտը" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                Խմբագրել աբոնեմենտը
            </h2>
        </template>

        <div class="card">
            <form
                class="card-body"
                @submit.prevent="submit"
            >

                <div class="mb-4">
                    <InputLabel value="Կատեգորիա" />

                    <select
                        v-model="form.membership_category_id"
                        class="form-select"
                    >
                        <option value="">
                            Ընտրել
                        </option>

                        <option
                            v-for="category in membershipCategories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>

                    <InputError
                        :message="form.errors.membership_category_id"
                    />
                </div>

                <div
                    v-for="code in langs"
                    :key="code"
                    class="border rounded p-3 mb-4"
                >
                    <h5 class="mb-3">
                        {{ code.toUpperCase() }}
                    </h5>

                    <div class="mb-3">
                        <InputLabel value="Անվանում" />

                        <input
                            v-model="form.translations[code].name"
                            type="text"
                            class="form-control"
                        />

                        <InputError
                            :message="form.errors[`translations.${code}.name`]"
                        />
                    </div>

                    <div>
                        <InputLabel value="Նկարագրություն" />

                        <textarea
                            v-model="form.translations[code].description"
                            class="form-control"
                            rows="3"
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
                    />

                    <InputError
                        :message="form.errors.price"
                    />
                </div>

                <div class="mb-4">
                    <InputLabel value="Աբոնեմենտի տեսակ" />

                    <select
                        v-model="form.duration_type"
                        class="form-select"
                    >
                        <option
                            v-for="item in durationTypes"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="showDurationValue"
                    class="mb-4"
                >
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
                        min="1"
                        class="form-control"
                    />

                    <InputError
                        :message="form.errors.duration_value"
                    />
                </div>

                <template v-if="showVisitFields">

                    <div class="mb-4">
                        <InputLabel value="Այցերի քանակ" />

                        <input
                            v-model="form.visits_limit"
                            type="number"
                            min="1"
                            class="form-control"
                        />

                        <InputError
                            :message="form.errors.visits_limit"
                        />
                    </div>

                    <div class="mb-4">
                        <InputLabel value="Քանի ամիս ուժի մեջ կլինի" />

                        <input
                            v-model="form.duration_value"
                            type="number"
                            min="1"
                            class="form-control"
                        />

                        <InputError
                            :message="form.errors.duration_value"
                        />
                    </div>

                </template>

                <div
                    v-if="showPeriodFields"
                    class="row"
                >
                    <div class="col-md-6">
                        <InputLabel value="Սկիզբ" />

                        <input
                            v-model="form.start_date"
                            type="date"
                            class="form-control"
                        />

                        <InputError
                            :message="form.errors.start_date"
                        />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Ավարտ" />

                        <input
                            v-model="form.end_date"
                            type="date"
                            class="form-control"
                        />

                        <InputError
                            :message="form.errors.end_date"
                        />
                    </div>
                </div>

                <div class="row mt-4">

                    <div class="col-md-6">
                        <InputLabel value="Հյուրերի քանակ" />

                        <input
                            v-model="form.guest_limit"
                            type="number"
                            min="0"
                            class="form-control"
                        />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Սառեցումների քանակ" />

                        <input
                            v-model="form.freeze_limit"
                            type="number"
                            min="0"
                            class="form-control"
                        />
                    </div>

                </div>

                <div class="mt-4">
                    <label class="form-check">
                        <input
                            v-model="form.active"
                            type="checkbox"
                            class="form-check-input"
                        >

                        <span class="form-check-label">
                            Ակտիվ
                        </span>
                    </label>
                </div>

                <div class="mt-4">
                    <PrimaryButton
                        :disabled="form.processing"
                    >
                        Թարմացնել
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </Index>
</template>
