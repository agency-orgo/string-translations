import StringTranslations from './components/StringTranslations.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('string-translations::StringTranslations', StringTranslations);
});
