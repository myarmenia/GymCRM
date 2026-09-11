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
const selectedMembershipIds = ref([]);
const entryQueue = ref([]);

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
        return "Մուտքը մերժված է. մուտքի կոդը չի գտնվել կամ տվյալ կոդով այցելու չի գտնվել";
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

const recordedMemberships = computed(() => {
    const memberships = entryData.value?.selected_memberships;

    if (Array.isArray(memberships) && memberships.length) {
        return memberships;
    }

    return entryData.value?.selected_membership
        ? [entryData.value.selected_membership]
        : [];
});

const requiresManagerSelection = computed(() => {
    return Boolean(
        entryData.value?.action === "entry" &&
        membershipSelectionContext.value?.requires_manager_selection &&
            selectableMemberships.value.length > 2,
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

const pendingEntries = computed(() => {
    return [entryData.value, ...entryQueue.value].filter(Boolean);
});

const entryOwner = (entry) => {
    return entry?.person ?? entry?.user ?? entry?.owner ?? null;
};

const entryOwnerName = (entry) => {
    const owner = entryOwner(entry);
    return [owner?.name, owner?.surname].filter(Boolean).join(" ") || "-";
};

const entryActionLabel = (entry) => {
    return entry?.action === "exit" ? "Ելք" : "Մուտք";
};

const selectPendingEntry = (entry) => {
    if (!entry || entry === entryData.value) {
        return;
    }

    const entryIndex = entryQueue.value.indexOf(entry);
    if (entryIndex === -1) {
        return;
    }

    const currentEntry = entryData.value;
    entryQueue.value.splice(entryIndex, 1, currentEntry);
    showNextEntry(entry);
};

const showNextEntry = (event) => {
    entryData.value = event;
    activatingMembershipId.value = null;
    selectedMembershipIds.value = [];
    showEntryModal.value = true;
};

const closeModal = () => {
    const nextEntry = entryQueue.value.shift();

    if (nextEntry) {
        showNextEntry(nextEntry);
        return;
    }

    showEntryModal.value = false;
    entryData.value = null;
    activatingMembershipId.value = null;
    selectedMembershipIds.value = [];
};

const selectMembership = async () => {
    if (!selectedMembershipIds.value.length || activatingMembershipId.value) {
        return;
    }

    const membershipId = selectedMembershipIds.value[0];
    activatingMembershipId.value = "multiple";

    try {
        const { data } = await axios.post(
            route("membership_sale.activate_waiting", {
                locale: currentLocale.value,
                id: membershipId,
            }),
            {
                membership_ids: selectedMembershipIds.value,
                action: entryData.value?.action,
                detected_at: entryData.value?.detected_at ?? entryData.value?.date,
                entry_code: entryData.value?.entry_code,
                scan_type: entryData.value?.scan_type,
                online: entryData.value?.online,
                local_ip: entryData.value?.local_ip,
                mac: entryData.value?.mac,
            },
        );

        if (!data?.attendance_id) {
            throw new Error("Entry was not recorded.");
        }

        toast.success("Մուտքը ֆիքսվեց ընտրված աբոնեմենտների համար");
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
        entryQueue.value = [];
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

        if (showEntryModal.value || entryData.value) {
            entryQueue.value.push(event);
            return;
        }

        showNextEntry(event);
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
        <div class="modal-dialog modal-dialog-centered modal-xl">
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

                <div class="modal-body p-0">
                    <div class="turnstile-entry-layout">
                        <aside class="turnstile-entry-list">
                            <div class="turnstile-entry-list__title">
                                Մուտքերի հերթ ({{ pendingEntries.length }})
                            </div>

                            <button
                                v-for="(entry, index) in pendingEntries"
                                :key="`${entry.entry_code}-${entry.detected_at}-${index}`"
                                type="button"
                                class="turnstile-entry-list__item"
                                :class="{ 'is-active': entry === entryData }"
                                @click="selectPendingEntry(entry)"
                            >
                                <span
                                    class="turnstile-entry-list__avatar"
                                    :class="entry.access_allowed === false ? 'is-denied' : 'is-allowed'"
                                >
                                    {{ entryOwnerName(entry).slice(0, 1) || "?" }}
                                </span>
                                <span class="turnstile-entry-list__content">
                                    <strong>{{ entryOwnerName(entry) }}</strong>
                                    <small>{{ entryActionLabel(entry) }} · {{ entry.entry_code || "-" }}</small>
                                    <small>{{ entry.detected_at || entry.date || "-" }}</small>
                                </span>
                            </button>
                        </aside>

                        <div v-if="entryData" class="turnstile-entry-detail">
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
                            v-if="recordedMemberships.length && !requiresManagerSelection"
                            class="alert alert-info mt-4 mb-0"
                        >
                            <div class="fw-semibold mb-3">
                                Ֆիքսված աբոնեմենտներ
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <div
                                    v-for="membership in recordedMemberships"
                                    :key="`recorded-${membership.id}`"
                                    class="border rounded p-3 bg-white"
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
                                    <div class="small text-muted">
                                        Visits left: {{ membership.visits_left ?? "-" }}
                                    </div>
                                </div>
                            </div>
                        </div>

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

                                            <label
                                                class="membership-select"
                                                :class="{ 'is-disabled': activatingMembershipId }"
                                            >
                                                <input
                                                    v-model="selectedMembershipIds"
                                                    type="checkbox"
                                                    :value="membership.id"
                                                    :disabled="activatingMembershipId"
                                                >
                                                <span class="membership-select__box" aria-hidden="true"></span>
                                            </label>
                                            <button
                                                type="button"
                                                class="btn btn-sm d-none"
                                                :class="
                                                    membership.status === 'active'
                                                        ? 'btn-outline-success'
                                                        : 'btn-primary'
                                                "
                                                :disabled="activatingMembershipId"
                                                @click="selectedMembershipIds = selectedMembershipIds.includes(membership.id) ? selectedMembershipIds.filter((id) => id !== membership.id) : [...selectedMembershipIds, membership.id]"
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
                </div>

                <div class="modal-footer">
                    <button v-if="requiresManagerSelection" class="btn btn-primary" :disabled="!selectedMembershipIds.length || activatingMembershipId" @click="selectMembership">
                        Ֆիքսել մուտքը ընտրված աբոնեմենտների համար
                    </button>
                    <button class="btn btn-secondary" @click="closeModal">
                        Փակել
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.turnstile-entry-layout {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    min-height: 520px;
}

.turnstile-entry-list {
    background: #f7f8fc;
    border-right: 1px solid #e6e8ef;
    max-height: 72vh;
    overflow-y: auto;
    padding: 14px 10px;
}

.turnstile-entry-list__title {
    color: #566075;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .04em;
    padding: 0 8px 10px;
    text-transform: uppercase;
}

.turnstile-entry-list__item {
    align-items: center;
    background: transparent;
    border: 0;
    border-radius: 10px;
    color: inherit;
    display: flex;
    gap: 10px;
    margin-bottom: 6px;
    padding: 10px 8px;
    text-align: left;
    transition: background-color .15s ease, box-shadow .15s ease;
    width: 100%;
}

.turnstile-entry-list__item:hover {
    background: #eef1fa;
}

.turnstile-entry-list__item.is-active {
    background: #fff;
    box-shadow: 0 2px 10px rgba(67, 89, 113, .12);
}

.turnstile-entry-list__avatar {
    align-items: center;
    background: #dff7e9;
    border-radius: 50%;
    color: #1a9b5a;
    display: inline-flex;
    flex: 0 0 34px;
    font-weight: 700;
    height: 34px;
    justify-content: center;
    width: 34px;
}

.turnstile-entry-list__avatar.is-denied {
    background: #fde1e3;
    color: #d5424f;
}

.turnstile-entry-list__content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.turnstile-entry-list__content strong,
.turnstile-entry-list__content small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.turnstile-entry-list__content small {
    color: #7b8496;
    font-size: .75rem;
}

.turnstile-entry-detail {
    max-height: 72vh;
    overflow-y: auto;
    padding: 24px;
}

@media (max-width: 991.98px) {
    .turnstile-entry-layout {
        grid-template-columns: 1fr;
    }

    .turnstile-entry-list {
        border-bottom: 1px solid #e6e8ef;
        border-right: 0;
        max-height: 220px;
    }

    .turnstile-entry-detail {
        max-height: none;
    }
}

.membership-select {
    align-items: center;
    cursor: pointer;
    display: inline-flex;
    height: 24px;
    justify-content: center;
    margin: 0;
    width: 24px;
}

.membership-select.is-disabled {
    cursor: not-allowed;
    opacity: .6;
}

.membership-select input {
    opacity: 0;
    position: absolute;
}

.membership-select__box {
    background: #fff;
    border: 1px solid #d9dee7;
    border-radius: 5px;
    box-shadow: 0 1px 2px rgba(25, 42, 70, .08);
    display: block;
    height: 20px;
    position: relative;
    transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
    width: 20px;
}

.membership-select input:checked + .membership-select__box {
    background: #2f6fed;
    border-color: #2f6fed;
    box-shadow: 0 2px 5px rgba(47, 111, 237, .3);
}

.membership-select input:checked + .membership-select__box::after {
    border: solid #fff;
    border-width: 0 2px 2px 0;
    content: "";
    height: 9px;
    left: 7px;
    position: absolute;
    top: 3px;
    transform: rotate(45deg);
    width: 5px;
}

.membership-select input:focus-visible + .membership-select__box {
    box-shadow: 0 0 0 3px rgba(47, 111, 237, .24);
}
</style>
