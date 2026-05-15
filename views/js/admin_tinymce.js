document.addEventListener('DOMContentLoaded', function() {
    // Initialize TinyMCE on all RTE fields
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: 'textarea.rte',
            plugins: 'link lists code',
            toolbar: 'undo redo | bold italic | bullist numlist | link | code',
            menubar: false,
            height: 200,
            setup: function(editor) {
                // Force TinyMCE to sync content with textarea before form submission
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    }
});