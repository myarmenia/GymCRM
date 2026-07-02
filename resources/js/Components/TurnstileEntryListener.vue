<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const page = usePage();
const toast = useToast();

const currentLocale = computed(
    () => page.props.locale ?? page.props.lang ?? "hy",
);

const clientId = computed(
    () =>
        page.props.client_id ??
        page.props.clientId ??
        page.props.auth?.user?.client_id ??
        page.props.auth?.user?.gym_id ??
        page.props.auth?.client_id ??
        null,
);

const getRoleName = (role) => {
    if (!role) {
        return null;
    }

    if (typeof role === "string") {
        return role;
    }

    return role.name ?? role.role_name ?? null;
};

const userRoles = computed(() => {
    const roles =
        page.props.auth?.user?.roles ??
        page.props.auth?.roles ??
        page.props.user?.roles ??
        [];

    if (!Array.isArray(roles)) {
        return [];
    }

    return roles.map(getRoleName).filter(Boolean);
});

const userRole = computed(
    () =>
        getRoleName(page.props.auth?.user?.role) ??
        page.props.auth?.user?.role_name ??
        page.props.auth?.role_name ??
        getRoleName(page.props.auth?.role) ??
        getRoleName(page.props.user?.role) ??
        null,
);

const isManager = computed(
    () => userRole.value === "manager" || userRoles.value.includes("manager"),
);

const showEntryModal = ref(false);
const entryData = ref(null);
const activatingMembershipId = ref(null);

let subscribedChannelName = null;

const isDenied = computed(() => {
    return (
        entryData.value?.status === "denied" ||
        entryData.value?.access_allowed === false
    );
});

const modalTitle = computed(() => {
    if (isDenied.value) {
        return "Մուտքը մերժված է";
    }

    return entryData.value?.action === "exit"
        ? "Ելքը գրանցված է"
        : "Մուտքը թույլատրված է";
});

const actionLabel = computed(() => {
    if (entryData.value?.action === "exit") {
        return "Ելք";
    }

    if (entryData.value?.action === "entry") {
        return "Մուտք";
    }

    return "Անհայտ";
});

const reasonLabel = computed(() => {
    if (
        ["subscription_expired", "no_active_subscription"].includes(
            entryData.value?.reason,
        )
    ) {
        return "Մուտքը մերժված է. աբոնեմենտի ժամկետը լրացել է կամ գործող աբոնեմենտ չկա";
    }

    if (entryData.value?.reason === "invalid_entry_code") {
        return "Մուտքը մերժված է. մուտքի կոդը չի գտնվել";
    }

    return entryData.value?.message ?? "Մուտքը մերժված է";
});

const currentOwner = computed(() => {
    return (
        entryData.value?.person ??
        entryData.value?.user ??
        entryData.value?.owner ??
        null
    );
});

const membershipSelectionContext = computed(() => {
    return entryData.value?.membership_activation_context ?? null;
});

const activeMemberships = computed(() => {
    return membershipSelectionContext.value?.active_memberships ?? [];
});

const waitingMemberships = computed(() => {
    return membershipSelectionContext.value?.waiting_memberships ?? [];
});

const selectableMemberships = computed(() => {
    return membershipSelectionContext.value?.selectable_memberships ?? [];
});

const requiresManagerSelection = computed(() => {
    return Boolean(
        entryData.value?.action === "entry" &&
        membershipSelectionContext.value?.requires_manager_selection &&
            selectableMemberships.value.length > 1,
    );
});

const formatMembershipPeriod = (membership) => {
    const startDate = membership?.start_date ?? "-";
    const endDate = membership?.valid_at ?? membership?.end_date ?? "-";

    return `${startDate} - ${endDate}`;
};

const formatBirthDate = (value) => {
    return value ? String(value).slice(0, 10) : "-";
};

const statusLabel = (status) => {
    if (status === "active") {
        return "active";
    }

    if (status === "waiting") {
        return "waiting";
    }

    return status ?? "-";
};

