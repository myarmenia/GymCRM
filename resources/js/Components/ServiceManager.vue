<!-- Components/ServiceManager.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { useAlert } from '@/composables/useAlert';
import { useConfirm } from '@/composables/useConfirm';

const page = usePage();
const currentLocale = page.props.locale ?? "en";
const alert = useAlert();
const { confirm } = useConfirm();

const props = defineProps({
    booking: Object,
    serviceTypes: Array,
    rooms: Array,
    selectedRoomId: {
        type: Number,
        default: null
    },
    canAddServices: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['service-added', 'service-removed']);

// Состояние
const showAddServiceModal = ref(false);
const showRoomSelectorModal = ref(false);
const currentRoomId = ref(props.selectedRoomId);
const isSaving = ref(false);
const localServices = ref([]);

// Форма для добавления новой услуги
const newServiceForm = ref({
    service_type_id: null,
    name: '',
    price_per_unit: 0,
    quantity: 1,
    meta: '',
    room_id: null
});

// Инициализируем локальные услуги из props
const initLocalServices = () => {
    if (props.booking?.services_with_details) {
        localServices.value = props.booking.services_with_details.map(detail => ({
            id: detail.id,
            name: detail.name,
            price_per_unit: detail.price_per_unit,
            quantity: detail.quantity,
            total_price: detail.total_price,
            room_id: detail.room_id,
            room_number: detail.room?.number,
            meta: detail.meta,
            created_at: detail.created_at,
            is_saved: true
        }));
    }
};

// Вызываем инициализацию при монтировании
initLocalServices();

// Получаем комнаты из бронирования
const bookingRooms = computed(() => {
    if (!props.booking?.booking_rooms) return [];
    return props.booking.booking_rooms.map(br => ({
        id: br.room_id,
        number: br.room?.number,
        type: br.room?.room_type?.translation?.name,
        price_per_night: br.room?.type?.price,
        price_per_hour: br.room?.type?.price_per_hour,
        total_price: br.total_price || br.price
    }));
});

// Группировка услуг по комнатам (используем локальные услуги)
const servicesByRoom = computed(() => {
    const grouped = {};

    localServices.value.forEach(service => {
        const roomId = service.room_id || 'common';
        if (!grouped[roomId]) {
            grouped[roomId] = {
                room: bookingRooms.value.find(r => r.id === roomId),
                services: []
            };
        }
        grouped[roomId].services.push(service);
    });

    return grouped;
});

// Общая сумма всех услуг
const totalServicesAmount = computed(() => {
    return localServices.value.reduce((sum, service) => sum + (service.total_price || 0), 0);
});

// Эмитим общую сумму в родительский компонент
watch(totalServicesAmount, (newTotal) => {
    emit('service-added', newTotal);
});

// Получить тип услуги по ID
const getServiceType = (typeId) => {
    return props.serviceTypes?.find(t => t.id == typeId);
};

// Получить название типа услуги
const getServiceTypeName = (typeId) => {
    const type = getServiceType(typeId);
    return type?.name || type?.slug || '';
};

// Получить цену типа услуги
const getServiceTypePrice = (typeId) => {
    const type = getServiceType(typeId);
    return type?.price || 0;
};

// При выборе service_type_id - автоматически заполняем name и price
const onServiceTypeChange = () => {
    const typeId = newServiceForm.value.service_type_id;

    if (typeId) {
        newServiceForm.value.name = getServiceTypeName(typeId);
        newServiceForm.value.price_per_unit = getServiceTypePrice(typeId);
    } else {
        newServiceForm.value.name = '';
        newServiceForm.value.price_per_unit = 0;
    }
};

// Валидация формы
const validateForm = () => {
    if (!newServiceForm.value.service_type_id) {
        alert.warning('Please select a service type');
        return false;
    }

    if (newServiceForm.value.price_per_unit <= 0) {
        alert.warning('Please enter a valid price per unit');
        return false;
    }

    if (newServiceForm.value.quantity <= 0) {
        alert.warning('Please enter a valid quantity');
        return false;
    }

    return true;
};

// Сохранение услуги на бэкенд
const addAndSaveService = async () => {
    if (!validateForm()) return;

    isSaving.value = true;
    const roomId = newServiceForm.value.room_id || currentRoomId.value;

    try {
        const response = await axios.post(route('booking.add-service', {
            locale: currentLocale,
            id: props.booking.id
        }), {
            service_type_id: newServiceForm.value.service_type_id,
            name: newServiceForm.value.name,
            price_per_unit: newServiceForm.value.price_per_unit,
            quantity: newServiceForm.value.quantity,
            meta: newServiceForm.value.meta,
            room_id: roomId
        });

        if (response.data.success) {
            // Добавляем новую услугу в локальный массив
            const newService = {
                id: response.data.service?.id || Date.now(),
                name: newServiceForm.value.name,
                price_per_unit: newServiceForm.value.price_per_unit,
                quantity: newServiceForm.value.quantity,
                total_price: newServiceForm.value.price_per_unit * newServiceForm.value.quantity,
                room_id: roomId,
                room_number: bookingRooms.value.find(r => r.id === roomId)?.number,
                meta: newServiceForm.value.meta,
                is_saved: true
            };

            localServices.value.push(newService);
            alert.success('Service added successfully!');

            // Сбрасываем форму и закрываем модалку
            resetForm();
        } else {
            alert.error(response.data.message || 'Error saving service');
        }
    } catch (error) {
        console.error('Error saving service:', error);
        alert.error(error.response?.data?.message || 'Failed to save service');
    } finally {
        isSaving.value = false;
    }
};

// Сброс формы
const resetForm = () => {
    newServiceForm.value = {
        service_type_id: null,
        name: '',
        price_per_unit: 0,
        quantity: 1,
        meta: '',
        room_id: null
    };
    showAddServiceModal.value = false;
    showRoomSelectorModal.value = false;
    currentRoomId.value = null;
};

// Удаление услуги
const removeService = async (service) => {
    const confirmed = await confirm(`Remove "${service.name}" from this booking?`);

    if (!confirmed) return;

    try {
        // Если услуга уже сохранена в БД, отправляем DELETE запрос
        if (service.id && !service.is_temp) {
            const response = await axios.delete(route('booking.remove_service', {
                locale: currentLocale,
                id: props.booking.id,
                detailId: service.id
            }));

            if (response.data.success) {
                // Удаляем из локального массива
                const index = localServices.value.findIndex(s => s.id === service.id);
                if (index !== -1) {
                    localServices.value.splice(index, 1);
                }
                alert.success('Service removed successfully');
                emit('service-removed', service.total_price);
            } else {
                alert.error(response.data.message || 'Error removing service');
            }
        } else {
            // Просто удаляем из локального массива
            const index = localServices.value.findIndex(s => s.id === service.id);
            if (index !== -1) {
                localServices.value.splice(index, 1);
            }
            alert.success('Service removed successfully');
        }
    } catch (error) {
        console.error('Error removing service:', error);
        alert.error(error.response?.data?.message || 'Failed to remove service');
    }
};

// Открыть модалку с выбором комнаты
const openRoomSelector = () => {
    if (bookingRooms.value.length > 1) {
        showRoomSelectorModal.value = true;
    } else if (bookingRooms.value.length === 1) {
        currentRoomId.value = bookingRooms.value[0].id;
        showAddServiceModal.value = true;
    } else {
        showAddServiceModal.value = true;
    }
};

// Выбор комнаты и добавление услуги
const selectRoomAndAddService = (roomId) => {
    currentRoomId.value = roomId;
    showRoomSelectorModal.value = false;
    showAddServiceModal.value = true;
};

// Форматирование цены в AMD
const formatCurrency = (amount) => {
    return Number(amount).toLocaleString('en-US') + ' AMD';
};

// Закрытие модалок
const closeModals = () => {
    resetForm();
};

// Обновить общую сумму в родителе
const updateTotal = () => {
    emit('service-added', totalServicesAmount.value);
};
</script>

<template>
    <div class="service-manager">
        <!-- Сообщение если нельзя добавлять услуги -->
        <div v-if="!canAddServices" class="alert alert-info mb-3">
            <i class="ti tabler-info-circle me-2"></i>
            Services cannot be added or modified for completed or cancelled bookings.
        </div>

        <!-- Services by Room -->
        <div v-for="(group, roomId) in servicesByRoom" :key="roomId" class="mb-4">
            <div class="card">
                <div class="card-header" :class="{ 'bg-light': roomId !== 'common' }">
                    <h6 class="mb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <i class="ti tabler-door me-2"></i>
                            {{ group.room ? `Room #${group.room.number} (${group.room.type})` : 'General Services' }}
                            <span v-if="group.room && group.room.price_per_night" class="text-muted ms-2 small">
                                ({{ formatCurrency(group.room.price_per_night) }}/night)
                            </span>
                        </div>
                        <span class="badge bg-primary">{{ group.services.length }} services</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th style="width: 80px" class="text-center">Qty</th>
                                    <th style="width: 130px" class="text-end">Price/Unit</th>
                                    <th style="width: 130px" class="text-end">Total</th>
                                    <th style="width: 50px" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="service in group.services" :key="service.id">
                                    <td>
                                        <div>
                                            <i class="ti tabler-settings text-info me-2"></i>
                                            <span>{{ service.name }}</span>
                                        </div>
                                        <small class="text-muted" v-if="service.meta">{{ service.meta }}</small>
                                    </td>
                                    <td class="text-center">{{ service.quantity }}</td>
                                    <td class="text-end">{{ formatCurrency(service.price_per_unit) }}</td>
                                    <td class="text-end fw-bold">{{ formatCurrency(service.total_price) }}</td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            :disabled="!canAddServices"
                                            @click="removeService(service)"
                                            title="Remove service"
                                        >
                                            <i class="ti tabler-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!group.services.length">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="ti tabler-info-circle me-1"></i>
                                        No services added yet
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Service Button - скрываем если нельзя добавлять -->
        <div class="text-center mt-3" v-if="bookingRooms.length > 0 && canAddServices">
            <button
                @click="openRoomSelector"
                class="btn btn-primary"
                :disabled="isSaving"
            >
                <i class="ti tabler-plus me-1"></i>
                Add Service
            </button>
        </div>

        <!-- Room Selection Modal -->
        <div class="modal fade" :class="{ show: showRoomSelectorModal }"
             v-if="showRoomSelectorModal"
             style="display: block; background: rgba(0,0,0,0.5)"
             @click.self="closeModals">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ti tabler-door me-2"></i>
                            Select Room
                        </h5>
                        <button type="button" class="btn-close" @click="closeModals"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-grid gap-2">
                            <button
                                v-for="room in bookingRooms"
                                :key="room.id"
                                class="btn btn-outline-primary text-start"
                                @click="selectRoomAndAddService(room.id)"
                            >
                                <i class="ti tabler-door me-2"></i>
                                Room #{{ room.number }} - {{ room.type }}
                                <small class="text-muted d-block ms-4 mt-1">
                                    <i class="ti tabler-coin me-1"></i>
                                    {{ formatCurrency(room.price_per_night) }}/night
                                </small>
                            </button>
                            <button
                                class="btn btn-outline-secondary text-start"
                                @click="selectRoomAndAddService(null)"
                            >
                                <i class="ti tabler-world me-2"></i>
                                General Service (not tied to room)
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeModals">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Service Modal -->
        <div class="modal fade" :class="{ show: showAddServiceModal }"
             v-if="showAddServiceModal"
             style="display: block; background: rgba(0,0,0,0.5)"
             @click.self="closeModals">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="ti tabler-plus me-2"></i>
                            Add Service
                            <span v-if="currentRoomId" class="badge bg-light text-dark ms-2">
                                <i class="ti tabler-door me-1"></i>
                                Room {{ bookingRooms.find(r => r.id === currentRoomId)?.number }}
                            </span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeModals"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Service Type Select -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="ti tabler-category me-1"></i>
                                Service Type
                            </label>
                            <select
                                v-model="newServiceForm.service_type_id"
                                class="form-select"
                                @change="onServiceTypeChange"
                            >
                                <option :value="null">-- Select service type --</option>
                                <option v-for="type in serviceTypes" :key="type.id" :value="type.id">
                                    {{ type.name }}
                                    <span v-if="type.price" class="text-muted">({{ formatCurrency(type.price) }})</span>
                                </option>
                            </select>
                        </div>

                        <!-- Selected Service Info -->
                        <div class="alert alert-info mb-3" v-if="newServiceForm.service_type_id">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ti tabler-check-circle me-1"></i>
                                    <strong>{{ newServiceForm.name }}</strong>
                                </div>
                                <div>
                                    Base price: {{ formatCurrency(newServiceForm.price_per_unit) }}
                                </div>
                            </div>
                        </div>

                        <!-- Price (editable) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="ti tabler-coin me-1"></i>
                                Price per unit (AMD)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">AMD</span>
                                <input
                                    type="number"
                                    v-model="newServiceForm.price_per_unit"
                                    class="form-control"
                                    step="100"
                                    placeholder="0"
                                />
                            </div>
                            <small class="text-muted">You can modify the price if needed</small>
                        </div>

                        <!-- Quantity -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="ti tabler-numbers me-1"></i>
                                Quantity
                            </label>
                            <input
                                type="number"
                                v-model="newServiceForm.quantity"
                                class="form-control"
                                min="1"
                            />
                        </div>

                        <!-- Meta Information -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="ti tabler-info-circle me-1"></i>
                                Meta Information
                            </label>
                            <textarea
                                v-model="newServiceForm.meta"
                                class="form-control"
                                rows="2"
                                placeholder="Additional information, notes, etc. (optional)"
                            ></textarea>
                        </div>

                        <!-- Total Preview -->
                        <div class="alert alert-success mb-0" v-if="newServiceForm.price_per_unit > 0 && newServiceForm.quantity > 0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="ti tabler-calculator me-1"></i>
                                    <strong>Total amount:</strong>
                                </span>
                                <span class="fs-4 fw-bold">
                                    {{ formatCurrency(newServiceForm.price_per_unit * newServiceForm.quantity) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeModals">
                            <i class="ti tabler-x me-1"></i>
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" @click="addAndSaveService" :disabled="isSaving">
                            <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="ti tabler-device-floppy me-1"></i>
                            {{ isSaving ? 'Saving...' : 'Add Service' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.table-sm td, .table-sm th {
    padding: 12px 8px;
}

.modal.show {
    display: block;
}

.spinner-border {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

.alert-info {
    background-color: #e1f5fe;
    border-color: #b3e5fc;
    color: #0277bd;
}

.alert-success {
    background-color: #e8f5e9;
    border-color: #c8e6c9;
    color: #2e7d32;
}

.modal-header.bg-primary {
    background-color: #7367f0 !important;
}
</style>
