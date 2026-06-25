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
    people: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    send_to_all: false,
    recipient_ids: [],
    about_id: '',
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

const submit = () => {
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
                        <div class="form-check">
                            <input
                                id="send_to_all"
                                v-model="form.send_to_all"
                                class="form-check-input"
                                type="checkbox"
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
                        v-if="!form.send_to_all"
                        class="col-12"
                    >
                        <label class="form-label">Ստացող օգտատերեր</label>
                        <MultiSelect
                            v-model="form.recipient_ids"
                            :options="users"
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
                        Ուղարկել
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </Index>
</template>
