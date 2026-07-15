<?php
$error = 0;
$login_function = new Login($db);

if (isset($_SESSION["id"])) {
    if ((int)$_SESSION["id"] > 0) {
        echo '<script> location.replace("dashboard"); </script>';
    }
}
?>

<div class="min-h-screen flex items-center justify-center">
    <div class="grid grid-cols-1 md:grid-cols-2 max-w-4xl w-full mx-auto shadow-lg rounded-lg overflow-hidden">
        
        <div class="hidden md:flex items-center justify-center   bg-surface">
            <img src="assets/img/login.png" alt="تصویر دسکتاپ" class="w-full h-full p-5 object-cover">
        </div>
        
        <div class="bg-surface p-8 mx-4 pt-0 md:mx-0">
            <h2 class="text-2xl font-bold mb-6 text-center text-text mt-12">ورود</h2>
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>
            <form id="login-form" class="space-y-4">
                <input type="text" name="username" id="username" placeholder="نام کاربری" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required style="direction: ltr;">
                <input type="password" name="password" id="password" placeholder="رمز عبور" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required style="direction: ltr;">
                <button type="submit" class="w-full bg-primary text-white py-2 rounded-lg hover:bg-blue-600">ورود</button>
            </form>
            <a href="forgot_password" class="text-primary hover:underline mt-4 block text-center">فراموشی رمز عبور؟</a>
        </div>


    </div>
</div>


<script>
    $('#login-form').on('submit', function(e) {
        e.preventDefault();
        var username = $('#username').val();
        var password = $('#password').val();

        $.ajax({
            url: 'apis/login.php',
            type: 'POST',
            data: {
                username: username,
                password: password
            },
            success: function(res) {
                if (res.success) {
                    window.location.href = 'dashboard';
                } else {
                    $('#error-message').text(res.message).show();
                }
            },
            error: function() {
                $('#error-message').text('خطایی رخ داد. لطفا دوباره امتحان کنید.').show();
            }
        });
    });
</script>