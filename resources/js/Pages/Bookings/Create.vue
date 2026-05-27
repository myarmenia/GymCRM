<script setup>
import { onMounted, computed, watch, ref, nextTick } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';

import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const availableRooms = ref([]);
const serverErrors = ref({});
const selectedServices = ref({});
const isInitializing = ref(true);

const props = defineProps({
    roomTypes: Array,
    additionalServices: Array,
    paymentMethods: Array,
    countries: Array,
});

const today = new Date();
today.setHours(0, 0, 0, 0);

const yesterday = new Date(today);
yesterday.setDate(today.getDate());

const minDate = yesterday.toISOString().split('T')[0];

const minDateTime = new Date(yesterday);
minDateTime.setHours(0, 0, 0, 0);
const minDateTimeStr = minDateTime.toISOString().slice(0, 16);

/**
 * FORM
 */
const form = useForm({
    room_types: [],
    room_ids: [],

    date_from: '',
    date_to: '',

    is_hourly: false,

    booking_type: 'daily',
    units_count: null,
    guests_count: '',
    additional_services: [],

    payment_method_id: null,
    card_type_id: null,

    prepayment: 0,
    discount_type: 'none',
    discount_value: 0,
    private_amount: 0,
    is_free: false,
    is_special: false,
    description: '',

    // Contact data - упрощенные поля вместо guest объекта
    contact_name: '',
    contact_phone: '',
});

// Добавляем вычисляемые числовые значения для полей, которые могут быть строками
const numericPrepayment = computed(() => Number(form.prepayment) || 0);
const numericDiscountValue = computed(() => Number(form.discount_value) || 0);
const numericPrivateAmount = computed(() => Number(form.private_amount) || 0);

/**
 * ROOM TYPES
 */
const roomTypeOptions = computed(() => {
    return props.roomTypes.map(r => ({
        id: r.id,
        text: r.translation.name,
    }));
});

const additionalServiceOptions = computed(() => {
    return props.additionalServices.map(s => ({
        id: s.id,
        text: s.translations?.[0]?.name ?? s.slug,
        price: Number(s.price),
        price_type: s.price_type,
        multiply_by_days: s.multiply_by_days ?? false,
    }));
});

const setService = (service, active) => {
    if (!selectedServices.value[service.id]) {
        selectedServices.value[service.id] = {
            active: false,
            qty: 1
        };
    }
    selectedServices.value[service.id].active = active;
};

const setQty = (service, qty) => {
    if (!selectedServices.value[service.id]) {
        selectedServices.value[service.id] = {
            active: true,
            qty: 1
        };
    }
    selectedServices.value[service.id].qty = Math.max(1, qty);
    selectedServices.value[service.id].active = true;
};

const serviceDays = computed(() => {
    if (!form.units_count) return 1;
    if (form.booking_type === 'hourly') {
        return Math.max(1, Math.ceil(form.units_count / 24));
    }
    return form.units_count;
});

// ЦЕНА УСЛУГ (всегда без скидки)
const servicesPrice = computed(() => {
    let total = 0;
    for (const id in selectedServices.value) {
        const service = props.additionalServices.find(s => s.id == id);
        const data = selectedServices.value[id];
        if (!service || !data || !data.active) continue;

        let qty = data.qty || 1;
        let price = service.price;

        if (service.multiply_by_days) {
            price = price * serviceDays.value;
        }

        total += price * qty;
    }
    return total;
});

// ЦЕНА КОМНАТ (БЕЗ скидки)
const roomsPrice = computed(() => {
    let total = 0;
    availableRooms.value.forEach(room => {
        if (form.room_ids.includes(room.id)) {
            const price = form.is_hourly
                ? (room.type?.price_per_hour || room.type?.price)
                : (room.type?.price || 0);
            total += price * (form.units_count || 1);
        }
    });
    return total;
});

const prepareServices = () => {
    const services = [];
    for (const id in selectedServices.value) {
        const data = selectedServices.value[id];
        if (!data || !data.active) continue;
        services.push({
            id: Number(id),
            quantity: data.qty || 1
        });
    }
    form.additional_services = services;
};

// СУБТОТАЛ = комнаты + услуги (БЕЗ скидки)
const subtotal = computed(() => {
    return roomsPrice.value + servicesPrice.value;
});

// СКИДКА ТОЛЬКО НА КОМНАТЫ (не на услуги!)
const roomsDiscountAmount = computed(() => {
    const value = numericDiscountValue.value;
    const roomsTotal = roomsPrice.value;

    if (form.discount_type === 'percent') {
        return roomsTotal * value / 100;
    }
    if (form.discount_type === 'fixed') {
        return Math.min(value, roomsTotal);
    }
    return 0;
});

