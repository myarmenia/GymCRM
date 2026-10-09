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
const filter = ref({
    sales_manager_id: props.filters.sales_manager_id ?? '',
    phone_number: props.filters.phone_number ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
})
const createOpen = ref(false)
const expanded = ref(null)
const addingTo = ref(null)
const createForm = useForm({ phone_number: '', note: '' })
const noteForm = useForm({ note: '' })

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
    onSuccess: () => {
        createForm.reset()
        createOpen.value = false
    },
})

const startNote = row => {
    addingTo.value = row.id
    expanded.value = row.id
    noteForm.reset()
    noteForm.clearErrors()
}

const addNote = row => noteForm.post(route('contact-notes.add-note', {
    locale: locale.value,
    contactNote: row.id,
}), {
    preserveScroll: true,
    onSuccess: () => {
        noteForm.reset()
        addingTo.value = null
        expanded.value = row.id
    },
})

const formatDate = value => value ? new Date(value).toLocaleString(locale.value) : '—'
const managerName = user => [user?.name, user?.surname].filter(Boolean).join(' ') || '—'
</script>

<template>
    <Head :title="t('title')" />
    <Index>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h2 class="mb-0">{{ t('title') }}</h2>
            <button v-if="canCreate" type="button" class="btn btn-primary" @click="createOpen = !createOpen">
                <i class="icon-base ti tabler-plus me-1"></i>{{ t('new_contact') }}
            </button>
        </div>

        <div v-if="createOpen && canCreate" class="card mb-4">
            <div class="card-body">
                <h5>{{ t('new_contact') }}</h5>
                <form @submit.prevent="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="contact-phone">{{ t('phone_number') }}</label>
                            <input id="contact-phone" v-model="createForm.phone_number" type="tel" class="form-control" maxlength="32" required>
                            <div v-if="createForm.errors.phone_number" class="text-danger small">{{ createForm.errors.phone_number }}</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="contact-note">{{ t('note') }}</label>
                            <textarea id="contact-note" v-model="createForm.note" class="form-control" rows="2" required></textarea>
                            <div v-if="createForm.errors.note" class="text-danger small">{{ createForm.errors.note }}</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3" :disabled="createForm.processing">{{ t('save') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <form class="card-body" @submit.prevent="applyFilters">
                <div class="row g-3 align-items-end">
                    <div v-if="!canCreate" class="col-md-3">
                        <label class="form-label" for="contact-manager">{{ t('sales_manager') }}</label>
                        <select id="contact-manager" v-model="filter.sales_manager_id" class="form-select">
                            <option value="">{{ t('all_managers') }}</option>
                            <option v-for="manager in salesManagers" :key="manager.id" :value="manager.id">{{ managerName(manager) }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filter-phone">{{ t('phone_number') }}</label>
                        <input id="filter-phone" v-model="filter.phone_number" type="search" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filter-from">{{ t('date_from') }}</label>
                        <input id="filter-from" v-model="filter.date_from" type="date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filter-to">{{ t('date_to') }}</label>
                        <input id="filter-to" v-model="filter.date_to" type="date" class="form-control" :min="filter.date_from || undefined">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ t('filter') }}</button>
                        <button type="button" class="btn btn-outline-secondary" @click="resetFilters">{{ t('reset') }}</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ t('phone_number') }}</th>
                            <th>{{ t('note') }}</th>
                            <th v-if="!canCreate">{{ t('sales_manager') }}</th>
                            <th>{{ t('created_at') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in contacts.data" :key="row.id">
                            <tr>
                                <td>
                                    <button type="button" class="btn btn-link p-0 text-start" @click="expanded = expanded === row.id ? null : row.id">
                                        <i :class="`icon-base ti tabler-chevron-${expanded === row.id ? 'down' : 'right'} me-1`"></i>
                                        {{ row.phone_number }}
                                    </button>
                                    <span v-if="row.children.length" class="badge bg-label-secondary ms-1">{{ row.children.length }}</span>
                                </td>
                                <td class="text-break" style="white-space: pre-wrap">{{ row.note }}</td>
                                <td v-if="!canCreate">{{ managerName(row.user) }}</td>
                                <td class="text-nowrap">{{ formatDate(row.created_at) }}</td>
                                <td class="text-end">
                                    <button v-if="canCreate" type="button" class="btn btn-sm btn-outline-primary" :aria-label="t('add_note')" @click="startNote(row)">+</button>
                                </td>
                            </tr>
                            <tr v-if="expanded === row.id">
                                <td :colspan="canCreate ? 4 : 5" class="bg-light ps-4">
                                    <div v-if="row.children.length" class="mb-2">
                                        <div v-for="child in row.children" :key="child.id" class="d-flex gap-3 border-bottom py-2">
                                            <span class="text-muted text-nowrap">{{ formatDate(child.created_at) }}</span>
                                            <span class="text-break" style="white-space: pre-wrap">{{ child.note }}</span>
                                            <button v-if="canCreate" type="button" class="btn btn-sm btn-outline-primary ms-auto" :aria-label="t('add_note')" @click="startNote(row)">+</button>
                                        </div>
                                    </div>
                                    <div v-else class="text-muted mb-2">{{ t('no_more_notes') }}</div>
                                    <form v-if="canCreate && addingTo === row.id" class="d-flex gap-2 align-items-start" @submit.prevent="addNote(row)">
                                        <div class="flex-grow-1">
                                            <textarea v-model="noteForm.note" class="form-control" :placeholder="t('note')" rows="2" required></textarea>
                                            <div v-if="noteForm.errors.note" class="text-danger small">{{ noteForm.errors.note }}</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary" :disabled="noteForm.processing">{{ t('save') }}</button>
                                    </form>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!contacts.data.length"><td :colspan="canCreate ? 4 : 5" class="text-center text-muted py-4">{{ t('empty') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="contacts.links?.length > 3" class="card-body"><Pagination :links="contacts.links" /></div>
        </div>
    </Index>
</template>
