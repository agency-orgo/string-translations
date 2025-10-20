@extends('statamic::layout')
@section('title', __('String Translations'))

@section('content')

<div class="flex items-center justify-between">
    <h1>{{ __('String Translations') }}</h1>
</div>

<div role="tablist" aria-label="Edit Content" class="publish-tabs tabs flex-shrink mt-4">
    @foreach(\Statamic\Facades\Site::all() as $site)
        <a href="?lang={{$site->handle()}}"
            class="tab-button {{ $active_lang === $site->handle() || (!$active_lang && $loop->first) ? " active" : "" }}">
            {{$site->name}}
        </a>
    @endforeach
</div>

<!-- Search/Filter Section -->
<div class="card p-4 mt-3">
    <div class="flex items-center gap-4">
        <div class="flex-1">
            <input aria-label="search" type="text" id="translation-search" class="input-text"
                placeholder="Search translation keys or values..." autocomplete="off">
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <span id="translation-count">{{ count($data) }}</span>
            <span>translations</span>
        </div>
    </div>
</div>

<form method="POST" action="{{ cp_route('utilities.string-translations') }}" onsubmit="return validateForm()">
    @csrf
    <input name="lang" type="hidden" class="input-text" value="{{$active_lang}}">
    <input name="keys_to_delete" type="hidden" class="input-text" value="" id="keys-to-delete" maxlength="10000">

    <div class="card p-0 mt-3">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $entry)
                    <tr class="translation-row {{Str::startsWith($entry->value, "untranslated_") ? "bg-red-100 text-red-500" : ""}}"
                        data-key="{{$entry->key}}" data-lang="{{$entry->lang}}" data-value="{{$entry->value}}">
                        <td class="pl-6 w-1/4">{{$entry->key}}</td>
                        <td class="break-all py-2">
                            <input name="strings[{{$entry->key}}]" type="text" class="input-text" value="{{$entry->value}}">
                        </td>

                        <td class="rtl:text-left ltr:text-right">
                            <button type="button" class="btn btn-xs btn-danger -my-1"
                                onclick="markForDeletion({{ json_encode($entry->key) }})">
                                Delete
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex mt-3">
        <button class="ml-auto btn-primary" id="save-button">Save</button>
    </div>


</form>

@once
    <script>
        let keysToDelete = new Set();

        function markForDeletion(key) {
            try {
                // Validate key format
                if (!key || typeof key !== 'string' || key.length > 255) {
                    alert('Invalid translation key format.');
                    return;
                }

                // Prevent too many deletions at once
                if (!keysToDelete.has(key) && keysToDelete.size >= 100) {
                    alert('You can only mark up to 100 keys for deletion at once.');
                    return;
                }
                // Safely escape the key for CSS selector
                const escapedKey = CSS.escape(key);
                const row = document.querySelector(`[data-key="${escapedKey}"]`);

                if (!row) {
                    console.error('Could not find row for key:', key);
                    // Try alternative selector method as fallback
                    const allRows = document.querySelectorAll('.translation-row');
                    let foundRow = null;
                    for (let r of allRows) {
                        if (r.getAttribute('data-key') === key) {
                            foundRow = r;
                            break;
                        }
                    }
                    if (!foundRow) {
                        alert('Could not find translation row. Please refresh the page and try again.');
                        return;
                    }
                    row = foundRow;
                }

                const button = row.querySelector('button');
                const input = row.querySelector('input[type="text"]');
                if (!button) {
                    console.error('Could not find delete button in row for key:', key);
                    return;
                }

                if (keysToDelete.has(key)) {
                    // Unmark for deletion
                    keysToDelete.delete(key);
                    row.classList.remove('bg-red-200', 'opacity-50');
                    button.textContent = 'Delete';
                    button.classList.remove('btn-secondary');
                    button.classList.add('btn-danger');
                    if (input) {
                        input.disabled = false;
                        input.setAttribute('aria-disabled', 'false');
                    }
                } else {
                    // Mark for deletion
                    keysToDelete.add(key);
                    row.classList.add('bg-red-200', 'opacity-50');
                    button.textContent = 'Undo Delete';
                    button.classList.remove('btn-danger');
                    button.classList.add('btn-secondary');
                    if (input) {
                        input.disabled = true; // Disabled inputs are not submitted
                        input.setAttribute('aria-disabled', 'true');
                    }
                }

                // Update hidden input with keys to delete
                document.getElementById('keys-to-delete').value = Array.from(keysToDelete).join(',');

                // Update save button text to show deletion count
                updateSaveButtonText();
            } catch (error) {
                console.error('Error marking key for deletion:', key, error);
                alert('An error occurred. Please refresh the page and try again.');
            }
        }

        function updateSaveButtonText() {
            const saveButton = document.getElementById('save-button');
            const deleteCount = keysToDelete.size;

            if (deleteCount > 0) {
                saveButton.textContent = `Save & Delete ${deleteCount} key${deleteCount === 1 ? '' : 's'} (All Locales)`;
                saveButton.classList.add('btn-danger');
                saveButton.classList.remove('btn-primary');
            } else {
                saveButton.textContent = 'Save';
                saveButton.classList.add('btn-primary');
                saveButton.classList.remove('btn-danger');
            }
        }

        function validateForm() {
            const keysToDeleteInput = document.getElementById('keys-to-delete');
            const keysString = keysToDeleteInput.value;

            // Check if keys_to_delete is too long
            if (keysString.length > 10000) {
                alert('Too many keys selected for deletion. Please select fewer keys.');
                return false;
            }

            // If there are keys to delete, confirm with user
            if (keysToDelete.size > 0) {
                const confirmMessage = `Are you sure you want to delete ${keysToDelete.size} key${keysToDelete.size === 1 ? '' : 's'} from ALL locales?\n\nThis action cannot be undone.`;
                if (!confirm(confirmMessage)) {
                    return false;
                }
            }

            return true;
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('translation-search');
            const translationRows = document.querySelectorAll('.translation-row');
            const countElement = document.getElementById('translation-count');
            const totalCount = translationRows.length;

            searchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase().trim();
                let visibleCount = 0;

                translationRows.forEach(function (row) {
                    const key = row.getAttribute('data-key').toLowerCase();
                    const value = row.getAttribute('data-value').toLowerCase();

                    if (searchTerm === '' || key.includes(searchTerm) || value.includes(searchTerm)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update count
                countElement.textContent = searchTerm === '' ? totalCount : visibleCount;
            });

            // Add keyboard shortcut (Ctrl/Cmd + F)
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        });
    </script>
@endonce
@stop