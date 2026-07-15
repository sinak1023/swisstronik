<?php
include 'config.php';

if (isset($_SESSION["id"]) && (int)$_SESSION["id"] > 0) {
    echo '<script> location.replace("dashboard"); </script>';
}
?>

<div class="min-h-screen flex items-center justify-center">
    <div class="grid grid-cols-1 md:grid-cols-2 max-w-4xl w-full mx-auto shadow-lg rounded-lg overflow-hidden">
        <div class="hidden md:flex items-center justify-center   bg-surface">
            <img src="assets/img/forgot_password.png" alt="تصویر دسکتاپ" class="w-full h-full p-5 object-cover">
        </div>
        <div class="bg-surface p-8 mx-4 pt-0 md:mx-0">
            <h2 class="text-2xl font-bold mb-6 text-center text-text mt-12">فراموشی رمز عبور</h2>
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>
            <div id="step-1" class="space-y-4">
                <form id="form-step-1">
                    <input type="text" name="username" id="username" placeholder="ایمیل یا شماره" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dir-ltr" required>
                    <button type="submit" class="w-full bg-primary text-white py-2 my-4 rounded-lg hover:bg-blue-600">ارسال کد تایید</button>
                </form>
            </div>
            <div id="step-2" class="space-y-4 hidden">
                <form id="form-step-2">
                    <input type="text" name="code" id="code" placeholder="کد تایید" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dir-ltr" required>
                    <button type="submit" class="w-full bg-primary text-white py-2 my-4 rounded-lg hover:bg-blue-600">تایید کد</button>
                </form>
            </div>
            <div id="step-3" class="space-y-4 hidden">
                <form id="form-step-3">
                    <input type="password" name="new_password" id="new_password" placeholder="رمز عبور جدید" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dir-ltr" required>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="تکرار رمز عبور" class="w-full px-4 py-2 my-4  border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dir-ltr" required>
                    <button type="submit" class="w-full bg-primary text-white py-2 my-4 rounded-lg hover:bg-blue-600">تغییر رمز عبور</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    
    let currentStep = 1;

    function showStep(step) {
        $('#step-1, #step-2, #step-3').addClass('hidden');
        $('#step-' + step).removeClass('hidden');
        currentStep = step;
    }

    
    $('#form-step-1').on('submit', function(e) {
        e.preventDefault();
        var username = $('#username').val();

        $.ajax({
            url: 'apis/forgot_password_step1.php',
            type: 'POST',
            data: {
                username: username
            },
            success: function(res) {
                if (res.success) {
                    showStep(2);
                    $('#error-message').hide();
                } else {
                    $('#error-message').text(res.message).show();
                }
            },
            error: function() {
                $('#error-message').text('خطایی رخ داد. لطفا دوباره امتحان کنید.').show();
            }
        });
    });

    
    $('#form-step-2').on('submit', function(e) {
        e.preventDefault();
        var code = $('#code').val();

        $.ajax({
            url: 'apis/forgot_password_step2.php',
            type: 'POST',
            data: {
                code: code
            },
            success: function(res) {
                if (res.success) {
                    showStep(3);
                    $('#error-message').hide();
                } else {
                    $('#error-message').text(res.message).show();
                }
            },
            error: function() {
                $('#error-message').text('خطایی رخ داد. لطفا دوباره امتحان کنید.').show();
            }
        });
    });

    
    $('#form-step-3').on('submit', function(e) {
        e.preventDefault();
        var new_password = $('#new_password').val();
        var confirm_password = $('#confirm_password').val();

        $.ajax({
            url: 'apis/forgot_password_step3.php',
            type: 'POST',
            data: {
                new_password: new_password,
                confirm_password: confirm_password
            },
            success: function(res) {
                if (res.success) {
                    window.location.href = 'login';
                } else {
                    $('#error-message').text(res.message).show();
                }
            },
            error: function() {
                $('#error-message').text('خطایی رخ داد. لطفا دوباره امتحان کنید.').show();
            }
        });
    });

    
    showStep(1);
</script>