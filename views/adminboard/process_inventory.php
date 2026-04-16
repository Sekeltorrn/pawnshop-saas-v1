<?php
session_start();
require_once '../../config/db_connect.php';

// 1. Validate Tenant Context
$schemaName = $_SESSION['schema_name'] ?? '';
if (!$schemaName) {
    die("Security Error: Missing Tenant Context.");
}

// 2. Process POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $item_id = $_POST['item_id'] ?? '';
    $item_ids = $_POST['item_ids'] ?? [];

    if (empty($action) || (empty($item_id) && empty($item_ids))) {
        header("Location: inventory.php?error=Missing+Data");
        exit;
    }

    try {
        // Set the search path to the specific shop's schema
        $pdo->exec("SET search_path TO \"$schemaName\", public;");

        // 3. The State Machine Action Router
        if ($action === 'update_location') {
            $location = trim($_POST['storage_location'] ?? '');
            
            $stmt = $pdo->prepare("UPDATE inventory SET storage_location = ?, updated_at = NOW() WHERE item_id = ?");
            $stmt->execute([$location, $item_id]);
            
            $_SESSION['toast_success'] = "Vault location updated successfully.";
        } 
        
        elseif ($action === 'bulk_update_location') {
            $item_ids = $_POST['item_ids'] ?? [];
            $new_location = trim($_POST['bulk_storage_location'] ?? '');
            
            if (empty($item_ids) || empty($new_location)) {
                $_SESSION['toast_error'] = "Please select items and provide a new location.";
                header("Location: inventory.php");
                exit;
            }

            // Create dynamic placeholders for the IN clause (e.g., ?, ?, ?)
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            
            // Merge the location with the array of IDs for execution
            $params = array_merge([$new_location], $item_ids);
            
            $stmt = $pdo->prepare("UPDATE inventory SET storage_location = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
            $stmt->execute($params);
            
            $_SESSION['toast_success'] = count($item_ids) . " items relocated to " . htmlspecialchars($new_location);
        }
        
        elseif ($action === 'move_to_retail') {
            $price = floatval($_POST['retail_price'] ?? 0);
            
            // Move to retail floor and lock in the price tag
            $stmt = $pdo->prepare("UPDATE inventory SET item_status = 'for_sale', retail_price = ?, updated_at = NOW() WHERE item_id = ?");
            $stmt->execute([$price, $item_id]);
            
            $_SESSION['toast_success'] = "Item priced and moved to retail display.";
        } 
        
        elseif ($action === 'mark_sold') {
            $stmt = $pdo->prepare("UPDATE inventory SET item_status = 'sold', updated_at = NOW() WHERE item_id = ?");
            $stmt->execute([$item_id]);
            
            $_SESSION['toast_success'] = "Item successfully marked as sold.";
        }

        // ROUTE 3: Bulk moving items to a new storage location
        elseif ($action === 'bulk_move') {
            $item_ids = $_POST['item_ids'] ?? [];
            $new_location = $_POST['new_location'] ?? '';

            if (!empty($item_ids) && !empty($new_location)) {
                // Create placeholders for the IN clause (?, ?, ?)
                $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
                
                // Build parameters array: location first, then all IDs
                $params = array_merge([$new_location], $item_ids);
                
                $stmt = $pdo->prepare("UPDATE inventory SET storage_location = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
                $stmt->execute($params);
                
                $_SESSION['toast_success'] = count($item_ids) . " items relocated to " . htmlspecialchars($new_location);
            }
        }

        // ROUTE: Bulk assign retail items to a lot directly from the Hub
        elseif ($action === 'bulk_assign_lot') {
            $item_ids = $_POST['item_ids'] ?? [];
            $lot_name = strtoupper(trim($_POST['lot_name'] ?? ''));
            $lot_price = !empty($_POST['lot_price']) ? (float)$_POST['lot_price'] : null;

            if (!empty($item_ids) && !empty($lot_name)) {
                // 1. Ensure the lot exists in the master list so it doesn't "zombie" away
                $stmt_master = $pdo->prepare("INSERT INTO retail_lots (lot_name, lot_price) VALUES (?, ?) ON CONFLICT (lot_name) DO NOTHING");
                $stmt_master->execute([$lot_name, $lot_price]);

                $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
                
                // 2. Assign the items to this lot
                if ($lot_price !== null) {
                    $params = array_merge([$lot_name, $lot_price], $item_ids);
                    $stmt = $pdo->prepare("UPDATE inventory SET lot_name = ?, lot_price = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
                } else {
                    $params = array_merge([$lot_name], $item_ids);
                    $stmt = $pdo->prepare("UPDATE inventory SET lot_name = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
                }
                $stmt->execute($params);
                
                $_SESSION['toast_success'] = count($item_ids) . " items assigned to Lot: " . htmlspecialchars($lot_name);
            }
        }

        // Redirect back to the inventory hub to see the updated state
        header("Location: inventory.php");
        exit;

    } catch (PDOException $e) {
        error_log("Inventory Process Error: " . $e->getMessage());
        header("Location: inventory.php?error=System+Failure");
        exit;
    }
} else {
    header("Location: inventory.php");
    exit;
}
?>
