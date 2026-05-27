<script setup>
import { ref, computed, onMounted } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import GuestForm from '@/Components/GuestForm.vue';
import DocumentsUploader from '@/Components/DocumentsUploader.vue';
import { useAlert } from '@/composables/useAlert';
import { useConfirm } from '@/composables/useConfirm';

const page = usePage();
const currentLocale = page.props.locale ?? "en";
const alert = useAlert();
const { confirm } = useConfirm();

const props = defineProps({
    booking: Object,
    countries: Array,
});

const booking = ref(props.booking);
const selectedRoom = ref(null);
const isLoading = ref(false);
const selectedGuestForDocs = ref(null);
const showDocumentsModal = ref(false);

// Данные для формы гостя
const guestFormData = ref({
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
    active: 1,
    gender: 'male'
});

// Флаг для отметки "главный гость" при добавлении
const markAsMainGuest = ref(false);

// Валидация
const validateGuest = () => {
    const requiredFields = [
        { key: 'name', label: 'Name' },
        { key: 'surname', label: 'Surname' },
        { key: 'phone', label: 'Phone' },
        { key: 'passport_number', label: 'Passport number' },
        { key: 'birth_date', label: 'Birth date' },
        { key: 'passport_expire_at', label: 'Passport expire date' },
        { key: 'country_id', label: 'Country' },
        { key: 'nationality', label: 'Nationality' }
    ];

    const emptyFields = requiredFields
        .filter(field => !guestFormData.value[field.key])
        .map(field => field.label);

    if (emptyFields.length) {
        alert.error(`Please fill: ${emptyFields.join(', ')}`);
        return false;
    }

    return true;
};

// Гости по комнатам
const roomGuests = ref({});

// Загрузка существующих гостей
const loadExistingGuests = () => {
    if (!booking.value.guests || booking.value.guests.length === 0) {
        return;
    }

    booking.value.guests.forEach(guest => {
        const roomId = guest.pivot?.room_id || guest.room_id;

        if (!roomId) {
            console.warn('Guest without room_id:', guest);
            return;
        }

        if (!roomGuests.value[roomId]) {
            roomGuests.value[roomId] = [];
        }

        roomGuests.value[roomId].push({
            id: guest.id,
            name: guest.name,
            surname: guest.surname,
            phone: guest.phone,
            email: guest.email || '',
            passport_number: guest.passport_number || '—',
            passport_expire_at: guest.passport_expire_at,
            nationality: guest.nationality,
            birth_date: guest.birth_date,
            city: guest.city,
            country_id: guest.country_id,
            active: guest.active,
            gender: guest.gender,
            is_main: guest.pivot?.is_main === 1 || guest.is_main === true,
            age: guest.pivot?.age,
            type: guest.pivot?.type
        });
    });
};

// Инициализация
onMounted(() => {
    // Инициализируем пустые массивы для всех комнат
    if (booking.value.booking_rooms?.length) {
        booking.value.booking_rooms.forEach(room => {
            if (!roomGuests.value[room.room_id]) {
                roomGuests.value[room.room_id] = [];
            }
        });
    }

    // Загружаем существующих гостей
    loadExistingGuests();

    // НЕ ДОБАВЛЯЕМ main_guest из booking автоматически!
    // Теперь main_guest будет добавляться только через форму вручную

    // Если selectedRoom все еще null, выбираем первую комнату
    if (!selectedRoom.value && roomsList.value.length > 0) {
        selectedRoom.value = roomsList.value[0].id;
    }
});

// Комнаты для выбора
const roomsList = computed(() => {
    return booking.value.booking_rooms?.map(room => ({
        id: room.room_id,
        number: room.room.number,
        maxGuests: room.room.max_guests,
        currentGuests: roomGuests.value[room.room_id]?.length || 0
    })) || [];
});

// Текущая комната
const currentRoom = computed(() => {
    return roomsList.value.find(r => r.id === selectedRoom.value);
});

// Можно ли добавить гостя
const canAddGuest = computed(() => {
    if (!currentRoom.value) return false;
    return currentRoom.value.currentGuests < currentRoom.value.maxGuests;
});

// Свободные места
const freeSpots = computed(() => {
    if (!currentRoom.value) return 0;
    return currentRoom.value.maxGuests - currentRoom.value.currentGuests;
});

