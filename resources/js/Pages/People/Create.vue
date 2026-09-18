<script setup>
import { computed, ref } from 'vue';
import Index from '@/Layouts/Index.vue';
import { translate, useTrans } from '/resources/js/trans'
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const t = (key, replacements = {}) => translate(page.props.translations, `app.people.${key}`, replacements);
const currentLocale = computed(() => page.props.locale ?? 'en');

const props = defineProps({
    initialGymId: Number,
    entryCodes: Array,
    entryCodeType: {
        type: String,
        default: 'rfId',
    },
});

const entryCodes = ref(props.entryCodes || []);
const imagePreview = ref(null);

const form = useForm({
    name: '',
    surname: '',
    image: null,
    email: '',
    password: '',
    phone: '',
    type: 'visitor',
    entry_code_mode: entryCodes.value.length ? 'existing' : 'new',
    entry_code_id: null,
    entry_code_token: '',
    birth_date: '',
    gender: '',
});

const handleImageUpload = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.image = file;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
};

const submit = () => {
    form.clearErrors('entry_code_id', 'entry_code_token');

    if (form.entry_code_mode === 'existing' && !form.entry_code_id) {
        form.setError('entry_code_id', t('entry_code_required'));
        return;
    }

    if (form.entry_code_mode === 'new' && !form.entry_code_token.trim()) {
        form.setError('entry_code_token', t('entry_code_required'));
        return;
    }

    if (form.entry_code_mode === 'new') {
        form.entry_code_token = form.entry_code_token.trim();
    }

    form.post(route('person.store', { locale: currentLocale.value }), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="t('create_person')" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ t('clients_create') }}</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('create_person') }}</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>{{ t('general_information') }}</h6>
                <div class="row g-6">
                    <div class="col-md-12">
                        <InputLabel for="image" class="form-label" :value="t('image')" />
                        <input
                            id="image"
                            type="file"
                            class="form-control"
                            accept="image/*"
                            @change="handleImageUpload"
                        />
                        <div v-if="imagePreview" class="mt-3">
                            <img
                                :src="imagePreview"
                                :alt="useTrans('app.ui.preview')"
                                class="rounded border object-fit-cover"
                                style="width: 120px; height: 120px;"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.image" />
                    </div>

                    <div class="col-md-12">
                        <InputLabel
                            :for="form.entry_code_mode === 'new' ? 'entry_code_token' : 'entry_codes'"
                            class="form-label"
                            :value="t('entry_code')"
                        />
                        <div v-if="entryCodes.length" class="d-flex flex-wrap gap-4 mb-3">
                            <div class="form-check">
                                <input
                                    id="entry_code_existing"
                                    v-model="form.entry_code_mode"
                                    class="form-check-input"
                                    type="radio"
                                    value="existing"
                                />
                                <label class="form-check-label" for="entry_code_existing">
                                    {{ t('use_existing_entry_code') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input
                                    id="entry_code_new"
                                    v-model="form.entry_code_mode"
                                    class="form-check-input"
                                    type="radio"
                                    value="new"
                                />
                                <label class="form-check-label" for="entry_code_new">
                                    {{ t('create_new_entry_code') }}
                                </label>
                            </div>
                        </div>

                        <select
                            v-if="form.entry_code_mode === 'existing' && entryCodes.length"
                            id="entry_codes"
                            v-model="form.entry_code_id"
                            class="form-select"
                        >
                            <option :value="null" disabled>{{ t('choose_entry_code') }}</option>
                            <option v-for="code in entryCodes" :key="code.id" :value="code.id">
                                {{ code.token }} ({{ code.gym?.name || t('without_gym') }}) {{ code.type }}
                            </option>
                        </select>

                        <div v-if="!entryCodes.length" class="alert alert-info mb-3">
                            {{ t('no_entry_codes_create_here') }}
                        </div>

                        <div v-if="form.entry_code_mode === 'new'">
                            <TextInput
                                id="entry_code_token"
                                v-model="form.entry_code_token"
                                type="text"
                                class="form-control"
                                :placeholder="t('enter_entry_code')"
                            />
                            <div class="form-text">
                                {{ useTrans('app.ui.entry_code_type') }}: {{ entryCodeType }}
                            </div>
                            <InputError class="mt-2" :message="form.errors.entry_code_token" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.entry_code_id" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" :value="t('name')" />
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus :placeholder="t('enter_name')" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="surname" class="form-label" :value="t('surname')" />
                        <TextInput id="surname" type="text" class="form-control" v-model="form.surname" :placeholder="t('enter_surname')" />
                        <InputError class="mt-2" :message="form.errors.surname" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" :value="t('email_alt')" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" :placeholder="t('enter_email_alt')" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="password" class="form-label" :value="t('password')" />
                        <TextInput id="password" type="password" class="form-control" v-model="form.password" :placeholder="t('enter_password')" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" :value="t('phone_number')" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" placeholder="+374 58 79 98 94" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="type" class="form-label" :value="t('type')" />
                        <select id="type" class="form-select" v-model="form.type">
                            <option value="visitor">{{ t('visitor') }}</option>
                            <option value="guest">{{ t('guest') }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="birth_date" class="form-label" :value="t('birth_date')" />
                        <TextInput id="birth_date" type="date" class="form-control" v-model="form.birth_date" />
                        <InputError :message="form.errors.birth_date" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="gender" class="form-label" :value="t('gender')" />
                        <select id="gender" class="form-select" v-model="form.gender">
                            <option value="" disabled>{{ t('select') }}</option>
                            <option value="male">{{ t('male') }}</option>
                            <option value="female">{{ t('female') }}</option>
                        </select>
                        <InputError :message="form.errors.gender" />
                    </div>
                </div>

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ t('create') }}
                    </PrimaryButton>
                    <button type="reset" class="btn btn-label-secondary waves-effect">{{ t('cancel') }}</button>
                </div>
            </form>
        </div>
    </Index>
</template>
