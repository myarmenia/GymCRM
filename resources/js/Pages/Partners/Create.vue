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

// 💡 Գործընկերոջ ստեղծման սկզբնական դատարկ դաշտերը
const form = useForm({
    name: '',
    account_number: '',
    contract_number: '',
    address: '',
    phone: '',
    email: '',
    contact_full_name: '',
    contact_phone: '',
});

const submit = () => {
    form.post(route('partner.store', { locale: currentLocale }), {
        preserveState: false, 
        onSuccess: () => {
            console.log('Redirecting to edit page...');
        },
        onError: (errors) => {
            if (Object.keys(errors).length === 0) {
                alert(t('ui.server_error'));
            }
        }
    });
};

const cancel = () => {
    router.get(route('partner.list', { locale: currentLocale }));
};
</script>

<template>
    <Head :title="t('ui.add_partner')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('ui.partner_add_header') }}
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('ui.add_partner') }}</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>{{ t('ui.partner_details') }}</h6>
                <div class="row g-6">
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" :value="t('ui.partner_company_name')" />
                        <TextInput
                            id="name"
                            type="text"
                            class="form-control"
                            v-model="form.name"
                            autofocus
                            tabindex="1"
                            :placeholder="t('ui.enter_partner_name')"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="account_number" class="form-label" :value="t('ui.account_number')" />
                        <TextInput
                            id="account_number"
                            type="text"
                            class="form-control"
                            v-model="form.account_number"
                            tabindex="2"
                            :placeholder="t('ui.enter_account_number')"
                        />
                        <InputError class="mt-2" :message="form.errors.account_number" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="contract_number" class="form-label" :value="t('ui.contract_number')" />
                        <TextInput
                            id="contract_number"
                            type="text"
                            class="form-control"
                            v-model="form.contract_number"
                            tabindex="3"
                            :placeholder="t('ui.enter_contract_number')"
                        />
                        <InputError class="mt-2" :message="form.errors.contract_number" />
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
                        <InputLabel for="email" class="form-label" :value="t('auth.email')" />
                        <TextInput
                            id="email"
                            type="email"
                            class="form-control"
                            v-model="form.email"
                            tabindex="4"
                            :placeholder="t('ui.enter_partner_email')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="col-6">
                        <InputLabel for="address" class="form-label" :value="t('inventory.address')" />
                      
                        <TextInput
                            id="address"
                            type="text"
                            class="form-control"
                            v-model="form.address"
                            tabindex="5"
                            :placeholder="t('ui.enter_partner_address')"
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-0">{{ t('ui.contact_person_details') }}</h6>

                    <div class="col-md-6">
                        <InputLabel for="contact_full_name" class="form-label" :value="t('ui.contact_full_name')" />
                        <TextInput
                            id="contact_full_name"
                            type="text"
                            class="form-control"
                            v-model="form.contact_full_name"
                            tabindex="6"
                            :placeholder="t('ui.enter_contact_name')"
                        />
                        <InputError class="mt-2" :message="form.errors.contact_full_name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="contact_phone" class="form-label" :value="t('ui.contact_phone_optional')" />
                        <TextInput
                            id="contact_phone"
                            type="text"
                            class="form-control"
                            v-model="form.contact_phone"
                            tabindex="7"
                            :placeholder="t('ui.enter_contact_phone')"
                        />
                        <InputError class="mt-2" :message="form.errors.contact_phone" />
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
