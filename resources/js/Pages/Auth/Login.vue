<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useTrans } from '/resources/js/trans';
import '../../../assets/vendor/css/pages/page-auth.css';

import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login', { locale: usePage().props.locale }), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="useTrans('app.auth.login')" />
        <!-- Login -->
        <div class="card">
            <div class="card-body">
                <!-- Logo -->
                <div class="app-brand justify-content-center mb-6">
                    <a href="index.html" class="app-brand-link">
                        <span class="app-brand-text demo text-heading fw-bold">Gym CRM</span>
                    </a>
                </div>
                <!-- /Logo -->

                <p class="mb-6">{{ useTrans("app.auth.login_intro") }}</p>

                <form @submit.prevent="submit" id="formAuthentication" class="mb-4 fv-plugins-bootstrap5 fv-plugins-framework" >
                    <div class="mb-6 form-control-validation fv-plugins-icon-container fv-plugins-bootstrap5-row-invalid">
                        <InputLabel for="email" class="form-label" :value="useTrans('app.auth.email_or_username')" />
                        <TextInput
                            id="email"
                            type="email"
                            class="form-control "
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            :placeholder="useTrans('app.auth.email_or_username_placeholder')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div class="mb-6 form-password-toggle form-control-validation fv-plugins-icon-container fv-plugins-bootstrap5-row-invalid">
                        <InputLabel class="form-label" for="password" :value="useTrans('app.auth.password_prompt')" />
                        <TextInput
                            id="password"
                            type="password"
                            class="form-control "
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="············"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="mb-6">
                        <button class="btn btn-primary d-grid w-100 waves-effect waves-light" type="submit">{{ useTrans("app.auth.login") }}</button>
                    </div>
                </form>
            </div>
            <!-- /Login -->
        </div>
    </GuestLayout>
</template>
