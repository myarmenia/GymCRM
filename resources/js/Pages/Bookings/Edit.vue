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
const allPayments = ref([]); // Все платежи (и prepayment, и payment)

const props = defineProps({
    booking: Object,
    roomTypes: Array,
    additionalServices: Array,
    paymentMethods: Array,
    countries: Array,
});

// Date helpers
const today = new Date();
today.setHours(0, 0, 0, 0);
const yesterday = new Date(today);
yesterday.setDate(today.getDate() - 1);
const minDate = yesterday.toISOString().split('T')[0];
const minDateTimeStr = new Date(yesterday.setHours(0, 0, 0, 0)).toISOString().slice(0, 16);

// Определяем исходный тип бронирования
const originalBookingType = ref(props.booking.booking_type);
const isInitializing = ref(true);

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

    // Контактные данные гостя
    contact_name: '',
    contact_phone: '',
});

// Блокируем смену типа бронирования
const isHourlyDisabled = computed(() => {
    return originalBookingType.value !== null;
});

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

const selectedPayment = computed(() => {
    return props.paymentMethods.find(p => p.id == form.payment_method_id);
});

// Price calculations
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
                ? room.type?.price_per_hour || room.type?.price
                : room.type?.price || 0;
            total += price * (form.units_count || 1);
        }
    });
    return total;
});

// СКИДКА ТОЛЬКО НА КОМНАТЫ (не на услуги!)
const roomsDiscountAmount = computed(() => {
    const value = Number(form.discount_value || 0);
    const roomsTotal = roomsPrice.value;

    if (form.discount_type === 'percent') {
        return roomsTotal * value / 100;
    }
    if (form.discount_type === 'fixed') {
        return Math.min(value, roomsTotal);
    }
    return 0;
});

// ФИНАЛЬНАЯ ЦЕНА = комнаты со скидкой + услуги (без скидки)
const finalTotal = computed(() => {
    const roomsWithDiscount = roomsPrice.value - roomsDiscountAmount.value;
    return roomsWithDiscount + servicesPrice.value;
});

const totalPrepayment = computed(() => {
    // Суммируем все платежи с типом 'prepayment'
    return allPayments.value
        .filter(p => p.type === 'prepayment')
        .reduce((sum, p) => sum + Number(p.amount || 0), 0);
});

const totalPayments = computed(() => {
    // Суммируем все платежи с типом 'payment'
    return allPayments.value
        .filter(p => p.type === 'payment')
        .reduce((sum, p) => sum + Number(p.amount || 0), 0);
});

const remaining = computed(() => {
    return finalTotal.value - totalPrepayment.value - totalPayments.value;
});

const officialAmount = computed(() => {
    return finalTotal.value - (form.private_amount || 0);
});

// Service handlers
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

// Helpers
const formatDate = (val) => {
    if (!val) return '';
    return val.split('T')[0];
};

const formatDateTime = (val) => {
    if (!val) return '';
    return val.replace(' ', 'T').slice(0, 16);
};

const formatDateDisplay = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const getPaymentMethodName = (methodId) => {
    const method = props.paymentMethods.find(p => p.id == methodId);
    return method ? method.name : 'Unknown';
};

const toNumberArray = (arr) =>
    (arr || []).map(i => Number(i)).filter(i => !isNaN(i));

// Auto calculate days/hours
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

// Validation watches
watch(() => form.private_amount, (val) => {
    if (isInitializing.value) return;
    if (finalTotal.value === 0 && val > 0) return;

    if (val < 0) form.private_amount = 0;
    if (val > finalTotal.value && finalTotal.value > 0) {
        form.private_amount = finalTotal.value;
    }
});

watch(() => form.discount_value, (val) => {
    if (isInitializing.value) return;
    if (roomsPrice.value === 0 && val > 0) return;

    if (val < 0) form.discount_value = 0;
    if (form.discount_type === 'percent' && val > 100) {
        form.discount_value = 100;
    }
});

watch(() => form.prepayment, (val) => {
    if (isInitializing.value) return;
    if (finalTotal.value === 0 && val > 0) return;

    if (val < 0) form.prepayment = 0;
    if (val > finalTotal.value && finalTotal.value > 0) {
        form.prepayment = finalTotal.value;
    }
});

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

