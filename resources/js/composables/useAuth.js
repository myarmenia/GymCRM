import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user);

    const roleNames = computed(() => {
        const roles = user.value?.roles ?? [];

        if (Array.isArray(roles)) {
            return roles
                .map((role) => {
                    if (typeof role === 'string') {
                        return role;
                    }

                    return role?.name ?? role?.role_name ?? null;
                })
                .filter(Boolean);
        }

        if (typeof roles === 'object') {
            return Object.values(roles)
                .map((role) => role?.name ?? role?.role_name ?? role)
                .filter(Boolean);
        }

        return [];
    });

    const hasRole = (role) => {
        return roleNames.value.includes(role);
    };

    const hasAnyRole = (roles = []) => {
        return roles.some((role) => roleNames.value.includes(role));
    };

    return {
        user,
        roleNames,
        hasRole,
        hasAnyRole,
    };
}
