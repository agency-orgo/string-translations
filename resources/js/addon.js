import StringTranslations from './components/StringTranslations/index.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('string-translations::StringTranslations', StringTranslations);
});