// Есть ли уже главный гость
const hasMainGuest = computed(() => {
    for (const guests of Object.values(roomGuests.value)) {
        if (guests.some(g => g.is_main)) {
            return true;
        }
    }
    return false;
});

// Добавление гостя в локальное состояние
const addGuest = () => {
    if (!selectedRoom.value) {
        alert.error('Please select a room first');
        return;
    }

    if (!canAddGuest.value) {
        alert.warning(`Room ${currentRoom.value.number} is full (max ${currentRoom.value.maxGuests} guests)`);
        return;
    }

    if (!validateGuest()) return;

    // Если пытаемся добавить главного гостя, но он уже существует
    if (markAsMainGuest.value && hasMainGuest.value) {
        alert.warning('Main guest already exists! Only one main guest allowed.');
        return;
    }

    const newGuest = {
        ...guestFormData.value,
        id: guestFormData.value.id ?? null,
        is_main: markAsMainGuest.value
    };

    if (!roomGuests.value[selectedRoom.value]) {
        roomGuests.value[selectedRoom.value] = [];
    }

    roomGuests.value[selectedRoom.value].push(newGuest);

    const mainGuestText = markAsMainGuest.value ? ' (Main Guest)' : '';
    alert.success(`Guest ${newGuest.name} ${newGuest.surname}${mainGuestText} added successfully`);

    resetForm();
};

// Удаление гостя
const removeGuestFromBooking = async (roomId, index) => {
    const guest = roomGuests.value[roomId][index];

    if (guest.is_main) {
        const confirmed = await confirm(`This is the MAIN GUEST. Removing them will require setting another main guest. Continue?`);
        if (!confirmed) return;
    } else {
        const confirmed = await confirm(`Remove ${guest.name} ${guest.surname} from this booking?`);
        if (!confirmed) return;
    }

    try {
        let response;

        if (guest.id) {
            const url = route('booking.remove_guest', {
                locale: currentLocale,
                id: booking.value.id,
                guestId: guest.id
            });

            response = await axios.delete(url, {
                data: {
                    room_id: roomId
                }
            });
        } else {
            response = { data: { success: true } };
        }

        if (response.data.success) {
            roomGuests.value[roomId].splice(index, 1);
            alert.success(`Guest ${guest.name} ${guest.surname} removed from booking`);
        }
    } catch (error) {
        console.error('Error removing guest:', error);
        alert.error(error.response?.data?.message || 'Failed to remove guest');
    }
};

// Сброс формы
const resetForm = () => {
    guestFormData.value = {
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
        active: 1,
        gender: 'male'
    };
    markAsMainGuest.value = false;
};

// Перемещение main guest
const moveMainGuest = (roomId) => {
    let mainGuest = null;
    let oldRoomId = null;

    for (const [rId, guests] of Object.entries(roomGuests.value)) {
        const guest = guests.find(g => g.is_main);
        if (guest) {
            mainGuest = guest;
            oldRoomId = parseInt(rId);
            break;
        }
    }

    if (mainGuest && oldRoomId !== roomId) {
        roomGuests.value[oldRoomId] = roomGuests.value[oldRoomId].filter(g => !g.is_main);

        if (!roomGuests.value[roomId]) {
            roomGuests.value[roomId] = [];
        }
        roomGuests.value[roomId].unshift(mainGuest);

        alert.success(`Main guest moved to Room ${roomsList.value.find(r => r.id === roomId)?.number}`);
    } else if (!mainGuest) {
        alert.warning('No main guest to move. Add a main guest first.');
    }
};

