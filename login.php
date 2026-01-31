<?php
session_start();
require 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if ($username && $email && $password) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or Email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                if ($stmt->execute([$username, $email, $hash])) {
                    $success = "Registration successful! Please login.";
                } else {
                    $error = "Registration failed.";
                }
            }
        } else {
            $error = "All fields are required.";
        }
    } elseif ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if ($username && $password) {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: calendar.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "All fields are required.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Calendar App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
        function toggleForms() {
            const loginForm = document.getElementById('login-form');
            const signupForm = document.getElementById('signup-form');
            const errorMsg = document.getElementById('error-message');
            const successMsg = document.getElementById('success-message');
            
            if (errorMsg) errorMsg.style.display = 'none';
            if (successMsg) successMsg.style.display = 'none';

            if (loginForm.classList.contains('hidden')) {
                loginForm.classList.remove('hidden');
                signupForm.classList.add('hidden');
            } else {
                loginForm.classList.add('hidden');
                signupForm.classList.remove('hidden');
            }
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <!-- Main Container -->
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100">
        
        <!-- Header Image / Decoration -->
        <div class="h-32 bg-gradient-to-r from-brand-500 to-indigo-600 flex items-center justify-center">
            <h1 class="text-white text-3xl font-semibold tracking-wide">Calendar App</h1>
        </div>

        <div class="p-8">
            
            <?php if ($error): ?>
                <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div id="login-form" class="<?php echo ($action === 'register' && !$success) ? 'hidden' : ''; ?> space-y-6">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-slate-800">Welcome Back</h2>
                    <p class="text-slate-500 mt-1">Please sign in to continue</p>
                </div>

                <form class="space-y-4" method="POST" action="login.php">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label for="login-username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" id="login-username" name="username" class="w-full px-4 py-3 rounded-lg bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition duration-200" placeholder="Enter your username" required>
                    </div>
                    <div>
                        <label for="login-password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" id="login-password" name="password" class="w-full px-4 py-3 rounded-lg bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition duration-200" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg transition duration-300 transform hover:-translate-y-0.5">
                        Login
                    </button>
                </form>

                <div class="text-center mt-6">
                    <p class="text-slate-600">Don't have an account? 
                        <button onclick="toggleForms()" type="button" class="text-brand-600 font-semibold hover:underline">Sign Up</button>
                    </p>
                </div>
            </div>

            <!-- Sign Up Form -->
            <div id="signup-form" class="<?php echo ($action === 'register' && !$success) ? '' : 'hidden'; ?> space-y-6">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-slate-800">Create Account</h2>
                    <p class="text-slate-500 mt-1">Join us and get organized</p>
                </div>

                <form class="space-y-4" method="POST" action="login.php">
                    <input type="hidden" name="action" value="register">
                    <div>
                        <label for="signup-username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" id="signup-username" name="username" class="w-full px-4 py-3 rounded-lg bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition duration-200" placeholder="Choose a username" required>
                    </div>
                    <div>
                        <label for="signup-email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" id="signup-email" name="email" class="w-full px-4 py-3 rounded-lg bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition duration-200" placeholder="you@example.com" required>
                    </div>
                    <div>
                        <label for="signup-password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <input type="password" id="signup-password" name="password" class="w-full px-4 py-3 rounded-lg bg-slate-50 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition duration-200" placeholder="Create a password" required>
                    </div>

                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg transition duration-300 transform hover:-translate-y-0.5">
                        Sign Up
                    </button>
                </form>

                <div class="text-center mt-6">
                    <p class="text-slate-600">Already have an account? 
                        <button onclick="toggleForms()" type="button" class="text-brand-600 font-semibold hover:underline">Login</button>
                    </p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
