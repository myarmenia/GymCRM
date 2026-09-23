<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useTrans } from '/resources/js/trans';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="useTrans('app.auth.confirm_password')" />

        <div class="mb-4 text-sm text-gray-600">
            {{ useTrans("app.auth.confirm_password_help") }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" :value="useTrans('app.auth.password')" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 flex justify-end">
                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ useTrans("app.confirm.accept") }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
