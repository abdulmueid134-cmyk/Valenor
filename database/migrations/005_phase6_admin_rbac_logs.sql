-- Phase 6 Migration: Permissions, Role Permissions, Admin Activity Logs & Currency Setting
USE valenor_watches;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id VARCHAR(60) NULL,
  details TEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_aal_user_time (user_id, created_at),
  INDEX idx_aal_action_time (action, created_at)
) ENGINE=InnoDB;

INSERT INTO permissions (code, description) VALUES
  ('dashboard.view', 'Access admin KPI overview and analytics graphs'),
  ('products.manage', 'Create, update, and manage watch catalog'),
  ('inventory.manage', 'Monitor and adjust stock levels'),
  ('orders.manage', 'Inspect and update order fulfillment lifecycle'),
  ('returns.manage', 'Approve, reject, restock, and refund returns'),
  ('customers.view', 'View customer accounts and order history'),
  ('reports.view', 'View and export sales and operations reports'),
  ('settings.manage', 'Modify storefront settings and policies'),
  ('users.manage', 'Manage admin users and role permissions')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('dashboard.view', 'products.manage', 'inventory.manage', 'orders.manage', 'returns.manage', 'customers.view', 'reports.view')
WHERE r.name = 'manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('dashboard.view', 'orders.manage', 'returns.manage', 'customers.view')
WHERE r.name = 'order_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('dashboard.view', 'products.manage', 'inventory.manage')
WHERE r.name = 'inventory_manager';

INSERT INTO settings (setting_key, setting_value) VALUES
  ('site_name', 'VALENOR Horology'),
  ('currency', 'PKR')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

