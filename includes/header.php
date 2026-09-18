<?php
/**
 * Global Header Component
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? 'MOMAI PLYWOOD - Inventory Management';
$user = auth_user();
$pathPrefix = $pathPrefix ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= $pathPrefix ?>assets/images/logo.svg">
    <link rel="icon" type="image/png" href="<?= $pathPrefix ?>assets/images/logo.png">
    <link rel="shortcut icon" href="<?= $pathPrefix ?>assets/images/logo.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Compiled Botanical Tailwind CSS -->
    <link rel="stylesheet" href="<?= $pathPrefix ?>assets/css/app.css">

    <!-- jQuery & DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <!-- ApexCharts (Modern Interactive Charts & Animations) -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Alpine.js (Lightweight reactive client store) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        a { text-decoration: none; }

        /* Responsive visibility utilities (not in pre-compiled app.css) */
        @media (min-width: 1024px) {
            .lg\:hidden { display: none !important; }
        }

        ::selection {
            background-color: #1F4225 !important;
            color: #ffffff !important;
        }
        ::-moz-selection {
            background-color: #1F4225 !important;
            color: #ffffff !important;
        }

        /* Tailwind Grid & Responsive Column Utilities Fallback */
        .col-span-1 { grid-column: span 1 / span 1 !important; }
        .col-span-2 { grid-column: span 2 / span 2 !important; }
        .col-span-3 { grid-column: span 3 / span 3 !important; }
        .col-span-4 { grid-column: span 4 / span 4 !important; }
        .col-span-5 { grid-column: span 5 / span 5 !important; }
        .col-span-6 { grid-column: span 6 / span 6 !important; }
        .col-span-7 { grid-column: span 7 / span 7 !important; }
        .col-span-8 { grid-column: span 8 / span 8 !important; }
        .col-span-9 { grid-column: span 9 / span 9 !important; }
        .col-span-10 { grid-column: span 10 / span 10 !important; }
        .col-span-11 { grid-column: span 11 / span 11 !important; }
        .col-span-12 { grid-column: span 12 / span 12 !important; }

        @media (min-width: 1024px) {
            .lg\:col-span-1 { grid-column: span 1 / span 1 !important; }
            .lg\:col-span-2 { grid-column: span 2 / span 2 !important; }
            .lg\:col-span-3 { grid-column: span 3 / span 3 !important; }
            .lg\:col-span-4 { grid-column: span 4 / span 4 !important; }
            .lg\:col-span-5 { grid-column: span 5 / span 5 !important; }
            .lg\:col-span-6 { grid-column: span 6 / span 6 !important; }
            .lg\:col-span-7 { grid-column: span 7 / span 7 !important; }
            .lg\:col-span-8 { grid-column: span 8 / span 8 !important; }
            .lg\:col-span-9 { grid-column: span 9 / span 9 !important; }
            .lg\:col-span-10 { grid-column: span 10 / span 10 !important; }
            .lg\:col-span-11 { grid-column: span 11 / span 11 !important; }
            .lg\:col-span-12 { grid-column: span 12 / span 12 !important; }
            .lg\:grid-cols-12 { grid-template-columns: repeat(12, minmax(0, 1fr)) !important; }
        }

        /* Mobile Sidebar Transitions */
        .sidebar-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        @media (max-width: 1023px) {
            .mobile-sidebar {
                position: fixed !important;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 1050;
                transform: translateX(-100%);
                transition: transform 0.25s ease-in-out;
                width: 288px !important;
            }
            .mobile-sidebar.open {
                transform: translateX(0);
            }
            .main-viewport {
                height: 100vh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* Custom DataTables Styling for Botanical Theme */
        .dataTables_wrapper {
            padding: 1rem;
            font-size: 0.8125rem;
            color: #374151;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
        .dataTables_wrapper .dataTables_length select {
            padding: 0.35rem 2rem 0.35rem 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #d1d5db;
            font-size: 0.75rem;
            background-color: #f9fafb;
            outline: none;
        }
        .dataTables_wrapper .dataTables_filter input {
            padding: 0.4rem 0.85rem;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            font-size: 0.75rem;
            margin-left: 0.5rem;
            background-color: #ffffff;
            outline: none;
            transition: all 0.15s;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #324b3e;
            box-shadow: 0 0 0 3px rgba(50, 75, 62, 0.15);
        }
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            width: 100% !important;
            margin-top: 0.5rem !important;
            margin-bottom: 0.75rem !important;
            border: none !important;
        }
        table.dataTable thead th {
            background-color: #f8fafc !important;
            color: #4b5563 !important;
            font-size: 0.6875rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            border-bottom: 1px solid #e2e8f0 !important;
            border-top: none !important;
            padding: 0.875rem 1rem !important;
        }
        table.dataTable tbody td {
            padding: 0.875rem 1rem !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
        }
        table.dataTable tbody tr:hover td {
            background-color: #f8fafc !important;
        }
        table.dataTable.no-footer {
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .dataTables_wrapper .dataTables_info {
            padding-top: 1rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 0.75rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.35rem 0.75rem !important;
            border-radius: 0.5rem !important;
            margin: 0 2px !important;
            border: 1px solid transparent !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            color: #4b5563 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #324b3e !important;
            color: #ffffff !important;
            border-color: #324b3e !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e2e8f0 !important;
            color: #1f2937 !important;
        }

        @media print {
            html, body {
                height: auto !important;
                overflow: visible !important;
                background: #ffffff !important;
            }
            body * {
                visibility: hidden;
            }
            #printable-slip, #printable-slip *,
            #printable-due-slip, #printable-due-slip * {
                visibility: visible;
            }
            #printable-slip {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            #printable-due-slip {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                max-width: 420px !important;
                margin: 0 auto !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            @page {
                size: auto;
                margin: 8mm;
            }
        }
    </style>
</head>
<body x-data="{ mobileOpen: false }" class="font-sans antialiased text-gray-800 h-screen w-screen overflow-hidden bg-[#edf4f7] flex">
    
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div x-show="mobileOpen"
         x-cloak
         @click="mobileOpen = false"
         class="sidebar-backdrop fixed inset-0 z-40 lg:hidden"></div>