// ФИНАЛЬНАЯ ЦЕНА = (комнаты со скидкой) + услуги (без скидки)
const finalTotal = computed(() => {
    const roomsWithDiscount = roomsPrice.value - roomsDiscountAmount.value;
    return roomsWithDiscount + servicesPrice.value;
});

const remaining = computed(() => {
    return finalTotal.value - numericPrepayment.value;
});

const officialAmount = computed(() => {
    return finalTotal.value - numericPrivateAmount.value;
});

const selectedPayment = computed(() => {
    return props.paymentMethods.find(p => p.id == form.payment_method_id);
});

// Нормализация полей при вводе
const normalizeNumber = (value) => {
    if (value === '' || value === null || value === undefined) return 0;
    const num = Number(value);
    return isNaN(num) ? 0 : num;
};

// Watchers для нормализации чисел
watch(() => form.prepayment, (val) => {
    if (isInitializing.value) return;
    const num = normalizeNumber(val);
    if (num !== form.prepayment) {
        form.prepayment = num;
    }
});

watch(() => form.discount_value, (val) => {
    if (isInitializing.value) return;
    const num = normalizeNumber(val);
    if (num !== form.discount_value) {
        form.discount_value = num;
    }
});

watch(() => form.private_amount, (val) => {
    if (isInitializing.value) return;
    const num = normalizeNumber(val);
    if (num !== form.private_amount) {
        form.private_amount = num;
    }
});

/**
 * SELECT2
 */
onMounted(() => {
    $('#room_types')
        .select2({ width: '100%' })
        .on('change', function () {
            form.room_types = $(this).val() || [];
        });

    nextTick(() => {
        $('#room_types').val(form.room_types).trigger('change.select2');
    });
});

watch(() => form.room_types, (val) => {
    $('#room_types').val(val).trigger('change.select2');
    isInitializing.value = false;
});

/**
 * DATE VALIDATION
 */
watch(() => form.date_from, (val) => {
    if (!val) return;
    if (new Date(val) < yesterday) {
        form.date_from = minDate;
    }
    if (form.date_to && form.date_to < val) {
        form.date_to = val;
    }
});

watch(() => form.date_to, (val) => {
    if (!val) return;
    if (new Date(val) < yesterday) {
        form.date_to = minDate;
    }
    if (form.date_from && val < form.date_from) {
        form.date_to = form.date_from;
    }
});

/**
 * AUTO CALC (DAYS / HOURS)
 */
watch(
    () => [form.date_from, form.date_to, form.is_hourly],
    ([from, to, hourly]) => {
        if (!from || !to) {
            form.units_count = null;
            form.booking_type = hourly ? 'hourly' : 'daily';
            return;
        }

        const diffMs = new Date(to) - new Date(from);

        if (diffMs <= 0) {
            form.units_count = 0;
            return;
        }

        const hours = diffMs / 1000 / 60 / 60;

        if (hourly) {
            form.booking_type = 'hourly';
            form.units_count = Math.max(1, Math.ceil(hours));
        } else {
            const days = Math.ceil(hours / 24);
            form.booking_type = 'daily';
            form.units_count = Math.max(1, days);
        }
    }
);

// Очищаем выбранные комнаты при изменении дат или типов комнат
watch(
    () => [form.date_from, form.date_to, form.room_types],
    (newValues, oldValues) => {
        if (isInitializing.value) return;
        if (!oldValues) return;

        const fromChanged = newValues[0] !== oldValues[0];
        const toChanged = newValues[1] !== oldValues[1];
        const roomTypesChanged = JSON.stringify(newValues[2]) !== JSON.stringify(oldValues[2]);

        if ((fromChanged || toChanged || roomTypesChanged) && form.room_ids.length > 0) {
            form.room_ids = [];
        }
    }
);

/**
 * LOAD ROOMS
 */
watch(
    () => [form.room_types, form.date_from, form.date_to],
    async ([types, from, to]) => {
        serverErrors.value = {};

        if (!types?.length || !from || !to) {
            availableRooms.value = [];
            return;
        }

        try {
            const { data } = await axios.post(
                route('booking.available-rooms', { locale: currentLocale }),
                {
                    room_type_ids: types.map(id => Number(id)),
                    date_from: from,
                    date_to: to,
                }
            );
            availableRooms.value = data;
        } catch (e) {
            if (e.response?.status === 422) {
                serverErrors.value = e.response.data.errors || {};
            } else {
                serverErrors.value = { general: ['Server error'] };
            }
        }
    },
    { immediate: true }
);

