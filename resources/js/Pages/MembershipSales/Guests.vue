<script setup>
import { translate } from '/resources/js/trans'
import { computed, ref, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const page = usePage()
const t = (key, replacements = {}) => translate(page.props.translations, `app.${key}`, replacements)
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipSale: Object,
    personMembership: Object,
    guests: {
        type: Array,
        default: () => [],
    },
    allowedGuestCount: {
        type: [Number, String],
        default: 0,
    },
    usedGuestCount: {
        type: [Number, String],
        default: 0,
    },
    remainingGuestCount: {
        type: [Number, String],
        default: 0,
    },
    entryCodes: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    entry_code_id: null,
    name: '',
    surname: '',
    email: '',
    phone: '',
    birth_date: '',
    gender: '',
})

const remaining = computed(() => Number(props.remainingGuestCount || 0))
const canAddGuest = computed(() => remaining.value > 0 && props.personMembership?.status === 'active')
const foundPerson = ref(null)
const lookupError = ref('')
const lookupInProgress = ref(false)
const lookupTimer = ref(null)
const entryCodeOptions = computed(() => {
    const codes = [...props.entryCodes]
    const personEntryCode = foundPerson.value?.entry_code

    if (personEntryCode && !codes.some(code => Number(code.id) === Number(personEntryCode.id))) {
        codes.unshift(personEntryCode)
    }

    return codes
})

