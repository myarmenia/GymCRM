<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ServiceManager from '@/Components/ServiceManager.vue';
import { useAlert } from '@/composables/useAlert';
import { useConfirm } from '@/composables/useConfirm';
import axios from 'axios';

const page = usePage();
const currentLocale = page.props.locale ?? "en";
const alert = useAlert();
const { confirm } = useConfirm();

const props = defineProps({
    booking: Object,
    paymentMethods: Array,
    serviceTypes: Array,
    reservationTypes: Object,
});

// Refs
const serviceManagerRef = ref(null);
const isSubmitting = ref(false);
const isRefunding = ref(false);
const customAmount = ref(null);
const useCustomAmount = ref(false);
const isLoading = ref(false);
const isChangingStatus = ref(false);
const showRefundModal = ref(false);
const refundAmount = ref(null);
const refundMethod = ref(null);
const refundCardType = ref(null);
const refundNotes = ref('');

// Локальные данные для реактивного обновления
const localBooking = ref({ ...props.booking });

// Форма для завершения оплаты
const paymentForm = useForm({
    amount: 0,
    payment_method_id: null,
    card_type_id: null,
    notes: null
});

// Функция для обновления данных с сервера
const refreshBookingData = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('booking.get', {
            locale: currentLocale,
            id: localBooking.value.id
        }));

        if (response.data.success && response.data.data) {
            localBooking.value = response.data.data;
            console.log('Booking data refreshed:', localBooking.value);
        }
    } catch (error) {
        console.error('Error refreshing booking data:', error);
        alert.error('Failed to refresh booking data');
    } finally {
        isLoading.value = false;
    }
};

// ========== РАСЧЕТЫ ==========
// Сумма комнат
const totalRoomsAmount = computed(() => {
    if (!localBooking.value.booking_rooms) return 0;
    return localBooking.value.booking_rooms.reduce((sum, room) => sum + Number(room.total_price || 0), 0);
});

// Сумма услуг
const bookingServicesTotal = computed(() => {
    if (!localBooking.value.services_with_details) return 0;
    return localBooking.value.services_with_details.reduce((sum, detail) => sum + Number(detail.total_price || 0), 0);
});

// Сумма скидки
const discountAmount = computed(() => {
    return Number(localBooking.value.discount_amount || 0);
});

// Тип скидки
const discountLabel = computed(() => {
    if (localBooking.value.discount_type === 'percent') {
        return `${localBooking.value.discount_value}%`;
    } else if (localBooking.value.discount_type === 'fixed') {
        return formatCurrency(localBooking.value.discount_value);
    }
    return null;
});

const hasDiscount = computed(() => {
    return discountAmount.value > 0;
});

// Сумма после скидки
const roomsAfterDiscountAmount = computed(() => {
    return totalRoomsAmount.value - discountAmount.value;
});

// Итоговая сумма
const finalTotalAmount = computed(() => {
    return Number(localBooking.value.final_price || 0);
});

// Предоплаты
const prepaymentsTotal = computed(() => {
    if (!localBooking.value.booking_payments) return 0;
    return localBooking.value.booking_payments
        .filter(payment => payment.type === 'prepayment' && payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount), 0);
});

// Обычные платежи
const regularPaymentsTotal = computed(() => {
    if (!localBooking.value.booking_payments) return 0;
    return localBooking.value.booking_payments
        .filter(payment => payment.type === 'payment' && payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount), 0);
});

// Возвраты (сумма положительная, но вычитается из оплаченного)
const refundsTotal = computed(() => {
    if (!localBooking.value.booking_payments) return 0;
    return localBooking.value.booking_payments
        .filter(payment => payment.type === 'refund' && payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount), 0);
});

// Общая оплаченная сумма (учитывая возвраты)
const totalPaid = computed(() => {
    return prepaymentsTotal.value + regularPaymentsTotal.value - refundsTotal.value;
});

// Остаток к оплате
const remainingToPay = computed(() => {
    const remaining = finalTotalAmount.value - totalPaid.value;
    return remaining > 0 ? remaining : 0;
});

// Сумма к возврату (если переплата)
const overpayment = computed(() => {
    const overpaid = totalPaid.value - finalTotalAmount.value;
    return overpaid > 0 ? overpaid : 0;
});