// Load rooms
watch(
    () => [form.room_types, form.date_from, form.date_to],
    async ([types, from, to]) => {
        if (!types?.length || !from || !to) {
            availableRooms.value = [];
            return;
        }

        try {
            const { data } = await axios.post(
                route('booking.available-rooms', { locale: currentLocale }),
                {
                    room_type_ids: toNumberArray(types),
                    date_from: from,
                    date_to: to,
                    booking_id: props.booking.id
                }
            );
            availableRooms.value = data;
        } catch (e) {
            serverErrors.value = e.response?.data?.errors || {
                general: ['Server error']
            };
        }
    },
    { immediate: true }
);

// Room toggle
const toggleRoom = (id) => {
    id = Number(id);
    const i = form.room_ids.indexOf(id);
    if (i === -1) form.room_ids.push(id);
    else form.room_ids.splice(i, 1);
};

// Group rooms
const groupedRooms = computed(() => {
    const groups = {};
    availableRooms.value.forEach(room => {
        const type = room.type?.translation?.name || 'Other';
        if (!groups[type]) groups[type] = [];
        groups[type].push(room);
    });
    return groups;
});

// INIT EDIT
onMounted(() => {
    const b = props.booking;

    originalBookingType.value = b.booking_type;
    form.is_hourly = b.booking_type === 'hourly';

    form.date_from = form.is_hourly
        ? formatDateTime(b.date_from)
        : formatDate(b.date_from);

    form.date_to = form.is_hourly
        ? formatDateTime(b.date_to)
        : formatDate(b.date_to);

    form.booking_type = b.booking_type;
    form.units_count = b.units_count;
    form.guests_count = b.guests_count;

    // Сохраняем ВСЕ платежи
    allPayments.value = b.booking_payments || [];

    // Находим prepayment для поля (только для отображения)
    const prepaymentPayment = allPayments.value.find(p => p.type === 'prepayment');
    if (prepaymentPayment) {
        form.prepayment = Number(prepaymentPayment.amount || 0);
        form.payment_method_id = prepaymentPayment.payment_method_id;
        form.card_type_id = prepaymentPayment.card_type_id;
    } else {
        form.prepayment = Number(b.prepayment || 0);
        form.payment_method_id = b.payment_method_id;
        form.card_type_id = b.card_type_id;
    }

    form.discount_type = b.discount_type ?? 'none';
    form.discount_value = Number(b.discount_value || 0);
    form.private_amount = Number(b.private_amount || 0);
    form.is_free = !!b.is_free;
    form.is_special = !!b.is_special_guest;
    form.description = b.notes ?? '';

    form.room_ids = b.booking_rooms.map(r => r.room_id);
    form.room_types = [...new Set(b.booking_rooms.map(r => r.room.room_type_id))];

    // Заполняем контактные данные
    form.contact_name = b.contact_name;
    form.contact_phone = b.contact_phone;

    // ТОЛЬКО AdditionalService, ServiceType ИГНОРИРУЕМ
    if (b.services_with_details && b.services_with_details.length) {
        b.services_with_details.forEach(detail => {
            const service = detail.serviceable;
            // Добавляем только если это AdditionalService (не ServiceType)
            if (service && detail.serviceable_type === 'App\\Models\\AdditionalService') {
                selectedServices.value[service.id] = {
                    active: true,
                    qty: detail.quantity || 1
                };
            }
        });
    }
});

// Select2
onMounted(() => {
    $('#room_types')
        .select2({ width: '100%' })
        .on('change', function () {
            form.room_types = $(this).val() || [];
        });

    $('#room_types').val(form.room_types).trigger('change.select2');
});

watch(() => form.room_types, (val) => {
    $('#room_types').val(val).trigger('change.select2');
    isInitializing.value = false
});

nextTick(() => {
    $('#room_types').val(form.room_types).trigger('change.select2');
});

// Submit
const submit = () => {
    prepareServices();
    form.patch(route('booking.update', {
        id: props.booking.id,
        locale: currentLocale,
    }));
};
</script>

