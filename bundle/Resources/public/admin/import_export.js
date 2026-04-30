(function (global, doc, ibexa, React, ReactDOM) {
    // Ensure the DOM is fully loaded before executing
    doc.addEventListener('DOMContentLoaded', function() {
        // Check if there's a section with the class `ibexa-import-export-import`
        var importExportSection = doc.querySelector('.ibexa-import-export-import');

        if (!importExportSection) {
            // If the section is not found, do not execute the rest of the script
            return;
        }

        var packageInput = doc.querySelector('input[type="file"]');
        var previewDiv = doc.querySelector('#ibexa-import-export__preview');
        var previewButton = doc.querySelector('#ibexa-import-export__preview-button');
        var formContainer = doc.querySelector('.ibexa-import-export__form');
        var parentLocationField = doc.querySelector('.ibexa-import-export__field-parent-location');
        var submitHint = doc.querySelector('.ibexa-import-export__submit-hint');
        var previewUrl = formContainer.dataset.previewUrl;

        // Tracks the latest preview state. Updated on every preview response.
        var previewSucceeded = false;

        function getImportMode() {
            var previewSection = previewDiv.querySelector('[data-import-mode]');
            return previewSection ? previewSection.dataset.importMode : '';
        }

        function getParentLocationValue() {
            if (!parentLocationField) {
                return '';
            }
            var hiddenInput = parentLocationField.querySelector('input');
            return hiddenInput ? (hiddenInput.value || '') : '';
        }

        function applyImportModeToForm() {
            if (!parentLocationField) {
                return;
            }

            var importMode = getImportMode();
            var hiddenInput = parentLocationField.querySelector('input');

            // parent_location is irrelevant for update-mode imports; grey it out.
            if (importMode === 'update') {
                parentLocationField.classList.add('ibexa-import-export__field-parent-location--disabled');
                if (hiddenInput) {
                    hiddenInput.disabled = true;
                }
            } else {
                parentLocationField.classList.remove('ibexa-import-export__field-parent-location--disabled');
                if (hiddenInput) {
                    hiddenInput.disabled = false;
                }
            }
        }

        function refreshSubmitState() {
            if (!previewButton) {
                return;
            }

            var importMode = getImportMode();
            // Block submit only when create-mode and no destination has been chosen yet.
            // For update-mode, parent_location is unused so it doesn't gate submit.
            var needsLocation = importMode === 'create' && getParentLocationValue() === '';
            var canSubmit = previewSucceeded && !needsLocation;

            previewButton.disabled = !canSubmit;
            previewButton.style.opacity = canSubmit ? 1 : 0.5;
            previewButton.title = needsLocation
                ? 'Choose an import location to enable the import.'
                : '';

            if (submitHint) {
                submitHint.hidden = !needsLocation;
            }
        }

        // Watch the parent_location wrapper for any DOM/value change made by the content browser
        // (it doesn't reliably fire native input/change events). Each mutation triggers a refresh.
        if (parentLocationField && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(refreshSubmitState);
            observer.observe(parentLocationField, {
                subtree: true,
                childList: true,
                attributes: true,
                characterData: true,
            });
        }

        if (packageInput) {
            packageInput.addEventListener('change', function() {
                var formData = new FormData();
                formData.append('file', packageInput.files[0]);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', previewUrl, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        previewDiv.innerHTML = xhr.responseText;
                        previewSucceeded = xhr.status === 200;

                        applyImportModeToForm();
                        refreshSubmitState();
                    }
                };
                xhr.send(formData);
            });
        }

        // Initial state: no preview yet, so submit is disabled.
        refreshSubmitState();
    });

})(window, window.document, window.ibexa, window.React, window.ReactDOM);
