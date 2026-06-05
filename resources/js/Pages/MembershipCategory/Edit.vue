<script setup>
import { onMounted } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const currentLocale = page.props.locale ?? 'hy';
const props = defineProps({
    category: Object,
    gyms: Array,
    canSelectGym: Boolean,
    locales: Array,
    translations: Object, // already formatted
});

const form = useForm({
    gym_id: props.category.gym_id ?? null,
    active: props.category.active,
    slug: props.category.slug,
    translations: props.translations,
});

const submit = () => {
    form.patch(route('membership-category.update', { locale: currentLocale, id: props.category.id }));
};
</script>

<template>
    <Head title="Խմբագրել կատեգորիա" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Անդամակցության կատեգորիաներ / Խմբագրել
            </h2>
        </template>
        <div class="card mb-6">
            <h5 class="card-header">Խմբագրել "{{ category.slug }}"</h5>
            <form @submit.prevent="submit" class="card-body">
                <!-- same fields as create, but with existing values -->
                <div class="row g-6">
                    <div v-if="canSelectGym" class="col-md-12">
                        <InputLabel value="Մարզադահլիճ" />
                        <select v-model="form.gym_id" class="form-select">
                            <option :value="null">Ընտրել...</option>
                            <option v-for="gym in gyms" :key="gym.id" :value="gym.id">
                                {{ gym.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gym_id" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ակտիվ</label>
                        <div class="form-check form-switch">
                            <input type="checkbox" v-model="form.active" class="form-check-input" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <InputLabel value="Slug" />
                        <TextInput v-model="form.slug" />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div v-for="locale in locales" :key="locale" class="col-md-12 border p-3 mb-3">
                        <h6>{{ locale.toUpperCase() }}</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <InputLabel :for="`name_${locale}`" value="Անվանում" />
                                <TextInput v-model="form.translations[locale].name" :id="`name_${locale}`" />
                                <InputError :message="form.errors[`translations.${locale}.name`]" />
                            </div>
                            <div class="col-md-6">
                                <InputLabel :for="`desc_${locale}`" value="Նկարագրություն" />
                                <textarea class="form-control" v-model="form.translations[locale].description" :id="`desc_${locale}`" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :disabled="form.processing">Թարմացնել</PrimaryButton>
                    <button type="reset" class="btn btn-secondary">Չեղարկել</button>
                </div>
            </form>
        </div>
    </Index>
</template>