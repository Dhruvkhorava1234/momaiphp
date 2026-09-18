<?php
/**
 * Login Controller & View
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/guest_guard.php';

$error = '';
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $emailVal = $email;

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Password matches (works 100% with existing Laravel bcrypt hashes)
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ];

            flash_set('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('../dashboard.php');
        } else {
            $error = 'These credentials do not match our records.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in - MOMAI PLYWOOD</title>

    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        ::selection {
            background-color: #1F4225 !important;
            color: #ffffff !important;
        }
        ::-moz-selection {
            background-color: #1F4225 !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-900 min-h-screen bg-cover bg-center bg-fixed relative selection:bg-[#1F4225] selection:text-white"
      style="background-image: url('../assets/images/botanical-bg.jpg');">
    <!-- Ambient depth overlay with soft blur -->
    <div class="fixed inset-0 bg-gradient-to-tr from-black/25 via-emerald-950/10 to-white/10 backdrop-blur-[4px] pointer-events-none z-0"></div>

    <main class="relative z-10 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <!-- Desktop & Tablet Split Card Design -->
        <div class="hidden md:flex w-full max-w-4xl bg-white rounded-[36px] shadow-2xl overflow-hidden min-h-[580px] border border-white/60">
            
            <!-- Left Side: Login Form -->
            <div class="w-1/2 p-10 lg:p-14 flex flex-col justify-center bg-white z-10">
                <!-- Header -->
                <div class="mb-7 text-center">
                    <div class="flex justify-center mb-3">
                        <img src="../assets/images/logo.svg" alt="Logo" class="w-12 h-12 rounded-2xl shadow-sm">
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-semibold text-gray-800 tracking-tight">
                        Log in
                    </h1>
                    <p class="text-xs text-gray-400 mt-1">MOMAI PLYWOOD Inventory & POS</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="mb-4 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($flashSuccess = flash_get('success')): ?>
                    <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs flex items-center gap-2">
                        <span><?= e($flashSuccess) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-4">
                    <?= csrf_field() ?>

                    <!-- Login / Email Input -->
                    <div>
                        <label for="email" class="block text-xs text-gray-400 font-normal mb-1.5 ml-3">
                            Login, email or phone number
                        </label>
                        <div class="relative">
                            <input id="email"
                                   type="text"
                                   name="email"
                                   value="<?= e($emailVal) ?>"
                                   required
                                   autofocus
                                   autocomplete="username"
                                   placeholder="name@example.com"
                                   class="w-full px-5 py-3 rounded-full text-sm text-gray-700 bg-white border border-gray-300 focus:border-[#23382f] focus:ring-2 focus:ring-[#23382f]/20 placeholder-gray-300 transition outline-none shadow-sm" />
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div x-data="{ show: false }">
                        <label for="password" class="block text-xs text-gray-400 font-normal mb-1.5 ml-3">
                            Password
                        </label>
                        <div class="relative">
                            <input id="password"
                                   :type="show ? 'text' : 'password'"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   placeholder="••••••••"
                                   class="w-full px-5 py-3 pr-12 rounded-full text-sm text-gray-700 bg-white border border-gray-300 focus:border-[#23382f] focus:ring-2 focus:ring-[#23382f]/20 placeholder-gray-300 transition outline-none shadow-sm" />
                            
                            <!-- Toggle Password Visibility -->
                            <button type="button"
                                    @click="show = !show"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition p-1 focus:outline-none"
                                    aria-label="Toggle password visibility">
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-3 px-6 rounded-full bg-[#344b3f] hover:bg-[#293d33] active:bg-[#203129] active:scale-[0.99] text-white font-medium text-sm tracking-wide shadow-md hover:shadow-lg transition-all duration-150">
                            Log in
                        </button>
                    </div>

                    <!-- <div class="text-center pt-3 text-xs text-gray-400">
                        Default login: <span class="font-mono text-gray-600">admin@momai.com</span> / <span class="font-mono text-gray-600">password123</span>
                    </div> -->
                </form>
            </div>

            <!-- Right Side: Botanical Leaves Image Banner -->
            <div class="w-1/2 relative bg-[#13251c] overflow-hidden select-none">
                <img src="../assets/images/papercut-leaves.png"
                     alt="Botanical papercut foliage"
                     class="w-full h-full object-cover object-left" />
                <div class="absolute inset-0 bg-gradient-to-t from-[#13251c]/90 via-transparent to-transparent flex items-end p-10">
                    <!-- <div>
                        <h3 class="text-white text-xl font-bold tracking-wide">MOMAI PLYWOOD</h3>
                        <p class="text-emerald-200 text-xs mt-1">High Performance Offline & Cloud Inventory System</p>
                    </div> -->
                </div>
            </div>
        </div>

        <!-- Mobile Card Fallback -->
        <div class="md:hidden w-full max-w-sm bg-white rounded-3xl p-6 shadow-xl border border-white/80">
            <div class="mb-5 text-center">
                <img src="../assets/images/logo.svg" alt="Logo" class="w-10 h-10 mx-auto rounded-xl mb-2">
                <h1 class="text-xl font-bold text-gray-800">Log in</h1>
                <p class="text-xs text-gray-400">MOMAI PLYWOOD</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1 ml-1">Email or Username</label>
                    <input type="text" name="email" value="<?= e($emailVal) ?>" required class="w-full px-4 py-2.5 rounded-full text-xs border border-gray-300 outline-none focus:border-[#23382f]">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1 ml-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 rounded-full text-xs border border-gray-300 outline-none focus:border-[#23382f]">
                </div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-full bg-[#344b3f] text-white text-xs font-semibold shadow-md">
                    Log in
                </button>
            </form>
        </div>
    </main>
</body>
</html>
