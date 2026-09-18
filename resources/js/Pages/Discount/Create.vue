<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipPlans: {
        type: Array,
        default: () => [],
    },
    langs: {
        type: Array,
        default: null,
    },
    locales: {
        type: Array,
        default: () => [],
    },
})

const availableLangs = computed(() => props.langs ?? props.locales ?? ['hy'])

const form = useForm({
    type: 'percent',
    value: 0,
    start_date: null,
    end_date: null,
    status: true,
    membership_plan_ids: [],
    translations: {},
})

availableLangs.value.forEach(code => {
    form.translations[code] = {
        name: '',
        description: '',
    }
})

watch(currentLocale, () => {
    form.errors = {}
})

watch(() => form.type, () => {
    form.value = null
})

const planName = plan => {
    return plan.translations?.find(item => item.locale === currentLocale.value)?.name
        ?? plan.name
        ?? `#${plan.id}`
}

const submit = () => {
    form.post(route('discount.store', { locale: currentLocale.value }))
}
</script>

<template>
    <Head :title="t('operations.create_discount')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                {{ t('operations.create_discount') }}
            </h2>
        </template>

        <div class="card">
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('people.type')" />
                        <select
                            v-model="form.type"
                            class="form-select"
                        >
                            <option value="percent">
                                {{ t('staff_reports.percent') }}
                            </option>
                            <option value="fixed">
                                {{ t('staff_reports.fixed') }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('membership.cost')" />
                        <input
                            v-model="form.value"
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                        />
                        <InputError :message="form.errors.value" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('operations.start_date')" />
                        <input
                            v-model="form.start_date"
                            type="datetime-local"
                            class="form-control"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="col-md-6 mb-4">
                        <InputLabel :value="t('operations.end_date')" />
                        <input
                            v-model="form.end_date"
                            type="datetime-local"
                            class="form-control"
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>
                </div>

                <div class="mb-4">
                    <InputLabel :value="t('operations.membership_plans')" />
                    <select
                        v-model="form.membership_plan_ids"
                        class="form-select"
                        multiple
                    >
                        <option
                            v-for="plan in membershipPlans"
                            :key="plan.id"
                            :value="plan.id"
                        >
                            {{ planName(plan) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.membership_plan_ids" />
                </div>

                <div
                    v-for="code in availableLangs"
                    :key="code"
                    class="border rounded p-3 mb-4"
                >
                    <h5>
                        {{ code.toUpperCase() }}
                    </h5>

                    <div class="mb-3">
                        <InputLabel :value="t('membership.title')" />
                        <input
                            v-model="form.translations[code].name"
                            class="form-control"
                            type="text"
                        />
                        <InputError :message="form.errors[`translations.${code}.name`]" />
                    </div>

                    <div>
                        <InputLabel :value="t('people.description')" />
                        <textarea
                            v-model="form.translations[code].description"
                            class="form-control"
                        />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-check">
                        <input
                            v-model="form.status"
                            type="checkbox"
                            class="form-check-input"
                        />
                        <span class="form-check-label">
                            {{ t('status.active') }}
                        </span>
                    </label>
                </div>

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :disabled="form.processing">
                        {{ t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Index>
</template>
