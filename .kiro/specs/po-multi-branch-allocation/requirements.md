# Requirements Document

## Introduction

This document specifies requirements for the Purchase Order Multi-Branch Allocation Enhancement feature. The enhancement improves the allocation workflow by allowing users to perform multiple consecutive branch allocations within a single modal session, eliminating the need to repeatedly close and reopen the allocation modal.

## Glossary

- **Allocation_Modal**: The user interface dialog that displays item information and accepts branch allocation inputs
- **Item_Information_Section**: The table within the Allocation Modal showing Family Code, Cost, Order Quantity, Already Allocated, and Quantity Left
- **Branch_Selection_Field**: The dropdown or input control that allows users to select a target branch for allocation
- **Quantity_Input_Fields**: The numeric input controls where users enter allocation quantities for each item
- **Set_Button**: The submit button that saves the current allocation and triggers processing
- **Allocation_System**: The backend service that persists allocation data to the database
- **PO_Details_Page**: The main Purchase Order details page that contains the Allocate Items button
- **User_Interface**: The browser-based frontend application
- **Backend_Service**: The server-side PHP application handling allocation persistence

## Requirements

### Requirement 1: Modal Persistence After Allocation

**User Story:** As a warehouse manager, I want the allocation modal to remain open after clicking Set, so that I can immediately allocate items to another branch without reopening the modal.

#### Acceptance Criteria

1. WHEN the User clicks the Set_Button AND the allocation saves successfully, THE Allocation_Modal SHALL remain visible and interactive
2. WHEN the User clicks the Set_Button AND the allocation fails, THE Allocation_Modal SHALL remain visible and display the error message
3. THE Allocation_Modal SHALL close only when the User clicks the Back button OR the close (X) button
4. WHEN the Allocation_Modal remains open after successful allocation, THE User_Interface SHALL NOT reload the page

### Requirement 2: Dynamic Quantity Updates

**User Story:** As a warehouse manager, I want to see updated allocation totals immediately after each allocation, so that I know how many items remain available for allocation.

#### Acceptance Criteria

1. WHEN an allocation saves successfully, THE Item_Information_Section SHALL update the "Already Allocated" column to reflect the new total allocated quantity for each item
2. WHEN an allocation saves successfully, THE Item_Information_Section SHALL update the "Quantity Left" column to reflect the remaining available quantity for each item
3. WHEN the "Quantity Left" value becomes negative OR zero after an allocation, THE User_Interface SHALL display the value in red text
4. THE Item_Information_Section SHALL update within 500 milliseconds of successful allocation save
5. THE "Already Allocated" and "Quantity Left" calculations SHALL match the values stored in the database

### Requirement 3: Input Reset After Allocation

**User Story:** As a warehouse manager, I want the branch selection and quantity inputs to reset after each allocation, so that I can quickly enter the next allocation without manual clearing.

#### Acceptance Criteria

1. WHEN an allocation saves successfully, THE Branch_Selection_Field SHALL reset to empty OR default state
2. WHEN an allocation saves successfully, THE Quantity_Input_Fields SHALL reset to zero OR empty state
3. WHEN the input fields reset, THE Item_Information_Section SHALL display the updated allocation totals from Requirement 2
4. THE User SHALL be able to immediately enter a new branch selection after reset completes

### Requirement 4: Visual Feedback During Save Operation

**User Story:** As a warehouse manager, I want clear feedback during the save operation, so that I know the system is processing my allocation and I don't accidentally submit duplicate allocations.

#### Acceptance Criteria

1. WHEN the User clicks the Set_Button, THE Set_Button SHALL become disabled
2. WHEN the User clicks the Set_Button, THE Set_Button SHALL display loading text such as "Saving..."
3. WHEN the allocation save completes successfully, THE User_Interface SHALL display a success notification message
4. WHEN the allocation save fails, THE User_Interface SHALL display an error notification message
5. WHEN the save operation completes (success OR failure), THE Set_Button SHALL return to enabled state with normal text
6. THE Set_Button SHALL remain disabled for the entire duration of the save operation to prevent double-clicks

### Requirement 5: Sequential Multi-Branch Allocation Support

**User Story:** As a warehouse manager, I want to allocate items to multiple branches consecutively in one modal session, so that I can complete all allocations efficiently without interruption.

#### Acceptance Criteria

1. THE Allocation_System SHALL support saving three OR more consecutive allocations to different branches within one modal session
2. WHEN the User completes multiple consecutive allocations, THE Backend_Service SHALL persist each allocation independently and immediately
3. WHEN the User completes multiple consecutive allocations, THE User_Interface SHALL NOT lose any allocation data between submissions
4. THE Allocation_Modal SHALL remain stable and functional throughout multiple consecutive allocations
5. WHEN the User closes the Allocation_Modal after multiple allocations, THE PO_Details_Page MAY optionally reload to display final state