// Процент оплаты
const paymentPercentage = computed(() => {
    if (finalTotalAmount.value === 0) return 0;
    let percent = (totalPaid.value / finalTotalAmount.value) * 100;
    return Math.min(percent, 100);
});

// Сумма к оплате сейчас
const amountToPayNow = computed(() => {
    if (useCustomAmount.value && customAmount.value) {
        const amount = Math.min(Number(customAmount.value), remainingToPay.value);
        return amount > 0 ? amount : 0;
    }
    return remainingToPay.value;
});

// Валидация кастомной суммы
const isCustomAmountValid = computed(() => {
    if (!useCustomAmount.value) return true;
    const amount = Number(customAmount.value);
    return amount > 0 && amount <= remainingToPay.value;
});

// Проверка, есть ли хоть какая-то оплата
const hasAnyPayment = computed(() => {
    return totalPaid.value > 0;
});

// Валидация возврата
const isRefundValid = computed(() => {
    const amount = Number(refundAmount.value);
    const maxRefund = totalPaid.value;
    return amount > 0 && amount <= maxRefund;
});

// Максимальная сумма возврата
const maxRefundAmount = computed(() => {
    return totalPaid.value;
});

// Маппинг reservation_type_id -> slug
const getReservationSlugFromId = (id) => {
    const mapping = {
        1: 'reservation',
        2: 'checkin',
        3: 'checkout',
        4: 'cancelled'
    };
    return mapping[id] || 'reservation';
};

// Текущий slug reservation type
const currentReservationSlug = computed(() => {
    const slugFromObject = localBooking.value.reservation_type?.slug;
    if (slugFromObject) {
        return slugFromObject;
    }
    const id = localBooking.value.reservation_type_id;
    const slug = getReservationSlugFromId(id);
    return slug;
});

// Текущий тип для отображения
const currentReservationDisplay = computed(() => {
    const slug = currentReservationSlug.value;
    if (props.reservationTypes?.[slug]) {
        return props.reservationTypes[slug];
    }
    const fallbackMap = {
        reservation: { label: 'Reservation', class: 'bg-label-warning', icon: 'ti-calendar' },
        checkin: { label: 'Checkin', class: 'bg-label-info', icon: 'ti-home' },
        checkout: { label: 'CheckOut', class: 'bg-label-success', icon: 'ti-door-exit' },
        cancelled: { label: 'Cancelled', class: 'bg-label-danger', icon: 'ti-ban' }
    };
    return fallbackMap[slug] || {
        label: slug,
        class: 'bg-label-secondary',
        icon: 'ti-info-circle'
    };
});

// Кнопка Check In доступна только для резервации с оплатой
const canMarkCheckin = computed(() => {
    return currentReservationSlug.value === 'reservation' && hasAnyPayment.value;
});

// Кнопка CheckOut доступна только после заселения
const canCheckout = computed(() => {
    return currentReservationSlug.value === 'checkin';
});

// Кнопка Cancel доступна только для резервации (не заселен)
const canCancel = computed(() => {
    return currentReservationSlug.value === 'reservation';
});

// Показывать кнопку возврата (есть оплата, но бронь аннулирована или выселена)
const showRefundButton = computed(() => {
    return totalPaid.value > 0 && currentReservationSlug.value === 'cancelled';
});

// Можно ли добавлять услуги (не checkout и не cancelled)
const canAddServices = computed(() => {
    return currentReservationSlug.value !== 'checkout' &&
           currentReservationSlug.value !== 'cancelled';
});

// Статус оплаты
const paymentStatus = computed(() => {
    let status = localBooking.value.payment_status || 'unpaid';

    if (overpayment.value > 0) {
        status = 'overpaid';
    } else if (remainingToPay.value === 0 && finalTotalAmount.value > 0) {
        status = 'paid';
    } else if (totalPaid.value > 0 && remainingToPay.value > 0) {
        status = 'partial';
    }

    const statuses = {
        unpaid: { class: 'badge bg-label-danger', text: 'Unpaid', icon: 'ti-circle-x' },
        partial: { class: 'badge bg-label-warning', text: 'Partial', icon: 'ti-clock' },
        paid: { class: 'badge bg-label-success', text: 'Paid', icon: 'ti-check' },
        overpaid: { class: 'badge bg-label-danger', text: 'Overpaid (Refund Owed)', icon: 'ti-arrow-back-up' }
    };
    return statuses[status] || statuses.unpaid;
});