// СОХРАНЕНИЕ ВСЕХ ГОСТЕЙ (ВКЛЮЧАЯ НОВЫХ)
const saveGuests = async () => {
    // Проверяем, есть ли главный гость
    if (!hasMainGuest.value) {
        alert.warning('Please add a main guest before saving!');
        return;
    }

    const guestsToSave = [];

    // Собираем всех гостей из всех комнат
    for (const [roomId, guests] of Object.entries(roomGuests.value)) {
        for (const guest of guests) {
            const guestObject = {
                name: guest.name,
                surname: guest.surname,
                phone: guest.phone,
                email: guest.email || '',
                passport_number: guest.passport_number,
                passport_expire_at: guest.passport_expire_at,
                nationality: guest.nationality,
                birth_date: guest.birth_date,
                city: guest.city,
                country_id: guest.country_id,
                active: guest.active,
                gender: guest.gender
            };

            if (guest.id) {
                guestObject.id = guest.id;
            }

            const guestData = {
                room_id: parseInt(roomId),
                guest: guestObject,
                is_main: guest.is_main || false
            };

            guestsToSave.push(guestData);
        }
    }

    if (guestsToSave.length === 0) {
        alert.warning('No guests to save');
        return;
    }

    console.log('Saving guests:', guestsToSave);

    isLoading.value = true;

    try {
        const response = await axios.post(route('booking.store_guests', {
            locale: currentLocale,
            id: booking.value.id
        }), { guests: guestsToSave });

        console.log('Save response:', response.data);
        alert.success('Guests saved successfully!');

        // Обновляем ID для новых гостей из ответа
        if (response.data.guests && response.data.guests.length) {
            let guestIndex = 0;
            for (const [roomId, guests] of Object.entries(roomGuests.value)) {
                for (let i = 0; i < guests.length; i++) {
                    const savedGuest = response.data.guests[guestIndex];
                    if (savedGuest && !guests[i].id) {
                        guests[i].id = savedGuest.id;
                    }
                    guestIndex++;
                }
            }
        }

        isLoading.value = false;

        setTimeout(() => {
            window.location.reload();
        }, 1500);

    } catch (error) {
        console.error('Error saving guests:', error);
        alert.error(error.response?.data?.message || 'Failed to save guests');
        isLoading.value = false;
    }
};

// Общее количество гостей
const totalGuests = computed(() => {
    return Object.values(roomGuests.value).reduce((sum, guests) => sum + guests.length, 0);
});

// Количество несохраненных гостей
const unsavedGuestsCount = computed(() => {
    return Object.values(roomGuests.value)
        .flat()
        .filter(guest => !guest.id).length;
});

// Получить комнату где находится main guest
const mainGuestRoom = computed(() => {
    for (const [roomId, guests] of Object.entries(roomGuests.value)) {
        if (guests.find(g => g.is_main)) {
            return parseInt(roomId);
        }
    }
    return null;
});

// Открыть модалку загрузки документов
const openDocumentsUploader = (guest) => {
    if (!guest.id) {
        alert.warning('Please save the guest first before uploading documents');
        return;
    }
    selectedGuestForDocs.value = guest;
    showDocumentsModal.value = true;
};

// Закрыть модалку
const closeDocumentsModal = () => {
    showDocumentsModal.value = false;
    selectedGuestForDocs.value = null;
};
</script>

