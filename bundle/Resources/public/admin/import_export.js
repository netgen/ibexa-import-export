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
        var previewUrl = formContainer.dataset.previewUrl;

        if (packageInput) {
            packageInput.addEventListener('change', function() {
                var formData = new FormData();
                formData.append('file', packageInput.files[0]);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', previewUrl, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        if (xhr.status === 200) {
                            previewDiv.innerHTML = xhr.responseText;
                            previewButton.disabled = false;
                            previewButton.style.opacity = 1;
                        } else {
                            previewDiv.innerHTML = xhr.responseText;
                            previewButton.disabled = true;
                            previewButton.style.opacity = 0.5;
                        }
                    }
                };
                xhr.send(formData);
            });
        }
    });

})(window, window.document, window.ibexa, window.React, window.ReactDOM);