// ========== ФОРМАТИРОВАНИЕ ==========
const formatDateRange = () => {
    const booking = localBooking.value;
    if (!booking.date_from || !booking.date_to) return '—';

    const fromDate = new Date(booking.date_from);
    const toDate = new Date(booking.date_to);

    if (booking.booking_type === 'hourly') {
        const formatOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        const fromStr = fromDate.toLocaleDateString('en-US', formatOptions);
        const toStr = toDate.toLocaleDateString('en-US', formatOptions);
        return `${fromStr} - ${toStr}`;
    } else {
        return `${formatDate(booking.date_from)} - ${formatDate(booking.date_to)}`;
    }
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatCurrency = (amount) => {
    return Number(amount).toLocaleString('en-US') + ' AMD';
};

const unitsLabel = computed(() => {
    return localBooking.value.booking_type === 'hourly' ? 'Hours' : 'Days';
});

// ========== ДЕЙСТВИЯ ==========
const changeReservationType = async (typeSlug, actionName) => {
    let title, message, confirmText, confirmClass;

    if (typeSlug === 'checkin') {
        title = 'Confirm Check In';
        message = 'Mark this booking as CheckIn? The guest will be checked in.';
        confirmText = 'Yes, Check In';
        confirmClass = 'btn-info';
    } else if (typeSlug === 'checkout') {
        title = 'Confirm Check Out';
        message = 'Complete CheckOut? This will mark the booking as completed.';
        confirmText = 'Yes, Check Out';
        confirmClass = 'btn-success';
    } else if (typeSlug === 'cancelled') {
        title = 'Confirm Cancellation';
        message = 'Cancel this booking? This will mark it as cancelled.';
        confirmText = 'Yes, Cancel';
        confirmClass = 'btn-danger';
    }

    const confirmed = await confirm(message, { title, confirmText, confirmClass });
    if (!confirmed) return;

    isChangingStatus.value = true;

    try {
        const response = await axios.post(route('booking.change_reservation_type', {
            locale: currentLocale,
            id: localBooking.value.id
        }), {
            reservation_type_slug: typeSlug
        });

        if (response.data.success) {
            alert.success(`Booking marked as ${actionName} successfully!`);
            await refreshBookingData();
        } else {
            alert.error(response.data.message || 'Failed to change booking status');
        }
    } catch (error) {
        console.error('Error changing status:', error);
        alert.error(error.response?.data?.message || 'Failed to change booking status');
    } finally {
        isChangingStatus.value = false;
    }
};

// Отмена бронирования с проверкой оплаты
const handleCancelBooking = async () => {
    // Если есть оплата, показываем предупреждение
    if (totalPaid.value > 0) {
        const confirmed = await confirm(
            `Guest has paid ${formatCurrency(totalPaid.value)}. ` +
            `If you cancel this booking, you will need to process a refund. ` +
            `Do you want to continue?`,
            {
                title: 'Cancel Booking with Payment',
                confirmText: 'Yes, Cancel Booking',
                confirmClass: 'btn-danger'
            }
        );

        if (!confirmed) return;

        // Отменяем бронь
        await changeReservationType('cancelled', 'Cancelled');

        // Открываем модалку для возврата
        if (totalPaid.value > 0) {
            openRefundModal();
        }
    } else {
        // Если нет оплаты, просто отменяем
        const confirmed = await confirm(
            'Are you sure you want to cancel this booking?',
            {
                title: 'Confirm Cancellation',
                confirmText: 'Yes, Cancel Booking',
                confirmClass: 'btn-danger'
            }
        );

        if (confirmed) {
            await changeReservationType('cancelled', 'Cancelled');
        }
    }
};

// Завершение оплаты
const completePayment = () => {
    if (amountToPayNow.value <= 0) {
        alert.warning('No amount to pay');
        return;
    }

    if (!paymentForm.payment_method_id) {
        alert.warning('Please select payment method');
        return;
    }

    const selectedMethod = props.paymentMethods?.find(p => p.id == paymentForm.payment_method_id);
    if (selectedMethod?.slug === 'card' && !paymentForm.card_type_id) {
        alert.warning('Card type is required for card payments');
        return;
    }

    isSubmitting.value = true;

    axios.post(route('booking.complete.payment', {
        locale: currentLocale,
        id: localBooking.value.id
    }), {
        amount: amountToPayNow.value,
        payment_method_id: paymentForm.payment_method_id,
        card_type_id: paymentForm.card_type_id,
        notes: paymentForm.notes
    })
    .then(async (response) => {
        if (response.data.success) {
            alert.success('Payment completed successfully!');
            await refreshBookingData();

            paymentForm.payment_method_id = null;
            paymentForm.card_type_id = null;
            paymentForm.notes = null;
            customAmount.value = null;
            useCustomAmount.value = false;
        } else {
            alert.error(response.data.message || 'Payment failed');
        }
    })
    .catch(error => {
        console.error('Payment error:', error);
        alert.error(error.response?.data?.message || 'Payment failed. Please try again.');
    })
    .finally(() => {
        isSubmitting.value = false;
    });
};

// Возврат средств
const processRefund = async () => {
    if (!isRefundValid.value) {
        alert.warning('Please enter a valid refund amount');
        return;
    }

    if (!refundMethod.value) {
        alert.warning('Please select refund method');
        return;
    }

    const selectedMethod = props.paymentMethods?.find(p => p.id == refundMethod.value);
    if (selectedMethod?.slug === 'card' && !refundCardType.value) {
        alert.warning('Card type is required for card refunds');
        return;
    }

    const confirmed = await confirm(
        `You are about to refund ${formatCurrency(refundAmount.value)} to the guest. This action cannot be undone.`,
        {
            title: 'Confirm Refund',
            confirmText: 'Yes, Process Refund',
            confirmClass: 'btn-danger'
        }
    );

    if (!confirmed) return;

    isRefunding.value = true;

    try {
        const response = await axios.post(route('booking.refund.payment', {
            locale: currentLocale,
            id: localBooking.value.id
        }), {
            amount: refundAmount.value,
            payment_method_id: refundMethod.value,
            card_type_id: refundCardType.value,
            notes: refundNotes.value || 'Refund processed'
        });

        if (response.data.success) {
            alert.success('Refund processed successfully!');
            await refreshBookingData();

            // Сброс формы возврата
            refundAmount.value = null;
            refundMethod.value = null;
            refundCardType.value = null;
            refundNotes.value = '';
            showRefundModal.value = false;
        } else {
            alert.error(response.data.message || 'Refund failed');
        }
    } catch (error) {
        console.error('Refund error:', error);
        alert.error(error.response?.data?.message || 'Refund failed. Please try again.');
    } finally {
        isRefunding.value = false;
    }
};

// Обновление при добавлении/удалении услуги
const handleServiceChange = async () => {
    console.log('Service changed, refreshing booking data...');
    await refreshBookingData();
};

const selectedPaymentMethod = computed(() => {
    return props.paymentMethods?.find(p => p.id == paymentForm.payment_method_id);
});

const selectedRefundMethod = computed(() => {
    return props.paymentMethods?.find(p => p.id == refundMethod.value);
});

watch(() => paymentForm.payment_method_id, (newMethodId) => {
    const method = props.paymentMethods?.find(p => p.id == newMethodId);
    if (method?.slug !== 'card') {
        paymentForm.card_type_id = null;
    }
});

watch(() => refundMethod.value, (newMethodId) => {
    const method = props.paymentMethods?.find(p => p.id == newMethodId);
    if (method?.slug !== 'card') {
        refundCardType.value = null;
    }
});

const toggleCustomAmount = () => {
    useCustomAmount.value = !useCustomAmount.value;
    if (!useCustomAmount.value) {
        customAmount.value = null;
    }
};

const openRefundModal = () => {
    refundAmount.value = totalPaid.value;
    refundMethod.value = null;
    refundCardType.value = null;
    refundNotes.value = '';
    showRefundModal.value = true;
};

const closeRefundModal = () => {
    showRefundModal.value = false;
    refundAmount.value = null;
    refundMethod.value = null;
    refundCardType.value = null;
    refundNotes.value = '';
};

// Группировка платежей
const prepayments = computed(() => {
    if (!localBooking.value.booking_payments) return [];
    return localBooking.value.booking_payments.filter(p => p.type === 'prepayment' && p.status === 'paid');
});

const payments = computed(() => {
    if (!localBooking.value.booking_payments) return [];
    return localBooking.value.booking_payments.filter(p => p.type === 'payment' && p.status === 'paid');
});

const refunds = computed(() => {
    if (!localBooking.value.booking_payments) return [];
    return localBooking.value.booking_payments.filter(p => p.type === 'refund');
});

// Устанавливаем сумму в форму
watch(amountToPayNow, (newAmount) => {
    paymentForm.amount = newAmount;
}, { immediate: true });
</script>

<template>
    <Head :title="`Complete Payment - Booking #${booking.id}`" />
    <Index>
        <div class="container-xxl flex-grow-1 container-p-y">
            <!-- Loading Overlay -->
            <div v-if="isLoading" class="loading-overlay">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between flex-wrap">
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-lg me-3">
                                        <div class="avatar-initial bg-label-primary rounded">
                                            <i class="ti tabler-credit-card fs-1"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="mb-1">Complete Payment</h4>
                                        <p class="mb-0 text-muted">
                                            Booking #{{ localBooking.id }} •
                                            {{ formatDateRange() }}
                                        </p>
                                        <div class="mt-2 d-flex gap-2 flex-wrap">
                                            <span :class="currentReservationDisplay.class" class="badge">
                                                <i :class="`ti tabler-${currentReservationDisplay.icon}`" class="me-1"></i>
                                                {{ currentReservationDisplay.label }}
                                            </span>
                                            <span :class="paymentStatus.class" class="badge">
                                                <i :class="`ti tabler-${paymentStatus.icon}`" class="me-1"></i>
                                                {{ paymentStatus.text }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    @click="refreshBookingData"
                                    class="btn btn-sm btn-outline-secondary waves-effect align-self-center"
                                    title="Refresh data"
                                >
                                    <i class="ti tabler-refresh"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <!-- Booking Rooms -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="ti tabler-bed me-2"></i>
                                Booked Rooms
                                <span class="badge bg-label-secondary ms-2">
                                    {{ localBooking.booking_type === 'hourly' ? 'Hourly' : 'Daily' }}
                                </span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Room #</th>
                                            <th>Type</th>
                                            <th>Price per {{ localBooking.booking_type === 'hourly' ? 'hour' : 'night' }}</th>
                                            <th>{{ unitsLabel }}</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="bookingRoom in localBooking.booking_rooms" :key="bookingRoom.id">
                                            <td class="fw-bold">#{{ bookingRoom.room?.number }}</td>
                                            <td>{{ bookingRoom.room?.type?.name }}</td>
                                            <td>
                                                {{ formatCurrency(localBooking.booking_type === 'hourly'
                                                    ? bookingRoom.room?.type?.price_per_hour
                                                    : bookingRoom.room?.type?.price) }}
                                            </td>
                                            <td>{{ bookingRoom.units }}</td>
                                            <td class="fw-bold">{{ formatCurrency(bookingRoom.total_price) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold">Total Rooms:</td>
                                            <td class="fw-bold">{{ formatCurrency(totalRoomsAmount) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Service Manager -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="ti tabler-settings me-2"></i>
                                Additional Services
                            </h5>
                        </div>
                        <div class="card-body">
                            <ServiceManager
                                ref="serviceManagerRef"
                                :booking="localBooking"
                                :service-types="serviceTypes"
                                :rooms="localBooking.booking_rooms"
                                :can-add-services="canAddServices"
                                @service-added="handleServiceChange"
                                @service-removed="handleServiceChange"
                            />
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Rooms:</span>
                                    <span class="fw-bold">{{ formatCurrency(totalRoomsAmount) }}</span>
                                </div>

                                <div v-if="hasDiscount" class="d-flex justify-content-between mb-2 text-success">
                                    <span>
                                        <i class="ti tabler-discount-2 me-1"></i>
                                        Discount ({{ discountLabel }}):
                                    </span>
                                    <span class="fw-bold">- {{ formatCurrency(discountAmount) }}</span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Rooms after discount:</span>
                                    <span class="fw-bold">{{ formatCurrency(roomsAfterDiscountAmount) }}</span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Additional Services:</span>
                                    <span class="fw-bold text-info">{{ formatCurrency(bookingServicesTotal) }}</span>
                                </div>

                                <div class="d-flex justify-content-between pt-2 border-top mt-2">
                                    <span class="h6 mb-0">Total Amount:</span>
                                    <span class="h6 mb-0 text-primary fw-bold">{{ formatCurrency(finalTotalAmount) }}</span>
                                </div>
                            </div>

                            <!-- Payment History -->
                            <div v-if="prepayments.length > 0 || payments.length > 0 || refunds.length > 0" class="mb-3">
                                <hr>
                                <h6 class="mb-2">Payment History</h6>

                                <div v-if="prepayments.length > 0" class="mb-2">
                                    <div class="text-muted small mb-1">Prepayments:</div>
                                    <div v-for="payment in prepayments" :key="payment.id"
                                         class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <i class="ti tabler-credit-card text-info me-1"></i>
                                            <small>{{ formatDateTime(payment.created_at) }}</small>
                                        </div>
                                        <span class="text-success fw-bold">+ {{ formatCurrency(payment.amount) }}</span>
                                    </div>
                                </div>

                                <div v-if="payments.length > 0" class="mb-2">
                                    <div class="text-muted small mb-1">Payments:</div>
                                    <div v-for="payment in payments" :key="payment.id"
                                         class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <i class="ti tabler-receipt text-success me-1"></i>
                                            <small>{{ formatDateTime(payment.created_at) }}</small>
                                        </div>
                                        <span class="text-success fw-bold">+ {{ formatCurrency(payment.amount) }}</span>
                                    </div>
                                </div>

                                <div v-if="refunds.length > 0" class="mb-2">
                                    <div class="text-muted small mb-1">Refunds:</div>
                                    <div v-for="refund in refunds" :key="refund.id"
                                         class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <i class="ti tabler-arrow-back-up text-danger me-1"></i>
                                            <small>{{ formatDateTime(refund.created_at) }}</small>
                                        </div>
                                        <span class="text-danger fw-bold">- {{ formatCurrency(refund.amount) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Paid & Remaining / Overpayment -->
                            <div class="d-flex justify-content-between mb-2 pt-2 border-top">
                                <span>Total Paid:</span>
                                <span class="text-success fw-bold">{{ formatCurrency(totalPaid) }}</span>
                            </div>

                            <div v-if="overpayment > 0" class="d-flex justify-content-between mb-2 text-danger">
                                <span>Overpayment:</span>
                                <span class="fw-bold">{{ formatCurrency(overpayment) }}</span>
                            </div>

                            <div v-else class="d-flex justify-content-between mb-3 pb-2 border-bottom">
                                <span>Remaining to Pay:</span>
                                <span class="text-warning fw-bold">{{ formatCurrency(remainingToPay) }}</span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="progress mb-2" style="height: 8px;">
                                <div
                                    class="progress-bar"
                                    :class="overpayment > 0 ? 'bg-danger' : 'bg-success'"
                                    :style="{ width: paymentPercentage + '%' }"
                                    role="progressbar"
                                ></div>
                            </div>
                            <div class="text-center mb-3">
                                <small class="text-muted">{{ paymentPercentage.toFixed(1) }}% paid</small>
                            </div>

                            <hr>

                            <!-- Complete Payment Form (только если есть остаток) -->
                            <div v-if="remainingToPay > 0 && currentReservationSlug !== 'cancelled' && currentReservationSlug !== 'checkout'">
                                <h6 class="mb-3">Make a Payment</h6>

                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input
                                            type="radio"
                                            id="fullPayment"
                                            class="form-check-input"
                                            :checked="!useCustomAmount"
                                            @change="useCustomAmount = false"
                                        >
                                        <label for="fullPayment" class="form-check-label">
                                            Pay remaining amount ({{ formatCurrency(remainingToPay) }})
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input
                                            type="radio"
                                            id="customPayment"
                                            class="form-check-input"
                                            :checked="useCustomAmount"
                                            @change="toggleCustomAmount"
                                        >
                                        <label for="customPayment" class="form-check-label">
                                            Pay custom amount
                                        </label>
                                    </div>
                                </div>

                                <div v-if="useCustomAmount" class="mb-3">
                                    <label class="form-label">Custom Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">AMD</span>
                                        <input
                                            type="number"
                                            class="form-control"
                                            :class="{ 'is-invalid': customAmount && (!isCustomAmountValid || customAmount > remainingToPay) }"
                                            v-model="customAmount"
                                            placeholder="Enter amount"
                                            min="1"
                                            :max="remainingToPay"
                                        >
                                    </div>
                                    <div v-if="customAmount && customAmount > remainingToPay" class="invalid-feedback d-block">
                                        Amount cannot exceed remaining balance ({{ formatCurrency(remainingToPay) }})
                                    </div>
                                    <div v-if="customAmount && customAmount <= 0" class="invalid-feedback d-block">
                                        Amount must be greater than 0
                                    </div>
                                    <small class="text-muted">Maximum: {{ formatCurrency(remainingToPay) }}</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select
                                        v-model="paymentForm.payment_method_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': paymentForm.errors.payment_method_id }"
                                    >
                                        <option :value="null">Select payment method</option>
                                        <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                            {{ method.name }}
                                        </option>
                                    </select>
                                    <div v-if="paymentForm.errors.payment_method_id" class="invalid-feedback">
                                        {{ paymentForm.errors.payment_method_id }}
                                    </div>
                                </div>

                                <div v-if="selectedPaymentMethod?.slug === 'card'" class="mb-3">
                                    <label class="form-label">Card Type <span class="text-danger">*</span></label>
                                    <select
                                        v-model="paymentForm.card_type_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': paymentForm.errors.card_type_id }"
                                    >
                                        <option :value="null">Select card type</option>
                                        <option v-for="card in selectedPaymentMethod.card_types" :key="card.id" :value="card.id">
                                            {{ card.name }}
                                        </option>
                                    </select>
                                    <div v-if="paymentForm.errors.card_type_id" class="invalid-feedback">
                                        {{ paymentForm.errors.card_type_id }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes (optional)</label>
                                    <textarea
                                        v-model="paymentForm.notes"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Payment notes..."
                                    ></textarea>
                                </div>

                                <button
                                    class="btn btn-primary w-100"
                                    :disabled="isSubmitting || (useCustomAmount && !isCustomAmountValid) || isLoading"
                                    @click="completePayment"
                                >
                                    <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="ti tabler-credit-card me-2"></i>
                                    {{ isSubmitting ? 'Processing...' : `Pay ${formatCurrency(amountToPayNow)}` }}
                                </button>

                                <div v-if="useCustomAmount && amountToPayNow < remainingToPay" class="alert alert-info mt-3 mb-0 py-2">
                                    <i class="ti tabler-info-circle me-1"></i>
                                    After this payment, remaining balance will be: {{ formatCurrency(remainingToPay - amountToPayNow) }}
                                </div>
                            </div>

                            <!-- Refund Section (для переплаты) -->
                            <div v-if="overpayment > 0" class="mt-3">
                                <div class="alert alert-danger">
                                    <i class="ti tabler-alert-circle me-2"></i>
                                    <strong>Overpayment detected!</strong>
                                    <div class="small mt-1">
                                        Guest has paid {{ formatCurrency(overpayment) }} more than the total amount.
                                        Please process a refund.
                                    </div>
                                    <button
                                        class="btn btn-danger btn-sm mt-2 w-100"
                                        @click="openRefundModal"
                                    >
                                        <i class="ti tabler-arrow-back-up me-1"></i>
                                        Process Refund ({{ formatCurrency(overpayment) }})
                                    </button>
                                </div>
                            </div>

                            <!-- Refund Button for Cancelled/Checkout bookings -->
                            <div v-if="showRefundButton" class="mt-3">
                                <button
                                    class="btn btn-danger w-100"
                                    @click="openRefundModal"
                                    :disabled="isRefunding"
                                >
                                    <i class="ti tabler-arrow-back-up me-2"></i>
                                    Refund Payment ({{ formatCurrency(totalPaid) }})
                                </button>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-3">
                                <!-- Check In -->
                                <button
                                    v-if="canMarkCheckin"
                                    class="btn btn-info w-100"
                                    :disabled="isChangingStatus"
                                    @click="changeReservationType('checkin', 'CheckIn')"
                                >
                                    <span v-if="isChangingStatus" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="ti tabler-home me-2"></i>
                                    {{ isChangingStatus ? 'Processing...' : 'Check In' }}
                                </button>

                                <!-- Check Out -->
                                <button
                                    v-if="canCheckout"
                                    class="btn btn-success w-100"
                                    :disabled="isChangingStatus"
                                    @click="changeReservationType('checkout', 'CheckOut')"
                                >
                                    <span v-if="isChangingStatus" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="ti tabler-door-exit me-2"></i>
                                    {{ isChangingStatus ? 'Processing...' : 'Check Out' }}
                                </button>

                                <!-- Cancel - только для резервации -->
                                <button
                                    v-if="canCancel"
                                    class="btn btn-danger w-100"
                                    :disabled="isChangingStatus"
                                    @click="handleCancelBooking"
                                >
                                    <span v-if="isChangingStatus" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="ti tabler-ban me-2"></i>
                                    {{ isChangingStatus ? 'Processing...' : 'Cancel Booking' }}
                                </button>
                            </div>

                            <div v-if="currentReservationSlug === 'checkout'" class="alert alert-info mt-3 mb-0">
                                <i class="ti tabler-check-circle me-2"></i>
                                <strong>CheckOut completed!</strong>
                                <div class="small mt-1">This booking has been checked out.</div>
                            </div>

                            <div v-if="currentReservationSlug === 'cancelled'" class="alert alert-danger mt-3 mb-0">
                                <i class="ti tabler-ban me-2"></i>
                                <strong>Booking cancelled!</strong>
                                <div class="small mt-1">This booking has been cancelled.</div>
                            </div>

                            <div v-if="!hasAnyPayment && currentReservationSlug === 'reservation'" class="alert alert-warning mt-3 mb-0">
                                <i class="ti tabler-alert-circle me-2"></i>
                                <strong>No payment made yet</strong>
                                <div class="small mt-1">Please make a payment to check in the guest.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refund Modal -->
        <div class="modal fade" :class="{ show: showRefundModal }" tabindex="-1" v-if="showRefundModal" style="display: block;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Process Refund</h5>
                        <button type="button" class="btn-close" @click="closeRefundModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Refund Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">AMD</span>
                                <input
                                    type="number"
                                    class="form-control"
                                    :class="{ 'is-invalid': refundAmount && (!isRefundValid || refundAmount > maxRefundAmount) }"
                                    v-model="refundAmount"
                                    placeholder="Enter refund amount"
                                    min="1"
                                    :max="maxRefundAmount"
                                >
                            </div>
                            <div v-if="refundAmount && refundAmount > maxRefundAmount" class="invalid-feedback d-block">
                                Amount cannot exceed total paid ({{ formatCurrency(maxRefundAmount) }})
                            </div>
                            <div v-if="refundAmount && refundAmount <= 0" class="invalid-feedback d-block">
                                Amount must be greater than 0
                            </div>
                            <small class="text-muted">Maximum refund: {{ formatCurrency(maxRefundAmount) }}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Refund Method <span class="text-danger">*</span></label>
                            <select
                                v-model="refundMethod"
                                class="form-select"
                            >
                                <option :value="null">Select refund method</option>
                                <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                    {{ method.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="selectedRefundMethod?.slug === 'card'" class="mb-3">
                            <label class="form-label">Card Type <span class="text-danger">*</span></label>
                            <select
                                v-model="refundCardType"
                                class="form-select"
                            >
                                <option :value="null">Select card type</option>
                                <option v-for="card in selectedRefundMethod.card_types" :key="card.id" :value="card.id">
                                    {{ card.name }}
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea
                                v-model="refundNotes"
                                class="form-control"
                                rows="2"
                                placeholder="Refund reason..."
                            ></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeRefundModal">Cancel</button>
                        <button
                            type="button"
                            class="btn btn-danger"
                            :disabled="!isRefundValid || isRefunding"
                            @click="processRefund"
                        >
                            <span v-if="isRefunding" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="ti tabler-arrow-back-up me-2"></i>
                            {{ isRefunding ? 'Processing...' : `Refund ${formatCurrency(refundAmount)}` }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showRefundModal" class="modal-backdrop fade show"></div>
    </Index>
</template>

<style scoped>
.is-invalid {
    border-color: #ff4d4f !important;
}

.invalid-feedback {
    color: #ff4d4f;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.spinner-border {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.table > :not(caption) > * > * {
    padding: 0.75rem;
}

.border-top {
    border-top: 1px solid #dee2e6;
}

.border-bottom {
    border-bottom: 1px solid #dee2e6;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.form-check {
    cursor: pointer;
}

.form-check-input {
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
}

.alert-info {
    background-color: #e1f5fe;
    border-color: #b3e5fc;
    color: #0277bd;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeeba;
    color: #856404;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.btn-info {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
    border-color: #117a8b;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mt-3 {
    margin-top: 1rem;
}

.modal.show {
    display: block;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-backdrop {
    opacity: 0.5;
}
</style>
