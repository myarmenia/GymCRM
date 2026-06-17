<script setup>
import { computed, ref, watch } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const page = usePage()
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
    waiting: 'Սպասման մեջ',
    active: 'Ակտիվ',
    frozen: 'Սառեցված',
    expired: 'Ժամկետանց',
    deleted: 'Ջնջված',
    cancelled: 'Չեղարկված',
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
        form.setError('entry_code_id', 'Մուտքի կոդը պարտադիր է։')
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
    <Head title="Ավելացնել հյուր" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                Ավելացնել հյուր
            </h2>
        </template>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Աբոնեմենտի տվյալներ</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Հաճախորդ</span>
                            <strong>{{ personName(personMembership.person) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Աբոնեմենտ</span>
                            <strong>{{ translatedName(personMembership.membership_plan) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Սկիզբ</span>
                            <strong>{{ formatDate(personMembership.start_date) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Ավարտ</span>
                            <strong>{{ formatDate(personMembership.end_date) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Կարգավիճակ</span>
                            <span class="badge bg-label-success">{{ statusLabel(personMembership.status) }}</span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Թույլատրված հյուրեր</span>
                            <strong>{{ allowedGuestCount }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Օգտագործված հյուրեր</span>
                            <strong>{{ usedGuestCount }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <span class="text-muted">Մնացած հյուրեր</span>
                            <strong class="text-primary">{{ remainingGuestCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Հյուրեր</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="guests.length"
                            class="table-responsive text-nowrap"
                        >
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Անուն Ազգանուն</th>
                                        <th>Հեռախոսահամար</th>
                                        <th>Էլ․ փոստ</th>
                                        <th>Ավելացվել է</th>
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
                            Հյուրեր դեռ չկան
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
                <h5 class="mb-0">Նոր հյուր</h5>
            </div>
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div class="row g-4">
                    <div class="col-md-12">
                        <InputLabel
                            for="phone"
                            value="Հեռախոսահամար"
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
                            Որոնում...
                        </div>
                        <div
                            v-if="foundPerson"
                            class="alert alert-info mt-2 mb-0"
                        >
                            Գտնվել է գոյություն ունեցող անձ։ Տվյալները լրացվել են, կարող եք ստուգել և փոփոխել։
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
                            value="Մուտքի կոդ"
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
                                Ընտրել մուտքի կոդը
                            </option>
                            <option
                                v-for="code in entryCodeOptions"
                                :key="code.id"
                                :value="code.id"
                            >
                                {{ code.token }} ({{ code.gym?.name || 'Առանց մարզադահլիճի' }}) {{ code.type }}
                            </option>
                        </select>
                        <div
                            v-else
                            class="alert alert-warning mb-0"
                        >
                            Մուտքի կոդեր չկան։ Խնդրում ենք նախ ստեղծել մուտքի կոդ։
                            <Link :href="route('entry-code.create', { locale: currentLocale })">
                                Ստեղծիր
                            </Link>
                        </div>
                        <InputError :message="form.errors.entry_code_id" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="name"
                            value="Անուն"
                        />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="form-control"
                            placeholder="Մուտքագրել անունը"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="surname"
                            value="Ազգանուն"
                        />
                        <TextInput
                            id="surname"
                            v-model="form.surname"
                            type="text"
                            class="form-control"
                            placeholder="Մուտքագրել ազգանունը"
                        />
                        <InputError :message="form.errors.surname" />
                    </div>


                    <div class="col-md-6">
                        <InputLabel
                            for="email"
                            value="Էլ․ փոստ"
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
                            value="Ծննդյան ամսաթիվ"
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
                            value="Սեռ"
                        />
                        <select
                            id="gender"
                            v-model="form.gender"
                            class="form-select"
                        >
                            <option value="">Ընտրել</option>
                            <option value="male">Արական</option>
                            <option value="female">Իգական</option>
                        </select>
                        <InputError :message="form.errors.gender" />
                    </div>
                </div>

                <div class="pt-4 d-flex justify-content-end gap-2">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('membership_sale.list', { locale: currentLocale })"
                    >
                        Չեղարկել
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        Ավելացնել հյուր
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <div
            v-else
            class="alert alert-warning"
        >
            Այս աբոնեմենտի համար հյուր ավելացնել հնարավոր չէ։
        </div>
    </Index>
</template>
