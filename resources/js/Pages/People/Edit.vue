<script setup>
import { computed, onMounted, ref } from 'vue';
import Index from '@/Layouts/Index.vue';
import { translate, useTrans } from '/resources/js/trans'
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DocumentsUploader from '@/Components/DocumentsUploader.vue';

const page = usePage();
const t = (key, replacements = {}) => translate(page.props.translations, `app.people.${key}`, replacements);
const currentLocale = computed(() => page.props.locale ?? 'en');

const props = defineProps({
    person: Object,
    initialGymId: Number,
    selectedEntryCodeId: Number,
    entryCodes: Array,
});

const entryCodes = ref([]);
const imagePreview = ref(props.person?.image ? `/storage/${props.person.image}` : null);

const form = useForm({
    _method: 'patch',
    name: props.person?.name || '',
    surname: props.person?.surname || '',
    image: null,
    email: props.person?.email || '',
    password: '',
    phone: props.person?.phone || '',
    type: props.person?.type || 'visitor',
    entry_code_id: props.selectedEntryCodeId ?? null,
    birth_date: props.person?.birth_date || '',
    gender: props.person?.gender || '',
});

onMounted(() => {
    entryCodes.value = props.entryCodes || [];
});

const handleImageUpload = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.image = file;
    imagePreview.value = file
        ? URL.createObjectURL(file)
        : (props.person?.image ? `/storage/${props.person.image}` : null);
};

const submit = () => {
    form.post(route('person.update', {
        locale: currentLocale.value,
        id: props.person.id,
    }), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="t('edit_person')" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ t('clients_edit') }}</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('edit_person') }}</h5>
            <form @submit.prevent="submit" class="card-body">
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

                    <div v-if="entryCodes.length" class="col-md-12">
                        <InputLabel for="entry_codes" class="form-label" :value="t('entry_code')" />
                        <select id="entry_codes" class="form-select" v-model="form.entry_code_id">
                            <option :value="null">{{ t('none') }}</option>
                            <option v-for="code in entryCodes" :key="code.id" :value="code.id">
                                {{ code.token }} ({{ code.gym?.name || t('without_gym') }}) {{ code.type }}
                            </option>
                        </select>
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
                        <InputLabel for="email" class="form-label" :value="t('email')" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" :placeholder="t('enter_email')" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="password" class="form-label" :value="t('password')" />
                        <TextInput id="password" type="password" class="form-control" v-model="form.password" :placeholder="t('leave_password_empty')" />
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
                        {{ t('update') }}
                    </PrimaryButton>
                    <button type="reset" class="btn btn-label-secondary waves-effect">{{ t('cancel') }}</button>
                </div>
            </form>
        </div>

        <DocumentsUploader :ownerType="'person'" :ownerId="props.person.id" />
    </Index>
</template>