// Валидация полей
watch(() => numericPrivateAmount.value, (val) => {
    if (isInitializing.value) return;
    if (finalTotal.value === 0 && val > 0) return;

    if (val < 0) form.private_amount = 0;
    if (val > finalTotal.value && finalTotal.value > 0) {
        form.private_amount = finalTotal.value;
    }
});

watch(() => numericDiscountValue.value, (val) => {
    if (isInitializing.value) return;
    if (roomsPrice.value === 0 && val > 0) return;

    if (val < 0) form.discount_value = 0;
    if (form.discount_type === 'percent' && val > 100) {
        form.discount_value = 100;
    }
});

watch(() => numericPrepayment.value, (val) => {
    if (isInitializing.value) return;
    if (finalTotal.value === 0 && val > 0) return;

    if (val < 0) form.prepayment = 0;
    if (val > finalTotal.value && finalTotal.value > 0) {
        form.prepayment = finalTotal.value;
    }
});

// Завершаем инициализацию после загрузки комнат
watch(availableRooms, () => {
    if (isInitializing.value) {
        isInitializing.value = false;
    }
});

/**
 * GROUP ROOMS
 */
const groupedRooms = computed(() => {
    const groups = {};
    availableRooms.value.forEach(room => {
        const type = room.type?.translation?.name || 'Other';
        if (!groups[type]) groups[type] = [];
        groups[type].push(room);
    });
    return groups;
});

/**
 * TOGGLE ROOM
 */
const toggleRoom = (id) => {
    id = Number(id);
    const i = form.room_ids.indexOf(id);
    if (i === -1) form.room_ids.push(id);
    else form.room_ids.splice(i, 1);
};

/**
 * SUBMIT
 */
const submit = () => {
    // Убеждаемся, что все числовые поля - числа
    form.prepayment = numericPrepayment.value;
    form.discount_value = numericDiscountValue.value;
    form.private_amount = numericPrivateAmount.value;

    prepareServices();
    form.post(route('booking.store', { locale: currentLocale }));
};
</script>