const closeModal = () => {
    showEntryModal.value = false;
    entryData.value = null;
    activatingMembershipId.value = null;
};

const selectMembership = async (membership) => {
    if (!membership?.id || activatingMembershipId.value) {
        return;
    }

    if (!["waiting", "active"].includes(membership.status)) {
        return;
    }

    activatingMembershipId.value = membership.id;

    try {
        const response = await axios.post(
            route("membership_sale.activate_waiting", {
                locale: currentLocale.value,
                id: membership.id,
            }),
            {
                action: entryData.value?.action,
                detected_at: entryData.value?.detected_at ?? entryData.value?.date,
                entry_code: entryData.value?.entry_code,
                scan_type: entryData.value?.scan_type,
                online: entryData.value?.online,
                local_ip: entryData.value?.local_ip,
                mac: entryData.value?.mac,
            },
        );

        const activatedMembership = response.data?.membership;
        const attendanceId = response.data?.attendance_id;

        if (!activatedMembership || !entryData.value?.membership_activation_context) {
            return;
        }

        const updatedSelectableMemberships = selectableMemberships.value.map(
            (item) => (item.id === membership.id ? activatedMembership : item),
        );

        const updatedWaitingMemberships = waitingMemberships.value.filter(
            (item) => item.id !== membership.id,
        );

        const updatedActiveMemberships = [
            activatedMembership,
            ...activeMemberships.value.filter((item) => item.id !== membership.id),
        ];

        entryData.value = {
            ...entryData.value,
            attendance_id: attendanceId ?? entryData.value?.attendance_id,
            membership_activation_context: {
                ...entryData.value.membership_activation_context,
                active_memberships: updatedActiveMemberships,
                waiting_memberships: updatedWaitingMemberships,
                selectable_memberships: updatedSelectableMemberships,
                requires_manager_selection: false,
            },
        };

        toast.success("Մուտքը ֆիքսվեց ընտրված աբոնեմենտի համար");
        closeModal();
    } catch (error) {
        toast.error(
            error?.response?.data?.message ??
                "Չհաջողվեց ակտիվացնել ընտրված աբոնեմենտը",
        );
    } finally {
        activatingMembershipId.value = null;
    }
};

const leaveCurrentChannel = () => {
    if (!window.Echo || !subscribedChannelName) {
        return;
    }

    window.Echo.leave(`private-${subscribedChannelName}`);
    subscribedChannelName = null;
};

const subscribeToTurnstileChannel = () => {
    if (!isManager.value) {
        leaveCurrentChannel();
        closeModal();
        return;
    }

    const currentClientId = clientId.value;

    if (!currentClientId || !window.Echo) {
        return;
    }

    const channelName = `turnstile.${currentClientId}`;

    if (subscribedChannelName === channelName) {
        return;
    }

    leaveCurrentChannel();
    subscribedChannelName = channelName;

    window.Echo.private(channelName).listen(".entry.detected", (event) => {
        if (!isManager.value) {
            return;
        }

        entryData.value = event;
        showEntryModal.value = true;
    });
};

onMounted(() => {
    subscribeToTurnstileChannel();
});

watch([clientId, isManager], () => {
    subscribeToTurnstileChannel();
});

onBeforeUnmount(() => {
    leaveCurrentChannel();
});
</script>

