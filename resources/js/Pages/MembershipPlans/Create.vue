<script setup>
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? 'hy')  // 👈 СДЕЛАЙ computed
// const currentLocale = page.props.lang ?? 'hy'

const props = defineProps({
    membershipCategories: Array,
    langs: Array,
})


// ЭТО РЕШЕНИЕ - очищаем ошибки при смене языка
watch(currentLocale, () => {
  form.errors = {}  // Очищаем все ошибки валидации
})


console.log(currentLocale, 555)
const form = useForm({
    membership_category_id: '',

    price: 0,

    duration_type: 'month',

    duration_value: null,
    visits_limit: null,

    start_date: null,
    end_date: null,

    guest_limit: 0,
    freeze_limit: 0,

    active: true,

    translations: {}
})

props.langs.forEach(code => {
    form.translations[code] = {
        name: '',
        description: ''
    }
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

/**
 * Եթե օրերով է,
 * visits_limit = duration_value
 */
watch(() => form.duration_value, (value) => {
    if (form.duration_type === 'day') {
        form.visits_limit = value
    }
})

watch(() => form.duration_type, (type) => {

    if (type === 'day') {
        form.visits_limit = form.duration_value
    }

    if (type === 'month' || type === 'year') {
        form.visits_limit = null
    }

    if (type !== 'visit') {
        form.start_date = null
        form.end_date = null
    }

    if (type !== 'period') {
        form.start_date = null
        form.end_date = null
    }
})

const submit = () => {
    form.post(
        route('membership_plan.store', {
            locale: currentLocale.value
        })
    )
}
</script>

<template>
    <Head title="Նոր աբոնեմենտ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                Նոր աբոնեմենտ
            </h2>
        </template>

        <div class="card">
            <form
                class="card-body"
                @submit.prevent="submit"
            >

                <!-- CATEGORY -->

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

                <!-- TRANSLATIONS -->

                <div
                    v-for="code in langs"
                    :key="code"
                    class="border rounded p-3 mb-4"
                >
                    <h5>
                        {{ code.toUpperCase() }}
                    </h5>

                    <div class="mb-3">
                        <InputLabel value="Անվանում" />

                        <input
                            v-model="form.translations[code].name"
                            class="form-control"
                            type="text"
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
                        />
                    </div>
                </div>

                <!-- PRICE -->

                <div class="mb-4">
                    <InputLabel value="Գին" />

                    <input
                        v-model="form.price"
                        type="number"
                        class="form-control"
                    />
                </div>

                <!-- TYPE -->

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

                <!-- DAY / MONTH / YEAR -->

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
                        class="form-control"
                    />
                </div>

                <!-- VISIT -->

                <template v-if="showVisitFields">

                    <div class="mb-4">
                        <InputLabel value="Այցերի քանակ" />

                        <input
                            v-model="form.visits_limit"
                            type="number"
                            class="form-control"
                        />
                    </div>

                    <div class="mb-4">
                        <InputLabel value="Քանի ամիս ուժի մեջ կլինի" />

                        <input
                            v-model="form.duration_value"
                            type="number"
                            class="form-control"
                        />
                    </div>

                </template>

                <!-- PERIOD -->

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
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Ավարտ" />

                        <input
                            v-model="form.end_date"
                            type="date"
                            class="form-control"
                        />
                    </div>
                </div>

                <!-- LIMITS -->

                <div class="row mt-4">

                    <div class="col-md-6">
                        <InputLabel value="Հյուրերի քանակ" />

                        <input
                            v-model="form.guest_limit"
                            type="number"
                            class="form-control"
                        />
                    </div>

                    <div class="col-md-6">
                        <InputLabel value="Սառեցումների քանակ" />

                        <input
                            v-model="form.freeze_limit"
                            type="number"
                            class="form-control"
                        />
                    </div>

                </div>

                <!-- ACTIVE -->

                <div class="mt-4">
                    <label class="form-check">
                        <input
                            v-model="form.active"
                            type="checkbox"
                            class="form-check-input"
                        />

                        <span class="form-check-label">
                            Ակտիվ
                        </span>
                    </label>
                </div>

                <div class="mt-4">
                    <PrimaryButton
                        :disabled="form.processing"
                    >
                        Պահպանել
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </Index>
</template>
