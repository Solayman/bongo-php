<!DOCTYPE html>
<html lang="en">
<head>

</head>
<body>
<?php include base_path('resources/views/admin/include/header.php'); ?>
<!-- Sidebar -->
<?php include base_path('resources/views/admin/include/menu.php'); ?>

<div class="content">

    <?php include base_path('resources/views/admin/include/select_panel.php'); ?>

    <h2 class="mb-4 text-center text-white">🧹@yield('dashboard_menu_title') </h2>

    @yield('content')

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title text-white">🧾 Output Console</h5>
            <pre id="output">Click a button above to run a cleanup task...</pre>
        </div>
    </div>
</div>

@yield('scripts')

</body>
</html>
