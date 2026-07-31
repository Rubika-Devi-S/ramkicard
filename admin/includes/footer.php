        </main>

        <footer class="admin-footer">
            <span>
                &copy; <?= date('Y'); ?>
                <strong>Ramki Cards</strong>.
                All rights reserved.
            </span>
        </footer>

        </div>
        </div>

        <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>

        <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">

                    <div class="modal-body p-4 text-center">

                        <div class="confirm-icon mb-3">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <h5 id="confirmTitle">
                            Confirm action
                        </h5>

                        <p class="text-muted" id="confirmMessage">
                            Are you sure?
                        </p>

                        <div class="d-flex justify-content-center gap-2">

                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="button" class="btn btn-danger" id="confirmActionButton">
                                Confirm
                            </button>

                        </div>

                    </div>

                </div>
            </div>
        </div>

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <!-- Bootstrap -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

        <!-- DataTables -->
        <script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>

        <script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>

        <!--
|--------------------------------------------------------------------------
| Common Admin Functions
|--------------------------------------------------------------------------
| Contains:
| - RamkiAdmin AJAX methods
| - Toast messages
| - Confirmation modal
| - Dynamic sidebar controller
|--------------------------------------------------------------------------
-->
        <script src="assets/js/admin-common.js?v=20260729-3"></script>

        <!-- Admin light and dark mode -->
        <script src="assets/js/admin-theme.js?v=20260729-3"></script>

        <!-- Page-specific JavaScript -->
        <?php if (!empty($pageScript)): ?>
        <script src="assets/js/<?= e((string)$pageScript); ?>?v=20260729-3"></script>
        <?php endif; ?>

        </body>

        </html>