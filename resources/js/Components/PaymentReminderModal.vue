<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import MultiSelect from '@/Components/MultiSelect.vue'

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
        default: 'Պլանավորել հիշեցումը',
    },
})

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
                        Աբոնեմենտի վճարման հիշեցում
                    </h4>
                    <div class="text-muted">
                        Մնացորդ՝
                        <strong class="text-danger">
                            {{ Number(debtAmount || 0).toFixed(2) }}
                        </strong>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-icon btn-sm btn-label-secondary"
                    aria-label="Փակել"
                    @click="emit('close')"
                >
                    <i class="icon-base ti tabler-x"></i>
                </button>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <InputLabel value="Կատեգորիա" />
                    <input
                        class="form-control"
                        value="Աբոնեմենտի վճարման օր"
                        readonly
                    >
                </div>

                <div class="mb-4">
                    <InputLabel value="Ում մասին է" />
                    <input
                        class="form-control"
                        :value="personName"
                        readonly
                    >
                </div>

                <div class="mb-4">
                    <InputLabel value="Ուղարկման օր և ժամ" />
                    <input
                        :value="scheduledAt"
                        type="datetime-local"
                        class="form-control"
                        @input="emit('update:scheduledAt', $event.target.value)"
                    >
                    <InputError :message="errors.reminder_scheduled_at" />
                </div>

                <div class="mb-4">
                    <InputLabel value="Ստացողներ" />
                    <MultiSelect
                        :model-value="recipientIds"
                        :options="users"
                        placeholder="Ընտրեք ստացողներին"
                        @update:model-value="emit('update:recipientIds', $event)"
                    />
                    <InputError
                        :message="errors.reminder_recipient_ids || errors['reminder_recipient_ids.0']"
                    />
                </div>

                <div class="mb-4">
                    <InputLabel value="Վերնագիր" />
                    <input
                        :value="title"
                        type="text"
                        class="form-control"
                        @input="emit('update:title', $event.target.value)"
                    >
                    <InputError :message="errors.reminder_title" />
                </div>

                <div class="mb-0">
                    <InputLabel value="Նկարագրություն" />
                    <textarea
                        :value="description"
                        class="form-control"
                        rows="3"
                        placeholder="Դատարկ թողնելու դեպքում տեքստը կկազմվի ավտոմատ"
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
                    Փակել
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmText }}
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
