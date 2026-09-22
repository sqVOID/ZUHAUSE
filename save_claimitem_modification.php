<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

// Only Super-Admin allowed
$system_level = isset($_SESSION['system_level']) ? trim($_SESSION['system_level']) : '';
if (strcasecmp($system_level, 'Super-Admin') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

try {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (!$data) {
        throw new Exception('Invalid JSON data received.');
    }

    // --- Required fields ---
    if (empty($data['invoiceNumber'])) {
        throw new Exception('Invoice number is required.');
    }
    if (empty($data['reason'])) {
        throw new Exception('Reason for modification is required.');
    }
    if (empty($data['modifications']) || !is_array($data['modifications'])) {
        throw new Exception('No modification data provided.');
    }

    $claimId       = intval($data['claimId'] ?? 0);
    $invoiceNumber = trim($data['invoiceNumber']);
    $customerName  = trim($data['customerName'] ?? '');
    $branch        = trim($data['branch'] ?? '');
    $reason        = trim($data['reason']);
    $modifications = $data['modifications'];

    $modifiedBy = $_SESSION['username'] ?? 'system';

    // --- Begin transaction ---
    $conn->begin_transaction();

    $updatedCount = 0;
    $deletedCount = 0;
    $errors       = [];

    foreach ($modifications as $mod) {
        $itemId         = intval($mod['itemId'] ?? 0);
        $originalStatus = strtolower(trim($mod['originalStatus'] ?? ''));
        $newStatus      = trim($mod['newStatus']   ?? '');
        $dateField      = trim($mod['dateField']   ?? '');
        $newDate        = trim($mod['newDate']     ?? '');
        $newQuantity    = $mod['newQuantity'] !== null ? intval($mod['newQuantity']) : null;

        if ($itemId <= 0) {
            $errors[] = "Invalid item ID: $itemId";
            continue;
        }

        // --- REMOVE action ---
        if ($newStatus === 'remove') {
            // Fetch current record details
            $fetchStmt = $conn->prepare(
                "SELECT status, item_code, item_description, quantity, branch
                 FROM unclaimed_freebies WHERE id = ?"
            );
            if (!$fetchStmt) {
                throw new Exception('Failed to prepare fetch before delete: ' . $conn->error);
            }
            $fetchStmt->bind_param('i', $itemId);
            $fetchStmt->execute();
            $fetchResult = $fetchStmt->get_result();
            $fetchRow    = $fetchResult->fetch_assoc();
            $fetchStmt->close();

            if (!$fetchRow) {
                $errors[] = "Record ID $itemId not found, skipping.";
                continue;
            }

            $recStatus   = strtolower($fetchRow['status']);
            $recItemCode = trim($fetchRow['item_code']);
            $recQty      = intval($fetchRow['quantity']);
            $recBranch   = trim($fetchRow['branch']);
            $recDesc     = $fetchRow['item_description'];

            if ($originalStatus === 'claimed') {
                // -------------------------------------------------------
                // Removing the CLAIMED status only:
                // Revert the unclaimed_freebies row back to 'unclaimed'
                // and clear claimed_at — do NOT delete the row.
                // -------------------------------------------------------
                $revertStmt = $conn->prepare(
                    "UPDATE unclaimed_freebies
                     SET status = 'unclaimed',
                         claimed_at = NULL,
                         note = ?
                     WHERE id = ?"
                );
                if (!$revertStmt) {
                    throw new Exception('Failed to prepare revert UPDATE: ' . $conn->error);
                }
                $revertStmt->bind_param('si', $reason, $itemId);
                if (!$revertStmt->execute()) {
                    throw new Exception('Failed to revert claimed record ID ' . $itemId . ': ' . $revertStmt->error);
                }
                $revertStmt->close();
                $updatedCount++;

                // Remove the matching claimed_items row
                $delClaimedStmt = $conn->prepare("DELETE FROM claimed_items WHERE unclaimed_freebie_id = ?");
                if ($delClaimedStmt) {
                    $delClaimedStmt->bind_param('i', $itemId);
                    $delClaimedStmt->execute();
                    $delClaimedStmt->close();
                }

                // Restore stock because the item was claimed (stock was deducted)
                if (!empty($recItemCode) && !empty($recBranch) && $recQty > 0) {
                    $stockUpdateStmt = $conn->prepare(
                        "UPDATE stock_on_hand
                         SET quantity = quantity + ?
                         WHERE TRIM(item_code) = TRIM(?) AND TRIM(branch) = TRIM(?)"
                    );
                    if (!$stockUpdateStmt) {
                        throw new Exception('Failed to prepare stock restore UPDATE: ' . $conn->error);
                    }
                    $stockUpdateStmt->bind_param('iss', $recQty, $recItemCode, $recBranch);
                    $stockUpdateStmt->execute();
                    $affectedRows = $stockUpdateStmt->affected_rows;
                    $stockUpdateStmt->close();

                    if ($affectedRows === 0) {
                        $stockInsertStmt = $conn->prepare(
                            "INSERT INTO stock_on_hand (item_code, item_description, quantity, branch)
                             VALUES (?, ?, ?, ?)"
                        );
                        if (!$stockInsertStmt) {
                            throw new Exception('Failed to prepare stock restore INSERT: ' . $conn->error);
                        }
                        $stockInsertStmt->bind_param('ssis', $recItemCode, $recDesc, $recQty, $recBranch);
                        if (!$stockInsertStmt->execute()) {
                            throw new Exception('Failed to insert stock restore for item ' . $recItemCode . ': ' . $stockInsertStmt->error);
                        }
                        $stockInsertStmt->close();
                    }

                    $stockRestoredItems[] = "$recItemCode (+$recQty) @ $recBranch";
                }

            } else {
                // -------------------------------------------------------
                // Removing the UNCLAIMED record entirely — full DELETE
                // -------------------------------------------------------
                $delStmt = $conn->prepare("DELETE FROM unclaimed_freebies WHERE id = ?");
                if (!$delStmt) {
                    throw new Exception('Failed to prepare DELETE: ' . $conn->error);
                }
                $delStmt->bind_param('i', $itemId);
                if (!$delStmt->execute()) {
                    throw new Exception('Failed to delete record ID ' . $itemId . ': ' . $delStmt->error);
                }
                $delStmt->close();
                $deletedCount++;

                // Also remove claimed_items if any
                $delClaimedStmt = $conn->prepare("DELETE FROM claimed_items WHERE unclaimed_freebie_id = ?");
                if ($delClaimedStmt) {
                    $delClaimedStmt->bind_param('i', $itemId);
                    $delClaimedStmt->execute();
                    $delClaimedStmt->close();
                }

                // Restore stock only if the actual DB status was claimed
                if ($recStatus === 'claimed' && !empty($recItemCode) && !empty($recBranch) && $recQty > 0) {
                    $stockUpdateStmt = $conn->prepare(
                        "UPDATE stock_on_hand
                         SET quantity = quantity + ?
                         WHERE TRIM(item_code) = TRIM(?) AND TRIM(branch) = TRIM(?)"
                    );
                    if (!$stockUpdateStmt) {
                        throw new Exception('Failed to prepare stock restore UPDATE: ' . $conn->error);
                    }
                    $stockUpdateStmt->bind_param('iss', $recQty, $recItemCode, $recBranch);
                    $stockUpdateStmt->execute();
                    $affectedRows = $stockUpdateStmt->affected_rows;
                    $stockUpdateStmt->close();

                    if ($affectedRows === 0) {
                        $stockInsertStmt = $conn->prepare(
                            "INSERT INTO stock_on_hand (item_code, item_description, quantity, branch)
                             VALUES (?, ?, ?, ?)"
                        );
                        if (!$stockInsertStmt) {
                            throw new Exception('Failed to prepare stock restore INSERT: ' . $conn->error);
                        }
                        $stockInsertStmt->bind_param('ssis', $recItemCode, $recDesc, $recQty, $recBranch);
                        if (!$stockInsertStmt->execute()) {
                            throw new Exception('Failed to insert stock restore for item ' . $recItemCode . ': ' . $stockInsertStmt->error);
                        }
                        $stockInsertStmt->close();
                    }

                    $stockRestoredItems[] = "$recItemCode (+$recQty) @ $recBranch";
                }
            }

            continue;
        }


        // --- BUILD UPDATE ---
        $setParts = [];
        $params   = [];
        $types    = '';

        // Update status
        if (!empty($newStatus) && in_array($newStatus, ['claimed', 'unclaimed'])) {
            $setParts[] = 'status = ?';
            $params[]   = $newStatus;
            $types     .= 's';
        }

        $createdAt   = trim($mod['createdAt']   ?? '');
        $claimedAt   = trim($mod['claimedAt']   ?? '');

        // Update created_at
        if (!empty($createdAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdAt)) {
            $setParts[] = "`created_at` = ?";
            $params[]   = $createdAt . ' 00:00:00';
            $types     .= 's';
        }

        // Update claimed_at
        if (!empty($claimedAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $claimedAt)) {
            $setParts[] = "`claimed_at` = ?";
            $params[]   = $claimedAt . ' 00:00:00';
            $types     .= 's';
        }

        // Fallback for single date field
        if (empty($createdAt) && empty($claimedAt) && !empty($newDate) && !empty($dateField) && in_array($dateField, ['created_at', 'claimed_at'])) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
                $setParts[] = "`$dateField` = ?";
                $params[]   = $newDate . ' 00:00:00';
                $types     .= 's';
            }
        }

        // Update quantity
        if ($newQuantity !== null && $newQuantity >= 0) {
            $setParts[] = 'quantity = ?';
            $params[]   = $newQuantity;
            $types     .= 'i';
        }

        // Always update note (reason) for every record
        $setParts[] = 'note = ?';
        $params[]   = $reason;
        $types     .= 's';

        if (empty($setParts)) {
            continue;
        }

        // Append itemId for WHERE clause
        $params[] = $itemId;
        $types   .= 'i';

        $sql = "UPDATE unclaimed_freebies SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare UPDATE: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update record ID ' . $itemId . ': ' . $stmt->error);
        }
        $updatedCount++;
        $stmt->close();
    }

    // --- Also update invoice_number, branch on ALL records for this invoice
    //     (in case the user changed the invoice number or branch fields)
    if (!empty($invoiceNumber)) {
        // First fetch all record IDs currently tied to the original claimId's invoice
        $invStmt = $conn->prepare(
            "SELECT invoice_number FROM unclaimed_freebies WHERE id = ?"
        );
        if ($invStmt) {
            $invStmt->bind_param('i', $claimId);
            $invStmt->execute();
            $invResult = $invStmt->get_result();
            $invRow    = $invResult->fetch_assoc();
            $invStmt->close();

            $originalInvoice = $invRow['invoice_number'] ?? '';

            if (!empty($originalInvoice)) {
                // Update invoice_number and branch for ALL records of this invoice
                $bulkStmt = $conn->prepare(
                    "UPDATE unclaimed_freebies 
                     SET invoice_number = ?, branch = ?
                     WHERE invoice_number = ?"
                );
                if ($bulkStmt) {
                    $bulkStmt->bind_param('sss', $invoiceNumber, $branch, $originalInvoice);
                    $bulkStmt->execute();
                    $bulkStmt->close();
                }
            }
        }
    }

    // --- Commit ---
    $conn->commit();

    $message = "Modification saved successfully.";
    if ($updatedCount > 0) {
        $message .= " Updated $updatedCount record(s).";
    }
    if ($deletedCount > 0) {
        $message .= " Removed $deletedCount record(s).";
    }
    if (!empty($stockRestoredItems)) {
        $message .= " Stock restored: " . implode(', ', $stockRestoredItems) . ".";
    }
    if (!empty($errors)) {
        $message .= ' Warnings: ' . implode('; ', $errors);
    }

    echo json_encode([
        'success'               => true,
        'message'               => $message,
        'updated_count'         => $updatedCount,
        'deleted_count'         => $deletedCount,
        'stock_restored_items'  => $stockRestoredItems ?? [],
        'errors'                => $errors
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