const personName = person => `${person?.name ?? ''} ${person?.surname ?? ''}`.trim() || '-'
const translatedName = item => {
    return item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
        ?? item?.name
        ?? item?.slug
        ?? (item?.id ? `#${item.id}` : '-')
}
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const statusLabel = status => ({
    waiting: t('people.waiting'),
    active: t('membership.active'),
    frozen: t('people.frozen'),
    expired: t('people.expired'),
    deleted: t('people.deleted'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')

const fillFromPerson = person => {
    form.name = person.name ?? ''
    form.surname = person.surname ?? ''
    form.email = person.email ?? ''
    form.birth_date = person.birth_date ? String(person.birth_date).slice(0, 10) : ''
    form.gender = person.gender ?? ''
    form.entry_code_id = person.entry_code_id ?? null
}

const lookupPersonByPhone = async phone => {
    const normalizedPhone = String(phone || '').trim()

    foundPerson.value = null
    lookupError.value = ''
    form.clearErrors('phone')

    if (!normalizedPhone) {
        return
    }

    lookupInProgress.value = true

    try {
        const response = await axios.get(route('membership_sale.guests.lookup', {
            locale: currentLocale.value,
            id: props.membershipSale.id,
        }), {
            params: {
                phone: normalizedPhone,
            },
        })

        if (response.data?.error) {
            lookupError.value = response.data.error
            form.setError('phone', response.data.error)
            return
        }

        if (response.data?.person) {
            foundPerson.value = response.data.person
            fillFromPerson(response.data.person)
        }
    } finally {
        lookupInProgress.value = false
    }
}

watch(() => form.phone, phone => {
    window.clearTimeout(lookupTimer.value)
    lookupTimer.value = window.setTimeout(() => lookupPersonByPhone(phone), 500)
})

const submit = () => {
    if (lookupError.value) {
        form.setError('phone', lookupError.value)
        return
    }

    if (!form.entry_code_id) {
        form.setError('entry_code_id', t('people.entry_code_required'))
        return
    }

    form.post(route('membership_sale.guests.store', {
        locale: currentLocale.value,
        id: props.membershipSale.id,
    }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head :title="t('people.add_guest')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                {{ t('people.add_guest') }}
            </h2>
        </template>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('sales.membership_details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.client') }}</span>
                            <strong>{{ personName(personMembership.person) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('people.membership') }}</span>
                            <strong>{{ translatedName(personMembership.membership_plan) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('membership.start') }}</span>
                            <strong>{{ formatDate(personMembership.start_date) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('membership.end') }}</span>
                            <strong>{{ formatDate(personMembership.end_date) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ t('membership.status') }}</span>
                            <span class="badge bg-label-success">{{ statusLabel(personMembership.status) }}</span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.used_guests') }}</span>
                            <strong>{{ allowedGuestCount }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.allowed_guests') }}</span>
                            <strong>{{ usedGuestCount }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <span class="text-muted">{{ t('sales.remaining_guests') }}</span>
                            <strong class="text-primary">{{ remainingGuestCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('people.guests') }}</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="guests.length"
                            class="table-responsive text-nowrap"
                        >
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ t('people.full_name') }}</th>
                                        <th>{{ t('people.phone_number') }}</th>
                                        <th>{{ t('people.email_alt') }}</th>
                                        <th>{{ t('people.added_at') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="guestRecord in guests"
                                        :key="guestRecord.id"
                                    >
                                        <td>{{ personName(guestRecord.guest) }}</td>
                                        <td>{{ guestRecord.guest?.phone ?? '-' }}</td>
                                        <td>{{ guestRecord.guest?.email ?? '-' }}</td>
                                        <td>{{ formatDate(guestRecord.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="text-muted"
                        >
                            {{ t('sales.no_guests_yet') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="canAddGuest"
            class="card mb-4"
        >
            <div class="card-header">
                <h5 class="mb-0">{{ t('sales.new_guest') }}</h5>
            </div>
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div class="row g-4">
                    <div class="col-md-12">
                        <InputLabel
                            for="phone"
                            :value="t('people.phone_number')"
                        />
                        <TextInput
                            id="phone"
                            v-model="form.phone"
                            type="text"
                            class="form-control"
                            placeholder="+374"
                            autofocus
                        />
                        <div
                            v-if="lookupInProgress"
                            class="form-text"
                        >
                            {{ t('sales.search') }}
                        </div>
                        <div
                            v-if="foundPerson"
                            class="alert alert-info mt-2 mb-0"
                        >
                            {{ t('sales.an_existing_client_was_found_review_and_edit_the_filled_details') }}
                        </div>
                        <div
                            v-if="lookupError"
                            class="alert alert-danger mt-2 mb-0"
                        >
                            {{ lookupError }}
                        </div>
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="col-md-12">
                        <InputLabel
                            for="entry_code_id"
                            :value="t('people.entry_code')"
                        />
                        <select
                            v-if="entryCodeOptions.length"
                            id="entry_code_id"
                            v-model="form.entry_code_id"
                            class="form-select"
                            required
                        >
                            <option
                                :value="null"
                                disabled
                            >
                                {{ t('people.choose_entry_code') }}
                            </option>
                            <option
                                v-for="code in entryCodeOptions"
                                :key="code.id"
                                :value="code.id"
                            >
                                {{ code.token }} ({{ code.gym?.name || t('people.without_gym') }}) {{ code.type }}
                            </option>
                        </select>
                        <div
                            v-else
                            class="alert alert-warning mb-0"
                        >
                            {{ t('people.no_entry_codes') }}
                            <Link :href="route('entry-code.create', { locale: currentLocale })">
                                {{ t('people.create_short') }}
                            </Link>
                        </div>
                        <InputError :message="form.errors.entry_code_id" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="name"
                            :value="t('membership.name')"
                        />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="form-control"
                            :placeholder="t('people.enter_name')"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="surname"
                            :value="t('people.surname')"
                        />
                        <TextInput
                            id="surname"
                            v-model="form.surname"
                            type="text"
                            class="form-control"
                            :placeholder="t('people.enter_surname')"
                        />
                        <InputError :message="form.errors.surname" />
                    </div>


                    <div class="col-md-6">
                        <InputLabel
                            for="email"
                            :value="t('people.email_alt')"
                        />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="form-control"
                            placeholder="guest@example.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="birth_date"
                            :value="t('people.birth_date')"
                        />
                        <TextInput
                            id="birth_date"
                            v-model="form.birth_date"
                            type="date"
                            class="form-control"
                        />
                        <InputError :message="form.errors.birth_date" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="gender"
                            :value="t('people.gender')"
                        />
                        <select
                            id="gender"
                            v-model="form.gender"
                            class="form-select"
                        >
                            <option value="">{{ t('membership.select') }}</option>
                            <option value="male">{{ t('people.male') }}</option>
                            <option value="female">{{ t('people.female') }}</option>
                        </select>
                        <InputError :message="form.errors.gender" />
                    </div>
                </div>

                <div class="pt-4 d-flex justify-content-end gap-2">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('membership_sale.list', { locale: currentLocale })"
                    >
                        {{ t('people.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ t('people.add_guest') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <div
            v-else
            class="alert alert-warning"
        >
            {{ t('sales.a_guest_cannot_be_added_to_this_membership') }}
        </div>
    </Index>
</template>