<template>
    <Head title="Edit Booking" />
    <Index>
        <div class="card mb-6">
            <div class="card-header">Edit Booking #{{ booking.id }}</div>
            <div class="card-body">

                <!-- MODE -->
                <div class="mb-3">
                    <label>
                        <input
                            type="checkbox"
                            v-model="form.is_hourly"
                            :disabled="isHourlyDisabled"
                        >
                        Hourly mode
                    </label>
                    <div v-if="isHourlyDisabled" class="small text-warning mt-1">
                        ⚠️ Cannot change booking type. Created as
                        <strong>{{ originalBookingType === 'daily' ? 'daily' : 'hourly' }}</strong>
                    </div>
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
                                        <small class="text-muted">Price: {{ form.is_hourly ? room.type?.price_per_hour : room.type?.price }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row px-3 ">
                        <!-- SERVICES -->
                        <div class="mt-5 p-4 border rounded col">
                            <h5>Additional Services (No discount)</h5>
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
                                <TextInput type="number" v-model="form.prepayment" disabled class="form-control"/>
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
                                <div class="small text-muted mt-1" v-if="booking.discount_value">
                                    Current discount: {{ booking.discount_value }} {{ booking.discount_type === 'percent' ? '%' : 'fixed' }}
                                </div>
                                <div class="small text-info mt-1">
                                    <i class="ti tabler-info-circle"></i> Discount applies only to rooms, not to services
                                </div>
                            </div>

                            <div class="d-flex gap-3 mb-3">
                                <label>
                                    <input type="checkbox" v-model="form.is_free">
                                    Free {{ form.is_free ? '(Yes)' : '(No)' }}
                                </label>
                                <label>
                                    <input type="checkbox" v-model="form.is_special">
                                    Special guest {{ form.is_special ? '(Yes)' : '(No)' }}
                                </label>
                            </div>

                            <div class="mb-3">
                                <InputLabel value="Description" />
                                <textarea v-model="form.description" class="form-control" rows="3"></textarea>
                                <div class="small text-muted mt-1" v-if="booking.notes">
                                    Current: {{ booking.notes }}
                                </div>
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
                                    <span><i class="ti tabler-bed"></i> Rooms</span>
                                    <span>{{ roomsPrice.toFixed(2) }} ֏</span>
                                </div>

                                <div class="d-flex justify-content-between text-danger">
                                    <span><i class="ti tabler-tag"></i> Discount </span>
                                    <span>- {{ roomsDiscountAmount.toFixed(2) }} ֏</span>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-bed"></i> Rooms (with discount)</span>
                                    <span>{{ (roomsPrice - roomsDiscountAmount).toFixed(2) }} ֏</span>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <span><i class="ti tabler-settings"></i> Services</span>
                                    <span>{{ servicesPrice.toFixed(2) }} ֏</span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between fw-bold mb-2">
                                <span><i class="ti tabler-chart-bar"></i> Total after discount</span>
                                <span class="text-primary">{{ finalTotal.toFixed(2) }} ֏</span>
                            </div>

                            <!-- Prepayment details -->
                            <div v-if="totalPrepayment > 0" class="d-flex justify-content-between mb-2 text-info">
                                <span><i class="ti tabler-wallet"></i> Prepayment ({{ getPaymentMethodName(allPayments.find(p => p.type === 'prepayment')?.payment_method_id) }})</span>
                                <span>- {{ totalPrepayment.toFixed(2) }} ֏</span>
                            </div>

                            <!-- All payments -->
                            <div v-if="totalPayments > 0" class="mb-2">
                                <div class="fw-bold mb-1">Payments received:</div>
                                <div v-for="payment in allPayments.filter(p => p.type === 'payment')" :key="payment.id"
                                     class="d-flex justify-content-between ms-3 mb-1">
                                    <span class="small">
                                        {{ formatDateDisplay(payment.created_at) }} - {{ getPaymentMethodName(payment.payment_method_id) }}
                                    </span>
                                    <span class="text-success">- {{ Number(payment.amount).toFixed(2) }} ֏</span>
                                </div>
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
                                    <span>{{ form.private_amount.toFixed(2) }} ֏</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Updating...' : 'Update Booking' }}
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

@media (min-width: 1200px) {
    .custom-col {
        width: 15%;
    }
}
</style>
