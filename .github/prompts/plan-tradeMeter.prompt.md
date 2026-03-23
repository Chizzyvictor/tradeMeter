# TradeMeter Database Schema Plan

## Overview
This document outlines the database schema for the TradeMeter application, a smart inventory and transaction management system. The schema is designed for SQLite with foreign key constraints, timestamps, and data integrity checks.

## Core Tables

### Company
- **Purpose**: Stores company/organization details
- **Key Fields**:
  - `cid` (PRIMARY KEY, AUTOINCREMENT)
  - `cName` (TEXT, NOT NULL, UNIQUE)
  - `cEmail` (TEXT, NOT NULL, UNIQUE)
  - `cPass` (TEXT, NOT NULL)
  - `question` (TEXT, NOT NULL) - Security question
  - `answer` (TEXT, NOT NULL) - Security answer
  - `cLogo` (TEXT, DEFAULT 'logo.jpg')
  - `regDate` (INTEGER, DEFAULT current timestamp)
  - `created_at` (TEXT, DEFAULT CURRENT_TIMESTAMP)

### Partner (Suppliers/Customers)
- **Purpose**: Manages business partners (suppliers and customers)
- **Key Fields**:
  - `sid` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `sName` (TEXT, NOT NULL)
  - `sEmail` (TEXT)
  - `sPhone` (TEXT)
  - `sAddress` (TEXT)
  - `outstanding` (REAL, DEFAULT 0.00) - Amount owed by customer
  - `advancePayment` (REAL, DEFAULT 0.00) - Amount paid in advance
  - `sLogo` (TEXT, DEFAULT 'user.jpg')
  - `created_at` (TEXT, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (INTEGER, DEFAULT current timestamp)
- **Constraints**: UNIQUE (cid, sName), FK to company

### Notification
- **Purpose**: Transaction logs for partner payments/debts
- **Key Fields**:
  - `nid` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `sid` (INTEGER, NOT NULL, FK to partner)
  - `amount` (REAL, NOT NULL)
  - `description` (TEXT, NOT NULL)
  - `status` (INTEGER, NOT NULL) - 0: debt, 1: payment
  - `timestamp` (INTEGER, DEFAULT current timestamp)
- **Constraints**: FK to company and partner

### Purchases
- **Purpose**: Records supplier purchase transactions
- **Key Fields**:
  - `purchase_id` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `supplier_id` (INTEGER, NOT NULL, FK to partner)
  - `totalAmount` (REAL, NOT NULL)
  - `status` (TEXT, NOT NULL)
  - `createdAt` (TEXT, DEFAULT CURRENT_TIMESTAMP)
- **Constraints**: FK to company and partner

## Inventory Tables

### Product Categories
- **Purpose**: Categorizes products
- **Key Fields**:
  - `category_id` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `category_name` (TEXT, NOT NULL)
  - `is_active` (INTEGER, DEFAULT 1, CHECK 0 or 1)
  - `created_at` (TEXT, DEFAULT CURRENT_TIMESTAMP)
- **Constraints**: UNIQUE (cid, category_name), FK to company

### Products
- **Purpose**: Inventory items
- **Key Fields**:
  - `product_id` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `category_id` (INTEGER, FK to product_categories)
  - `product_name` (TEXT, NOT NULL)
  - `product_image` (TEXT, DEFAULT 'product.jpg')
  - `product_unit` (TEXT, DEFAULT 'pcs')
  - `cost_price` (REAL, DEFAULT 0.00)
  - `selling_price` (REAL, DEFAULT 0.00)
  - `reorder_level` (INTEGER, DEFAULT 0)
  - `is_active` (INTEGER, DEFAULT 1, CHECK 0 or 1)
  - `timestamp` (INTEGER, DEFAULT current timestamp)
  - `created_at` (TEXT, DEFAULT CURRENT_TIMESTAMP)
- **Constraints**: UNIQUE (cid, product_name), FK to company and category

### Stock Ledger
- **Purpose**: Tracks inventory movements
- **Key Fields**:
  - `ledger_id` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `product_id` (INTEGER, NOT NULL, FK to products)
  - `quantity_change` (INTEGER, NOT NULL)
  - `reference_type` (TEXT, NOT NULL) - 'purchase', 'sale', 'adjustment'
  - `reference_id` (INTEGER, NOT NULL)
  - `timestamp` (INTEGER, DEFAULT current timestamp)
- **Constraints**: FK to company and products

## Transaction Tables

### Transactions
- **Purpose**: Sales and purchase transactions
- **Key Fields**:
  - `transaction_id` (PRIMARY KEY, AUTOINCREMENT)
  - `cid` (INTEGER, NOT NULL, FK to company)
  - `partner_id` (INTEGER, FK to partner)
  - `transaction_type` (TEXT, NOT NULL) - 'sale', 'purchase'
  - `total_amount` (REAL, NOT NULL)
  - `status` (TEXT, NOT NULL)
  - `created_at` (TEXT, DEFAULT CURRENT_TIMESTAMP)
- **Constraints**: FK to company and partner

### Transaction Items
- **Purpose**: Line items for transactions
- **Key Fields**:
  - `item_id` (PRIMARY KEY, AUTOINCREMENT)
  - `transaction_id` (INTEGER, NOT NULL, FK to transactions)
  - `product_id` (INTEGER, NOT NULL, FK to products)
  - `quantity` (INTEGER, NOT NULL)
  - `unit_price` (REAL, NOT NULL)
  - `total_price` (REAL, NOT NULL)
- **Constraints**: FK to transactions and products

## Key Relationships
- All tables are scoped by `cid` (company ID) for multi-tenancy
- Partners link to transactions and purchases
- Products link to categories and stock movements
- Notifications track partner balance changes
- Foreign keys ensure referential integrity
- Timestamps track creation and updates

## Data Integrity Features
- UNIQUE constraints prevent duplicates
- CHECK constraints on boolean fields
- Foreign key cascades on delete
- Real numbers for monetary values
- Integer timestamps for efficient querying

## Next Steps for Implementation
1. Implement API endpoints for CRUD operations
2. Add business logic for balance calculations
3. Create frontend components for data visualization
4. Add reporting and analytics features
5. Implement user authentication and authorization