<template>
    <Head title="Add Booking" />
    <Index>
        <div class="card mb-6">
            <div class="card-header">Add Booking</div>
            <div class="card-body">

                <!-- MODE -->
                <div class="mb-3">
                    <label>
                        <input type="checkbox" v-model="form.is_hourly">
                        Hourly mode
                    </label>
                </div>

                <!-- ERRORS -->
                <div v-if="Object.keys(serverErrors).length" class="alert alert-danger mb-4">
                    <div v-if="serverErrors.general">
                        <div v-for="msg in serverErrors.general" :key="msg">{{ msg }}</div>
                    </div>
                    <div v-for="(err, key) in serverErrors" :key="key" v-if="key !== 'general'">
                        <div class="fw-bold">{{ key }}</div>
                        <div v-for="msg in err" :key="msg">- {{ msg }}</div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="card-body">

                    <!-- DATES -->
                    <div class="row mb-4">
                        <div class="col">
                            <InputLabel value="From" />
                            <TextInput
                                v-if="!form.is_hourly"
                                type="date"
                                v-model="form.date_from"
                                :min="minDate"
                                class="form-control"
                            />
                            <TextInput
                                v-else
                                type="datetime-local"
                                v-model="form.date_from"
                                :min="minDateTimeStr"
                                class="form-control"
                            />
                            <InputError class="mt-2" :message="form.errors.date_from" />
                        </div>

                        <div class="col">
                            <InputLabel value="To" />
                            <TextInput
                                v-if="!form.is_hourly"
                                type="date"
                                v-model="form.date_to"
                                :min="form.date_from || minDate"
                                class="form-control"
                            />
                            <TextInput
                                v-else
                                type="datetime-local"
                                v-model="form.date_to"
                                :min="form.date_from || minDateTimeStr"
                                class="form-control"
                            />
                            <InputError class="mt-2" :message="form.errors.date_to" />
                        </div>

                        <div class="col">
                            <InputLabel :value="form.is_hourly ? 'Total Hours' : 'Total Days'" />
                            <TextInput type="number" v-model="form.units_count" disabled class="form-control"/>
                            <InputError class="mt-2" :message="form.errors.units_count" />
                        </div>
                    </div>

                    <div class="row my-3">
                        <div class="col">
                            <InputLabel value="Guests count" />
                            <TextInput
                                type="number"
                                v-model="form.guests_count"
                                min="1"
                                class="form-control"
                            />
                            <InputError class="mt-2" :message="form.errors.guests_count" />
                        </div>

                        <div class="col">
                            <InputLabel value="Room Types" />
                            <select id="room_types" class="form-select" multiple>
                                <option v-for="r in roomTypeOptions" :key="r.id" :value="r.id">
                                    {{ r.text }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.room_ids" />
                        </div>
                    </div>

                    <!-- ROOMS -->
                    <div class="mt-5" v-if="Object.keys(groupedRooms).length">
                        <div v-for="(rooms, type) in groupedRooms" :key="type" class="room-group p-4 mb-4">
                            <div class="mb-3">
                                <span class="type-badge">{{ type }}</span>
                            </div>
                            <div class="row rooms g-3">
                                <div v-for="room in rooms" :key="room.id" class="col-6 col-sm-4 col-md-3 col-lg-2 custom-col">
                                    <div
                                        class="card room-card"
                                        :class="{ selected: form.room_ids.includes(room.id) }"
                                        @click="toggleRoom(room.id)"
                                    >
                                        <div class="fw-bold">{{ room.number }}</div>
                                        <i class="ti tabler-door fs-2 mb-2 text-primary"></i>
                                        <small class="text-muted">Floor: {{ room.floor }}</small>
                                        <small class="text-muted">Max guests: {{ room.max_guests }}</small>
                                        <small class="text-muted">
                                            Price: {{ form.is_hourly
                                                ? (room.type?.price_per_hour || room.type?.price)
                                                : (room.type?.price || 0)
                                            }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row px-3 ">
                        <!-- SERVICES -->
                        <div class="mt-5 p-4 border rounded col">
                            <h5>Additional Services (No discount applied)</h5>
                            <div v-for="service in additionalServiceOptions" :key="service.id">
                                <InputLabel :value="service.text" />
                                <div class="text-muted d-flex justify-content-between align-items-center mb-3 border p-2 rounded">
                                    <div>
                                        <small>{{ service.price }} / {{ service.price_type }}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <input
                                            type="checkbox"
                                            :checked="selectedServices[service.id]?.active"
                                            @change="setService(service, $event.target.checked)"
                                        />
                                        <div v-if="selectedServices[service.id]?.active" class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="setQty(service, (selectedServices[service.id]?.qty || 1) - 1)">-</button>
                                            <input
                                                type="number"
                                                min="1"
                                                style="width:60px"
                                                class="form-control form-control-sm text-center"
                                                :value="selectedServices[service.id]?.qty || 1"
                                                @input="setQty(service, +$event.target.value)"
                                            />
                                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="setQty(service, (selectedServices[service.id]?.qty || 1) + 1)">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                <i class="ti tabler-info-circle"></i> Services are NOT discounted
                            </div>
                        </div>

                        <!-- PAYMENT -->
                        <div class="mt-5 p-4 border rounded col">
                            <h5>Payment</h5>

                            <div class="mb-3">
                                <InputLabel value="Payment method" />
                                <select v-model="form.payment_method_id" class="form-select">
                                    <option :value="null">Select</option>
                                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">
                                        {{ m.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.payment_method_id" />
                            </div>

                            <div v-if="selectedPayment?.slug === 'card'" class="mb-3">
                                <InputLabel value="Card type" />
                                <select v-model="form.card_type_id" class="form-select">
                                    <option :value="null">Select</option>
                                    <option v-for="c in selectedPayment.card_types" :key="c.id" :value="c.id">
                                        {{ c.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.card_type_id" />
                            </div>

                            <div class="mb-3">
                                <InputLabel value="Prepayment" />
                                <TextInput type="number" v-model="form.prepayment" class="form-control"/>
                                <InputError class="mt-2" :message="form.errors.prepayment" />
                            </div>

                            <!-- DISCOUNT - только на комнаты -->
                            <div class="mb-3">
                                <label class="form-label">Discount (applies to ROOMS only)</label>
                                <div class="d-flex gap-3 mb-2">
                                    <label><input type="radio" value="none" v-model="form.discount_type"> None</label>
                                    <label><input type="radio" value="percent" v-model="form.discount_type"> %</label>
                                    <label><input type="radio" value="fixed" v-model="form.discount_type"> Fixed</label>
                                </div>
                                <div v-if="form.discount_type !== 'none'">
                                    <input
                                        type="number"
                                        v-model="form.discount_value"
                                        class="form-control"
                                        :placeholder="form.discount_type === 'percent' ? 'Percent %' : 'Amount'"
                                    />
                                </div>
                                <div class="small text-muted mt-1">
                                    <i class="ti tabler-info-circle"></i> Discount applies only to rooms, not to services
                                </div>
                            </div>

                            <div class="d-flex gap-3 mb-3">
                                <label>
                                    <input type="checkbox" v-model="form.is_free">
                                    Free
                                </label>
                                <label>
                                    <input type="checkbox" v-model="form.is_special">
                                    Special guest
                                </label>
                            </div>

                            <div class="mb-3">
                                <InputLabel value="Description" />
                                <textarea v-model="form.description" class="form-control"></textarea>
                            </div>

                            <div class="mb-3">
                                <InputLabel value="*" />
                                <TextInput type="number" v-model="form.private_amount" class="form-control"/>
                                <InputError class="mt-2" :message="form.errors.private_amount" />
                            </div>
                        </div>
                    </div>

                    <!-- GUEST FORM и PRICE BREAKDOWN в одной строке -->
                    <div class="row mt-4">
                        <!-- Guest Contact Info (левая колонка) -->
                        <div class="col">
                            <div class="card p-4">
                                <h5 class="mb-3">Guest Information</h5>
                                <div class="mb-3">
                                    <InputLabel value="Guest Name" />
                                    <TextInput
                                        type="text"
                                        v-model="form.contact_name"
                                        class="form-control"
                                        placeholder="Enter guest name"
                                    />
                                    <InputError class="mt-2" :message="form.errors.contact_name" />
                                </div>
                                <div class="mb-3">
                                    <InputLabel value="Guest Phone" />
                                    <TextInput
                                        type="text"
                                        v-model="form.contact_phone"
                                        class="form-control"
                                        placeholder="Enter phone number"
                                    />
                                    <InputError class="mt-2" :message="form.errors.contact_phone" />
                                </div>
                            </div>
                        </div>

                        <!-- PRICE BREAKDOWN (правая колонка) -->
                        <div class="card p-4 col">
                            <h5 class="mb-3">
                                <i class="ti tabler-calculator"></i> Price breakdown
                            </h5>

                            <div class="space-y-2">
                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-bed"></i> Rooms </span>
                                    <span>{{ roomsPrice.toFixed(2) }} ֏</span>
                                </div>

                                <div class="d-flex justify-content-between text-danger">
                                    <span><i class="ti tabler-tag"></i> Discount </span>
                                    <span>- {{ roomsDiscountAmount.toFixed(2) }} ֏</span>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-bed"></i> Rooms</span>
                                    <span>{{ (roomsPrice - roomsDiscountAmount).toFixed(2) }} ֏</span>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-settings"></i> Services </span>
                                    <span>{{ servicesPrice.toFixed(2) }} ֏</span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between fw-bold mb-2">
                                <span><i class="ti tabler-chart-bar"></i> Total after discount</span>
                                <span class="text-primary">{{ finalTotal.toFixed(2) }} ֏</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="ti tabler-wallet"></i> Prepayment</span>
                                <span class="text-success">- {{ numericPrepayment.toFixed(2) }} ֏</span>
                            </div>

                            <div class="alert alert-success mt-3 mb-2 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">
                                        <i class="ti tabler-coin"></i> Remaining to pay
                                    </span>
                                    <span class="fw-bold fs-4">{{ remaining.toFixed(2) }} ֏</span>
                                </div>
                            </div>

                            <div class="small text-muted mt-3">
                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-building"></i> Official amount</span>
                                    <span>{{ officialAmount.toFixed(2) }} ֏</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-lock"></i>*</span>
                                    <span>{{ numericPrivateAmount.toFixed(2) }} ֏</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <div class="text-end mt-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Submitting...' : 'Submit' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.room-group {
    background: #f6f8fa;
    border: 1px solid #e6e9ef;
    border-radius: 14px;
}

.type-badge {
    background: #e9f6f6;
    color: #0d9394;
    padding: 6px 12px;
    border-radius: 10px;
    font-weight: 600;
}

.row {
    display: flex;
    gap: 20px;
}

.rooms {
    display: flex;
    flex-wrap: wrap;
}

.room-card {
    cursor: pointer;
    border-radius: 12px;
    transition: 0.2s;
    border: 1px solid #e6e9ef;
    aspect-ratio: 1 / 1;
    padding: 6px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.room-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
}

.room-card.selected {
    border: 2px solid #0d9394 !important;
    background: rgba(13, 147, 148, 0.08);
}

.cursor-pointer {
    cursor: pointer;
}

@media (min-width: 1200px) {
    .custom-col {
        width: 15%;
    }
}
</style>
