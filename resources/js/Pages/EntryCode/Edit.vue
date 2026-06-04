<script setup>
import { onMounted, computed, nextTick } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router } from '@inertiajs/vue3';

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const props = defineProps({
    entryCode: Object,
    gyms: Array,
});

const form = useForm({
    gym_id: '',
    token: '',
    type: 'rfId',
    activation: false,
    status: true,
});

const gymOptions = computed(() => {
    return props.gyms.map(gym => ({
        value: gym.id,
        text: gym.name
    }));
});

onMounted(async () => {
    await nextTick();

    // Fill form with existing data
    if (props.entryCode) {
        form.gym_id = props.entryCode.gym_id || '';
        form.token = props.entryCode.token || '';
        form.type = props.entryCode.type || 'rfId';
        form.activation = props.entryCode.activation ?? false;
        form.status = props.entryCode.status ?? true;
    }

    // Initialize Select2 for gyms (only if owner and gyms exist)
    const $gyms = window.$('#gyms');
    if ($gyms.length) {
        $gyms.select2({
            width: '100%',
            placeholder: 'Ընտրել մարզասրահը'
        });
        if (form.gym_id) {
            $gyms.val(form.gym_id).trigger('change');
        }
        $gyms.on('change', function () {
            form.gym_id = window.$(this).val();
        });
    }
});

const submit = () => {
    form.patch(route('entry-code.update', { 
        locale: currentLocale, 
        id: props.entryCode.id 
    }), {
        onError: (errors) => {
            console.log(errors);
        }
    });
};
</script>

<template>
    <Head title="Խմբագրել մուտքի կոդ" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Մուտքի կոդ / Խմբագրել
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Խմբագրել կոդը</h5>
            <form @submit.prevent="submit" class="card-body">
                <!-- Gym dropdown – visible only for owner (same logic as create) -->
                <div class="row mb-3" v-if="$page.props.auth.user.roles.some(r => r.name === 'owner')">
                    <label class="col-sm-3 col-form-label">Մարզասրահ</label>
                    <div class="col-sm-9 select2-primary">
                        <select v-model="form.gym_id" class="form-select">
                            <option value="" disabled>Ընտրել մարզասրահը</option>


                            <option
                                v-for="gym in gyms"
                                :key="gym.id"
                                :value="gym.id"
                            >
                                {{ gym.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.gym_id" />
                    </div>
                </div>

                <!-- Token -->
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Թոքեն</label>
                    <div class="col-sm-9">
                        <TextInput
                            type="text"
                            class="form-control"
                            v-model="form.token"
                            placeholder="Մուտքագրել թոքենը"
                        />
                        <InputError :message="form.errors.token" />
                    </div>
                </div>

                <!-- Type (only for owner) -->
                <div class="row mb-3" >
                    <label class="col-sm-3 col-form-label">Տեսակ</label>
                    <div class="col-sm-9">
                        <select class="form-select" v-model="form.type">
                            <option value="rfId">rfId</option>
                            <option value="FaceId">FaceID</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>

                <!-- Status (active/inactive) -->
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Կարգավիճակ</label>
                    <div class="col-sm-9">
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                v-model="form.status"
                                :checked="form.status"
                            />
                            <label class="form-check-label">
                                {{ form.status ? 'Ակտիվ է' : 'Ակտիվ չէ' }}
                            </label>
                        </div>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>



                <!-- Submit buttons -->
                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Թարմացնել
                    </PrimaryButton>
                    <button
                        type="button"
                        class="btn btn-label-secondary waves-effect"
                        @click="() => {
                            router.get(route('entry-code.list', { locale: currentLocale }));
                        }"
                    >
                        Չեղարկել
                    </button>
                </div>
            </form>
        </div>
    </Index>
</template>