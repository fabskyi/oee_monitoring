            </div>
            <!-- End of Main Content -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->


    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery sudah di-load di header.php -->
    <!-- 2. Bootstrap (butuh jQuery) -->
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- 3. jQuery Easing -->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <!-- 4. Chart.js -->
    <script src="vendor/chart.js/Chart.min.js"></script>
    <!-- 5. DataTables (butuh jQuery) -->
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <!-- 6. SB Admin 2 (butuh semua di atas) -->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- Inline Scripts-->
    <script>
        /**
         * Safe DataTable initializer — removes placeholder colspan rows first,
         * then initializes. Call this anywhere before $.fn.DataTable.
         */
        function safeDataTable(selector, options) {
            var $tables = typeof selector === 'string' ? $(selector) : $(selector);
            var dtInstance = null;
            $tables.each(function () {
                try {
                    if ($.fn.DataTable && !$.fn.DataTable.isDataTable(this)) {
                        $(this).find('tbody tr').each(function () {
                            if ($(this).find('td[colspan], th[colspan]').length > 0) {
                                $(this).remove();
                            }
                        });
                        var opts = $.extend({ responsive: true, language: { url: 'vendor/datatables/i18n/id.json' } }, options || {});
                        dtInstance = $(this).DataTable(opts);
                    } else if ($.fn.DataTable && $.fn.DataTable.isDataTable(this)) {
                        dtInstance = $(this).DataTable();
                    }
                } catch (e) {
                    console.warn('DataTable init error on #' + (this.id || '?') + ':', e.message);
                }
            });
            return dtInstance;   // ← kembalikan instance agar bisa dipakai untuk row.add()
        }

        $(document).ready(function () {
            // Auto-initialize tables with class .dataTable (skip .no-auto-init)
            if ($.fn.DataTable) {
                safeDataTable('.dataTable:not(.no-auto-init)');
            }

            // Initialize Bootstrap tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>

</body>
</html>
