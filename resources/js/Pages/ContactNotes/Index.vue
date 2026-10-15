<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import { translate } from '/resources/js/trans'

const props = defineProps({
    contacts: { type: Object, required: true },
    salesManagers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    canCreate: { type: Boolean, default: false },
})
const page = usePage()
const locale = computed(() => page.props.locale ?? 'hy')
const t = key => translate(page.props.translations, `app.contact_notes.${key}`)
const contactRows = computed(() => Array.isArray(props.contacts?.data) ? props.contacts.data : [])
const personFields = ['name', 'surname', 'email', 'birth_date', 'gender']
const fields = [...personFields, 'relationship']
const blank = () => ({ phone_number: '', note: '', name: '', surname: '', email: '', birth_date: '', gender: '', relationship: '' })
const filter = ref({
    sales_manager_id: props.filters.sales_manager_id ?? '',
    phone_number: props.filters.phone_number ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
})
const createOpen = ref(false)
const expanded = ref(null)
const addingTo = ref(null)
const relatedMode = ref('new')
const editing = ref(null)
const notingTo = ref(null)
const createForm = useForm(blank())
const relatedForm = useForm(blank())
const linkForm = useForm({ phone_number: '', relationship: '' })
const noteForm = useForm({ note: '' })
const editForm = useForm(blank())

const applyFilters = () => router.get(
    route('contact-notes.index', { locale: locale.value }),
    Object.fromEntries(Object.entries(filter.value).filter(([, value]) => value !== '')),
    { preserveState: true, preserveScroll: true, replace: true },
)
const resetFilters = () => {
    filter.value = { sales_manager_id: '', phone_number: '', date_from: '', date_to: '' }
    applyFilters()
}
const create = () => createForm.post(route('contact-notes.store', { locale: locale.value }), {
    preserveScroll: true,
    onSuccess: () => { createForm.reset(); createOpen.value = false },
})
const startRelated = group => {
    expanded.value = group.id
    addingTo.value = group.id
    relatedMode.value = 'new'
    relatedForm.reset()
    relatedForm.clearErrors()
    linkForm.reset()
    linkForm.clearErrors()
}
const addRelated = group => relatedForm.post(route('contact-notes.add-related', {
    locale: locale.value, contactProfile: group.id,
}), {
    preserveScroll: true,
    onSuccess: () => { relatedForm.reset(); addingTo.value = null; expanded.value = group.id },
})
const linkExisting = group => linkForm.post(route('contact-notes.link-existing', {
    locale: locale.value, contactProfile: group.id,
}), {
    preserveScroll: true,
    onSuccess: () => { linkForm.reset(); addingTo.value = null; expanded.value = group.id },
})
const startEdit = member => {
    editing.value = member.id
    for (const field of fields) editForm[field] = member[field] ?? ''
    editForm.clearErrors()
}
const saveEdit = member => editForm.patch(route('contact-notes.update-profile', {
    locale: locale.value, contactProfile: member.id,
}), { preserveScroll: true, onSuccess: () => { editing.value = null } })
const addNote = member => noteForm.post(route('contact-notes.add-profile-note', {
    locale: locale.value, contactProfile: member.id,
}), { preserveScroll: true, onSuccess: () => { noteForm.reset(); notingTo.value = null } })
const formatDate = value => value ? new Date(value).toLocaleString(locale.value) : '—'
const fullName = value => [value?.name, value?.surname].filter(Boolean).join(' ') || '—'
const latestPrimaryNote = group => group.members?.find(member => member.id === group.id)?.notes?.[0]?.note || '—'
const toggleGroup = group => { expanded.value = expanded.value === group.id ? null : group.id }
</script>

