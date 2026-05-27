import { usePage } from '@inertiajs/vue3';

export function useTrans(value, replacements = {}) {
    const array = usePage().props.translations;

    // Находим перевод по ключу
    let translation = value.split('.').reduce((t, k) => t?.[k] ?? value, array);

    // Подставляем параметры вида :count, :name
    Object.keys(replacements).forEach(key => {
        const regex = new RegExp(`:${key}`, 'g');
        translation = translation.replace(regex, replacements[key]);
    });

    return translation;
}
