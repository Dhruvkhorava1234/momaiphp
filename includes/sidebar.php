<?php
/**
 * Sidebar Navigation Component
 * MOMAI PLYWOOD - Core PHP
 */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$user = auth_user();
$pathPrefix = $pathPrefix ?? '';
?>
<!-- Left Full-Height Dark Sidebar (Palette: #23382f, #293b32) -->
<aside :class="mobileOpen ? 'open' : ''"
       class="mobile-sidebar w-72 bg-[#23382f] text-gray-200 flex flex-col justify-between shrink-0 p-5 border-r border-[#1a2d25] h-full overflow-y-auto select-none z-50 lg:static lg:translate-x-0">
    <div>
        <!-- Brand Title -->
        <div class="flex items-center justify-between pb-5 border-b border-[#2e473d]">
            <a href="<?= $pathPrefix ?>dashboard.php" class="flex items-center gap-2.5">
                <img src="<?= $pathPrefix ?>assets/images/logo.svg" alt="MOMAI PLYWOOD Logo" class="w-9 h-9 rounded-xl shadow-sm object-cover" />
                <div>
                    <span class="font-bold text-sm text-white tracking-wide block">MOMAI PLYWOOD</span>
                    <span class="text-[10px] text-emerald-300 font-medium">Inventory POS</span>
                </div>
            </a>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-[#1b2b24] text-emerald-400 font-bold border border-[#2e473d]">
                <?= date('Y') ?>
            </span>
        </div>

        <!-- Admin Profile: Premium Minimal Card -->
        <div class="relative overflow-hidden p-3.5 my-3 rounded-2xl bg-gradient-to-b from-[#2a4539] to-[#1c3027] border border-[#3b5a4b]/60 shadow-md">
            <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-[#3b5e4d] via-[#4d7863] to-[#72a38a] flex items-center justify-center text-white font-bold text-sm shadow-inner ring-2 ring-emerald-400/20">
                        <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <!-- Glowing Online Status Dot -->
                    <span class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 border-2 border-[#23382f]"></span>
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <h2 class="font-bold text-xs text-white tracking-wide truncate">
                            <?= e($user['name'] ?? 'Administrator') ?>
                        </h2>
                    </div>
                    <p class="text-[11px] text-emerald-200/80 font-normal truncate mt-0.5">
                        <?= e($user['email'] ?? 'admin@momai.com') ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="mt-3 space-y-1.5 font-medium text-xs">
            <!-- Dashboard -->
            <a href="<?= $pathPrefix ?>dashboard.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentPage === 'dashboard.php') ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= ($currentPage === 'dashboard.php') ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                <span>Dashboard</span>
            </a>

            <!-- Make Bill (POS) -->
            <a href="<?= $pathPrefix ?>bill-create.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentPage === 'bill-create.php') ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= ($currentPage === 'bill-create.php') ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>Make Bill (POS)</span>
            </a>

            <!-- Orders & Bill History -->
            <a href="<?= $pathPrefix ?>bills.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= in_array($currentPage, ['bills.php', 'bill-slip.php', 'bill-due-slip.php']) ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= in_array($currentPage, ['bills.php', 'bill-slip.php', 'bill-due-slip.php']) ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Bills & History</span>
            </a>

            <!-- Return Bills (Sales Returns) -->
            <a href="<?= $pathPrefix ?>bill-returns.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= in_array($currentPage, ['bill-returns.php', 'return-slip.php']) ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= in_array($currentPage, ['bill-returns.php', 'return-slip.php']) ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                <span>Return Bills</span>
            </a>

            <!-- Inventory & Products -->
            <a href="<?= $pathPrefix ?>products.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentPage === 'products.php') ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= ($currentPage === 'products.php') ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span>Inventory & Stock</span>
            </a>

            <!-- Reporting -->
            <a href="<?= $pathPrefix ?>reports.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentPage === 'reports.php') ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= ($currentPage === 'reports.php') ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Reporting</span>
            </a>

            <!-- Settings -->
            <a href="<?= $pathPrefix ?>profile.php"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentPage === 'profile.php') ? 'bg-[#edf4f7] text-[#23382f] font-bold shadow-md' : 'text-gray-300 hover:bg-[#2e473d] hover:text-white' ?>">
                <svg class="w-4 h-4 <?= ($currentPage === 'profile.php') ? 'text-[#23382f]' : 'text-emerald-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Settings</span>
            </a>
        </nav>
    </div>

    <!-- Bottom Logout Button -->
    <div class="pt-4 border-t border-[#2e473d]">
        <form method="POST" action="<?= $pathPrefix ?>auth/logout.php">
            <?= csrf_field() ?>
            <button type="submit"
                    class="w-full flex items-center gap-3 px-3.5 py-2 text-xs text-gray-300 hover:text-white hover:bg-rose-900/30 rounded-xl transition duration-150 font-medium">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>

<!-- Right Side Full-Width Content Area -->
<div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-[#edf4f7] main-viewport">
