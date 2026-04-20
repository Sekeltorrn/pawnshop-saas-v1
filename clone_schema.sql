CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = timezone('utc', now());
    RETURN NEW;
END;
$$ language 'plpgsql';


CREATE TABLE roles (
  role_id UUID NOT NULL DEFAULT gen_random_uuid(),
  role_name VARCHAR(50) NOT NULL,
  permissions JSONB NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT roles_pkey PRIMARY KEY (role_id),
  CONSTRAINT roles_name_key UNIQUE (role_name)
);

CREATE TRIGGER update_tenant_roles_modtime
BEFORE UPDATE ON roles
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();


CREATE TABLE categories (
  category_id UUID NOT NULL DEFAULT gen_random_uuid(),
  category_name VARCHAR(50) NOT NULL,
  description TEXT NULL,
  default_interest_rate NUMERIC(5, 2) NULL DEFAULT 3.00,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT categories_pkey PRIMARY KEY (category_id),
  CONSTRAINT categories_category_name_key UNIQUE (category_name)
);

CREATE TRIGGER update_categories_modtime
BEFORE UPDATE ON categories
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();


CREATE TABLE retail_lots (
  lot_name VARCHAR(100) NOT NULL,
  lot_price NUMERIC(15, 2) NULL,
  created_at TIMESTAMP WITHOUT TIME ZONE NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT retail_lots_pkey PRIMARY KEY (lot_name)
);


CREATE TABLE storage_locations (
  location_id UUID NOT NULL DEFAULT gen_random_uuid(),
  location_name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT storage_locations_pkey PRIMARY KEY (location_id)
);


CREATE TABLE tenant_settings (
  setting_id uuid not null default gen_random_uuid (),
  ltv_percentage numeric(5, 2) null default 60.00,
  interest_rate numeric(5, 2) null default 3.50,
  service_fee numeric(10, 2) null default 5.00,
  penalty_rate numeric(10, 2) null default 2.00,
  grace_period_days integer null default 3,
  gold_rate_18k numeric(10, 2) null default 3000.00,
  gold_rate_21k numeric(10, 2) null default 3500.00,
  gold_rate_24k numeric(10, 2) null default 4200.00,
  diamond_base_rate numeric(15, 2) null default 50000.00,
  shop_name character varying(100) null,
  receipt_header_text text null,
  timezone character varying(50) not null default 'Asia/Manila'::character varying,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  store_open_time time without time zone null default '08:00:00'::time without time zone,
  store_close_time time without time zone null default '17:00:00'::time without time zone,
  closed_days jsonb null default '["Sunday"]'::jsonb,
  portal_title character varying(100) null,
  portal_tagline text null,
  portal_bg_color character varying(7) null default '#0a0b0d'::character varying,
  portal_btn_color character varying(7) null default '#ff6b00'::character varying,
  portal_logo_url text null,
  portal_custom_blocks jsonb null default '[{"icon": "location_on", "title": "Location", "content": "1245 Opulence Avenue\nSuite 200\nMetropolis, NY 10022"}, {"icon": "call", "title": "Contact", "content": "+1 (555) 867-5309\nconcierge@merlinpawnshop.com"}, {"icon": "schedule", "title": "Hours", "content": "Mon - Fri: 10:00 AM - 6:00 PM\nSat: 11:00 AM - 4:00 PM\nSun: By Appointment Only"}]'::jsonb,
  admin_bg_color character varying(50) null default '#05010a'::character varying,
  admin_btn_color character varying(50) null default '#ff6a00'::character varying,
  admin_text_color character varying(50) null default '#ffffff'::character varying,
  constraint tenant_settings_pkey primary key (setting_id)
);

CREATE TRIGGER update_tenant_settings_modtime
BEFORE UPDATE ON tenant_settings
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();


CREATE TABLE customers (
  customer_id UUID NOT NULL DEFAULT gen_random_uuid(),
  auth_user_id UUID NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) NULL,
  contact_no VARCHAR(20) NULL,
  address TEXT NULL,
  id_type VARCHAR(50) NULL,
  id_number VARCHAR(50) NULL,
  status VARCHAR(20) NULL DEFAULT 'pending',
  is_walk_in BOOLEAN NULL DEFAULT false,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  password TEXT NULL,
  middle_name VARCHAR(50) NULL,
  birthday DATE NULL,
  id_photo_front_url TEXT NULL,
  id_photo_back_url TEXT NULL,
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  deleted_at TIMESTAMP WITH TIME ZONE NULL,
  rejection_reason TEXT NULL,
  CONSTRAINT customers_pkey PRIMARY KEY (customer_id),
  CONSTRAINT customers_auth_user_id_key UNIQUE (auth_user_id),
  CONSTRAINT customers_email_key UNIQUE (email)
);

