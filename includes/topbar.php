<?php
/**
 * Topbar Component
 * MOMAI PLYWOOD - Core PHP
 */
$user = auth_user();
$firstName = explode(' ', $user['name'] ?? 'Nirmal')[0];
$pathPrefix = $pathPrefix ?? '';
?>
<!-- Top Navbar Bar (Full Screen & Responsive) -->
<header class="px-4 sm:px-6 lg:px-8 py-3 bg-white border-b border-gray-200/80 shrink-0 flex items-center justify-between gap-3 z-10 shadow-2xs">
    <!-- Left Title & Mobile Hamburger Button -->
    <div class="flex items-center gap-3 min-w-0">
        <!-- Mobile Hamburger Button -->
        <button type="button"
                @click="mobileOpen = true"
                class="lg:hidden p-2 rounded-xl text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none transition shrink-0"
                aria-label="Open Navigation">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <div class="truncate">
            <h2 class="text-base sm:text-lg lg:text-xl font-bold text-gray-800 tracking-tight truncate">
                Welcome <?= e($firstName) ?> !
            </h2>
            <p class="text-[11px] text-gray-500 font-medium hidden sm:block lg:hidden truncate">Inventory & POS</p>
        </div>
        <span class="hidden md:inline-block text-xs text-gray-400 font-medium">|</span>
        <span class="hidden md:inline-block text-xs font-semibold text-gray-600 uppercase tracking-wider">Inventory & POS Dashboard</span>
    </div>

    <!-- Right Search & Quick Actions -->
    <div class="flex items-center gap-3">
        <!-- Global AJAX Autosearch -->
        <div x-data="globalAutoSearch()" class="relative hidden sm:block w-72" @click.away="isOpen = false">
            <form action="<?= $pathPrefix ?>products.php" method="GET" class="relative">
                <input type="text"
                       name="search"
                       x-model="query"
                       @input="onInput()"
                       @focus="if (results.length > 0) isOpen = true"
                       placeholder="Search products, SKU, company..."
                       autocomplete="off"
                       class="w-full pl-4 pr-9 py-1.5 rounded-full text-xs bg-gray-50 border border-gray-300 focus:bg-white focus:border-[#324b3e] focus:ring-2 focus:ring-[#324b3e]/20 outline-none transition" />
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </form>

            <!-- Autosearch Dropdown Results -->
            <div x-show="isOpen"
                 x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-gray-200/90 py-2 z-50 max-h-80 overflow-y-auto">
                
                <!-- Loading indicator -->
                <div x-show="loading" class="px-4 py-3 text-center text-xs text-gray-400 flex items-center justify-center gap-2">
                    <svg class="animate-spin h-3.5 w-3.5 text-[#324b3e]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Searching...</span>
                </div>

                <!-- Results list -->
                <template x-for="item in results" :key="item.id">
                    <a :href="'<?= $pathPrefix ?>products.php?search=' + encodeURIComponent(item.name)"
                       class="block px-4 py-2.5 hover:bg-gray-50 border-b border-gray-100 last:border-0 transition">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-bold text-xs text-gray-800" x-text="item.name"></div>
                            <span class="font-mono text-xs font-bold text-[#324b3e]" x-text="'₹' + Number(item.selling_price).toLocaleString('en-IN')"></span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-gray-400 font-mono">
                            <span class="font-semibold text-gray-600" x-text="item.sku"></span>
                            <template x-if="item.company_name">
                                <span>• <span class="text-indigo-600 font-sans" x-text="item.company_name"></span></span>
                            </template>
                            <span>• <span class="text-gray-500 font-sans" x-text="item.category"></span></span>
                            <span>• <span :class="item.stock_quantity <= 0 ? 'text-rose-500 font-bold' : 'text-emerald-600 font-bold'" x-text="item.stock_quantity + ' ' + item.unit"></span></span>
                        </div>
                    </a>
                </template>

                <!-- No results found -->
                <div x-show="!loading && results.length === 0 && query.trim().length >= 2"
                     class="px-4 py-3 text-center text-xs text-gray-400">
                    No matching products found for "<span class="font-bold text-gray-600" x-text="query"></span>"
                </div>
            </div>
        </div>

        <!-- Status Pill -->
        <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-full px-3 py-1 text-xs text-emerald-800 shadow-2xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="font-semibold text-[11px]">System Online</span>
        </div>
    </div>
</header>

<!-- Alerts -->
<?php if ($flashSuccess = flash_get('success')): ?>
    <div class="mx-4 sm:mx-6 lg:mx-8 mt-4 p-3 rounded-xl bg-emerald-100/90 border border-emerald-300 text-emerald-900 text-xs flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2 font-medium">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span><?= e($flashSuccess) ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if ($flashError = flash_get('error')): ?>
    <div class="mx-4 sm:mx-6 lg:mx-8 mt-4 p-3 rounded-xl bg-rose-100/90 border border-rose-300 text-rose-900 text-xs flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2 font-medium">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= e($flashError) ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content Area -->
<main class="flex-1 overflow-y-auto px-3 pt-3 pb-20 sm:p-5 lg:p-8 space-y-4 sm:space-y-6" style="padding-bottom: max(5rem, calc(1.25rem + env(safe-area-inset-bottom)))">
