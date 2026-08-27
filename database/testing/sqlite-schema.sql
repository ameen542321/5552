-- SQLite testing baseline generated from database/reference/carled_schema_2026-08-09.sql.
-- Structure only: no production data is included.
PRAGMA foreign_keys = ON;

CREATE TABLE "accountants" (
    "id" INTEGER NOT NULL,
    "employee_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "store_id" INTEGER DEFAULT NULL,
    "name" varchar(255) NOT NULL,
    "email" varchar(255) NOT NULL,
    "phone" varchar(255) DEFAULT NULL,
    "password" varchar(255) NOT NULL,
    "role" varchar(255) NOT NULL DEFAULT 'accountant',
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "suspension_reason" varchar(255) DEFAULT NULL,
    "remember_token" varchar(100) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "accountants_email_unique" UNIQUE ("email"),
    CONSTRAINT "fk_accountants_store_id_cascade" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "fk_accountants_user_cascade" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "archived_items" (
    "id" INTEGER NOT NULL,
    "owner_id" INTEGER DEFAULT NULL,
    "store_id" INTEGER DEFAULT NULL,
    "archivable_type" varchar(255) NOT NULL,
    "archivable_id" INTEGER NOT NULL,
    "original_name" varchar(255) DEFAULT NULL,
    "original_slug" varchar(255) DEFAULT NULL,
    "archived_slug" varchar(255) DEFAULT NULL,
    "reference" varchar(255) NOT NULL,
    "status" varchar(255) NOT NULL DEFAULT 'archived',
    "archived_by" INTEGER DEFAULT NULL,
    "archived_at" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "owner_restore_deadline" timestamp NULL DEFAULT NULL,
    "restored_at" timestamp NULL DEFAULT NULL,
    "restored_by" INTEGER DEFAULT NULL,
    "admin_message" text DEFAULT NULL,
    "metadata" TEXT DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "archived_items_archivable_type_archivable_id_unique" UNIQUE ("archivable_type","archivable_id"),
    CONSTRAINT "archived_items_reference_unique" UNIQUE ("reference"),
    CONSTRAINT "archived_items_archived_by_foreign" FOREIGN KEY ("archived_by") REFERENCES "users" ("id") ON DELETE SET NULL,
    CONSTRAINT "archived_items_owner_id_foreign" FOREIGN KEY ("owner_id") REFERENCES "users" ("id") ON DELETE SET NULL,
    CONSTRAINT "archived_items_restored_by_foreign" FOREIGN KEY ("restored_by") REFERENCES "users" ("id") ON DELETE SET NULL,
    CONSTRAINT "archived_items_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE SET NULL
);

CREATE TABLE "cache" (
    "key" varchar(255) NOT NULL,
    "value" TEXT NOT NULL,
    "expiration" INTEGER NOT NULL,
    PRIMARY KEY ("key")
);

CREATE TABLE "cache_locks" (
    "key" varchar(255) NOT NULL,
    "owner" varchar(255) NOT NULL,
    "expiration" INTEGER NOT NULL,
    PRIMARY KEY ("key")
);

CREATE TABLE "categories" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "name" varchar(255) NOT NULL,
    "slug" varchar(255) NOT NULL,
    "description" text DEFAULT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "deleted_at" timestamp NULL DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "is_main_category" INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY ("id"),
    CONSTRAINT "categories_store_slug_unique" UNIQUE ("store_id","slug"),
    CONSTRAINT "fk_categories_store_cascade" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "credit_sales" (
    "id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "person_type" varchar(255) DEFAULT NULL,
    "store_id" INTEGER NOT NULL,
    "sale_id" INTEGER DEFAULT NULL,
    "amount" NUMERIC(10,2) NOT NULL,
    "remaining_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "partial_payments" TEXT DEFAULT NULL,
    "description" varchar(255) DEFAULT NULL,
    "credit_note" text DEFAULT NULL,
    "date" date NOT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "month" varchar(255) NOT NULL,
    "deducted_month" varchar(255) DEFAULT NULL,
    "added_by" INTEGER NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employee_credit_sales_employee_id_foreign" FOREIGN KEY ("person_id") REFERENCES "employees" ("id") ON DELETE CASCADE,
    CONSTRAINT "employee_credit_sales_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "daily_balances" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "accountant_id" INTEGER NOT NULL,
    "system_sales_total" NUMERIC(15,2) NOT NULL,
    "system_cash_expected" NUMERIC(15,2) NOT NULL,
    "actual_cash_submitted" NUMERIC(15,2) NOT NULL,
    "difference" NUMERIC(15,2) NOT NULL,
    "start_time" timestamp NULL DEFAULT NULL,
    "end_time" timestamp NULL DEFAULT NULL,
    "business_date" date DEFAULT NULL,
    "closed_at" timestamp NULL DEFAULT NULL,
    "next_shift_business_date" date DEFAULT NULL,
    "next_shift_decision" varchar(40) DEFAULT NULL,
    "next_shift_decided_by" INTEGER DEFAULT NULL,
    "notes" text DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "daily_balances_accountant_id_foreign" FOREIGN KEY ("accountant_id") REFERENCES "accountants" ("id") ON DELETE CASCADE,
    CONSTRAINT "daily_balances_next_shift_decided_by_foreign" FOREIGN KEY ("next_shift_decided_by") REFERENCES "accountants" ("id") ON DELETE SET NULL,
    CONSTRAINT "daily_balances_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "debts" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "debt_parent_id" INTEGER DEFAULT NULL,
    "person_type" varchar(255) DEFAULT NULL,
    "amount" NUMERIC(10,2) NOT NULL,
    "description" varchar(255) DEFAULT NULL,
    "payment_method" varchar(255) DEFAULT NULL,
    "payment_method_label" varchar(255) DEFAULT NULL,
    "cash_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "card_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "date" date DEFAULT NULL,
    "type" VARCHAR(255) NOT NULL DEFAULT 'normal',
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "month" varchar(255) NOT NULL,
    "deducted_month" varchar(255) DEFAULT NULL,
    "added_by" INTEGER NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "debts_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "employee_debts_employee_id_foreign" FOREIGN KEY ("person_id") REFERENCES "employees" ("id") ON DELETE CASCADE
);

CREATE TABLE "device_tokens" (
    "id" INTEGER NOT NULL,
    "user_id" INTEGER DEFAULT NULL,
    "accountant_id" INTEGER DEFAULT NULL,
    "token" varchar(255) NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "employee_absences" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "person_type" varchar(255) DEFAULT NULL,
    "date" date NOT NULL,
    "description" varchar(255) DEFAULT NULL,
    "penalty_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "month" varchar(255) NOT NULL,
    "deducted_month" varchar(255) DEFAULT NULL,
    "added_by" INTEGER NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employee_absences_employee_id_foreign" FOREIGN KEY ("person_id") REFERENCES "employees" ("id") ON DELETE CASCADE,
    CONSTRAINT "employee_absences_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "employee_credit_collections" (
    "id" INTEGER NOT NULL,
    "credit_sale_id" INTEGER NOT NULL,
    "sale_id" INTEGER DEFAULT NULL,
    "store_id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "person_type" varchar(255) NOT NULL,
    "amount" NUMERIC(12,2) NOT NULL,
    "payment_method" varchar(20) NOT NULL DEFAULT 'cash',
    "payment_method_label" varchar(50) DEFAULT NULL,
    "cash_amount" NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    "card_amount" NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    "collection_date" date NOT NULL,
    "collected_by" INTEGER DEFAULT NULL,
    "meta" TEXT DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employee_credit_collections_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "employee_logs" (
    "id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "person_type" varchar(255) NOT NULL,
    "store_id" INTEGER NOT NULL,
    "action_name" varchar(255) DEFAULT NULL,
    "amount" NUMERIC(10,2) DEFAULT NULL,
    "meta" TEXT DEFAULT NULL,
    "description" text DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employee_logs_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "employee_salary_reports" (
    "id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "person_type" varchar(255) DEFAULT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "month" varchar(255) NOT NULL,
    "year" varchar(255) NOT NULL,
    "base_salary" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "total_withdrawals" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "total_absences" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "total_normal_debts" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "total_credit_sales" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "previous_debts" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "bonus" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "extra_deduction" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "final_salary" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "notes" text DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employee_salary_reports_employee_id_foreign" FOREIGN KEY ("person_id") REFERENCES "employees" ("id") ON DELETE CASCADE,
    CONSTRAINT "employee_salary_reports_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "employee_salary_reports_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "employee_withdrawals" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "person_id" INTEGER NOT NULL,
    "person_type" varchar(255) DEFAULT NULL,
    "amount" NUMERIC(10,2) NOT NULL,
    "description" varchar(255) DEFAULT NULL,
    "date" date NOT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "month" varchar(255) NOT NULL,
    "deducted_month" varchar(255) DEFAULT NULL,
    "added_by" INTEGER NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "business_date" date DEFAULT NULL,
    "daily_balance_id" INTEGER DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employee_withdrawals_daily_balance_id_foreign" FOREIGN KEY ("daily_balance_id") REFERENCES "daily_balances" ("id") ON DELETE SET NULL,
    CONSTRAINT "employee_withdrawals_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "employees" (
    "id" INTEGER NOT NULL,
    "user_id" INTEGER DEFAULT NULL,
    "store_id" INTEGER NOT NULL,
    "role" VARCHAR(255) NOT NULL DEFAULT 'employee',
    "name" varchar(255) NOT NULL,
    "phone" varchar(255) DEFAULT NULL,
    "salary" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "added_by" INTEGER DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "employees_added_by_foreign" FOREIGN KEY ("added_by") REFERENCES "users" ("id") ON DELETE SET NULL,
    CONSTRAINT "fk_employees_store_id_cascade" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "expenses" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "type" varchar(255) DEFAULT NULL,
    "employee_id" INTEGER DEFAULT NULL,
    "actor_type" varchar(255) DEFAULT NULL,
    "description" varchar(255) NOT NULL,
    "amount" NUMERIC(10,2) NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "business_date" date DEFAULT NULL,
    "daily_balance_id" INTEGER DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "expenses_daily_balance_id_foreign" FOREIGN KEY ("daily_balance_id") REFERENCES "daily_balances" ("id") ON DELETE SET NULL,
    CONSTRAINT "expenses_employee_id_foreign" FOREIGN KEY ("employee_id") REFERENCES "employees" ("id") ON DELETE SET NULL
);

CREATE TABLE "failed_jobs" (
    "id" INTEGER NOT NULL,
    "uuid" varchar(255) NOT NULL,
    "connection" text NOT NULL,
    "queue" text NOT NULL,
    "payload" TEXT NOT NULL,
    "exception" TEXT NOT NULL,
    "failed_at" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    CONSTRAINT "failed_jobs_uuid_unique" UNIQUE ("uuid")
);

CREATE TABLE "inventory_logs" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "product_id" INTEGER NOT NULL,
    "product_name_snapshot" varchar(255) DEFAULT NULL,
    "quantity_change" INTEGER NOT NULL,
    "quantity_snapshot" NUMERIC(15,4) DEFAULT NULL,
    "type" varchar(255) NOT NULL,
    "business_date" date DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "inventory_logs_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "invoices" (
    "id" INTEGER NOT NULL,
    "sale_id" INTEGER NOT NULL,
    "invoice_number" varchar(255) NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "customer_name" varchar(255) DEFAULT NULL,
    "customer_phone" varchar(255) DEFAULT NULL,
    "vehicle_type" varchar(255) DEFAULT NULL,
    "plate_number" varchar(255) DEFAULT NULL,
    "notes" text DEFAULT NULL,
    "description" text DEFAULT NULL,
    "tax_number" varchar(255) DEFAULT NULL,
    "subtotal" NUMERIC(15,2) NOT NULL,
    "tax_amount" NUMERIC(15,2) NOT NULL,
    "total_amount" NUMERIC(15,2) NOT NULL,
    "status" varchar(255) NOT NULL DEFAULT 'printed',
    PRIMARY KEY ("id"),
    CONSTRAINT "invoices_invoice_number_unique" UNIQUE ("invoice_number"),
    CONSTRAINT "invoices_sale_id_foreign" FOREIGN KEY ("sale_id") REFERENCES "sales" ("id") ON DELETE CASCADE
);

CREATE TABLE "job_batches" (
    "id" varchar(255) NOT NULL,
    "name" varchar(255) NOT NULL,
    "total_jobs" INTEGER NOT NULL,
    "pending_jobs" INTEGER NOT NULL,
    "failed_jobs" INTEGER NOT NULL,
    "failed_job_ids" TEXT NOT NULL,
    "options" TEXT DEFAULT NULL,
    "cancelled_at" INTEGER DEFAULT NULL,
    "created_at" INTEGER NOT NULL,
    "finished_at" INTEGER DEFAULT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "jobs" (
    "id" INTEGER NOT NULL,
    "queue" varchar(255) NOT NULL,
    "payload" TEXT NOT NULL,
    "attempts" INTEGER NOT NULL,
    "reserved_at" INTEGER DEFAULT NULL,
    "available_at" INTEGER NOT NULL,
    "created_at" INTEGER NOT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "logs" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER DEFAULT NULL,
    "user_id" INTEGER DEFAULT NULL,
    "actor_type" varchar(255) DEFAULT NULL,
    "actor_id" INTEGER DEFAULT NULL,
    "model_type" varchar(255) DEFAULT NULL,
    "model_id" INTEGER DEFAULT NULL,
    "action" varchar(255) NOT NULL,
    "description" text DEFAULT NULL,
    "details" TEXT DEFAULT NULL,
    "ip" varchar(255) DEFAULT NULL,
    "user_agent" varchar(255) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "fk_logs_user_id_cascade" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE,
    CONSTRAINT "logs_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "notifications" (
    "id" INTEGER NOT NULL,
    "sender_id" INTEGER DEFAULT NULL,
    "sender_type" VARCHAR(255) NOT NULL,
    "target_type" VARCHAR(255) NOT NULL,
    "target_ids" TEXT DEFAULT NULL,
    "title" varchar(255) NOT NULL,
    "message" text NOT NULL,
    "template_key" varchar(255) DEFAULT NULL,
    "channel" VARCHAR(255) NOT NULL,
    "read_by" TEXT DEFAULT NULL,
    "data" TEXT DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "onesignal_settings" (
    "id" INTEGER NOT NULL,
    "app_id" varchar(255) DEFAULT NULL,
    "api_key" varchar(255) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "password_resets" (
    "email" varchar(255) NOT NULL,
    "token" varchar(255) NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL
);

CREATE TABLE "plans" (
    "id" INTEGER NOT NULL,
    "name" varchar(255) NOT NULL,
    "allowed_stores" INTEGER NOT NULL,
    "allowed_accountants" INTEGER NOT NULL,
    "price" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "product_fractions" (
    "id" INTEGER NOT NULL,
    "product_id" INTEGER NOT NULL,
    "option_label" varchar(255) NOT NULL,
    "deduction_value" NUMERIC(10,2) NOT NULL,
    "price" NUMERIC(15,2) NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "product_fractions_product_id_foreign" FOREIGN KEY ("product_id") REFERENCES "products" ("id") ON DELETE CASCADE
);

CREATE TABLE "products" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "category_id" INTEGER DEFAULT NULL,
    "product_type" VARCHAR(255) NOT NULL DEFAULT 'standard',
    "usage_type" varchar(30) NOT NULL DEFAULT 'sale',
    "roll_length" NUMERIC(8,2) NOT NULL DEFAULT 30.00,
    "waste_percentage" NUMERIC(5,2) NOT NULL DEFAULT 0.00,
    "name" varchar(255) NOT NULL,
    "slug" varchar(255) NOT NULL,
    "description" text DEFAULT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "deleted_at" timestamp NULL DEFAULT NULL,
    "barcode" varchar(255) DEFAULT NULL,
    "price" NUMERIC(10,2) NOT NULL,
    "piece_price" NUMERIC(10,2) DEFAULT NULL,
    "cost_price" NUMERIC(10,2) DEFAULT NULL,
    "quantity" NUMERIC(18,6) NOT NULL DEFAULT 0.000000,
    "is_splittable" INTEGER NOT NULL DEFAULT 0,
    "items_per_unit" INTEGER NOT NULL DEFAULT 1,
    "carton_qty" INTEGER DEFAULT NULL,
    "quick_sale_default_unit" varchar(10) NOT NULL DEFAULT 'unit',
    "min_stock" NUMERIC(18,8) NOT NULL DEFAULT 0.00000000,
    "image" varchar(255) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "products_slug_unique" UNIQUE ("slug"),
    CONSTRAINT "fk_products_store_id_cascade" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "purchases" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "product_id" INTEGER DEFAULT NULL,
    "product_name_snapshot" varchar(255) DEFAULT NULL,
    "purchase_name" varchar(255) DEFAULT NULL,
    "quantity" NUMERIC(10,2) NOT NULL DEFAULT 1.00,
    "cost" NUMERIC(10,2) NOT NULL,
    "description" varchar(255) DEFAULT NULL,
    "business_date" date DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "purchases_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "sale_items" (
    "id" INTEGER NOT NULL,
    "sale_id" INTEGER NOT NULL,
    "product_id" INTEGER NOT NULL,
    "product_name_snapshot" varchar(255) DEFAULT NULL,
    "quantity" INTEGER NOT NULL,
    "price" NUMERIC(10,2) NOT NULL,
    "total" NUMERIC(10,2) NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "fraction_id" INTEGER DEFAULT NULL,
    "is_custom" INTEGER NOT NULL DEFAULT 0,
    "custom_name" varchar(255) DEFAULT NULL,
    "custom_consumption" NUMERIC(8,2) DEFAULT NULL,
    "custom_meters" NUMERIC(8,2) DEFAULT NULL,
    "roll_length_at_sale" NUMERIC(8,2) DEFAULT NULL,
    "unit_type" varchar(255) DEFAULT NULL,
    "unit_label_snapshot" varchar(50) DEFAULT NULL,
    "product_type_snapshot" varchar(50) DEFAULT NULL,
    "product_usage_snapshot" varchar(50) DEFAULT NULL,
    "is_splittable_snapshot" INTEGER DEFAULT NULL,
    "items_per_unit_snapshot" NUMERIC(12,4) DEFAULT NULL,
    "roll_length_snapshot" NUMERIC(12,4) DEFAULT NULL,
    "quantity_snapshot" NUMERIC(12,4) DEFAULT NULL,
    "sale_price_snapshot" NUMERIC(12,2) DEFAULT NULL,
    "cost_price_snapshot" NUMERIC(12,2) DEFAULT NULL,
    "snapshot_source" varchar(30) DEFAULT NULL,
    "cost_price" NUMERIC(10,2) DEFAULT NULL,
    "total_cost" NUMERIC(10,2) DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "sale_items_fraction_id_foreign" FOREIGN KEY ("fraction_id") REFERENCES "product_fractions" ("id") ON DELETE SET NULL,
    CONSTRAINT "sale_items_sale_id_foreign" FOREIGN KEY ("sale_id") REFERENCES "sales" ("id") ON DELETE CASCADE
);

CREATE TABLE "sales" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "accountant_id" INTEGER NOT NULL,
    "employee_id" INTEGER DEFAULT NULL,
    "total" NUMERIC(10,2) NOT NULL,
    "paid_amount" NUMERIC(10,2) NOT NULL,
    "cash_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "card_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "remaining_amount" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "sale_type" VARCHAR(255) DEFAULT 'cash',
    "has_partial_credit" INTEGER NOT NULL DEFAULT 0,
    "has_invoice" INTEGER NOT NULL DEFAULT 0,
    "description" varchar(255) DEFAULT NULL,
    "internal_notes" text DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "business_date" date DEFAULT NULL,
    "daily_balance_id" INTEGER DEFAULT NULL,
    "client_operation_id" char(36) DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "products_total" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "tax_rate" INTEGER NOT NULL DEFAULT 0,
    "labor_total" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "final_total" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "profit" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY ("id"),
    CONSTRAINT "sales_client_operation_id_unique" UNIQUE ("client_operation_id"),
    CONSTRAINT "sales_daily_balance_id_foreign" FOREIGN KEY ("daily_balance_id") REFERENCES "daily_balances" ("id") ON DELETE SET NULL,
    CONSTRAINT "sales_employee_id_foreign" FOREIGN KEY ("employee_id") REFERENCES "employees" ("id") ON DELETE SET NULL,
    CONSTRAINT "sales_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "stock_movements" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "product_id" INTEGER DEFAULT NULL,
    "product_name_snapshot" varchar(255) DEFAULT NULL,
    "sale_price_snapshot" NUMERIC(12,2) DEFAULT NULL,
    "cost_price_snapshot" NUMERIC(12,2) DEFAULT NULL,
    "user_id" INTEGER DEFAULT NULL,
    "type" VARCHAR(255) NOT NULL,
    "quantity" NUMERIC(18,6) NOT NULL,
    "requested_quantity" NUMERIC(15,4) DEFAULT NULL,
    "unit_type_at_movement" varchar(30) DEFAULT NULL,
    "product_type_at_movement" varchar(30) DEFAULT NULL,
    "is_splittable_at_movement" INTEGER DEFAULT NULL,
    "items_per_unit_at_movement" NUMERIC(15,4) DEFAULT NULL,
    "roll_length_value_at_movement" NUMERIC(15,4) DEFAULT NULL,
    "display_unit_label" varchar(30) DEFAULT NULL,
    "balance_before" NUMERIC(18,6) DEFAULT NULL,
    "balance_after" NUMERIC(18,6) DEFAULT NULL,
    "meters" NUMERIC(18,6) DEFAULT NULL,
    "roll_length_at_movement" NUMERIC(18,6) DEFAULT NULL,
    "note" varchar(255) DEFAULT NULL,
    "business_date" date DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "fk_stock_movements_store_id_cascade" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "stock_movements_product_id_foreign" FOREIGN KEY ("product_id") REFERENCES "products" ("id") ON DELETE SET NULL,
    CONSTRAINT "stock_movements_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "store_purchase_order_count_attempts" (
    "id" INTEGER NOT NULL,
    "store_purchase_order_id" INTEGER NOT NULL,
    "store_purchase_order_item_id" INTEGER NOT NULL,
    "attempt" INTEGER NOT NULL,
    "counted_quantity" NUMERIC(15,2) NOT NULL,
    "system_quantity_image" NUMERIC(15,2) NOT NULL,
    "unit_type" varchar(30) NOT NULL,
    "accountant_id" INTEGER DEFAULT NULL,
    "note" text DEFAULT NULL,
    "submitted_at" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "po_count_attempt_item_attempt_unique" UNIQUE ("store_purchase_order_item_id","attempt"),
    CONSTRAINT "po_counts_accountant_fk" FOREIGN KEY ("accountant_id") REFERENCES "accountants" ("id") ON DELETE SET NULL,
    CONSTRAINT "po_counts_item_fk" FOREIGN KEY ("store_purchase_order_item_id") REFERENCES "store_purchase_order_items" ("id") ON DELETE CASCADE,
    CONSTRAINT "po_counts_order_fk" FOREIGN KEY ("store_purchase_order_id") REFERENCES "store_purchase_orders" ("id") ON DELETE CASCADE
);

CREATE TABLE "store_purchase_order_events" (
    "id" INTEGER NOT NULL,
    "store_purchase_order_id" INTEGER NOT NULL,
    "store_purchase_order_item_id" INTEGER DEFAULT NULL,
    "event" varchar(60) NOT NULL,
    "from_status" varchar(50) DEFAULT NULL,
    "to_status" varchar(50) DEFAULT NULL,
    "actor_type" varchar(20) NOT NULL,
    "actor_id" INTEGER NOT NULL,
    "note" text DEFAULT NULL,
    "data" TEXT DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "store_purchase_order_events_store_purchase_order_id_foreign" FOREIGN KEY ("store_purchase_order_id") REFERENCES "store_purchase_orders" ("id") ON DELETE CASCADE,
    CONSTRAINT "store_purchase_order_events_store_purchase_order_item_id_foreign" FOREIGN KEY ("store_purchase_order_item_id") REFERENCES "store_purchase_order_items" ("id") ON DELETE CASCADE
);

CREATE TABLE "store_purchase_order_items" (
    "id" INTEGER NOT NULL,
    "store_purchase_order_id" INTEGER NOT NULL,
    "product_id" INTEGER DEFAULT NULL,
    "matched_product_id" INTEGER DEFAULT NULL,
    "custom_product_name" varchar(255) DEFAULT NULL,
    "quantity_requested" NUMERIC(15,2) NOT NULL,
    "quantity_received" NUMERIC(15,2) DEFAULT NULL,
    "unit_type" varchar(30) NOT NULL DEFAULT 'unit',
    "items_per_unit" INTEGER DEFAULT NULL,
    "roll_length" NUMERIC(12,2) DEFAULT NULL,
    "cost_price_at_order" NUMERIC(10,2) DEFAULT NULL,
    "cost_price_at_receipt" NUMERIC(10,2) DEFAULT NULL,
    "price_variance" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "price_variance_percent" NUMERIC(8,2) NOT NULL DEFAULT 0.00,
    "update_product_cost" INTEGER NOT NULL DEFAULT 0,
    "stock_quantity_before" NUMERIC(15,3) DEFAULT NULL,
    "stock_quantity_after" NUMERIC(15,3) DEFAULT NULL,
    "cost_price_before" NUMERIC(10,2) DEFAULT NULL,
    "cost_price_after" NUMERIC(10,2) DEFAULT NULL,
    "owner_purchase_id" INTEGER DEFAULT NULL,
    "add_to_owner_purchases" INTEGER NOT NULL DEFAULT 0,
    "receipt_notes" text DEFAULT NULL,
    "inventory_count_required" INTEGER NOT NULL DEFAULT 0,
    "inventory_count_quantity" NUMERIC(15,2) DEFAULT NULL,
    "inventory_count_unit" varchar(20) DEFAULT NULL,
    "inventory_count_note" text DEFAULT NULL,
    "system_quantity_snapshot" NUMERIC(15,2) DEFAULT NULL,
    "inventory_counted_quantity" NUMERIC(15,3) DEFAULT NULL,
    "inventory_snapshot_quantity" NUMERIC(15,3) DEFAULT NULL,
    "inventory_counted_at" timestamp NULL DEFAULT NULL,
    "inventory_snapshot_at" timestamp NULL DEFAULT NULL,
    "inventory_count_submitted_at" timestamp NULL DEFAULT NULL,
    "inventory_count_submitted_by" INTEGER DEFAULT NULL,
    "inventory_count_notes" text DEFAULT NULL,
    "inventory_changed_after_count" INTEGER NOT NULL DEFAULT 0,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "inventory_count_attempt" INTEGER NOT NULL DEFAULT 0,
    "excluded_after_count" INTEGER NOT NULL DEFAULT 0,
    "excluded_at" timestamp NULL DEFAULT NULL,
    "exclusion_reason" text DEFAULT NULL,
    "changed_by_type" varchar(20) DEFAULT NULL,
    "changed_by_id" INTEGER DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "store_purchase_order_items_matched_product_id_foreign" FOREIGN KEY ("matched_product_id") REFERENCES "products" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_purchase_order_items_owner_purchase_id_foreign" FOREIGN KEY ("owner_purchase_id") REFERENCES "purchases" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_purchase_order_items_product_id_foreign" FOREIGN KEY ("product_id") REFERENCES "products" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_purchase_order_items_store_purchase_order_id_foreign" FOREIGN KEY ("store_purchase_order_id") REFERENCES "store_purchase_orders" ("id") ON DELETE CASCADE
);

CREATE TABLE "purchase_order_limit_settings" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER DEFAULT NULL,
    "weekly_limit" INTEGER NOT NULL DEFAULT 4,
    "counted_statuses" TEXT NOT NULL,
    "exception_weekly_limit" INTEGER DEFAULT NULL,
    "exception_expires_at" timestamp NULL DEFAULT NULL,
    "exception_reason" text DEFAULT NULL,
    "exception_admin_id" INTEGER DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "purchase_order_limit_settings_store_id_unique" UNIQUE ("store_id"),
    CONSTRAINT "purchase_order_limit_settings_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "purchase_order_limit_settings_exception_admin_id_foreign" FOREIGN KEY ("exception_admin_id") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "store_purchase_orders" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "accountant_id" INTEGER DEFAULT NULL,
    "supplier_name" varchar(255) DEFAULT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'draft',
    "workflow_status" varchar(50) NOT NULL DEFAULT 'draft_accountant',
    "inventory_review_status" varchar(40) DEFAULT NULL,
    "edit_return_count" INTEGER NOT NULL DEFAULT 0,
    "notes" text DEFAULT NULL,
    "inventory_review_note" text DEFAULT NULL,
    "owner_notes" text DEFAULT NULL,
    "accountant_notes" text DEFAULT NULL,
    "sent_at" timestamp NULL DEFAULT NULL,
    "inventory_returned_at" timestamp NULL DEFAULT NULL,
    "inventory_draft_saved_at" timestamp NULL DEFAULT NULL,
    "owner_reviewed_at" timestamp NULL DEFAULT NULL,
    "returned_for_inventory_at" timestamp NULL DEFAULT NULL,
    "inventory_submitted_at" timestamp NULL DEFAULT NULL,
    "inventory_submitted_by" INTEGER DEFAULT NULL,
    "rejected_at" timestamp NULL DEFAULT NULL,
    "received_at" timestamp NULL DEFAULT NULL,
    "approved_at" timestamp NULL DEFAULT NULL,
    "reversed_at" timestamp NULL DEFAULT NULL,
    "reversed_by" INTEGER DEFAULT NULL,
    "reversal_reason" text DEFAULT NULL,
    "reversal_operation_id" char(36) DEFAULT NULL,
    "approved_business_date" date DEFAULT NULL,
    "cancelled_at" timestamp NULL DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "rejection_reason" text DEFAULT NULL,
    "receipt_actor_type" varchar(20) DEFAULT NULL,
    "receipt_actor_id" INTEGER DEFAULT NULL,
    "approval_operation_id" char(36) DEFAULT NULL,
    "final_notice_until" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "store_purchase_orders_approval_operation_id_unique" UNIQUE ("approval_operation_id"),
    CONSTRAINT "store_purchase_orders_reversal_operation_id_unique" UNIQUE ("reversal_operation_id"),
    CONSTRAINT "store_purchase_orders_reversed_by_foreign" FOREIGN KEY ("reversed_by") REFERENCES "users" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_purchase_orders_accountant_id_foreign" FOREIGN KEY ("accountant_id") REFERENCES "accountants" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_purchase_orders_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "store_purchase_orders_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "store_settings" (
    "id" INTEGER NOT NULL,
    "store_id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "key" varchar(255) NOT NULL,
    "value" varchar(255) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "store_settings_store_id_foreign" FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "store_transfer_items" (
    "id" INTEGER NOT NULL,
    "store_transfer_id" INTEGER NOT NULL,
    "sender_product_id" INTEGER DEFAULT NULL,
    "receiver_product_id" INTEGER DEFAULT NULL,
    "product_name_snapshot" varchar(255) DEFAULT NULL,
    "requested_quantity" NUMERIC(15,3) NOT NULL,
    "normalized_quantity" NUMERIC(15,3) NOT NULL,
    "unit_type" varchar(30) NOT NULL DEFAULT 'unit',
    "unit_label_snapshot" varchar(50) DEFAULT NULL,
    "product_type_snapshot" varchar(50) DEFAULT NULL,
    "is_splittable_snapshot" INTEGER DEFAULT NULL,
    "items_per_unit_snapshot" NUMERIC(12,4) DEFAULT NULL,
    "roll_length_snapshot" NUMERIC(12,4) DEFAULT NULL,
    "cost_price" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "sender_stock_before" NUMERIC(15,3) DEFAULT NULL,
    "sender_stock_after" NUMERIC(15,3) DEFAULT NULL,
    "receiver_stock_before" NUMERIC(15,3) DEFAULT NULL,
    "receiver_stock_after" NUMERIC(15,3) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "store_transfer_items_receiver_product_id_foreign" FOREIGN KEY ("receiver_product_id") REFERENCES "products" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_transfer_items_sender_product_id_foreign" FOREIGN KEY ("sender_product_id") REFERENCES "products" ("id") ON DELETE SET NULL,
    CONSTRAINT "store_transfer_items_store_transfer_id_foreign" FOREIGN KEY ("store_transfer_id") REFERENCES "store_transfers" ("id") ON DELETE CASCADE
);

CREATE TABLE "store_transfers" (
    "id" INTEGER NOT NULL,
    "sender_store_id" INTEGER NOT NULL,
    "receiver_store_id" INTEGER NOT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "request_business_date" date DEFAULT NULL,
    "action_business_date" date DEFAULT NULL,
    "notes" text DEFAULT NULL,
    "rejection_reason" text DEFAULT NULL,
    "created_by_type" varchar(255) DEFAULT NULL,
    "created_by_id" INTEGER DEFAULT NULL,
    "action_by_type" varchar(255) DEFAULT NULL,
    "action_by_id" INTEGER DEFAULT NULL,
    "acted_at" timestamp NULL DEFAULT NULL,
    "completed_at" timestamp NULL DEFAULT NULL,
    "rejected_at" timestamp NULL DEFAULT NULL,
    "cancelled_at" timestamp NULL DEFAULT NULL,
    "receiver_seen_at" timestamp NULL DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "store_transfers_receiver_store_id_foreign" FOREIGN KEY ("receiver_store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    CONSTRAINT "store_transfers_sender_store_id_foreign" FOREIGN KEY ("sender_store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE TABLE "stores" (
    "id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "name" varchar(255) NOT NULL,
    "description" text DEFAULT NULL,
    "phone" varchar(255) DEFAULT NULL,
    "address" varchar(255) DEFAULT NULL,
    "tax_number" varchar(255) DEFAULT NULL,
    "commercial_registration" varchar(255) DEFAULT NULL,
    "bank_accounts" text DEFAULT NULL,
    "labor_description_options" TEXT DEFAULT NULL,
    "logo" varchar(255) DEFAULT NULL,
    "slug" varchar(255) NOT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "suspension_reason" varchar(255) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "number_of_shifts" INTEGER NOT NULL DEFAULT 1,
    "shift_1_start" time DEFAULT NULL,
    "shift_2_start" time DEFAULT NULL,
    "shift_3_start" time DEFAULT NULL,
    "force_shift_closure" INTEGER NOT NULL DEFAULT 0,
    "inventory_audit_cycle_months" INTEGER NOT NULL DEFAULT 6,
    "inventory_audit_start_mode" varchar(20) NOT NULL DEFAULT 'store_created_at',
    "inventory_audit_start_date" date DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "stores_slug_unique" UNIQUE ("slug"),
    CONSTRAINT "fk_stores_user_id_cascade" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "subscriptions" (
    "id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "type" VARCHAR(255) NOT NULL,
    "price" NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    "start_at" date NOT NULL,
    "end_at" date NOT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "fk_subscriptions_user_id_cascade" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "support_sessions" (
    "id" INTEGER NOT NULL,
    "support_ticket_id" INTEGER DEFAULT NULL,
    "admin_id" INTEGER NOT NULL,
    "admin_name" varchar(255) DEFAULT NULL,
    "admin_email" varchar(255) DEFAULT NULL,
    "target_type" varchar(255) NOT NULL,
    "target_id" INTEGER NOT NULL,
    "target_name" varchar(255) DEFAULT NULL,
    "target_role" varchar(255) DEFAULT NULL,
    "reason" text NOT NULL,
    "ticket_reference" varchar(255) DEFAULT NULL,
    "started_at" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "expires_at" timestamp NULL DEFAULT NULL,
    "ended_at" timestamp NULL DEFAULT NULL,
    "started_ip" varchar(45) DEFAULT NULL,
    "ended_ip" varchar(45) DEFAULT NULL,
    "user_agent" text DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "support_sessions_admin_id_foreign" FOREIGN KEY ("admin_id") REFERENCES "users" ("id") ON DELETE CASCADE,
    CONSTRAINT "support_sessions_support_ticket_id_foreign" FOREIGN KEY ("support_ticket_id") REFERENCES "support_tickets" ("id") ON DELETE SET NULL
);

CREATE TABLE "support_ticket_events" (
    "id" INTEGER NOT NULL,
    "support_ticket_id" INTEGER NOT NULL,
    "event_type" varchar(40) NOT NULL,
    "actor_role" varchar(20) NOT NULL,
    "actor_id" INTEGER DEFAULT NULL,
    "metadata" TEXT DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "support_ticket_events_support_ticket_id_foreign" FOREIGN KEY ("support_ticket_id") REFERENCES "support_tickets" ("id") ON DELETE CASCADE
);

CREATE TABLE "support_ticket_messages" (
    "id" INTEGER NOT NULL,
    "support_ticket_id" INTEGER NOT NULL,
    "sender_role" varchar(20) NOT NULL,
    "sender_id" INTEGER DEFAULT NULL,
    "message" text NOT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "support_ticket_messages_support_ticket_id_foreign" FOREIGN KEY ("support_ticket_id") REFERENCES "support_tickets" ("id") ON DELETE CASCADE
);

CREATE TABLE "support_tickets" (
    "id" INTEGER NOT NULL,
    "reference" varchar(255) NOT NULL,
    "owner_id" INTEGER NOT NULL,
    "accountant_id" INTEGER DEFAULT NULL,
    "requested_role" varchar(30) NOT NULL DEFAULT 'owner',
    "category" varchar(40) NOT NULL DEFAULT 'general',
    "priority" varchar(20) NOT NULL DEFAULT 'normal',
    "subject" varchar(255) NOT NULL,
    "description" text NOT NULL,
    "status" varchar(30) NOT NULL DEFAULT 'open',
    "support_response" text DEFAULT NULL,
    "responded_by" INTEGER DEFAULT NULL,
    "responded_at" timestamp NULL DEFAULT NULL,
    "closed_at" timestamp NULL DEFAULT NULL,
    "last_activity_at" timestamp NULL DEFAULT NULL,
    "expires_at" timestamp NULL DEFAULT NULL,
    "cancelled_at" timestamp NULL DEFAULT NULL,
    "cancel_reason" varchar(255) DEFAULT NULL,
    "owner_unread_count" INTEGER NOT NULL DEFAULT 0,
    "support_unread_count" INTEGER NOT NULL DEFAULT 0,
    "created_by_support" INTEGER NOT NULL DEFAULT 0,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "support_tickets_reference_unique" UNIQUE ("reference"),
    CONSTRAINT "support_tickets_accountant_id_foreign" FOREIGN KEY ("accountant_id") REFERENCES "accountants" ("id") ON DELETE SET NULL,
    CONSTRAINT "support_tickets_owner_id_foreign" FOREIGN KEY ("owner_id") REFERENCES "users" ("id") ON DELETE CASCADE,
    CONSTRAINT "support_tickets_responded_by_foreign" FOREIGN KEY ("responded_by") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "user_settings" (
    "id" INTEGER NOT NULL,
    "user_id" INTEGER NOT NULL,
    "notifications_expiry" INTEGER NOT NULL DEFAULT 15,
    "invoices_expiry" INTEGER NOT NULL DEFAULT 30,
    "email_notifications" INTEGER NOT NULL DEFAULT 1,
    "created_at" timestamp NULL DEFAULT NULL,
    "updated_at" timestamp NULL DEFAULT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "fk_user_settings_user_id_cascade" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "users" (
    "id" INTEGER NOT NULL,
    "current_store_id" INTEGER DEFAULT NULL,
    "name" varchar(255) NOT NULL,
    "email" varchar(255) NOT NULL,
    "email_verified_at" timestamp NULL DEFAULT NULL,
    "phone" varchar(255) DEFAULT NULL,
    "password" varchar(255) NOT NULL,
    "role" VARCHAR(255) NOT NULL DEFAULT 'user',
    "status" VARCHAR(255) NOT NULL DEFAULT 'active',
    "suspension_reason" varchar(255) DEFAULT NULL,
    "subscription_end_at" date DEFAULT NULL,
    "last_login_at" timestamp NULL DEFAULT NULL,
    "slug" varchar(255) DEFAULT NULL,
    "expires_at" date DEFAULT NULL,
    "deleted_at" timestamp NULL DEFAULT NULL,
    "remember_token" varchar(100) DEFAULT NULL,
    "created_at" timestamp NULL DEFAULT NULL,
    "must_reset_password" INTEGER NOT NULL DEFAULT 0,
    "updated_at" timestamp NULL DEFAULT NULL,
    "plan_id" INTEGER DEFAULT NULL,
    "allowed_stores" INTEGER NOT NULL DEFAULT 1,
    "allowed_accountants" INTEGER NOT NULL DEFAULT 1,
    "welcome_shown" INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY ("id"),
    CONSTRAINT "users_email_unique" UNIQUE ("email"),
    CONSTRAINT "users_slug_unique" UNIQUE ("slug"),
    CONSTRAINT "users_current_store_id_foreign" FOREIGN KEY ("current_store_id") REFERENCES "stores" ("id") ON DELETE SET NULL
);

CREATE TABLE "security_events" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "event_code" VARCHAR(80) NOT NULL,
    "category" VARCHAR(40) NOT NULL,
    "severity" VARCHAR(20) NOT NULL,
    "confidence" INTEGER NOT NULL DEFAULT 50,
    "status" VARCHAR(30) NOT NULL DEFAULT 'new',
    "title" VARCHAR(255) NOT NULL,
    "description" TEXT NULL,
    "source_ip" VARCHAR(45) NULL,
    "user_agent" TEXT NULL,
    "route" VARCHAR(255) NULL,
    "http_method" VARCHAR(10) NULL,
    "actor_type" VARCHAR(255) NULL,
    "actor_id" INTEGER NULL,
    "target_type" VARCHAR(255) NULL,
    "target_id" INTEGER NULL,
    "fingerprint" VARCHAR(64) NOT NULL,
    "occurrences" INTEGER NOT NULL DEFAULT 1,
    "evidence" TEXT NULL,
    "resolution" TEXT NULL,
    "verification_note" TEXT NULL,
    "response_action" VARCHAR(40) NULL,
    "response_expires_at" DATETIME NULL,
    "assigned_to" INTEGER NULL,
    "acknowledged_by" INTEGER NULL,
    "first_seen_at" DATETIME NOT NULL,
    "last_seen_at" DATETIME NOT NULL,
    "detected_at" DATETIME NOT NULL,
    "acknowledged_at" DATETIME NULL,
    "contained_at" DATETIME NULL,
    "resolved_at" DATETIME NULL,
    "verified_at" DATETIME NULL,
    "verified_by" INTEGER NULL,
    "created_at" DATETIME NULL,
    "updated_at" DATETIME NULL,
    FOREIGN KEY ("assigned_to") REFERENCES "users" ("id") ON DELETE SET NULL,
    FOREIGN KEY ("acknowledged_by") REFERENCES "users" ("id") ON DELETE SET NULL,
    FOREIGN KEY ("verified_by") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "security_event_activities" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "security_event_id" INTEGER NOT NULL,
    "user_id" INTEGER NULL,
    "action" VARCHAR(40) NOT NULL,
    "from_status" VARCHAR(30) NULL,
    "to_status" VARCHAR(30) NULL,
    "note" TEXT NULL,
    "ip_address" VARCHAR(45) NULL,
    "created_at" DATETIME NULL,
    "updated_at" DATETIME NULL,
    FOREIGN KEY ("security_event_id") REFERENCES "security_events" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS "accountants_user_id_foreign" ON "accountants" ("user_id");

CREATE INDEX IF NOT EXISTS "accountants_store_id_foreign" ON "accountants" ("store_id");

CREATE INDEX IF NOT EXISTS "archived_items_store_id_foreign" ON "archived_items" ("store_id");

CREATE INDEX IF NOT EXISTS "archived_items_archived_by_foreign" ON "archived_items" ("archived_by");

CREATE INDEX IF NOT EXISTS "archived_items_restored_by_foreign" ON "archived_items" ("restored_by");

CREATE INDEX IF NOT EXISTS "archived_items_owner_id_status_index" ON "archived_items" ("owner_id","status");

CREATE INDEX IF NOT EXISTS "archived_items_status_owner_restore_deadline_index" ON "archived_items" ("status","owner_restore_deadline");

CREATE INDEX IF NOT EXISTS "employee_credit_sales_employee_id_foreign" ON "credit_sales" ("person_id");

CREATE INDEX IF NOT EXISTS "employee_credit_sales_store_id_foreign" ON "credit_sales" ("store_id");

CREATE INDEX IF NOT EXISTS "credit_sales_sale_id_index" ON "credit_sales" ("sale_id");

CREATE INDEX IF NOT EXISTS "daily_balances_store_id_foreign" ON "daily_balances" ("store_id");

CREATE INDEX IF NOT EXISTS "daily_balances_accountant_id_foreign" ON "daily_balances" ("accountant_id");

CREATE INDEX IF NOT EXISTS "daily_balances_business_date_index" ON "daily_balances" ("business_date");

CREATE INDEX IF NOT EXISTS "daily_balances_closed_at_index" ON "daily_balances" ("closed_at");

CREATE INDEX IF NOT EXISTS "daily_balances_next_shift_decided_by_foreign" ON "daily_balances" ("next_shift_decided_by");

CREATE INDEX IF NOT EXISTS "daily_balances_next_shift_business_date_index" ON "daily_balances" ("next_shift_business_date");

CREATE INDEX IF NOT EXISTS "employee_debts_employee_id_foreign" ON "debts" ("person_id");

CREATE INDEX IF NOT EXISTS "employee_debts_parent_id_index" ON "debts" ("debt_parent_id");

CREATE INDEX IF NOT EXISTS "debts_store_id_foreign" ON "debts" ("store_id");

CREATE INDEX IF NOT EXISTS "employee_absences_employee_id_foreign" ON "employee_absences" ("person_id");

CREATE INDEX IF NOT EXISTS "employee_absences_store_id_foreign" ON "employee_absences" ("store_id");

CREATE INDEX IF NOT EXISTS "credit_collections_credit_date_index" ON "employee_credit_collections" ("credit_sale_id","collection_date");

CREATE INDEX IF NOT EXISTS "credit_collections_store_date_index" ON "employee_credit_collections" ("store_id","collection_date");

CREATE INDEX IF NOT EXISTS "credit_collections_person_index" ON "employee_credit_collections" ("person_id","person_type");

CREATE INDEX IF NOT EXISTS "credit_collections_sale_id_index" ON "employee_credit_collections" ("sale_id");

CREATE INDEX IF NOT EXISTS "employee_logs_store_id_foreign" ON "employee_logs" ("store_id");

CREATE INDEX IF NOT EXISTS "employee_salary_reports_employee_id_foreign" ON "employee_salary_reports" ("person_id");

CREATE INDEX IF NOT EXISTS "employee_salary_reports_store_id_foreign" ON "employee_salary_reports" ("store_id");

CREATE INDEX IF NOT EXISTS "employee_salary_reports_user_id_foreign" ON "employee_salary_reports" ("user_id");

CREATE INDEX IF NOT EXISTS "employee_withdrawals_employee_id_foreign" ON "employee_withdrawals" ("person_id");

CREATE INDEX IF NOT EXISTS "employee_withdrawals_daily_balance_id_foreign" ON "employee_withdrawals" ("daily_balance_id");

CREATE INDEX IF NOT EXISTS "employee_withdrawals_business_date_index" ON "employee_withdrawals" ("business_date");

CREATE INDEX IF NOT EXISTS "employee_withdrawals_store_id_foreign" ON "employee_withdrawals" ("store_id");

CREATE INDEX IF NOT EXISTS "employees_store_id_foreign" ON "employees" ("store_id");

CREATE INDEX IF NOT EXISTS "employees_added_by_foreign" ON "employees" ("added_by");

CREATE INDEX IF NOT EXISTS "expenses_employee_id_foreign" ON "expenses" ("employee_id");

CREATE INDEX IF NOT EXISTS "expenses_daily_balance_id_foreign" ON "expenses" ("daily_balance_id");

CREATE INDEX IF NOT EXISTS "expenses_business_date_index" ON "expenses" ("business_date");

CREATE INDEX IF NOT EXISTS "inventory_logs_business_date_index" ON "inventory_logs" ("business_date");

CREATE INDEX IF NOT EXISTS "inventory_logs_store_id_foreign" ON "inventory_logs" ("store_id");

CREATE INDEX IF NOT EXISTS "invoices_sale_id_foreign" ON "invoices" ("sale_id");

CREATE INDEX IF NOT EXISTS "jobs_queue_index" ON "jobs" ("queue");

CREATE INDEX IF NOT EXISTS "logs_user_id_foreign" ON "logs" ("user_id");

CREATE INDEX IF NOT EXISTS "logs_store_id_foreign" ON "logs" ("store_id");

CREATE INDEX IF NOT EXISTS "password_resets_email_index" ON "password_resets" ("email");

CREATE INDEX IF NOT EXISTS "product_fractions_product_id_foreign" ON "product_fractions" ("product_id");

CREATE INDEX IF NOT EXISTS "fk_products_store_id_cascade" ON "products" ("store_id");

CREATE INDEX IF NOT EXISTS "products_description_index" ON "products" ("description");

CREATE INDEX IF NOT EXISTS "purchases_business_date_index" ON "purchases" ("business_date");

CREATE INDEX IF NOT EXISTS "purchases_store_id_foreign" ON "purchases" ("store_id");

CREATE INDEX IF NOT EXISTS "sale_items_sale_id_foreign" ON "sale_items" ("sale_id");

CREATE INDEX IF NOT EXISTS "sale_items_fraction_id_foreign" ON "sale_items" ("fraction_id");

CREATE INDEX IF NOT EXISTS "sales_store_id_foreign" ON "sales" ("store_id");

CREATE INDEX IF NOT EXISTS "sales_employee_id_foreign" ON "sales" ("employee_id");

CREATE INDEX IF NOT EXISTS "sales_daily_balance_id_foreign" ON "sales" ("daily_balance_id");

CREATE INDEX IF NOT EXISTS "sales_business_date_index" ON "sales" ("business_date");

CREATE INDEX IF NOT EXISTS "stock_movements_store_id_foreign" ON "stock_movements" ("store_id");

CREATE INDEX IF NOT EXISTS "stock_movements_product_id_foreign" ON "stock_movements" ("product_id");

CREATE INDEX IF NOT EXISTS "stock_movements_user_id_foreign" ON "stock_movements" ("user_id");

CREATE INDEX IF NOT EXISTS "stock_movements_business_date_index" ON "stock_movements" ("business_date");

CREATE INDEX IF NOT EXISTS "po_counts_order_fk" ON "store_purchase_order_count_attempts" ("store_purchase_order_id");

CREATE INDEX IF NOT EXISTS "po_counts_accountant_fk" ON "store_purchase_order_count_attempts" ("accountant_id");

CREATE INDEX IF NOT EXISTS "store_purchase_order_events_store_purchase_order_item_id_foreign" ON "store_purchase_order_events" ("store_purchase_order_item_id");

CREATE INDEX IF NOT EXISTS "po_events_order_created_index" ON "store_purchase_order_events" ("store_purchase_order_id","created_at");

CREATE INDEX IF NOT EXISTS "store_purchase_order_items_store_purchase_order_id_foreign" ON "store_purchase_order_items" ("store_purchase_order_id");

CREATE INDEX IF NOT EXISTS "store_purchase_order_items_product_id_index" ON "store_purchase_order_items" ("product_id");

CREATE INDEX IF NOT EXISTS "store_purchase_order_items_matched_product_id_index" ON "store_purchase_order_items" ("matched_product_id");

CREATE INDEX IF NOT EXISTS "store_purchase_order_items_owner_purchase_id_foreign" ON "store_purchase_order_items" ("owner_purchase_id");

CREATE INDEX IF NOT EXISTS "store_purchase_order_items_excluded_after_count_index" ON "store_purchase_order_items" ("excluded_after_count");

CREATE INDEX IF NOT EXISTS "store_purchase_orders_store_id_status_index" ON "store_purchase_orders" ("store_id","status");

CREATE INDEX IF NOT EXISTS "store_purchase_orders_user_id_status_index" ON "store_purchase_orders" ("user_id","status");

CREATE INDEX IF NOT EXISTS "store_purchase_orders_approved_business_date_index" ON "store_purchase_orders" ("approved_business_date");

CREATE INDEX IF NOT EXISTS "store_purchase_orders_accountant_id_status_index" ON "store_purchase_orders" ("accountant_id","status");

CREATE INDEX IF NOT EXISTS "store_purchase_orders_workflow_status_index" ON "store_purchase_orders" ("workflow_status");

CREATE INDEX IF NOT EXISTS "store_settings_store_id_foreign" ON "store_settings" ("store_id");

CREATE INDEX IF NOT EXISTS "store_transfer_items_store_transfer_id_foreign" ON "store_transfer_items" ("store_transfer_id");

CREATE INDEX IF NOT EXISTS "store_transfer_items_sender_product_id_index" ON "store_transfer_items" ("sender_product_id");

CREATE INDEX IF NOT EXISTS "store_transfer_items_receiver_product_id_index" ON "store_transfer_items" ("receiver_product_id");

CREATE INDEX IF NOT EXISTS "store_transfers_sender_store_id_status_index" ON "store_transfers" ("sender_store_id","status");

CREATE INDEX IF NOT EXISTS "store_transfers_receiver_store_id_status_index" ON "store_transfers" ("receiver_store_id","status");

CREATE INDEX IF NOT EXISTS "store_transfers_created_by_type_created_by_id_index" ON "store_transfers" ("created_by_type","created_by_id");

CREATE INDEX IF NOT EXISTS "store_transfers_action_by_type_action_by_id_index" ON "store_transfers" ("action_by_type","action_by_id");

CREATE INDEX IF NOT EXISTS "store_transfers_request_business_date_index" ON "store_transfers" ("request_business_date");

CREATE INDEX IF NOT EXISTS "store_transfers_action_business_date_index" ON "store_transfers" ("action_business_date");

CREATE INDEX IF NOT EXISTS "stores_user_id_foreign" ON "stores" ("user_id");

CREATE INDEX IF NOT EXISTS "subscriptions_user_id_foreign" ON "subscriptions" ("user_id");

CREATE INDEX IF NOT EXISTS "support_sessions_target_type_target_id_index" ON "support_sessions" ("target_type","target_id");

CREATE INDEX IF NOT EXISTS "support_sessions_admin_id_ended_at_index" ON "support_sessions" ("admin_id","ended_at");

CREATE INDEX IF NOT EXISTS "support_sessions_ticket_reference_index" ON "support_sessions" ("ticket_reference");

CREATE INDEX IF NOT EXISTS "support_sessions_support_ticket_id_foreign" ON "support_sessions" ("support_ticket_id");

CREATE INDEX IF NOT EXISTS "support_sessions_expires_at_index" ON "support_sessions" ("expires_at");

CREATE INDEX IF NOT EXISTS "support_ticket_events_support_ticket_id_created_at_index" ON "support_ticket_events" ("support_ticket_id","created_at");

CREATE INDEX IF NOT EXISTS "support_ticket_messages_support_ticket_id_created_at_index" ON "support_ticket_messages" ("support_ticket_id","created_at");

CREATE INDEX IF NOT EXISTS "support_tickets_accountant_id_foreign" ON "support_tickets" ("accountant_id");

CREATE INDEX IF NOT EXISTS "support_tickets_responded_by_foreign" ON "support_tickets" ("responded_by");

CREATE INDEX IF NOT EXISTS "support_tickets_owner_id_status_index" ON "support_tickets" ("owner_id","status");

CREATE INDEX IF NOT EXISTS "support_tickets_status_created_at_index" ON "support_tickets" ("status","created_at");

CREATE INDEX IF NOT EXISTS "support_tickets_last_activity_at_index" ON "support_tickets" ("last_activity_at");

CREATE INDEX IF NOT EXISTS "support_tickets_expires_at_index" ON "support_tickets" ("expires_at");

CREATE INDEX IF NOT EXISTS "user_settings_user_id_foreign" ON "user_settings" ("user_id");

CREATE INDEX IF NOT EXISTS "users_plan_id_foreign" ON "users" ("plan_id");

CREATE INDEX IF NOT EXISTS "users_current_store_id_foreign" ON "users" ("current_store_id");

CREATE INDEX "security_events_event_code_index" ON "security_events" ("event_code");

CREATE INDEX "security_events_category_index" ON "security_events" ("category");

CREATE INDEX "security_events_severity_index" ON "security_events" ("severity");

CREATE INDEX "security_events_status_index" ON "security_events" ("status");

CREATE INDEX "security_events_source_ip_index" ON "security_events" ("source_ip");

CREATE INDEX "security_events_actor_type_actor_id_index" ON "security_events" ("actor_type", "actor_id");

CREATE INDEX "security_events_target_type_target_id_index" ON "security_events" ("target_type", "target_id");

CREATE INDEX "security_events_fingerprint_index" ON "security_events" ("fingerprint");

CREATE INDEX "security_events_grouping_index" ON "security_events" ("fingerprint", "status", "last_seen_at");
