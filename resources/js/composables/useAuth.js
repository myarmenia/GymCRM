import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth.user);

    const hasRole = (role) => {
        console.log('Checking role:', role);
        return user.value?.roles?.some(r => r.name === role);
    };

    const hasAnyRole = (roles = []) => {
        return user.value?.roles?.some(r => roles.includes(r.name));
    };

    return {
        user,
        hasRole,
        hasAnyRole,
    };
}