<template>
    <Head :title="t('title')" />
    <Index>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">{{ t('title') }}</h2>
            <button v-if="canCreate" class="btn btn-primary" type="button" @click="createOpen = !createOpen">{{ t('new_contact') }}</button>
        </div>

        <div v-if="createOpen && canCreate" class="card mb-4"><form class="card-body" @submit.prevent="create">
            <h5>{{ t('new_contact') }}</h5>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">{{ t('phone_number') }} *</label><input v-model="createForm.phone_number" class="form-control" type="tel" required><div class="text-danger small">{{ createForm.errors.phone_number }}</div></div>
                <div class="col-md-8"><label class="form-label">{{ t('note') }} *</label><textarea v-model="createForm.note" class="form-control" required></textarea><div class="text-danger small">{{ createForm.errors.note }}</div></div>
                <div v-for="field in personFields" :key="field" class="col-md-4">
                    <label class="form-label">{{ t(field) }}</label>
                    <select v-if="field === 'gender'" v-model="createForm[field]" class="form-select"><option value="">—</option><option value="male">{{ t('male') }}</option><option value="female">{{ t('female') }}</option></select>
                    <input v-else v-model="createForm[field]" class="form-control" :type="field === 'birth_date' ? 'date' : field === 'email' ? 'email' : 'text'">
                    <div class="text-danger small">{{ createForm.errors[field] }}</div>
                </div>
            </div>
            <button class="btn btn-primary mt-3" type="submit" :disabled="createForm.processing">{{ t('save') }}</button>
        </form></div>

        <div class="card mb-4"><form class="card-body" @submit.prevent="applyFilters"><div class="row g-3 align-items-end">
            <div v-if="!canCreate" class="col-md-3"><label class="form-label">{{ t('sales_manager') }}</label><select v-model="filter.sales_manager_id" class="form-select"><option value="">{{ t('all_managers') }}</option><option v-for="manager in salesManagers" :key="manager.id" :value="manager.id">{{ fullName(manager) }}</option></select></div>
            <div class="col-md-3"><label class="form-label">{{ t('phone_number') }}</label><input v-model="filter.phone_number" class="form-control" type="search"></div>
            <div class="col-md-2"><label class="form-label">{{ t('date_from') }}</label><input v-model="filter.date_from" class="form-control" type="date"></div>
            <div class="col-md-2"><label class="form-label">{{ t('date_to') }}</label><input v-model="filter.date_to" class="form-control" type="date" :min="filter.date_from || undefined"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary" type="submit">{{ t('filter') }}</button><button class="btn btn-outline-secondary" type="button" @click="resetFilters">{{ t('reset') }}</button></div>
        </div></form></div>

        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>{{ t('phone_number') }}</th><th>{{ t('name') }}</th><th>{{ t('latest_note') }}</th><th v-if="!canCreate">{{ t('sales_manager') }}</th><th>{{ t('related_phones') }}</th><th></th></tr></thead>
            <tbody>
                <template v-for="group in contactRows" :key="group.id">
                    <tr class="contact-group-row" role="button" tabindex="0" :aria-expanded="expanded === group.id" @click="toggleGroup(group)" @keydown.enter.self.prevent="toggleGroup(group)" @keydown.space.self.prevent="toggleGroup(group)">
                        <td><i class="icon-base ti me-1" :class="expanded === group.id ? 'tabler-chevron-down' : 'tabler-chevron-right'" aria-hidden="true"></i>{{ group.phone_number }}</td>
                        <td>{{ fullName(group) }}</td><td class="text-break" style="white-space: pre-wrap">{{ latestPrimaryNote(group) }}</td><td v-if="!canCreate">{{ fullName(group.user) }}</td>
                        <td>{{ Math.max(0, (group.members?.length ?? 0) - 1) }}</td>
                        <td class="text-end"><button v-if="canCreate" class="btn btn-sm btn-outline-primary" type="button" @click.stop="startRelated(group)">{{ t('add_phone') }}</button></td>
                    </tr>
                    <tr v-if="expanded === group.id"><td :colspan="canCreate ? 5 : 6" class="bg-light">
                        <div v-for="member in group.members" :key="member.id" class="p-3" id="border-div">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <strong>{{ member.phone_number }}</strong><span>{{ fullName(member) }}</span>
                                <span v-if="member.relationship && !member.is_primary" class="badge bg-label-secondary">{{ member.relationship }}</span>
                                <span v-if="member.is_primary" class="badge bg-label-primary">{{ t('primary') }}</span>
                                <button v-if="canCreate" class="btn btn-sm btn-outline-secondary ms-auto" type="button" @click="startEdit(member)">{{ t('edit') }}</button>
                            </div>
                            <div class="small text-muted mb-2">{{ member.email || '—' }} · {{ member.birth_date || '—' }} · {{ member.gender ? t(member.gender) : '—' }}</div>
                            <form v-if="editing === member.id && canCreate" class="row g-2 mb-3" @submit.prevent="saveEdit(member)">
                                <div v-for="field in (member.is_primary ? personFields : fields)" :key="field" class="col-md-4"><label class="form-label">{{ t(field) }}</label>
                                    <select v-if="field === 'gender'" v-model="editForm[field]" class="form-select"><option value="">—</option><option value="male">{{ t('male') }}</option><option value="female">{{ t('female') }}</option></select>
                                    <input v-else v-model="editForm[field]" class="form-control" :type="field === 'birth_date' ? 'date' : field === 'email' ? 'email' : 'text'">
                                    <div class="text-danger small">{{ editForm.errors[field] }}</div>
                                </div><div class="col-12"><button class="btn btn-primary" type="submit" :disabled="editForm.processing">{{ t('save') }}</button></div>
                            </form>
                            <div v-for="note in member.notes" :key="note.id" class="small py-1"><span class="text-muted me-2">{{ formatDate(note.created_at) }}</span><span style="white-space: pre-wrap">{{ note.note }}</span></div>
                            <button v-if="canCreate && notingTo !== member.id" class="btn btn-sm btn-outline-primary mt-2" type="button" @click="notingTo = member.id; noteForm.reset(); noteForm.clearErrors()">{{ t('add_note') }}</button>
                            <form v-if="canCreate && notingTo === member.id" class="d-flex gap-2 mt-2" @submit.prevent="addNote(member)"><textarea v-model="noteForm.note" class="form-control" :placeholder="t('note')" rows="1" required></textarea><button class="btn btn-outline-primary" type="submit" :disabled="noteForm.processing">{{ t('save') }}</button></form>
                        </div>
                        <div v-if="addingTo === group.id && canCreate" class="d-flex gap-3 px-3 pt-3">
                            <label class="form-check-label"><input v-model="relatedMode" class="form-check-input me-1" type="radio" value="new">{{ t('new_phone') }}</label>
                            <!-- <label class="form-check-label"><input v-model="relatedMode" class="form-check-input me-1" type="radio" value="existing">{{ t('existing_phone') }}</label> -->
                        </div>
                        <form v-if="addingTo === group.id && canCreate && relatedMode === 'new'" class="row g-2 p-3" @submit.prevent="addRelated(group)">
                            <h6>{{ t('add_phone') }}</h6>
                            <div class="col-md-4"><label class="form-label">{{ t('phone_number') }} *</label><input v-model="relatedForm.phone_number" class="form-control" type="tel" required><div class="text-danger small">{{ relatedForm.errors.phone_number }}</div></div>
                            <div v-for="field in fields" :key="field" class="col-md-4"><label class="form-label">{{ t(field) }}</label>
                                <select v-if="field === 'gender'" v-model="relatedForm[field]" class="form-select"><option value="">—</option><option value="male">{{ t('male') }}</option><option value="female">{{ t('female') }}</option></select>
                                <input v-else v-model="relatedForm[field]" class="form-control" :type="field === 'birth_date' ? 'date' : field === 'email' ? 'email' : 'text'">
                                <div class="text-danger small">{{ relatedForm.errors[field] }}</div>
                            </div>
                            <div class="col-12"><label class="form-label">{{ t('note') }}</label><textarea v-model="relatedForm.note" class="form-control"></textarea><div class="text-danger small">{{ relatedForm.errors.note }}</div></div>
                            <div class="col-12"><button class="btn btn-primary" type="submit" :disabled="relatedForm.processing">{{ t('save') }}</button></div>
                        </form>
                        <form v-if="addingTo === group.id && canCreate && relatedMode === 'existing'" class="row g-2 p-3" @submit.prevent="linkExisting(group)">
                            <h6>{{ t('link_existing') }}</h6>
                            <p class="form-text mb-0">{{ t('existing_phone_help') }}</p>
                            <div class="col-md-6"><label class="form-label">{{ t('phone_number') }} *</label><input v-model="linkForm.phone_number" class="form-control" type="tel" required><div class="text-danger small">{{ linkForm.errors.phone_number }}</div></div>
                            <div class="col-md-6"><label class="form-label">{{ t('relationship') }}</label><input v-model="linkForm.relationship" class="form-control" type="text"><div class="text-danger small">{{ linkForm.errors.relationship }}</div></div>
                            <div class="col-12"><button class="btn btn-primary" type="submit" :disabled="linkForm.processing">{{ t('link_existing') }}</button></div>
                        </form>
                    </td></tr>
                </template>
                <tr v-if="!contactRows.length"><td :colspan="canCreate ? 5 : 6" class="text-center text-muted py-4">{{ t('empty') }}</td></tr>
            </tbody>
        </table></div><div v-if="contacts.links?.length > 3" class="card-body"><Pagination :links="contacts.links" /></div></div>
    </Index>
</template>

<style scoped>
.form-control { background-color: #f8f9fa; }
.form-select { background-color: #f8f9fa; }
#border-div { border-bottom: 2px solid #faf9f9; }
.contact-group-row { cursor: pointer; }
.contact-group-row:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
</style>
