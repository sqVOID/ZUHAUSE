<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$st_number = isset($_POST['st_number']) ? $conn->real_escape_string($_POST['st_number']) : '';
$status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
$approver = isset($_SESSION['username']) ? $conn->real_escape_string($_SESSION['username']) : '';
$receiver = isset($_SESSION['username']) ? $conn->real_escape_string($_SESSION['username']) : '';

if (empty($st_number) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
if (!in_array($status, ['Approved', 'Disapproved', 'Received'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// We'll update status and (if Approved) move the stock in a single transaction.
$conn->begin_transaction();
try {
    // Lock transfer row so double-click doesn't double-apply stock changes.
    $stmt = $conn->prepare("SELECT st_number, st_date, branch_from, branch_to, status FROM stock_transfers WHERE st_number = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('s', $st_number);
    $stmt->execute();
    $transfer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transfer) {
        throw new Exception("Transfer not found");
    }

    $currentStatus = $transfer['status'] ?? 'Pending';
    $stDate = $transfer['st_date'];
    $branchFromInput = $transfer['branch_from'];
    $branchToInput = $transfer['branch_to'];

    // If already in the requested status, don't re-apply changes.
    if ($currentStatus === $status) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Transfer already $status"]);
        exit;
    }

    // Update transfer status first (still in transaction).
    if ($status === 'Disapproved') {
        // For disapproval, also save the disapproved_by user
        $sql = "UPDATE stock_transfers
                SET status = ?,
                    approver = ?,
                    approval_date = NOW(),
                    disapproved_by = ?
                WHERE st_number = ?";
        $stmtStatus = $conn->prepare($sql);
        $stmtStatus->bind_param('ssss', $status, $approver, $approver, $st_number);
        $stmtStatus->execute();
        $stmtStatus->close();
        
        // No stock movement on disapproval.
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Transfer Disapproved successfully"]);
        exit;
    } elseif ($status === 'Approved') {
        // For approval, set approver and approval_date
        $sql = "UPDATE stock_transfers
                SET status = ?,
                    approver = ?,
                    approval_date = NOW()
                WHERE st_number = ?";
        $stmtStatus = $conn->prepare($sql);
        $stmtStatus->bind_param('sss', $status, $approver, $st_number);
        $stmtStatus->execute();
        $stmtStatus->close();
        
        // Approval stage only marks the transfer as approved.
        // Actual stock movement happens when the transfer is received.
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Transfer approved successfully"]);
        exit;
    } elseif ($status === 'Received') {
        // For receiving, ONLY update status - DO NOT touch approver or approval_date
        $sql = "UPDATE stock_transfers
                SET status = ?
                WHERE st_number = ?";
        $stmtStatus = $conn->prepare($sql);
        $stmtStatus->bind_param('ss', $status, $st_number);
        $stmtStatus->execute();
        $stmtStatus->close();
    } else {
        throw new Exception("Unsupported status: {$status}");
    }

    if ($status !== 'Received') {
        throw new Exception("Unexpected flow - should have exited by now");
    }

    // ----- Received: Update received_by and received_date -----
    $sqlReceived = "UPDATE stock_transfers 
                    SET received_by = ?, 
                        received_date = NOW() 
                    WHERE st_number = ?";
    $stmtReceived = $conn->prepare($sqlReceived);
    $stmtReceived->bind_param('ss', $receiver, $st_number);
    $stmtReceived->execute();
    $stmtReceived->close();

    // ----- Received: move stock -----
    // Branch mapping:
    // - stock_transfers.branch_from is typically branch_code
    // - stock_transfers.branch_to is typically branch_name
    $fromInput = (string)$branchFromInput;
    $toInput = (string)$branchToInput;

    $fromName = null;
    $fromCodeFromName = null;
    $toCode = null;
    $toName = null;

    // If fromInput is a branch_code, fetch its name.
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? LIMIT 1");
    $stmt->bind_param('s', $fromInput);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $fromName = $res->fetch_assoc()['branch_name'] ?? null;
    }
    $stmt->close();

    // If fromInput is a branch_name, fetch its code.
    $stmt = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ? LIMIT 1");
    $stmt->bind_param('s', $fromInput);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $fromCodeFromName = $res->fetch_assoc()['branch_code'] ?? null;
    }
    $stmt->close();

    // Resolve toInput as branch_name -> branch_code (most common)
    $stmt = $conn->prepare("SELECT branch_code FROM branches WHERE branch_name = ? LIMIT 1");
    $stmt->bind_param('s', $toInput);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $toCode = $res->fetch_assoc()['branch_code'] ?? null;
    }
    $stmt->close();

    // Resolve toInput as branch_code -> branch_name (fallback)
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = ? LIMIT 1");
    $stmt->bind_param('s', $toInput);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $toName = $res->fetch_assoc()['branch_name'] ?? null;
    }
    $stmt->close();

    // Defaults when lookups fail.
    if ($toName === null) $toName = $toInput;
    if ($toCode === null) $toCode = $toInput;

    // Candidate from-branch values (stock_on_hand.branch might store either code or name).
    $fromCandidates = array_values(array_unique(array_filter([$fromInput, $fromName, $fromCodeFromName])));
    if (count($fromCandidates) === 0) {
        throw new Exception("Unable to resolve from-branch for transfer");
    }

    // Candidate values that represent "code" (for deciding destination representation).
    $fromCodeValues = array_values(array_unique(array_filter([
        ($fromName !== null ? $fromInput : null), // fromInput is a code if it resolved to a name
        $fromCodeFromName, // fromInput is a name if it resolved to a code
    ])));

    $fromPlaceholders = implode(',', array_fill(0, count($fromCandidates), '?'));

    // Load transfer items.
    $stmtItems = $conn->prepare("SELECT item_code, item_description, imei, quantity FROM stock_transfer_items WHERE st_number = ?");
    $stmtItems->bind_param('s', $st_number);
    $stmtItems->execute();
    $itemsRes = $stmtItems->get_result();

    while ($item = $itemsRes->fetch_assoc()) {
        $itemCode = (string)($item['item_code'] ?? '');
        $itemDesc = (string)($item['item_description'] ?? '');
        $imei = isset($item['imei']) ? trim((string)$item['imei']) : '';
        $qty = (int)($item['quantity'] ?? 0);

        if ($itemCode === '' || $qty <= 0) {
            continue;
        }

        if (!empty($imei)) {
            // Serialized item (IMEI): move the single stock row for that IMEI.
            // Fetch the actual stock row so we can detect whether stock_on_hand.branch stores code or name.
            $sqlMoveSerial = "SELECT id, branch
                               FROM stock_on_hand
                               WHERE item_code = ?
                                 AND item_type = 'IMEI'
                                 AND imei = ?
                                 AND branch IN ($fromPlaceholders)
                               LIMIT 1";

            $stmtSerial = $conn->prepare($sqlMoveSerial);

            $types = str_repeat('s', 2 + count($fromCandidates));
            $bindParams = array_merge([$itemCode, $imei], $fromCandidates);
            $stmtSerial->bind_param($types, ...$bindParams);
            $stmtSerial->execute();
            $rowSerial = $stmtSerial->get_result()->fetch_assoc();
            $stmtSerial->close();

            if (!$rowSerial) {
                throw new Exception("IMEI stock row not found for item {$itemCode} IMEI {$imei}");
            }

            // Fetch the FULL stock row to get the original dr_number and system_entry_date (for IOU preservation)
            $stmtFull = $conn->prepare("SELECT id, branch, dr_number, dr_date, system_entry_date, description, group_name, department, brand, family_code, item_type, status FROM stock_on_hand WHERE id = ? LIMIT 1");
            $stmtFull->bind_param('i', $rowSerial['id']);
            $stmtFull->execute();
            $fullRow = $stmtFull->get_result()->fetch_assoc();
            $stmtFull->close();

            $srcBranchValue = $rowSerial['branch'];
            $srcIsCode = in_array($srcBranchValue, $fromCodeValues, true);
            $targetBranchValue = $srcIsCode ? $toCode : $toName;

            // Preserve the original dr_number (PO reference) — do NOT overwrite with ST number
            $originalDrNumber = !empty($fullRow['dr_number']) ? $fullRow['dr_number'] : $st_number;
            
            // Preserve the original system_entry_date to maintain IOU days
            $originalSystemEntryDate = !empty($fullRow['system_entry_date']) ? $fullRow['system_entry_date'] : date('Y-m-d H:i:s');
            
            // Preserve the original status
            $originalStatus = !empty($fullRow['status']) ? $fullRow['status'] : 'Good Stock';

            // Update: Reset Aging (dr_date = NOW()) but preserve IOU (keep original system_entry_date) and status
            $sqlUpdate = "UPDATE stock_on_hand
                           SET branch = ?,
                               dr_number = ?,
                               dr_date = NOW(),
                               system_entry_date = ?,
                               status = ?,
                               quantity = 1
                           WHERE id = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param('ssssi', $targetBranchValue, $originalDrNumber, $originalSystemEntryDate, $originalStatus, $rowSerial['id']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            // Non-serialized item (Accessories): deduct qty then insert qty into destination.
            $needed = $qty;

            $sqlSource = "SELECT id, quantity, description, family_code, branch, system_entry_date, status
                           FROM stock_on_hand
                           WHERE item_code = ?
                             AND item_type = 'Accessories'
                             AND branch IN ($fromPlaceholders)
                             AND (LOWER(TRIM(status)) = 'active' OR LOWER(TRIM(status)) = 'available' OR LOWER(TRIM(status)) = 'good stock')
                             AND quantity > 0
                           ORDER BY dr_date ASC, id ASC";

            $stmtSource = $conn->prepare($sqlSource);
            $types = str_repeat('s', 1) . str_repeat('s', count($fromCandidates)); // item_code + branches
            $bindParams = array_merge([$itemCode], $fromCandidates);
            $stmtSource->bind_param($types, ...$bindParams);
            $stmtSource->execute();
            $sourceRes = $stmtSource->get_result();

            $firstSource = null;
            $oldestSystemEntryDate = null;
            $preservedStatus = 'Good Stock'; // Default status
            while ($row = $sourceRes->fetch_assoc()) {
                if ($needed <= 0) break;

                $rowId = (int)$row['id'];
                $rowQty = (int)$row['quantity'];
                if ($rowQty <= 0) continue;

                if ($firstSource === null) {
                    $firstSource = $row;
                    // Track the oldest system_entry_date to preserve IOU
                    $oldestSystemEntryDate = $row['system_entry_date'] ?? null;
                    // Preserve the original status
                    $preservedStatus = $row['status'] ?? 'Good Stock';
                }

                $take = min($rowQty, $needed);

                if ($take === $rowQty) {
                    $stmtDel = $conn->prepare("DELETE FROM stock_on_hand WHERE id = ?");
                    $stmtDel->bind_param('i', $rowId);
                    $stmtDel->execute();
                    $stmtDel->close();
                } else {
                    $stmtDec = $conn->prepare("UPDATE stock_on_hand SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                    $stmtDec->bind_param('iii', $take, $rowId, $take);
                    $stmtDec->execute();
                    $stmtDec->close();
                }

                $needed -= $take;
            }
            $stmtSource->close();

            if ($needed > 0) {
                throw new Exception("Insufficient Accessories stock to transfer for item {$itemCode}. Missing qty: {$needed}");
            }

            if ($firstSource === null) {
                throw new Exception("Accessories source stock not found for item {$itemCode}");
            }

            $insertDescription = (string)($firstSource['description'] ?? $itemDesc);
            $insertFamilyCode = (string)($firstSource['family_code'] ?? '');
            $srcBranchValue = (string)$firstSource['branch'];
            $srcIsCode = in_array($srcBranchValue, $fromCodeValues, true);
            $targetBranchValue = $srcIsCode ? $toCode : $toName;
            
            // Preserve original system_entry_date to maintain IOU days
            $preservedSystemEntryDate = $oldestSystemEntryDate ?? date('Y-m-d H:i:s');

            // Create new stock row for the transferred qty.
            // Reset Aging (dr_date = NOW()) but preserve IOU (original system_entry_date) and status
            $sqlInsert = "INSERT INTO stock_on_hand
                           (item_code, description, item_type, dr_number, branch,
                            dr_date, system_entry_date, status, quantity, family_code)
                           VALUES (?, ?, 'Accessories', ?, ?, NOW(), ?, ?, ?, ?)";
            $stmtIns = $conn->prepare($sqlInsert);
            $drNumber = $st_number;
            $stmtIns->bind_param(
                'sssssssi',
                $itemCode,
                $insertDescription,
                $drNumber,
                $targetBranchValue,
                $preservedSystemEntryDate,
                $preservedStatus,
                $qty,
                $insertFamilyCode
            );
            $stmtIns->execute();
            $stmtIns->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Transfer received and stock moved successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error approving transfer: ' . $e->getMessage()]);
}

$conn->close();
?>