CREATE TRIGGER update_tenant_customers_modtime
BEFORE UPDATE ON customers
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();


CREATE TABLE employees (
  employee_id UUID NOT NULL DEFAULT gen_random_uuid(),
  auth_user_id UUID NULL,
  role_id UUID NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL,
  phone_number VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  deleted_at TIMESTAMP WITH TIME ZONE NULL,
  password_hash TEXT NULL,
  CONSTRAINT employees_pkey PRIMARY KEY (employee_id),
  CONSTRAINT employees_auth_key UNIQUE (auth_user_id),
  CONSTRAINT employees_email_key UNIQUE (email),
  CONSTRAINT employees_role_id_fkey FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT
);

CREATE TRIGGER update_tenant_employees_modtime
BEFORE UPDATE ON employees
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();


CREATE TABLE asset_matrix (
  node_id UUID NOT NULL DEFAULT gen_random_uuid(),
  category_id UUID NOT NULL,
  parent_id UUID NULL,
  name VARCHAR(100) NOT NULL,
  hierarchy_level VARCHAR(50) NOT NULL,
  is_accepted BOOLEAN NULL DEFAULT true,
  base_appraisal_value NUMERIC(15, 2) NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT asset_matrix_pkey PRIMARY KEY (node_id),
  CONSTRAINT unique_node_name_per_parent UNIQUE (category_id, parent_id, name, hierarchy_level),
  CONSTRAINT fk_asset_parent FOREIGN KEY (parent_id) REFERENCES asset_matrix(node_id) ON DELETE CASCADE,
  CONSTRAINT fk_matrix_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
);



CREATE TABLE inventory (
  item_id UUID NOT NULL DEFAULT gen_random_uuid(),
  category_id UUID NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  item_description TEXT NULL,
  item_condition TEXT NULL,
  serial_number VARCHAR(100) NULL,
  weight_grams NUMERIC(10, 2) NULL,
  appraised_value NUMERIC(15, 2) NULL,
  item_status VARCHAR(20) NULL DEFAULT 'in_vault',
  item_image TEXT NULL,
  storage_location TEXT NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  retail_price NUMERIC(15, 2) NULL,
  lot_name VARCHAR(100) NULL,
  lot_price NUMERIC(15, 2) NULL,
  CONSTRAINT inventory_pkey PRIMARY KEY (item_id),
  CONSTRAINT inventory_category_id_fkey FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT
);

CREATE TRIGGER update_inventory_modtime
BEFORE UPDATE ON inventory
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();



CREATE TABLE shifts (
  shift_id UUID NOT NULL DEFAULT gen_random_uuid(),
  employee_id UUID NOT NULL,
  starting_cash NUMERIC(12, 2) NOT NULL,
  actual_closing_cash NUMERIC(12, 2) NULL,
  variance NUMERIC(12, 2) NULL,
  start_time TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT timezone('utc', now()),
  end_time TIMESTAMP WITH TIME ZONE NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  expected_cash NUMERIC(15, 2) NULL DEFAULT 0.00,
  status VARCHAR(20) NULL DEFAULT 'Open',
  CONSTRAINT shifts_pkey PRIMARY KEY (shift_id),
  CONSTRAINT shifts_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE RESTRICT
);

CREATE TRIGGER update_tenant_shifts_modtime
BEFORE UPDATE ON shifts
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();



CREATE TABLE asset_attributes (
  attribute_id UUID NOT NULL DEFAULT gen_random_uuid(),
  node_id UUID NOT NULL,
  label VARCHAR(100) NOT NULL,
  field_type VARCHAR(20) NOT NULL,
  options JSONB NULL,
  is_required BOOLEAN NULL DEFAULT true,
  CONSTRAINT asset_attributes_pkey PRIMARY KEY (attribute_id),
  CONSTRAINT fk_attr_node FOREIGN KEY (node_id) REFERENCES asset_matrix(node_id) ON DELETE CASCADE
);



CREATE TABLE asset_tests (
  test_id UUID NOT NULL DEFAULT gen_random_uuid(),
  node_id UUID NOT NULL,
  test_name VARCHAR(100) NOT NULL,
  test_group VARCHAR(50) NOT NULL,
  impact_type VARCHAR(20) NULL DEFAULT 'penalty',
  impact_value NUMERIC(5, 2) NULL,
  CONSTRAINT asset_tests_pkey PRIMARY KEY (test_id),
  CONSTRAINT fk_test_node FOREIGN KEY (node_id) REFERENCES asset_matrix(node_id) ON DELETE CASCADE
);



