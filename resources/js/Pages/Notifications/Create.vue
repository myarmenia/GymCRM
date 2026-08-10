<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import PersonSelect from './PersonSelect.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    reminderUsers: {
        type: Array,
        default: () => [],
    },
    people: {
        type: Array,
        default: () => [],
    },
    reminderCategories: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    delivery_mode: 'now',
    send_to_all: false,
    recipient_ids: [],
    about_id: '',
    category_id: '',
    scheduled_at: '',
    title: '',
    description: '',
})

watch(
    () => form.send_to_all,
    value => {
        if (value) {
            form.recipient_ids = []
        }
    },
)

watch(
    () => form.delivery_mode,
    value => {
        form.recipient_ids = []

        if (value === 'scheduled') {
            form.send_to_all = false
        } else {
            form.category_id = ''
            form.scheduled_at = ''
        }
    },
)

const submit = () => {
    if (form.delivery_mode === 'scheduled') {
        form.post(route('reminders.store', { locale: currentLocale.value }))
        return
    }

    form
        .transform(data => ({
            ...data,
            send_to_all: data.send_to_all ? 1 : 0,
            recipient_ids: data.send_to_all ? [] : data.recipient_ids,
        }))
        .post(route('notifications.store', { locale: currentLocale.value }), {
            onFinish: () => form.transform(data => data),
        })
}
</script>

<template>
    <Head title="Ուղարկել ծանուցում" />

    <Index>
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
            <h2 class="mb-0">Ուղարկել ծանուցում</h2>
            <Link
                class="btn btn-secondary"
                :href="route('notifications.index', { locale: currentLocale })"
            >
                Վերադառնալ
            </Link>
        </div>

        <form
            class="card"
            @submit.prevent="submit"
        >
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label">Ուղարկման տեսակ</label>
                        <div class="d-flex gap-4">
                            <label class="form-check">
                                <input
                                    v-model="form.delivery_mode"
                                    class="form-check-input"
                                    type="radio"
                                    value="now"
                                >
                                <span class="form-check-label">Ուղարկել հիմա</span>
                            </label>
                            <label class="form-check">
                                <input
                                    v-model="form.delivery_mode"
                                    class="form-check-input"
                                    type="radio"
                                    value="scheduled"
                                >
                                <span class="form-check-label">Պլանավորել որպես հիշեցում</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input
                                id="send_to_all"
                                v-model="form.send_to_all"
                                class="form-check-input"
                                type="checkbox"
                                :disabled="form.delivery_mode === 'scheduled'"
                            >
                            <label
                                class="form-check-label"
                                for="send_to_all"
                            >
                                Ուղարկել բոլոր օգտատերերին
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="form.delivery_mode === 'scheduled'"
                        class="col-md-6"
                    >
                        <label class="form-label">Կատեգորիա</label>
                        <select
                            v-model="form.category_id"
                            class="form-select"
                        >
                            <option value="" disabled>Ընտրեք կատեգորիան</option>
                            <option
                                v-for="category in reminderCategories"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category_id" />
                    </div>

                    <div
                        v-if="form.delivery_mode === 'scheduled'"
                        class="col-md-6"
                    >
                        <label class="form-label">Ուղարկման օր և ժամ</label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="form-control"
                        >
                        <InputError :message="form.errors.scheduled_at" />
                    </div>

                    <div
                        v-if="!form.send_to_all"
                        class="col-12"
                    >
                        <label class="form-label">Ստացող օգտատերեր</label>
                        <MultiSelect
                            v-model="form.recipient_ids"
                            :options="form.delivery_mode === 'scheduled' ? reminderUsers : users"
                            placeholder="Ընտրեք օգտատերերին"
                        />
                        <InputError :message="form.errors.recipient_ids || form.errors['recipient_ids.0']" />
                    </div>

                    <div class="col-12">
                        <label class="form-label">Հաճախորդ</label>
                        <PersonSelect
                            v-model="form.about_id"
                            :options="people"
                            placeholder="Որոնել կամ ընտրել հաճախորդին"
                        />
                        <InputError :message="form.errors.about_id" />
                    </div>

                    <div class="col-12">
                        <label
                            class="form-label"
                            for="title"
                        >
                            Վերնագիր
                        </label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="form-control"
                        >
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="col-12">
                        <label
                            class="form-label"
                            for="description"
                        >
                            Նկարագրություն
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="form-control"
                            rows="5"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('notifications.index', { locale: currentLocale })"
                    >
                        Չեղարկել
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.delivery_mode === 'scheduled' ? 'Պլանավորել' : 'Ուղարկել' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </Index>
</template>
