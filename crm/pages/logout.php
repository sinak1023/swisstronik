<?php
$_SESSION["loginok"] = "no";
$_SESSION["id"] = 0;
unset($_SESSION["loginok"]);
unset($_SESSION["id"]);
?>
<script>
    window.location.href = 'login';
</script>