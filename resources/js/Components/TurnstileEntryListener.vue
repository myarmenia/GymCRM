<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();

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
        return "Մուտքը մերժված է․ aboniment-ի ժամկետը լրացել է կամ active aboniment չկա";
    }

    if (entryData.value?.reason === "invalid_entry_code") {
        return "Մուտքը մերժված է․ մուտքի կոդը չի գտնվել";
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

const closeModal = () => {
    showEntryModal.value = false;
    entryData.value = null;
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
        console.log("Turnstile socket disabled: user is not manager", userRole.value);
        return;
    }

    const currentClientId = clientId.value;

    if (!currentClientId) {
        console.warn("Turnstile socket: clientId is missing", page.props);
        return;
    }

    if (!window.Echo) {
        console.warn("Turnstile socket: window.Echo is missing");
        return;
    }

    const channelName = `turnstile.${currentClientId}`;

    if (subscribedChannelName === channelName) {
        return;
    }

    leaveCurrentChannel();
    subscribedChannelName = channelName;

    console.log("Turnstile socket role:", userRole.value);
    console.log("Turnstile socket isManager:", isManager.value);

    window.Echo.private(channelName).listen(".entry.detected", (event) => {
        if (!isManager.value) {
            return;
        }

        console.log("Global entry detected event:", event);

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
        <div class="modal-dialog modal-dialog-centered">
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
                                        : entryData.message ||
                                          "Մուտքը թույլատրված է"
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
                                alt="Այցելու"
                            />

                            <div>
                                <h5 class="mb-1">
                                    {{ currentOwner?.name }}
                                    {{ currentOwner?.surname }}
                                </h5>

                                <div class="text-muted">
                                    {{
                                        currentOwner?.phone ||
                                        currentOwner?.email
                                    }}
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
                                {{
                                    isDenied
                                        ? "Մերժված"
                                        : "Թույլատրված"
                                }}
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
