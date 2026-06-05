<script setup>
import { computed, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    category: Object,
    gyms: {
        type: Array,
        default: () => [],
    },
    canSelectGym: Boolean,
    langs: {
        type: Array,
        default: null,
    },
    locales: {
        type: Array,
        default: () => [],
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
})

const availableLangs = computed(() => props.langs ?? props.locales ?? ['hy'])
const preparedTranslations = {}

availableLangs.value.forEach(code => {
    preparedTranslations[code] = {
        name: props.translations?.[code]?.name ?? '',
        description: props.translations?.[code]?.description ?? '',
    }
})

const form = useForm({
    gym_id: props.category.gym_id ?? null,
    slug: props.category.slug ?? '',
    active: Boolean(props.category.active),
    translations: preparedTranslations,
})

watch(currentLocale, () => {
    form.errors = {}
})

const submit = () => {
    form.patch(
        route('membership-category.update', {
            locale: currentLocale.value,
            id: props.category.id,
        })
    )
}
</script>

<template>
    <Head title="Խմբագրել կատեգորիան" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                Խմբագրել կատեգորիան
            </h2>
        </template>

        <div class="card">
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div
                    v-if="canSelectGym"
                    class="mb-4"
                >
                    <InputLabel value="Մարզադահլիճ" />

                    <select
                        v-model="form.gym_id"
                        class="form-select"
                    >
                        <option :value="null">
                            Ընտրել
                        </option>

                        <option
                            v-for="gym in gyms"
                            :key="gym.id"
                            :value="gym.id"
                        >
                            {{ gym.name }}
                        </option>
                    </select>

                    <InputError :message="form.errors.gym_id" />
                </div>

                <div class="mb-4">
                    <InputLabel value="Slug" />

                    <input
                        v-model="form.slug"
                        type="text"
                        class="form-control"
                    />

                    <InputError :message="form.errors.slug" />
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

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :disabled="form.processing">
                        Թարմացնել
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Index>
</template>
