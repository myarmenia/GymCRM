<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTrans } from '/resources/js/trans';

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    availableLanguages: {
        type: Array,
        default: () => [],
    },
    selectedLanguageCodes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    address: '',
    phone: '',
    email: '',
    entry_code_type: 'rfId',
    language_codes: [...props.selectedLanguageCodes],
    logo: null,
    trainer_salary_mode: 'prepaid',
});

const submit = () => {
    form.post(route('gym.store', { locale: currentLocale }), {
        onError: (errors) => {
            if (Object.keys(errors).length === 0) {
                alert(t('ui.server_error'));
            }
        }
    });
};

const cancel = () => {
    router.get(route('gym.list', { locale: currentLocale }));
};

</script>

<template>
    <Head :title="t('ui.add_gym')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('ui.gym_add_header') }}
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('ui.add_gym') }}</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>{{ t('ui.gym_details') }}</h6>
                <div class="row g-6">
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" :value="t('ui.gym_name')" />
                        <TextInput
                            id="name"
                            type="text"
                            class="form-control"
                            v-model="form.name"
                            autofocus
                            tabindex="1"
                            :placeholder="t('ui.enter_gym_name')"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" :value="t('auth.email')" />
                        <TextInput
                            id="email"
                            type="email"
                            class="form-control"
                            v-model="form.email"
                            tabindex="2"
                            :placeholder="t('ui.enter_gym_email')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" :value="t('people.phone_number')" />
                        <TextInput
                            id="phone"
                            type="text"
                            class="form-control"
                            v-model="form.phone"
                            tabindex="3"
                            :placeholder="t('ui.enter_phone_example')"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="address" class="form-label" :value="t('inventory.address')" />
                        <TextInput
                            id="address"
                            type="text"
                            class="form-control"
                            v-model="form.address"
                            tabindex="4"
                            :placeholder="t('ui.enter_gym_address')"
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="entry_code_type" class="form-label" :value="t('ui.entry_code_type')" />
                        <select id="entry_code_type" v-model="form.entry_code_type" class="form-select">
                            <option value="rfId">{{ t('ui.rfid_only') }}</option>
                            <option value="FaceId">{{ t('ui.face_only') }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.entry_code_type" />
                        <InputLabel
                            for="trainer_salary_mode"
                            class="form-label"
                            :value="t('sales.trainer_salary_calculation')"
                        />
                        <select
                            id="trainer_salary_mode"
                            v-model="form.trainer_salary_mode"
                            class="form-select"
                        >
                            <option value="prepaid">{{ t('sales.prepaid') }}</option>
                            <option value="postpaid">{{ t('sales.postpaid') }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.trainer_salary_mode" />
                    </div>

                    <div class="col-12">
                        <InputLabel class="form-label" :value="t('ui.gym_languages')" />
                        <div class="form-text mb-3">{{ t('ui.gym_languages_help') }}</div>
                        <div class="d-flex flex-wrap gap-4">
                            <label
                                v-for="language in props.availableLanguages"
                                :key="language.code"
                                class="form-check"
                            >
                                <input
                                    v-model="form.language_codes"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="language.code"
                                />
                                <span class="form-check-label">
                                    {{ language.name }} ({{ language.code.toUpperCase() }})
                                </span>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="form.errors.language_codes" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="logo" class="form-label" :value="t('ui.gym_logo')" />
                        <input
                            id="logo"
                            type="file"
                            class="form-control"
                            @input="form.logo = $event.target.files[0]"
                        />
                        <InputError class="mt-2" :message="form.errors.logo" />
                    </div>

                    </div>

                <div class="pt-6">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        class="me-4"
                    >
                        {{ t('ui.submit') }}
                    </PrimaryButton>
                    <button
                        type="button"
                        @click="cancel"
                        class="btn btn-label-secondary waves-effect"
                    >
                        {{ t('confirm.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </Index>
</template>