<template>
    <Head title="Add Guests" />

    <Index>
        <div class="card">
            <!-- Header -->
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">
                        <i class="ti tabler-user-plus me-2"></i>
                        Add Guests to Booking #{{ booking.id }}
                    </h5>
                    <small class="text-muted">
                        Contact: {{ booking.contact_name || 'Not provided' }}
                        ({{ booking.contact_phone || 'No phone' }})
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                        <i class="icon-base ti tabler-door"></i> {{ roomsList.length }} Rooms
                    </span>
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="icon-base ti tabler-user"></i>
                        {{ totalGuests }} Guests
                    </span>
                    <span v-if="unsavedGuestsCount > 0" class="badge bg-warning fs-6 px-3 py-2">
                        <i class="icon-base ti tabler-alert"></i>
                        {{ unsavedGuestsCount }} Unsaved
                    </span>
                </div>
            </div>

            <div class="card-body">
                <!-- Инструкция -->
                <div class="alert alert-info mb-4">
                    <div class="d-flex align-items-start">
                        <i class="ti tabler-info-circle fs-4 me-2"></i>
                        <div>
                            <strong>How to add guests:</strong>
                            <ol class="mb-0 mt-1">
                                <li>Select a room</li>
                                <li>Fill guest information</li>
                                <li><strong>Check "Mark as Main Guest"</strong> for ONE guest (required)</li>
                                <li>Click "Add to Room"</li>
                                <li>Add all other guests (without main guest flag)</li>
                                <li>Click "Save All Guests" to save them to database</li>
                                <li>After page reloads, click "Documents" button to upload files for each guest</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Main Guest Warning -->
                <div v-if="!hasMainGuest" class="alert alert-warning mb-4">
                    <i class="ti tabler-alert-circle me-2"></i>
                    <strong>No main guest assigned!</strong> Please add a main guest before saving.
                </div>

                <!-- Main Guest Room Selector -->
                <div class="alert alert-primary mb-4">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ti tabler-star fs-4"></i>
                            <strong>Main Guest Location:</strong>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button
                                v-for="room in roomsList"
                                :key="room.id"
                                type="button"
                                class="btn btn-sm"
                                :class="mainGuestRoom === room.id ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="moveMainGuest(room.id)"
                                :disabled="!hasMainGuest"
                            >
                                <i class="ti tabler-door me-1"></i> Room {{ room.number }}
                                <span v-if="mainGuestRoom === room.id" class="ms-1">⭐</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Room Selector -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Select Room to Add Guests</label>
                    <div class="d-flex gap-3 flex-wrap">
                        <button
                            v-for="room in roomsList"
                            :key="room.id"
                            type="button"
                            class="btn"
                            :class="selectedRoom === room.id ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="selectedRoom = room.id"
                        >
                            <i class="ti tabler-door me-1"></i> Room {{ room.number }}
                            <span class="badge ms-2 " :class="selectedRoom === room.id ? 'bg-light text-dark' : 'bg-secondary text-white'">
                                {{ room.currentGuests }}/{{ room.maxGuests }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row g-4">
                    <!-- Add Guest Form -->
                    <div class="col-lg-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="ti tabler-user-plus me-1"></i>
                                    Add Guest to {{ selectedRoom ? `Room ${currentRoom?.number}` : 'Room' }}
                                </h6>
                            </div>
                            <div class="card-body">
                                <div v-if="!selectedRoom" class="text-center py-5 text-muted">
                                    <i class="ti tabler-door fs-1"></i>
                                    <p class="mt-2">Select a room to add guests</p>
                                </div>

                                <div v-else-if="!canAddGuest" class="text-center py-5 text-warning">
                                    <i class="ti tabler-alert-circle fs-1"></i>
                                    <p class="mt-2">Room {{ currentRoom?.number }} is full (max {{ currentRoom?.maxGuests }} guests)</p>
                                </div>

                                <div v-else>
                                    <GuestForm
                                        v-model="guestFormData"
                                        :countries="countries"
                                        :errors="{}"
                                        :is-edit="false"
                                    />

                                    <!-- Checkbox для отметки главного гостя -->
                                    <div class="form-check mt-3 mb-3">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="markAsMainGuest"
                                            v-model="markAsMainGuest"
                                            :disabled="hasMainGuest"
                                        >
                                        <label class="form-check-label" for="markAsMainGuest">
                                            <i class="ti tabler-star"></i>
                                            Mark as Main Guest
                                            <small v-if="hasMainGuest" class="text-muted d-block">
                                                (Main guest already exists)
                                            </small>
                                        </label>
                                    </div>

                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-success flex-grow-1" @click="addGuest">
                                            <i class="ti tabler-plus me-1"></i>
                                            Add to Room {{ currentRoom?.number }}
                                        </button>
                                        <button class="btn btn-secondary" @click="resetForm">
                                            <i class="ti tabler-x me-1"></i>
                                            Reset
                                        </button>
                                    </div>

                                    <div class="mt-3 small text-muted text-center">
                                        <i class="ti tabler-info-circle"></i>
                                        Free spots: {{ freeSpots }} / {{ currentRoom?.maxGuests }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Guests List -->
                    <div class="col-lg-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="ti tabler-users me-1"></i>
                                        Guests in {{ selectedRoom ? `Room ${currentRoom?.number}` : 'Room' }}
                                    </span>
                                    <span class="badge bg-primary">{{ roomGuests[selectedRoom]?.length || 0 }} guests</span>
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div v-if="!selectedRoom" class="text-center py-5 text-muted">
                                    <i class="ti tabler-door fs-1"></i>
                                    <p class="mt-2">Select a room to view guests</p>
                                </div>
                                <div v-else-if="!roomGuests[selectedRoom]?.length" class="text-center py-5 text-muted">
                                    <i class="ti tabler-user-off fs-1"></i>
                                    <p class="mt-2">No guests in this room yet</p>
                                </div>
                                <div v-else class="list-group list-group-flush">
                                    <div
                                        v-for="(guest, idx) in roomGuests[selectedRoom]"
                                        :key="guest.id || idx"
                                        class="list-group-item"
                                    >
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div>
                                                    <strong>{{ guest.name }} {{ guest.surname }}</strong>
                                                    <span v-if="guest.is_main" class="badge bg-warning ms-2">
                                                        <i class="ti tabler-star me-1"></i>Main Guest
                                                    </span>
                                                    <span v-if="!guest.id" class="badge bg-secondary ms-2">
                                                        <i class="ti tabler-clock me-1"></i>Not saved
                                                    </span>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <div><i class="ti tabler-id me-1"></i> Passport: {{ guest.passport_number || '—' }}</div>
                                                    <div><i class="ti tabler-phone me-1"></i> Phone: {{ guest.phone || '—' }}</div>
                                                    <div v-if="guest.email"><i class="ti tabler-mail me-1"></i> Email: {{ guest.email }}</div>
                                                </div>

                                                <button
                                                    v-if="guest.id"
                                                    class="btn btn-sm btn-outline-info mt-2"
                                                    @click="openDocumentsUploader(guest)"
                                                >
                                                    <i class="ti tabler-file me-1"></i>
                                                    Documents
                                                </button>
                                                <small v-else class="text-muted d-block mt-2">
                                                    <i class="ti tabler-info-circle"></i>
                                                    Save all guests first to upload documents
                                                </small>
                                            </div>

                                            <button
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeGuestFromBooking(selectedRoom, idx)"
                                            >
                                                <i class="ti tabler-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary & Actions -->
                <div class="mt-4 p-3 bg-light rounded">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex gap-3 flex-wrap">
                                <div>
                                    <strong><i class="icon-base ti tabler-chart-bar me-1"></i>Total Guests</strong>
                                    <div class="small text-muted">{{ totalGuests }} guests assigned</div>
                                </div>
                                <div>
                                    <strong><i class="icon-base ti tabler-building-community me-1"></i> Rooms</strong>
                                    <div class="small text-muted">
                                        <span v-for="room in roomsList" :key="room.id" class="me-2">
                                            Room {{ room.number }}: {{ roomGuests[room.id]?.length || 0 }}/{{ room.maxGuests }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="unsavedGuestsCount > 0">
                                    <strong><i class="icon-base ti tabler-alert me-1"></i> Unsaved</strong>
                                    <div class="small text-warning">{{ unsavedGuestsCount }} guests need to be saved</div>
                                </div>
                                <div v-if="!hasMainGuest">
                                    <strong><i class="icon-base ti tabler-star me-1"></i> Main Guest</strong>
                                    <div class="small text-danger">No main guest assigned!</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-secondary me-2" @click="window.history.back()">
                                <i class="ti tabler-arrow-left me-1"></i>
                                Cancel
                            </button>
                            <button class="btn btn-primary" @click="saveGuests" :disabled="isLoading || !hasMainGuest">
                                <i v-if="isLoading" class="ti tabler-loader-2 spin me-1"></i>
                                <i v-else class="ti tabler-device-floppy me-1"></i>
                                Save All Guests ({{ unsavedGuestsCount }} new)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Documents Uploader -->
        <div class="modal fade" :class="{ show: showDocumentsModal }" tabindex="-1" :style="{ display: showDocumentsModal ? 'block' : 'none' }">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ti tabler-file me-2"></i>
                            Documents for {{ selectedGuestForDocs?.name }} {{ selectedGuestForDocs?.surname }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeDocumentsModal"></button>
                    </div>
                    <div class="modal-body">
                        <DocumentsUploader
                            v-if="selectedGuestForDocs?.id"
                            :ownerType="'guest'"
                            :ownerId="selectedGuestForDocs.id"
                        />
                        <div v-else class="text-center py-5">
                            <i class="ti tabler-alert-circle fs-1 text-warning"></i>
                            <p class="mt-2">Guest not saved yet. Please save guests first.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeDocumentsModal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backdrop -->
        <div v-if="showDocumentsModal" class="modal-backdrop fade show" @click="closeDocumentsModal"></div>
    </Index>
</template>

<style scoped>
.row {
  display: flex;
  flex-wrap: wrap;
}
.col-xl-8, .col-xl-4 {
  display: flex;
  flex-direction: column;
}
.card {
  height: 100%; /* Растянуть карточки на всю высоту колонки */
}
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.modal.show {
    display: block;
    background-color: rgba(0,0,0,0.5);
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1040;
}

.list-group-item {
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>
