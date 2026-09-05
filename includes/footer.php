    </div><!-- /.ism-content -->

    <div class="ism-footer no-print">
      <?= e(setting('software_name', 'ISP Management System')) ?> &copy; <?= date('Y') ?> <?= e(setting('organization_name', '')) ?> &middot; All rights reserved.
    </div>
  </div><!-- /.ism-main -->
</div><!-- /.ism-wrapper -->

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:1080;"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/js/dataTables.bootstrap5.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>

<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
