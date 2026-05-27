<script setup>
import { ref } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head } from '@inertiajs/vue3';
import { Link, usePage } from "@inertiajs/vue3";
import { useTrans } from '/resources/js/trans';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  bookings: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const bookingsList = ref(props.bookings.data);
const pagination = ref(props.bookings);

const statusClass = (status) => {
    return {
        paid: 'bg-label-success',
        partial: 'bg-label-warning',
        unpaid: 'bg-label-danger'
    }[status] || 'bg-label-secondary';
};

const resTypeStatusClass = (status) => {
    return {
        reservation: 'bg-label-success',
        occupied: 'bg-label-primary',
        checkout: 'bg-label-danger'
    }[status] || 'bg-label-secondary';
};


</script>

<template>
    <Head title="Bookings List" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Bookings List</h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" >
                <h5 class="mb-0">Bookings List</h5>
                <Link class="btn create-new btn-primary" tabindex="0" aria-controls="DataTables_Table_0" type="button"
                    :href="route('booking.create', { locale: currentLocale }) "
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block">Add New Booking</span>
                        </span>
                    </span>
                </Link>
            </div>
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Date</th>
                                <th>Booking Type</th>
                                <th>Room Number</th>
                                <th>Payment Status</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in bookingsList" :key="booking.id">
                                <td>{{booking.id}}</td>
                                <td>
                                    {{
                                        booking.main_guest?.name
                                            ? `${booking.main_guest.name} ${booking.main_guest.surname ?? ''}`
                                            : booking.contact_name
                                    }}
                                </td>
                                <td>
                                    {{
                                        booking.main_guest?.phone ?? booking.contact_phone
                                    }}
                                </td>
                                <td>{{booking.date_from}} - {{booking.date_to}}</td>

                                <td>{{booking.booking_type}}</td>
                                 <td>
                                    <span
                                        v-for="item in booking.booking_rooms"
                                        :key="item.id"
                                        class="badge bg-label-primary"
                                    >
                                        {{ item.room.number }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="statusClass(booking.payment_status)">
                                        {{ booking.payment_status }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge"  :class="resTypeStatusClass(booking.reservation_type.slug)">
                                        {{ booking.reservation_type.name }}
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                            <i class="icon-base ti tabler-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <Link class="dropdown-item waves-effect"
                                                :href="route('booking.edit', { locale: currentLocale, id: booking.id }) "
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i> Edit
                                            </Link>
                                            <Link class="dropdown-item"  :href="route('booking.add_guests', { locale: currentLocale, id: booking.id }) ">
                                                <i class="icon-base ti tabler-user-plus me-1"></i>Add Guests
                                            </Link>
                                            <Link class="dropdown-item text-success" v-if="booking.main_guest.name != null"
                                                :href="route('booking.complete.show', { locale: currentLocale, id: booking.id }) "
                                            >
                                                <i class="icon-base ti tabler-circle-check me-1"></i>Complete
                                            </Link>

                                            <!-- <Link class="dropdown-item" @click="addServices(booking)">
                                                <i class="icon-base ti tabler-gym-service me-1"></i>Services
                                            </Link> -->

                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <Pagination :links="pagination.meta.links" />
            </div>
        </div>
    </Index>
</template>
