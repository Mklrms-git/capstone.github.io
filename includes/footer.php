                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.10/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.10/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.10/main.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Flatpickr JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize all DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                responsive: true,
                pageLength: 10,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
        });

        // Common AJAX setup
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Common success message function
        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: message,
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Common error message function
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        }

        // Replacement for native alert() - uses modal dialog
        function showAlert(message, title = 'Information', icon = 'info') {
            return Swal.fire({
                icon: icon,
                title: title,
                text: message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd'
            });
        }

        // Generic confirmation dialog (green OK, red Cancel) - replacement for confirm()
        function confirmDialog(message, okText = 'OK', cancelText = 'Cancel', title = 'Are you sure?') {
            return Swal.fire({
                title: title,
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: okText,
                cancelButtonText: cancelText,
                confirmButtonColor: '#198754', // Bootstrap success
                cancelButtonColor: '#dc3545',   // Bootstrap danger
                reverseButtons: true
            }).then(result => result.isConfirmed);
        }

        // Helpers to replace native confirm() in anchors/forms
        function confirmLink(event, message, okText = 'OK', cancelText = 'Cancel') {
            event.preventDefault();
            const anchor = event.currentTarget;
            const href = anchor.getAttribute('href');
            confirmDialog(message, okText, cancelText).then(confirmed => {
                if (confirmed && href) {
                    window.location.href = href;
                }
            });
            return false;
        }

        function confirmSubmit(event, message, okText = 'OK', cancelText = 'Cancel') {
            event.preventDefault();
            const form = event.target;
            confirmDialog(message, okText, cancelText).then(confirmed => {
                if (confirmed) {
                    form.submit();
                }
            });
            return false;
        }

        // Common confirmation dialog
        function confirmDelete(callback) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        }

        // Function to format date - Returns format: "December 07, 2025"
        function formatDate(dateString) {
            if (!dateString || dateString === 'null' || dateString === null || dateString === '') return '';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '';
                
                const options = { 
                    year: 'numeric', 
                    month: 'long', 
                    day: '2-digit'
                };
                return date.toLocaleDateString('en-US', options);
            } catch (e) {
                return '';
            }
        }

        // Function to format date with time - Returns format: "December 07, 2025 1:14 AM"
        function formatDateTime(dateString) {
            if (!dateString || dateString === 'null' || dateString === null || dateString === '') return '';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '';
                
                const dateOptions = { 
                    year: 'numeric', 
                    month: 'long', 
                    day: '2-digit'
                };
                const timeOptions = {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                };
                const dateStr = date.toLocaleDateString('en-US', dateOptions);
                const timeStr = date.toLocaleTimeString('en-US', timeOptions);
                return dateStr + ' ' + timeStr;
            } catch (e) {
                return '';
            }
        }

        // Function to format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        }

        // Function to handle form validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
            return form.checkValidity();
        }

        // Function to handle file upload preview
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Function to handle AJAX form submission
        function submitForm(formId, url, successCallback) {
            if (!validateForm(formId)) return;

            const form = document.getElementById(formId);
            const formData = new FormData(form);

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        if (typeof successCallback === 'function') {
                            successCallback(response);
                        }
                    } else {
                        showError(response.message);
                    }
                },
                error: function(xhr) {
                    showError('An error occurred. Please try again.');
                }
            });
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <?php if (isset($pageScripts)): ?>
        <?php echo $pageScripts; ?>
    <?php endif; ?>
</body>
</html> 