CREATE TABLE audit_logs (
  log_id UUID NOT NULL DEFAULT gen_random_uuid(),
  employee_id UUID NULL,
  action_type VARCHAR(50) NOT NULL,
  table_affected VARCHAR(50) NOT NULL,
  record_id VARCHAR(255) NULL,
  old_data JSONB NULL,
  new_data JSONB NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT audit_logs_pkey PRIMARY KEY (log_id),
  CONSTRAINT audit_logs_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
);



CREATE TABLE profile_change_requests (
  request_id UUID NOT NULL DEFAULT gen_random_uuid(),
  customer_id UUID NOT NULL,
  requested_email VARCHAR(100) NULL,
  requested_contact_no VARCHAR(20) NULL,
  requested_address TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  admin_notes TEXT NULL,
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT profile_change_requests_pkey PRIMARY KEY (request_id),
  CONSTRAINT profile_change_requests_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TRIGGER update_profile_change_modtime
BEFORE UPDATE ON profile_change_requests
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();



CREATE TABLE appointments (
  appointment_id UUID NOT NULL DEFAULT gen_random_uuid(),
  customer_id UUID NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME WITHOUT TIME ZONE NOT NULL,
  purpose VARCHAR(50) NOT NULL,
  remarks TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  CONSTRAINT appointments_pkey PRIMARY KEY (appointment_id),
  CONSTRAINT appointments_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TRIGGER update_tenant_appointments_modtime
BEFORE UPDATE ON appointments
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();



CREATE TABLE loans (
  loan_id UUID NOT NULL DEFAULT gen_random_uuid(),
  customer_id UUID NULL,
  item_id UUID NULL,
  pawn_ticket_no SERIAL NOT NULL,
  principal_amount NUMERIC(15, 2) NOT NULL,
  interest_rate NUMERIC(5, 2) NOT NULL,
  loan_date DATE NULL DEFAULT CURRENT_DATE,
  due_date DATE NOT NULL,
  status VARCHAR(20) NULL DEFAULT 'active',
  created_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  service_charge NUMERIC(10, 2) NULL DEFAULT 5.00,
  net_proceeds NUMERIC(10, 2) NULL,
  employee_id UUID NULL,
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  reference_no VARCHAR(50) NULL,
  expiry_date DATE NULL,
  reminder_sent BOOLEAN NULL DEFAULT false,
  shift_id UUID NULL,
  admin_notes TEXT NULL,
  CONSTRAINT loans_pkey PRIMARY KEY (loan_id),
  CONSTRAINT loans_reference_no_key UNIQUE (reference_no),
  CONSTRAINT loans_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT loans_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
  CONSTRAINT loans_item_id_fkey FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE SET NULL,
  CONSTRAINT loans_shift_id_fkey FOREIGN KEY (shift_id) REFERENCES shifts(shift_id)
);

CREATE TRIGGER update_tenant_loans_modtime
BEFORE UPDATE ON loans
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();



CREATE TABLE payments (
  payment_id UUID NOT NULL DEFAULT gen_random_uuid(),
  loan_id UUID NULL,
  amount NUMERIC(15, 2) NOT NULL,
  payment_date TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  payment_type VARCHAR(20) NULL,
  or_number VARCHAR(50) NULL,
  reference_number TEXT NULL,
  payment_channel VARCHAR(50) NULL DEFAULT 'Walk-In',
  employee_id UUID NULL,
  shift_id UUID NULL,
  status VARCHAR(20) NULL DEFAULT 'completed',
  notes TEXT NULL,
  updated_at TIMESTAMP WITH TIME ZONE NULL DEFAULT timezone('utc', now()),
  interest_paid NUMERIC(15, 2) NULL DEFAULT 0,
  penalty_paid NUMERIC(15, 2) NULL DEFAULT 0,
  service_fee_paid NUMERIC(15, 2) NULL DEFAULT 0,
  principal_paid NUMERIC(15, 2) NULL DEFAULT 0,
  CONSTRAINT payments_pkey PRIMARY KEY (payment_id),
  CONSTRAINT payments_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
  CONSTRAINT payments_loan_id_fkey FOREIGN KEY (loan_id) REFERENCES loans(loan_id),
  CONSTRAINT payments_shift_id_fkey FOREIGN KEY (shift_id) REFERENCES shifts(shift_id) ON DELETE SET NULL
);

CREATE TRIGGER update_tenant_payments_modtime
BEFORE UPDATE ON payments
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

