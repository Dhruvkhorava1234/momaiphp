<?php
/**
 * User Profile & Password Settings
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo = get_db();
$user = auth_user();
$userId = (int) $user['id'];

$profileError = '';
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // 1. Update Profile Info
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $profileError = 'Name and email are required.';
        } else {
            // Check email uniqueness
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $userId]);
            if ($chk->fetchColumn() > 0) {
                $profileError = 'This email is already associated with another account.';
            } else {
                $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$name, $email, $userId]);

                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                flash_set('success', 'Profile information updated successfully!');
                redirect('profile.php');
            }
        }
    }

    // 2. Update Password
    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirmation'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $passwordError = 'Please fill in all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters long.';
        } else {
            // Fetch current password hash
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();

            if (!password_verify($currentPassword, $currentHash)) {
                $passwordError = 'The provided current password does not match our records.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $upd = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$newHash, $userId]);

                flash_set('success', 'Password changed successfully!');
                redirect('profile.php');
            }
        }
    }
}

// Reload fresh user data
$stmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

$pageTitle = 'Account Settings - MOMAI PLYWOOD';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="max-w-4xl space-y-6">
    
    <!-- Header -->
    <div class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-2xs">
        <h1 class="text-xl font-bold text-gray-800 tracking-tight">
            Account Profile & Security
        </h1>
        <p class="text-xs text-gray-500">
            Manage your personal login credentials and security settings.
        </p>
    </div>

    <!-- 1. Profile Information Card -->
    <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-2xs space-y-4">
        <div>
            <h2 class="text-sm font-bold text-gray-800">Profile Information</h2>
            <p class="text-xs text-gray-400">Update your name and primary contact email address.</p>
        </div>

        <?php if (!empty($profileError)): ?>
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                <?= e($profileError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" class="space-y-4 max-w-md">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name *</label>
                <input type="text" name="name" value="<?= e($userData['name']) ?>" required
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Email Address *</label>
                <input type="email" name="email" value="<?= e($userData['email']) ?>" required
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
            </div>

            <button type="submit" class="px-5 py-2.5 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition">
                Save Profile
            </button>
        </form>
    </div>

    <!-- 2. Update Password Card -->
    <div class="bg-white rounded-2xl p-6 border border-gray-200/80 shadow-2xs space-y-4">
        <div>
            <h2 class="text-sm font-bold text-gray-800">Update Password</h2>
            <p class="text-xs text-gray-400">Ensure your account is using a long, secure passphrase.</p>
        </div>

        <?php if (!empty($passwordError)): ?>
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                <?= e($passwordError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" class="space-y-4 max-w-md">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_password">

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Current Password *</label>
                <input type="password" name="current_password" required placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">New Password (min 8 chars) *</label>
                <input type="password" name="password" required placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Confirm New Password *</label>
                <input type="password" name="password_confirmation" required placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] outline-none">
            </div>

            <button type="submit" class="px-5 py-2.5 rounded-full bg-[#324b3e] hover:bg-[#23382f] text-white text-xs font-bold shadow-md transition">
                Update Password
            </button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