<template>
    <div
        v-if="isManager && showEntryModal"
        class="modal fade show d-block"
        tabindex="-1"
        style="background: rgba(0, 0, 0, 0.5)"
    >
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ modalTitle }}
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        @click="closeModal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div v-if="entryData">
                        <div
                            class="alert mb-3"
                            :class="
                                isDenied ? 'alert-danger' : 'alert-success'
                            "
                        >
                            <div class="fw-semibold">
                                {{
                                    isDenied
                                        ? reasonLabel
                                        : entryData.message || "Մուտքը թույլատրված է"
                                }}
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img
                                v-if="currentOwner?.image"
                                :src="`/storage/${currentOwner.image}`"
                                width="70"
                                height="70"
                                class="rounded-circle object-fit-cover"
                                alt="Person"
                            />

                            <div>
                                <h5 class="mb-1">
                                    {{ currentOwner?.name }}
                                    {{ currentOwner?.surname }}
                                </h5>

                                <div class="text-muted">
                                    {{ currentOwner?.phone || currentOwner?.email }}
                                </div>
                                <div class="text-muted small">
                                    Ծննդյան ամսաթիվ: {{ formatBirthDate(currentOwner?.birth_date) }}
                                </div>
                            </div>
                        </div>

                        <p class="mb-1">
                            <strong>Entry Code:</strong>
                            {{ entryData.entry_code }}
                        </p>

                        <p class="mb-1">
                            <strong>Կարգավիճակ:</strong>
                            <span
                                class="badge"
                                :class="
                                    isDenied ? 'bg-label-danger' : 'bg-label-success'
                                "
                            >
                                {{ isDenied ? "Մերժված" : "Թույլատրված" }}
                            </span>
                        </p>

                        <p class="mb-1">
                            <strong>Գործողություն:</strong>
                            {{ actionLabel }}
                        </p>

                        <p class="mb-1">
                            <strong>Ամսաթիվ:</strong>
                            {{ entryData.detected_at || entryData.date }}
                        </p>

                        <p class="mb-0">
                            <strong>Տեսակ:</strong>
                            {{ entryData.owner_type || currentOwner?.type }}
                        </p>

                        <div
                            v-if="requiresManagerSelection"
                            class="alert alert-warning mt-4 mb-0"
                        >
                            <div class="fw-semibold mb-3">
                                Այս անձի մոտ կա մեկից ավելի գործող աբոնեմենտ։ Ընտրեք, թե որ աբոնեմենտի սահմաններում է մուտքը գրանցվել։
                            </div>

                            <div
                                v-if="activeMemberships.length"
                                class="mb-3"
                            >
                                <div class="small text-muted mb-2">
                                    Արդեն active աբոնեմենտներ
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <div
                                        v-for="membership in activeMemberships"
                                        :key="`active-${membership.id}`"
                                        class="border rounded p-2 bg-white"
                                    >
                                        <div class="fw-semibold">
                                            {{ membership.membership_plan_name }}
                                        </div>
                                        <div class="small text-muted">
                                            Category: {{ membership.membership_category_name || "-" }}
                                        </div>
                                        <div class="small text-muted">
                                            {{ formatMembershipPeriod(membership) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="small text-muted mb-2">
                                    Ընտրության համար հասանելի աբոնեմենտներ
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <div
                                        v-for="membership in selectableMemberships"
                                        :key="`selectable-${membership.id}`"
                                        class="border rounded p-3 bg-white"
                                    >
                                        <div
                                            class="d-flex justify-content-between align-items-start gap-3 flex-wrap"
                                        >
                                            <div>
                                                <div class="fw-semibold">
                                                    {{ membership.membership_plan_name }}
                                                </div>
                                                <div class="small mb-1">
                                                    <span
                                                        class="badge"
                                                        :class="
                                                            membership.status === 'active'
                                                                ? 'bg-label-success'
                                                                : 'bg-label-info'
                                                        "
                                                    >
                                                        {{ statusLabel(membership.status) }}
                                                    </span>
                                                </div>
                                                <div class="small text-muted">
                                                    Category: {{ membership.membership_category_name || "-" }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ formatMembershipPeriod(membership) }}
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                class="btn btn-sm"
                                                :class="
                                                    membership.status === 'active'
                                                        ? 'btn-outline-success'
                                                        : 'btn-primary'
                                                "
                                                :disabled="activatingMembershipId === membership.id"
                                                @click="selectMembership(membership)"
                                            >
                                                {{
                                                    activatingMembershipId === membership.id
                                                        ? "Ակտիվացվում է..."
                                                        : membership.status === 'active'
                                                          ? "Ընտրել"
                                                          : "Սարքել active և ընտրել"
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="closeModal">
                        Փակել
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
