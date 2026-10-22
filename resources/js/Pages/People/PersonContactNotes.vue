<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { translate } from '/resources/js/trans'

const props = defineProps({
    person: { type: Object, required: true },
    notes: { type: Array, default: () => [] },
    canAdd: { type: Boolean, default: false },
})

const page = usePage()
const locale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')
const t = key => translate(page.props.translations, `app.contact_notes.${key}`)
const showingForm = ref(false)
const form = useForm({ note: '' })
const formatNoteDate = value => value ? new Date(value).toLocaleString(locale.value) : '-'
const authorName = user => [user?.name, user?.surname].filter(Boolean).join(' ')

const saveNote = () => form.post(route('person.profile.notes.store', {
    locale: locale.value,
    id: props.person.id,
}), {
    preserveScroll: true,
    onSuccess: () => {
        form.reset()
        showingForm.value = false
    },
})
</script>

<template>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">{{ t('title') }}</h5>
                <small class="text-muted">{{ person.phone || '-' }}</small>
            </div>
            <button v-if="canAdd && !showingForm" type="button" class="btn btn-primary" @click="showingForm = true; form.clearErrors()">
                {{ t('add_note') }}
            </button>
        </div>
        <div class="card-body">
            <form v-if="canAdd && showingForm" class="mb-4" @submit.prevent="saveNote">
                <label class="form-label" for="person-contact-note">{{ t('note') }}</label>
                <textarea id="person-contact-note" v-model="form.note" class="form-control" rows="3" required></textarea>
                <div v-if="form.errors.note || form.errors.phone_number" class="text-danger small mt-1">
                    {{ form.errors.note || form.errors.phone_number }}
                </div>
                <div class="mt-3 d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">{{ t('save') }}</button>
                </div>
            </form>
            <div v-if="notes.length" class="d-flex flex-column gap-3">
                <div v-for="note in notes" :key="note.id" class="border rounded p-3">
                    <div class="small text-muted mb-2">
                        {{ formatNoteDate(note.created_at) }}
                        <span v-if="authorName(note.user)" class="ms-2">{{ authorName(note.user) }}</span>
                    </div>
                    <div class="text-break" style="white-space: pre-wrap">{{ note.note }}</div>
                </div>
            </div>
            <div v-else class="text-muted">{{ t('empty') }}</div>
        </div>
    </div>
</template>