### Requirement 6: Preservation of Existing Validation Logic

**User Story:** As a system administrator, I want all existing allocation validation and business rules to continue functioning correctly, so that data integrity is maintained.

#### Acceptance Criteria

1. WHEN the User enters allocation quantities, THE Allocation_System SHALL validate that total allocated quantity does not exceed order quantity for each item
2. WHEN the User submits an allocation, THE Allocation_System SHALL calculate costs accurately using item cost OR provided cost
3. WHEN the User submits an allocation, THE Backend_Service SHALL correctly map allocations to family codes
4. WHEN items have received quantities, THE Allocation_System SHALL respect received quantities and prevent invalid modifications
5. THE Backend_Service SHALL enforce all database constraints and triggers
6. THE Allocation_System SHALL maintain all existing allocation features without regression

### Requirement 7: Error Handling Without Modal Disruption

**User Story:** As a warehouse manager, I want the system to handle errors gracefully without closing the modal, so that I can retry failed allocations without losing my work.

#### Acceptance Criteria

1. WHEN a network error occurs during allocation save, THE User_Interface SHALL display a user-friendly error message
2. WHEN an allocation save fails, THE Allocation_Modal SHALL remain open
3. WHEN an allocation save fails, THE User SHALL be able to retry the allocation without losing entered data
4. WHEN validation errors occur, THE User_Interface SHALL display clear and actionable error messages
5. WHEN database errors occur, THE Backend_Service SHALL rollback the transaction AND return an error response
6. THE Allocation_System SHALL recover gracefully from errors and return to ready state

### Requirement 8: Parser Round-Trip Property for Allocation Data

**User Story:** As a developer, I want allocation data to serialize and deserialize correctly, so that data integrity is preserved through the save and retrieval process.

#### Acceptance Criteria

1. WHEN the User_Interface serializes allocation data to JSON, THE Backend_Service SHALL parse it into allocation objects
2. WHEN the Backend_Service saves allocation data, THE subsequent fetch SHALL return equivalent allocation data
3. FOR ALL valid allocation data structures, serializing to JSON then parsing then serializing SHALL produce equivalent JSON
4. WHEN allocation data contains special characters OR numeric edge cases, THE Parser SHALL handle them correctly
5. THE Parser SHALL return descriptive error messages when allocation data is malformed

## Non-Functional Requirements

### Performance Requirements

1. THE Backend_Service SHALL complete each allocation save within 2 seconds under normal network conditions
2. THE User_Interface SHALL update the Item_Information_Section within 500 milliseconds after receiving updated allocation data
3. THE Allocation_Modal SHALL NOT cause memory leaks when kept open for extended periods (up to 30 minutes)

### Usability Requirements

1. THE User_Interface SHALL provide visual indication of successful allocations that is clearly visible to users
2. THE Allocation_Modal workflow SHALL be intuitive and require no additional user training
3. THE User_Interface SHALL display error messages that are accessible and comply with WCAG 2.1 Level AA
4. THE Set_Button SHALL provide responsive state changes that give immediate feedback to user actions

### Compatibility Requirements

1. THE User_Interface SHALL function correctly in Chrome, Firefox, Edge, and Safari browsers
2. THE Backend_Service SHALL maintain compatibility with the existing PHP/MySQL backend architecture
3. THE Allocation_System SHALL NOT require database schema changes to existing tables

## Out of Scope

The following features are explicitly NOT included in this enhancement:

- Bulk allocation across multiple branches in a single action
- Allocation editing or deletion within the same modal session
- Undo/redo functionality for allocations
- Offline allocation support
- Mobile-specific optimizations
- Allocation templates or presets
- Real-time collaborative allocation by multiple users

## Success Criteria

1. Users can complete multi-branch allocations without reopening the modal
2. Reduction in number of clicks required for multi-branch allocation scenarios
3. No increase in allocation errors or data inconsistencies compared to current implementation
4. Positive user feedback on improved workflow efficiency

## Technical Context

### Database Tables
- `purchase_order_allocations` - Stores branch allocation records
- `purchase_order_items` - Stores PO line items with order quantities
- `branches` - Contains active branch information

### Key Implementation Files
- `purchaseorder-details.php` - Main UI and allocation modal
- `save_all_allocations.php` - Backend allocation save endpoint
- JavaScript function `setAllAllocations()` - Handles save operation
- JavaScript function `updateAllQuantityLeft()` - Calculates remaining quantities

### Current Implementation Behavior
- After successful save, `window.location.reload()` is called
- Page reload causes modal to close
- Users must click "Allocate Items" button to reopen modal for next allocation
