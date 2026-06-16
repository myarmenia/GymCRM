<script setup>
import { watch, ref, computed } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const page = usePage()
const currentLocale = page.props.locale ?? 'hy'

const props = defineProps({
    modelValue: Object,
    countries: Array,
    errors: Object,
    isEdit: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue'])

const defaultGuest = {
    id: null,
    phone: '',
    name: '',
    surname: '',
    email: '',
    passport_number: '',
    passport_expire_at: '',
    nationality: '',
    birth_date: '',
    city: '',
    country_id: null,
    gender: 'male',
    active: 1
}

const guest = ref({ ...defaultGuest, ...props.modelValue })
const guestFound = ref(null)
const guestLoading = ref(false)
const guestErrors = ref({})
const showGuestForm = ref(false)
let isInternalUpdate = false

// Вычисляем нужно ли показывать предупреждение о необходимости нажать Find
const showFindWarning = computed(() => {
    return !showGuestForm.value &&
           guest.value.passport_number &&
           guest.value.passport_number.trim() !== '' &&
           guestFound.value === null &&
           !guestLoading.value
})

// Вычисляем нужно ли показывать предупреждение о заполнении полей
const showRequiredFieldsWarning = computed(() => {
    if (!showGuestForm.value) return false

    // В режиме Edit не показываем это предупреждение, так как поля уже заполнены
    if (props.isEdit) return false

    // Если форма показана, но пользователь не нажал Find или гость не найден
    return guestFound.value !== true
})

/**
 * SYNC FROM PARENT
 */
watch(
    () => props.modelValue,
    (val) => {
        if (!isInternalUpdate && val) {
            guest.value = { ...defaultGuest, ...val }

            if (val.id || val.name || val.surname || val.passport_number) {
                showGuestForm.value = true
                guestFound.value = true
            } else {
                showGuestForm.value = false
                guestFound.value = null
            }
        }
    },
    { deep: true, immediate: true }
)

/**
 * SYNC TO PARENT
 */
watch(
    guest,
    () => {
        if (!isInternalUpdate) {
            isInternalUpdate = true
            emit('update:modelValue', { ...guest.value })
            setTimeout(() => {
                isInternalUpdate = false
            }, 100)
        }
    },
    { deep: true }
)

const findGuest = async () => {
    guestErrors.value = {}
    guestFound.value = null

    if (!guest.value.passport_number) return

    guestLoading.value = true
    showGuestForm.value = true

    try {
        const response = await axios.get(
            route('guests.find', { locale: currentLocale }),
            {
                params: { passport_number: guest.value.passport_number }
            }
        )

        const guestData = response.data.data

        if (guestData) {
            guestFound.value = true
            isInternalUpdate = true
            guest.value = {
                ...defaultGuest,
                ...guest.value,
                ...guestData,
                passport_number: guest.value.passport_number
            }
            isInternalUpdate = false
        } else {
            guestFound.value = false
        }

    } catch (error) {
        console.error('Error finding guest:', error)
        guestFound.value = false
    } finally {
        guestLoading.value = false
    }
}

const onPassportInput = (event) => {
    const newValue = event.target.value

    isInternalUpdate = true
    guest.value = {
        ...defaultGuest,
        passport_number: newValue
    }
    isInternalUpdate = false

    showGuestForm.value = false
    guestFound.value = null
}
</script>

<template>
    <div class="card p-4 col">

        <!-- PASSPORT + FIND -->
        <div class="d-flex gap-2 mb-2">
            <div class="w-75">
                <input
                    :value="guest.passport_number"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': errors?.['guest.passport_number'] || guestErrors.passport_number?.[0] }"
                    placeholder="Passport number"
                    @input="onPassportInput"
                />
                <InputError :message="errors?.['guest.passport_number']" />
                <InputError :message="guestErrors.passport_number?.[0]" />
            </div>

            <div class="w-25 text-end">
                <button
                    type="button"
                    class="w-100 btn btn-primary"
                    @click="findGuest"
                    :disabled="!guest.passport_number"
                >
                    Find
                </button>
            </div>
        </div>

        <!-- STATUS -->
        <div v-if="guestLoading" class="text-info">Searching...</div>
        <div v-else-if="guestFound === true" class="text-success">
            ✓ Guest found
        </div>
        <div v-else-if="guestFound === false" class="text-warning">
            ⚠ Guest not found - fill in the form
        </div>

        <!-- WARNING: нужно нажать Find -->
        <div v-if="showFindWarning" class="text-danger small mt-1">
            ⚠ Please click "Find" to search for guest with this passport number
        </div>

        <!-- WARNING: нужно заполнить форму -->
        <div v-if="showRequiredFieldsWarning" class="text-danger small mt-1">
            ⚠ Please fill in all required fields below
        </div>

        <!-- FORM -->
        <div v-if="showGuestForm" class="mt-3">
            <div class="row mt-2">
                <div class="col">
                    <input
                        v-model="guest.name"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.name'] }"
                        placeholder="Name *"
                    />
                    <InputError :message="errors?.['guest.name']" />
                </div>
                <div class="col">
                    <input
                        v-model="guest.surname"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.surname'] }"
                        placeholder="Surname *"
                    />
                    <InputError :message="errors?.['guest.surname']" />
                </div>
            </div>

            <div class="row mt-2">
                <div class="col">
                    <input
                        v-model="guest.email"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.email'] }"
                        placeholder="Email"
                        type="email"
                    />
                    <InputError :message="errors?.['guest.email']" />
                </div>
                <div class="col">
                    <input
                        v-model="guest.city"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.city'] }"
                        placeholder="City"
                    />
                    <InputError :message="errors?.['guest.city']" />
                </div>
            </div>

            <div class="row mt-2">
                <div class="col">
                    <input
                        v-model="guest.phone"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.phone'] }"
                        placeholder="Phone number *"
                    />
                    <InputError :message="errors?.['guest.phone']" />
                </div>
                <div class="col">
                    <input
                        v-model="guest.nationality"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.nationality'] }"
                        placeholder="Nationality"
                    />
                    <InputError :message="errors?.['guest.nationality']" />
                </div>
            </div>

            <div class="row mt-2">
                <div class="col">
                    <label class="form-label small">Passport expire date</label>
                    <input
                        v-model="guest.passport_expire_at"
                        type="date"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.passport_expire_at'] }"
                    />
                    <InputError :message="errors?.['guest.passport_expire_at']" />
                </div>
                <div class="col">
                    <label class="form-label small">Birth date *</label>
                    <input
                        v-model="guest.birth_date"
                        type="date"
                        class="form-control"
                        :class="{ 'is-invalid': errors?.['guest.birth_date'] }"
                    />
                    <InputError :message="errors?.['guest.birth_date']" />
                </div>
            </div>

            <div class="row mt-2">
                <div class="col">
                    <select
                        v-model="guest.country_id"
                        class="form-select"
                        :class="{ 'is-invalid': errors?.['guest.country_id'] }"
                    >
                        <option :value="null">Select country</option>
                        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="errors?.['guest.country_id']" />
                </div>
                <div class="col">
                    <select
                        v-model="guest.gender"
                        class="form-select"
                        :class="{ 'is-invalid': errors?.['guest.gender'] }"
                    >
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                    <InputError :message="errors?.['guest.gender']" />
                </div>
                <div class="col d-flex align-items-center">
                    <label class="d-flex align-items-center gap-2">
                        <input type="checkbox" v-model="guest.active" :true-value="1" :false-value="0" />
                        Active guest
                    </label>
                </div>
            </div>
        </div>

        <div v-if="!showGuestForm && guest.passport_number && guestFound === null && !guestLoading" class="text-muted small mt-2">
            🔄 Press "Find" to search for guest with this passport number
        </div>
    </div>
</template>
