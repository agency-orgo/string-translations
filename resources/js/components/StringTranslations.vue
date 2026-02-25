<template>
    <div>
        <Head :title="title" />

        <Header :title="title">
            <template #actions>
                <Button
                    :variant="keysToDelete.size > 0 ? 'danger' : 'primary'"
                    :disabled="isSaving"
                    @click="save"
                >
                    {{ saveButtonText }}
                </Button>
            </template>
        </Header>

        <Alert v-if="missingTable" variant="warning" class="mb-4">
            <p class="font-bold">Database table not found</p>
            <p class="text-sm mt-1">
                Run <code class="bg-gray-200 dark:bg-gray-800 px-1 rounded">php artisan migrate</code> to create the required table.
            </p>
        </Alert>

        <Tabs v-model="activeTab">
            <TabList>
                <TabTrigger
                    v-for="site in sites"
                    :key="site.handle"
                    :name="site.handle"
                    :text="site.name"
                />
            </TabList>
        </Tabs>

        <div class="mt-4">
            <Listing
                :items="filteredTranslations"
                :columns="columns"
                sortColumn="key"
                sortDirection="asc"
                :allowBulkActions="false"
                :allowCustomizingColumns="false"
                v-slot="{ items, loading }"
            >
                <div class="flex items-center justify-between gap-3 min-h-16">
                    <ListingSearch />
                    <Select
                        v-model="statusFilter"
                        :options="statusFilterOptions"
                        class="w-44"
                    />
                </div>

                <div
                    v-if="!items.length"
                    class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center text-gray-500"
                >
                    No results
                </div>

                <Panel v-else>
                    <ListingTable>
                        <template #cell-key="{ row }">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs" :class="{ 'line-through opacity-50': keysToDelete.has(row.key) }">
                                    {{ row.key }}
                                </span>
                                <Badge v-if="row.untranslated" color="amber" size="sm" text="Untranslated" />
                            </div>
                        </template>
                        <template #cell-value="{ row }">
                            <Input
                                :modelValue="editedValues[row.key] ?? row.value"
                                :disabled="keysToDelete.has(row.key)"
                                @update:modelValue="val => editedValues[row.key] = val"
                            />
                        </template>
                        <template #cell-actions="{ row }">
                            <Button
                                v-if="keysToDelete.has(row.key)"
                                size="sm"
                                @click="toggleDelete(row.key)"
                            >
                                Undo
                            </Button>
                            <Button
                                v-else
                                variant="danger"
                                size="sm"
                                @click="toggleDelete(row.key)"
                            >
                                Delete
                            </Button>
                        </template>
                    </ListingTable>
                </Panel>
            </Listing>
        </div>

        <ConfirmationModal
            v-model:open="showDeleteConfirmation"
            title="Delete translation keys"
            :bodyText="`Are you sure you want to delete ${keysToDelete.size} key(s) from ALL locales? This action cannot be undone.`"
            buttonText="Delete"
            danger
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Input,
    Alert,
    Badge,
    Select,
    Panel,
    Tabs,
    TabList,
    TabTrigger,
    Listing,
    ListingSearch,
    ListingTable,
    ConfirmationModal,
} from '@statamic/cms/ui';

const props = defineProps({
    translations: { type: Array, required: true },
    activeLang: { type: String, required: true },
    sites: { type: Array, required: true },
    saveUrl: { type: String, required: true },
    missingTable: { type: Boolean, default: false },
});

const title = 'String Translations';

const columns = [
    { field: 'key', label: 'Key', sortable: true },
    { field: 'value', label: 'Value' },
    { field: 'actions', label: '' },
];

const editedValues = reactive({});
const keysToDelete = ref(new Set());
const isSaving = ref(false);
const showDeleteConfirmation = ref(false);
const statusFilter = ref('all');

const statusFilterOptions = [
    { label: 'All', value: 'all' },
    { label: 'Untranslated', value: 'untranslated' },
    { label: 'Translated', value: 'translated' },
];

const filteredTranslations = computed(() => {
    if (statusFilter.value === 'untranslated') {
        return props.translations.filter(t => t.untranslated);
    }
    if (statusFilter.value === 'translated') {
        return props.translations.filter(t => !t.untranslated);
    }
    return props.translations;
});

const saveButtonText = computed(() => {
    if (keysToDelete.value.size > 0) {
        const count = keysToDelete.value.size;
        return `Save & Delete ${count} key${count === 1 ? '' : 's'} (All Locales)`;
    }
    return 'Save';
});

const activeTab = computed({
    get: () => props.activeLang,
    set: (handle) => router.get(window.location.pathname, { lang: handle }, { preserveState: false }),
});

function onKeydown(e) {
    if (e.key === 'Enter' || ((e.metaKey || e.ctrlKey) && e.key === 's')) {
        e.preventDefault();
        save();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

function toggleDelete(key) {
    const next = new Set(keysToDelete.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        if (next.size >= 100) {
            Statamic.$toast.error('You can only mark up to 100 keys for deletion at once.');
            return;
        }
        next.add(key);
    }
    keysToDelete.value = next;
}

function save() {
    if (keysToDelete.value.size > 0) {
        showDeleteConfirmation.value = true;
        return;
    }
    confirmSave();
}

function confirmSave() {
    showDeleteConfirmation.value = false;
    isSaving.value = true;

    const strings = {};
    for (const entry of props.translations) {
        if (keysToDelete.value.has(entry.key)) continue;
        strings[entry.key] = editedValues[entry.key] ?? entry.value;
    }

    router.post(props.saveUrl, {
        lang: props.activeLang,
        strings,
        keys_to_delete: Array.from(keysToDelete.value).join(','),
    }, {
        onFinish: () => {
            isSaving.value = false;
        },
    });
}
</script>
