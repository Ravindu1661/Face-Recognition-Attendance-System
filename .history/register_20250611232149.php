<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

if ($_POST) {
    $auth = new Auth();
    $data = [
        'employee_id' => sanitize($_POST['employee_id']),
        'name' => sanitize($_POST['name']),
        'email' => sanitize($_POST['email']),
        'password' => $_POST['password']
    ];
    
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = 'Passwords do not match!';
    } else {
        try {
            if ($auth->register($data)) {
                $message = 'Registration successful! You can now login.';
            } else {
                $error = 'Registration failed. Email or Employee ID might already exist.';
            }
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Face Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- පරණ face-api.js script එක remove කරලා මේක use කරන්න -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js"></script>

    <!-- හෝ local version එකක් download කරන්න -->
    <!-- <script src="assets/js/face-api.min.js"></script> -->
</head>
<body class="bg-gradient-primary">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">Create an Account</h1>
                                    </div>
                                    
                                    <?php if ($error): ?>
                                        <div class="alert alert-danger"><?= $error ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($message): ?>
                                        <div class="alert alert-success"><?= $message ?></div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="user">
                                        <div class="form-group mb-3">
                                            <input type="text" class="form-control form-control-user" 
                                                   name="employee_id" placeholder="Employee ID" required>
                                        </div>
                                        <div class="form-group mb-3">
                                            <input type="text" class="form-control form-control-user" 
                                                   name="name" placeholder="Full Name" required>
                                        </div>
                                        <div class="form-group mb-3">
                                            <input type="email" class="form-control form-control-user" 
                                                   name="email" placeholder="Email Address" required>
                                        </div>
                                        <div class="form-group mb-3">
                                            <input type="password" class="form-control form-control-user"
                                                   name="password" placeholder="Password" required>
                                        </div>
                                        <div class="form-group mb-3">
                                            <input type="password" class="form-control form-control-user"
                                                   name="confirm_password" placeholder="Confirm Password" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-user btn-block w-100">
                                            Register Account
                                        </button>
                                    </form>
                                    
                                    <hr>
                                    <div class="text-center">
                                        <a class="small" href="index.php">Already have an account? Login!</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>