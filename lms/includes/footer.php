<?php
// includes/footer.php
// Variables: $template_path, $assets_path, $page_scripts (optional)
?>
        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>School Library Management System &copy; <?= date('Y') ?></span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->

    </div>
    <!-- End of #content -->
    </div>
    <!-- End of #content-wrapper -->

    </div>
    <!-- End of #wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap core JS-->
    <script src="<?= $template_path ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?= $template_path ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $template_path ?>vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?= $template_path ?>js/sb-admin-2.min.js"></script>

    <!-- DataTables JS -->
    <script src="<?= $template_path ?>vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="<?= $template_path ?>vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <!-- Custom LMS JS -->
    <script src="<?= $assets_path ?>js/lms.js"></script>

    <?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>
</html>
