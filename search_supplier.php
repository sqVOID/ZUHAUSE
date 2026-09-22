<?php
require_once 'session_check.php';
include 'config.php';

header('Content-Type: application/json');

$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'company'; // 'company' or 'supplier'
$companyName = isset($_GET['company']) ? trim($_GET['company']) : '';

// Debug logging
error_log("Search Supplier Debug - Type: $type, Company: $companyName, Term: $searchTerm");

try {
    if ($type === 'company') {
        // Get unique company names
        if (empty($searchTerm)) {
            $sql = "SELECT DISTINCT store_name 
                    FROM suppliers 
                    WHERE status = 'Active' 
                    ORDER BY store_name ASC 
                    LIMIT 20";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } else {
            $sql = "SELECT DISTINCT store_name 
                    FROM suppliers 
                    WHERE status = 'Active' 
                    AND store_name LIKE ? 
                    ORDER BY store_name ASC 
                    LIMIT 20";
            
            $stmt = $conn->prepare($sql);
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->bind_param('s', $searchPattern);
            $stmt->execute();
        }
        
        $result = $stmt->get_result();
        $companies = [];
        while ($row = $result->fetch_assoc()) {
            $companies[] = ['store_name' => $row['store_name']];
        }
        
        error_log("Companies found: " . count($companies));
        
        echo json_encode([
            'status' => 'success',
            'data' => $companies
        ]);
        
    } else if ($type === 'supplier') {
        // Get suppliers for a specific company
        if (empty($companyName)) {
            echo json_encode(['status' => 'error', 'message' => 'Company name is required']);
            exit;
        }
        
        // Since we removed supplier_name (agent), just return the company info
        $sql = "SELECT id, store_name, contact_number, address 
                FROM suppliers 
                WHERE status = 'Active' 
                AND TRIM(store_name) = TRIM(?) 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $companyName);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $suppliers = [];
        if ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        
        error_log("Supplier found for company '$companyName': " . count($suppliers));
        
        echo json_encode([
            'status' => 'success',
            'data' => $suppliers
        ]);
    }
    
} catch (Exception $e) {
    error_log("Search Supplier Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>