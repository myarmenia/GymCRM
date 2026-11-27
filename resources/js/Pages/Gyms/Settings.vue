<script setup>
import { translate } from '/resources/js/trans';
import Index from '@/Layouts/Index.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    gym: { type: Object, required: true },
    mode: { type: String, required: true },
    startMode: { type: String, required: true },
    isCreate: { type: Boolean, required: true },
});

const page = usePage();
const locale = page.props.locale ?? 'en';
const t = (key) => translate(page.props.translations, `app.ui.${key}`);
const form = useForm({ membership_selection_mode: props.mode, membership_start_mode: props.startMode });

const submit = () => {
    const routeName = props.isCreate ? 'gym.settings.store' : 'gym.settings.update';
    const url = route(routeName, { locale, gym: props.gym.id });
    if (props.isCreate) {
        form.post(url);
    } else {
        form.patch(url);
    }
};
</script>

<template>
    <Head :title="t('gym_settings')" />
    <Index>
        <template #header>
            <h2>{{ t('gym_settings') }} — {{ gym.name }}</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('gym_settings') }} — {{ gym.name }}</h5>
            <form class="card-body" @submit.prevent="submit">
                <div class="mb-4 fw-semibold">{{ t('membership_selection_mode') }}</div>
                <div class="d-flex flex-column gap-3">
                    <label class="border rounded p-3 d-flex gap-3 align-items-start">
                        <input v-model="form.membership_selection_mode" class="form-check-input mt-1" type="radio" value="auto_up_to_two" />
                        <span>
                            <strong>{{ t('auto_up_to_two') }}</strong>
                            <span class="d-block text-muted small">{{ t('auto_up_to_two_help') }}</span>
                        </span>
                    </label>
                    <label class="border rounded p-3 d-flex gap-3 align-items-start">
                        <input v-model="form.membership_selection_mode" class="form-check-input mt-1" type="radio" value="manager_select_multiple" />
                        <span>
                            <strong>{{ t('manager_select_multiple') }}</strong>
                            <span class="d-block text-muted small">{{ t('manager_select_multiple_help') }}</span>
                        </span>
                    </label>
                </div>
                <div v-if="form.errors.membership_selection_mode" class="text-danger mt-2">{{ form.errors.membership_selection_mode }}</div>
                <div class="mb-4 mt-5 fw-semibold">{{ t('membership_start_mode') }}</div>
                <div class="d-flex flex-column gap-3">
                    <label class="border rounded p-3 d-flex gap-3 align-items-start">
                        <input v-model="form.membership_start_mode" class="form-check-input mt-1" type="radio" value="sale_date" />
                        <span><strong>{{ t('sale_date_start') }}</strong><span class="d-block text-muted small">{{ t('sale_date_start_help') }}</span></span>
                    </label>
                    <label class="border rounded p-3 d-flex gap-3 align-items-start">
                        <input v-model="form.membership_start_mode" class="form-check-input mt-1" type="radio" value="first_entry" />
                        <span><strong>{{ t('first_entry_start') }}</strong><span class="d-block text-muted small">{{ t('first_entry_start_help') }}</span></span>
                    </label>
                </div>
                <div v-if="form.errors.membership_start_mode" class="text-danger mt-2">{{ form.errors.membership_start_mode }}</div>
                <div class="d-flex gap-2 mt-5">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">{{ t('save_settings') }}</button>
                    <Link v-if="!isCreate" class="btn btn-label-secondary" :href="route('gym.list', { locale })">{{ t('back_to_gyms') }}</Link>
                </div>
            </form>
        </div>
    </Index>
</template>
