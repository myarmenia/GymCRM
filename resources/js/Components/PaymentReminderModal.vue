<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { usePage } from '@inertiajs/vue3'
import { translate } from '/resources/js/trans'

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    personName: {
        type: String,
        default: '',
    },
    debtAmount: {
        type: Number,
        default: 0,
    },
    users: {
        type: Array,
        default: () => [],
    },
    scheduledAt: {
        type: String,
        default: '',
    },
    recipientIds: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    processing: {
        type: Boolean,
        default: false,
    },
    confirmText: {
        type: String,
        default: '',
    },
})

const page = usePage()
const t = (key, replacements = {}) => translate(page.props.translations, `app.people.${key}`, replacements)

const emit = defineEmits([
    'close',
    'confirm',
    'update:scheduledAt',
    'update:recipientIds',
    'update:title',
    'update:description',
])
</script>

<template>
    <div
        v-if="show"
        class="payment-reminder-backdrop"
        @click.self="emit('close')"
    >
        <div
            class="card payment-reminder-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="payment-reminder-title"
        >
            <div class="card-header d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h4
                        id="payment-reminder-title"
                        class="mb-1"
                    >
                        {{ t('reminder_title') }}
                    </h4>
                    <div class="text-muted">
                        {{ t('balance') }}
                        <strong class="text-danger">
                            {{ Number(debtAmount || 0).toFixed(2) }}
                        </strong>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-icon btn-sm btn-label-secondary"
                    :aria-label="t('close')"
                    @click="emit('close')"
                >
                    <i class="icon-base ti tabler-x"></i>
                </button>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <InputLabel :value="t('category')" />
                    <input
                        class="form-control"
                        :value="t('payment_day')"
                        readonly
                    >
                </div>

                <div class="mb-4">
                    <InputLabel :value="t('about_person')" />
                    <input
                        class="form-control"
                        :value="personName"
                        readonly
                    >
                </div>

                <div class="mb-4">
                    <InputLabel :value="t('send_at')" />
                    <input
                        :value="scheduledAt"
                        type="datetime-local"
                        class="form-control"
                        @input="emit('update:scheduledAt', $event.target.value)"
                    >
                    <InputError :message="errors.reminder_scheduled_at" />
                </div>

                <div class="mb-4">
                    <InputLabel :value="t('recipients')" />
                    <MultiSelect
                        :model-value="recipientIds"
                        :options="users"
                        :placeholder="t('choose_recipients')"
                        @update:model-value="emit('update:recipientIds', $event)"
                    />
                    <InputError
                        :message="errors.reminder_recipient_ids || errors['reminder_recipient_ids.0']"
                    />
                </div>

                <div class="mb-4">
                    <InputLabel :value="t('title')" />
                    <input
                        :value="title"
                        type="text"
                        class="form-control"
                        @input="emit('update:title', $event.target.value)"
                    >
                    <InputError :message="errors.reminder_title" />
                </div>

                <div class="mb-0">
                    <InputLabel :value="t('description')" />
                    <textarea
                        :value="description"
                        class="form-control"
                        rows="3"
                        :placeholder="t('description_auto')"
                        @input="emit('update:description', $event.target.value)"
                    />
                    <InputError :message="errors.reminder_description" />
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <button
                    type="button"
                    class="btn btn-label-secondary"
                    @click="emit('close')"
                >
                    {{ t('close') }}
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmText || t('schedule_reminder') }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.payment-reminder-backdrop {
    align-items: center;
    background: rgba(20, 24, 31, 0.62);
    display: flex;
    inset: 0;
    justify-content: center;
    overflow-y: auto;
    padding: 1.5rem;
    position: fixed;
    z-index: 1090;
}

.payment-reminder-modal {
    margin: auto;
    max-width: 680px;
    width: 100%;
}
</style>
