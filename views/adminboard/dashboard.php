<?php
session_start();
// Mock data for now
$_SESSION['user_name'] = 'Admin';
$_SESSION['user_role'] = 'Owner Admin';
$_SESSION['shop_name'] = 'Marilao Gold Pawn';

// Set the title for the header dynamically!
$pageTitle = 'Dashboard Overview';

// Include the header (which also brings in the sidebar)
include '../../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
    </div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    </div>
<?php 
// Include the footer to close it up
include '../../includes/footer.php'; 
?>