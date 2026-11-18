-- =============================================================================
-- VALENOR HOROLOGY — MIGRATION 009: PHASE 09 RETURNS, REVIEWS, COUPONS
-- =============================================================================

-- 1. Update return_requests statuses & metadata
ALTER TABLE `return_requests` 
    MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'Return Requested',
    ADD COLUMN `rejection_reason` TEXT DEFAULT NULL AFTER `admin_notes`,
    ADD COLUMN `proof_images` TEXT DEFAULT NULL AFTER `proof_image`,
    ADD COLUMN `refund_status` ENUM('Pending', 'Processing', 'Refunded', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER `refund_amount`,
    ADD COLUMN `refund_method` VARCHAR(50) DEFAULT 'Bank Transfer / Original Payment' AFTER `refund_status`,
    ADD COLUMN `refund_notes` TEXT DEFAULT NULL AFTER `refund_method`,
    ADD COLUMN `refunded_at` DATETIME DEFAULT NULL AFTER `refund_notes`;

-- 2. Update product_reviews for multiple image support
ALTER TABLE `product_reviews`
    ADD COLUMN `review_images` TEXT DEFAULT NULL AFTER `review_image`;

-- 3. Update orders order_status enum to include all return phases
ALTER TABLE `orders` 
    MODIFY `order_status` ENUM(
        'Pending',
        'Confirmed',
        'Processing',
        'Packed',
        'Dispatched',
        'Shipped / In Transit',
        'Out for Delivery',
        'Delivered',
        'Cancelled',
        'Return Requested',
        'Return Approved',
        'Return Rejected',
        'Return in Transit',
        'Returned',
        'Refund Processing',
        'Refunded'
    ) NOT NULL DEFAULT 'Pending';

-- 4. Default website settings for return policy & eligibility window
INSERT INTO `website_settings` (`setting_key`, `setting_value`, `setting_group`) 
VALUES 
    ('return_window_days', '14', 'returns'),
    ('return_policy_text', 'Returns are accepted within 14 days of delivery for unworn timepieces in original box with documentation.', 'returns')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
