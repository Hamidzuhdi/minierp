<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Mini ERP Bengkel'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #212529;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #adb5bd;
            padding: .75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: #343a40;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #0d6efd;
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        .navbar {
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
        }
        main {
            margin-left: 240px;
            padding-top: 56px;
        }
        @media (max-width: 768px) {
            main {
                margin-left: 0;
            }
            .sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-tools"></i> Mini ERP Bengkel
            </a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['username'] ?? 'User'; ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../users/index.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../customers/index.php">
                        <i class="fas fa-user-tie"></i> Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../vehicles/index.php">
                        <i class="fas fa-car"></i> Vehicles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../spareparts/index.php">
                        <i class="fas fa-cog"></i> Spareparts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../services/index.php">
                        <i class="fas fa-wrench"></i> Harga Jasa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../purchases/index.php">
                        <i class="fas fa-shopping-cart"></i> Purchases
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../spk/index.php">
                        <i class="fas fa-clipboard-list"></i> SPK
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../warehouse_out/index.php">
                        <i class="fas fa-dolly"></i> Warehouse Out
                    </a>
                </li>
                <?php
                // Menu Invoice hanya untuk Owner
                $user_role = $_SESSION['role'] ?? 'Admin';
                if ($user_role === 'Owner'):
                ?>
                <li class="nav-item">
                    <a class="nav-link" href="../invoices/index.php">
                        <i class="fas fa-file-invoice"></i> Invoices
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="../audit/index.php">
                        <i class="fas fa-history"></i> Audit Log
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
