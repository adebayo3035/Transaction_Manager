<?php
session_start();

if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthenticated']);
    exit();
}
$role = $_SESSION['role'];
$menuItems = [];

// Common links for both Admin and Super Admin
$menuItems[] = ['name' => 'Manage Orders', 'link' => 'dashboard.php', 'id' => 'orderFood'];
$menuItems[] = ['name' => 'View Pending Orders', 'link' => 'pending_orders.php', 'id' => 'viewOrders'];

// Links only visible to Super Admin
if ($role === 'Super Admin') {
    $menuItems[] = ['name' => 'Manage Revenue', 'link' => 'revenue_dashboard.php', 'id' => 'fundWallet'];
    $menuItems[] = ['name' => 'All Orders', 'link' => 'orders.php', 'id' => 'allOrder'];
    $menuItems[] = ['name' => 'Transactions', 'link' => 'transactions.php', 'id' => 'allTransactions'];
    $menuItems[] = ['name' => 'Inflow and Outflow', 'link' => 'revenue.php', 'id' => 'allRevenues'];
    $menuItems[] = ['name' => 'Promos', 'link' => 'promo.php', 'id' => 'promo'];
}

echo json_encode(['menuItems' => $menuItems]);
