<script setup>
import { computed } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import Index from "@/Layouts/Index.vue";

const props = defineProps({
    customer: { type: Object, required: true },
    selectedMembershipId: { type: [Number, String], required: true },
    calendar: { type: Object, required: true },
});

const page = usePage();
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? "hy");
const fullName = computed(() => `${props.customer.name ?? ""} ${props.customer.surname ?? ""}`.trim() || "-");
const memberships = computed(() => props.customer.memberships ?? []);
const calendarCells = computed(() => {
    const cells = [...Array(Number(props.calendar?.starts_on ?? 0)).fill(null), ...(props.calendar?.days ?? [])];
    while (cells.length % 7) cells.push(null);
    return cells;
});

const weekdays = ["Երկ", "Երք", "Չոր", "Հնգ", "Ուր", "Շբ", "Կիր"];
const planName = (membership) => {
    const plan = membership?.membership_plan;
    return (plan?.translations ?? []).find((item) => item.locale === currentLocale.value)?.name ?? plan?.name ?? "-";
};
const statusLabel = (status) => ({ waiting: "Սպասման մեջ", active: "Ակտիվ", frozen: "Սառեցված" })[status] ?? status ?? "-";
const statusClass = (status) => ({ waiting: "bg-label-warning", active: "bg-label-success", frozen: "bg-label-info" })[status] ?? "bg-label-secondary";
const formatDate = (value) => (value ? String(value).slice(0, 10) : "-");
const monthLabel = (value) => value ? new Intl.DateTimeFormat("hy-AM", { month: "long", year: "numeric" }).format(new Date(`${value}-01T12:00:00`)) : "-";
const formatHours = (minutes) => {
    const total = Number(minutes ?? 0);
    return total % 60 ? `${Math.floor(total / 60)}ժ ${total % 60}ր` : `${Math.floor(total / 60)}ժ`;
};
const membershipLabel = (membership) => `${planName(membership)} (${formatDate(membership.start_date)} — ${formatDate(membership.valid_at ?? membership.end_date)})`;
const monthWithOffset = (offset) => {
    const [year, month] = String(props.calendar.month).split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1 + offset, 1));
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}`;
};
const currentMonth = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
};
const loadCalendar = (month, membershipId = props.selectedMembershipId) => {
    router.get(route("trainer.my-customers.show", { locale: currentLocale.value, personId: props.customer.id }), { month, membership: membershipId }, { preserveScroll: true, preserveState: true, replace: true });
};
const changeMembership = (event) => loadCalendar(props.calendar.month, event.target.value);
</script>

<template>
    <Head :title="`${fullName} — գրաֆիկ`" />
    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-0">Հաճախորդի տվյալները</h2>
                <Link :href="route('trainer.my-customers', { locale: currentLocale })" class="btn btn-outline-secondary"><i class="icon-base ti tabler-arrow-left me-1"></i>Հետ</Link>
            </div>
        </template>

        <div class="card mb-4"><div class="card-body"><div class="row g-3">
            <div class="col-md-4"><div class="text-muted small">Անուն, ազգանուն</div><div class="fw-semibold">{{ fullName }}</div></div>
            <div class="col-md-4"><div class="text-muted small">Հեռախոս</div><div>{{ customer.phone || "-" }}</div></div>
            <div class="col-md-4"><div class="text-muted small">Էլ. հասցե</div><div>{{ customer.email || "-" }}</div></div>
            <div class="col-md-4"><div class="text-muted small">Ծննդյան ամսաթիվ</div><div>{{ formatDate(customer.birth_date) }}</div></div>
            <div class="col-md-4"><div class="text-muted small">Տեսակ</div><div>{{ customer.type || "-" }}</div></div>
        </div></div></div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Գործող աբոնեմենտներ</h5></div>
            <div class="table-responsive"><table class="table table-bordered align-middle mb-0">
                <thead><tr><th>Աբոնեմենտ</th><th>Սկիզբ</th><th>Ավարտ</th><th>Կարգավիճակ</th></tr></thead>
                <tbody><tr v-for="membership in memberships" :key="membership.id">
                    <td>{{ planName(membership) }}</td><td>{{ formatDate(membership.start_date) }}</td><td>{{ formatDate(membership.valid_at ?? membership.end_date) }}</td>
                    <td><span class="badge" :class="statusClass(membership.status)">{{ statusLabel(membership.status) }}</span></td>
                </tr></tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div><h5 class="mb-1">Ամսական գրաֆիկ և այցելություններ</h5><small class="text-muted">Գրաֆիկի ժամերը հաշվարկված են՝ առանց նշված ընդմիջման ժամերի</small></div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select class="form-select form-select-sm membership-select" :value="selectedMembershipId" @change="changeMembership"><option v-for="membership in memberships" :key="membership.id" :value="membership.id">{{ membershipLabel(membership) }}</option></select>
                    <div class="calendar-navigation d-flex gap-2 flex-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Նախորդ ամիս" @click="loadCalendar(monthWithOffset(-1))"><i class="icon-base ti tabler-chevron-left"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" @click="loadCalendar(currentMonth())">Այս ամիս</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Հաջորդ ամիս" @click="loadCalendar(monthWithOffset(1))"><i class="icon-base ti tabler-chevron-right"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0"><div class="calendar-scroll">
                <div class="calendar-month-label">{{ monthLabel(calendar.month) }}</div>
                <div class="month-calendar">
                    <div v-for="weekday in weekdays" :key="weekday" class="calendar-weekday">{{ weekday }}</div>
                    <div v-for="(day, index) in calendarCells" :key="day?.date ?? `empty-${index}`" class="calendar-day" :class="{ 'calendar-day--empty': !day, 'calendar-day--inactive': day && !day.is_membership_active, 'calendar-day--frozen': day?.is_frozen }">
                        <template v-if="day">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><span class="day-number">{{ day.day }}</span><span v-if="day.is_frozen" class="badge bg-label-info">Սառեցված</span></div>
                            <div v-if="!day.is_membership_active" class="calendar-note">Աբոնեմենտից դուրս</div>
                            <div v-else-if="day.is_frozen" class="calendar-note">Այս օրը սառեցված է</div>
                            <template v-else-if="day.slots?.length">
                                <div v-for="(slot, slotIndex) in day.slots" :key="`${day.date}-${slotIndex}`" class="schedule-slot">
                                    <div class="fw-semibold">{{ slot.start_time }} – {{ slot.end_time }}</div>
                                    <div v-if="slot.break_start_time && slot.break_end_time" class="schedule-break">Ընդմիջում՝ {{ slot.break_start_time }} – {{ slot.break_end_time }}</div>
                                </div>
                                <div class="work-total">{{ formatHours(day.work_minutes) }} գրաֆիկով</div>
                            </template>
                            <div v-else class="calendar-note">Գրաֆիկ չկա</div>
                            <div v-if="day.attendances?.length" class="attendance-list"><span v-for="attendance in day.attendances" :key="attendance.id" class="attendance-badge" :class="attendance.direction === 'entry' ? 'attendance-entry' : 'attendance-exit'">{{ attendance.direction === 'entry' ? 'Մուտք' : 'Ելք' }}՝ {{ attendance.time }}</span></div>
                        </template>
                    </div>
                </div>
            </div></div>
        </div>
    </Index>
</template>

<style scoped>
.membership-select { max-width: 330px; } .calendar-navigation { white-space: nowrap; } .calendar-scroll { overflow-x: auto; padding: 1.25rem; } .calendar-month-label { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; text-transform: capitalize; }
.month-calendar { display: grid; grid-template-columns: repeat(7, minmax(155px, 1fr)); min-width: 1085px; border: 1px solid var(--bs-border-color); border-radius: .5rem; overflow: hidden; }
.calendar-weekday { background: var(--bs-light); border-bottom: 1px solid var(--bs-border-color); font-size: .8rem; font-weight: 600; padding: .7rem; text-align: center; }
.calendar-day { border-right: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); min-height: 185px; padding: .7rem; } .calendar-day:nth-child(7n) { border-right: 0; }
.calendar-day--empty, .calendar-day--inactive { background: rgba(var(--bs-secondary-rgb), .035); } .calendar-day--frozen { background: rgba(var(--bs-info-rgb), .07); } .day-number { font-weight: 700; }
.calendar-note { color: var(--bs-secondary-color); font-size: .78rem; padding-top: .4rem; } .schedule-slot { background: rgba(var(--bs-primary-rgb), .07); border-left: 3px solid var(--bs-primary); border-radius: .25rem; font-size: .78rem; margin-bottom: .35rem; padding: .35rem .45rem; }
.schedule-break { color: var(--bs-secondary-color); font-size: .7rem; margin-top: .2rem; } .work-total { color: var(--bs-primary); font-size: .74rem; font-weight: 600; margin-top: .35rem; }
.attendance-list { border-top: 1px dashed var(--bs-border-color); display: grid; gap: .25rem; margin-top: .65rem; padding-top: .55rem; } .attendance-badge { border-radius: .25rem; font-size: .72rem; font-weight: 600; padding: .25rem .35rem; text-align: center; }
.attendance-entry { background: rgba(var(--bs-success-rgb), .12); color: var(--bs-success); } .attendance-exit { background: rgba(var(--bs-danger-rgb), .1); color: var(--bs-danger); }
@media (max-width: 576px) { .membership-select { max-width: 100%; width: 100%; } }
</style>
