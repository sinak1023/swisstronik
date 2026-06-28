<?php if (isset($_SESSION["id"])) { ?>
    </div>
<?php } ?>


<script src="assets/plugins/print/jQuery.print.js"></script>


<script src="assets/plugins/select2/js/select2.full.js"></script>


<script src="assets//plugins/sweet-alert2/sweetalert2.min.js"></script>


<script src="assets/plugins/alertify/js/alertify.js"></script>


<script src="assets/plugins/moment/moment.js"></script>
<script src="assets/plugins/x-editable/js/bootstrap-editable.min.js"></script>


<script type="text/javascript" src="assets/plugins/persian-datepicker/jalalidatepicker.js"></script>


<script type="text/javascript" src="assets/plugins/clockpicker/jquery-clockpicker.js"></script>

<script type="text/javascript" src="assets/js/app.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jdate@0.0.2/index.min.js"></script>
<?php
if (file_exists('assets/js/pages/' . $ex[0] . '.js')) {
    echo "<script src=\"assets/js/pages/" . $ex[0] . ".js?v=$version_app\"></script>";
}
if (isset($extra_footer)) {
    echo $extra_footer;
}
?>
</body>

</html>