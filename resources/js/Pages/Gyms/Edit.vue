<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    gym: Object,
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
    name: props.gym.name,
    address: props.gym.address,
    phone: props.gym.phone,
    email: props.gym.email,
    entry_code_type: props.gym.entry_code_type ?? 'rfId',
    language_codes: [...props.selectedLanguageCodes],

    logo: null,
    trainer_salary_mode: props.gym.trainer_salary_mode ?? 'prepaid',
});

const submit = () => {

    form.transform((data) => ({
        ...data,
        _method: 'PATCH',
    })).post(route('gym.update', { locale: currentLocale, id: props.gym.id }), {
        onError: () => {
            console.log('Update failed');
        }
    });
};

const cancel = () => {
    router.get(route('gym.list', { locale: currentLocale }));
};
</script>

<template>
    <Head :title="`${t('ui.edit_gym')} - ${props.gym.name}`" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('ui.gym_edit_header') }}
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('ui.edit_gym') }}: {{ props.gym.name }}</h5>
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
                        <div class="form-text">
                            {{ t('operations.the_change_will_apply_only_to_newly_sold_memberships') }}
                        </div>
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
                        <div class="d-flex align-items-center gap-3">
                            <div v-if="props.gym.logo" class="mb-2">
                                <img
                                    :src="`/storage/${props.gym.logo}`"
                                    :alt="t('ui.current_logo')"
                                    class="rounded object-fit-cover"
                                    style="width: 50px; height: 50px; border: 1px solid #e5e7eb;"
                                />
                            </div>
                            <div class="flex-grow-1">
                                <input
                                    id="logo"
                                    type="file"
                                    class="form-control"
                                    @input="form.logo = $event.target.files[0]"
                                />
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.logo" />
                    </div>


                </div>

                <div class="pt-6">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        class="me-4"
                    >
                        {{ t('inventory.save_changes') }}
                    </PrimaryButton>
                    <button type="button" @click="cancel" class="btn btn-label-secondary waves-effect">{{ t('confirm.cancel') }}</button>
                </div>
            </form>
        </div>
    </Index>
</template>
